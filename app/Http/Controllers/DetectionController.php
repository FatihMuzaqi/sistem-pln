<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessPhotoDetectionJob;
use App\Models\KmzBatch;
use App\Models\Photo;
use App\Services\ExcelExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DetectionController extends Controller
{
    /**
     * Halaman "Hasil Deteksi Foto"
     * Menampilkan tabel foto dengan filter jenis foto, status, dan pencarian nama foto.
     */
    public function index(Request $request)
    {
        $query = Photo::with('batch');

        // 1. Pencarian berdasarkan Nama Foto (dan ID Pelanggan / Merek jika diketik)
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('photo_name', 'like', "%{$search}%")
                  ->orWhere('customer_id', 'like', "%{$search}%")
                  ->orWhere('meter_brand', 'like', "%{$search}%");
            });
        }

        // 2. Filter Jenis Foto (kWh, Tiang, Trafo)
        if ($request->filled('photo_type')) {
            $query->where('photo_type', $request->photo_type);
        }

        // 3. Filter Status (Sesuai, Tidak Sesuai, Perlu Verifikasi, Belum Diproses)
        if ($request->filled('status')) {
            $statusFilter = $request->status;
            if ($statusFilter === 'Belum Diproses') {
                $query->where(function ($q) {
                    $q->where('processing_status', 'unprocessed')
                      ->orWhereNull('status')
                      ->orWhereIn('status', ['active', 'unprocessed']);
                })->where('photo_type', 'kWh');
            } elseif ($statusFilter === '-') {
                $query->where('photo_type', '!=', 'kWh');
            } else {
                $query->where('status', $statusFilter)
                      ->where('photo_type', 'kWh');
            }
        }

        // 4. Filter Batch KMZ (Opsional)
        if ($request->filled('batch_id')) {
            $query->where('batch_id', $request->batch_id);
        }

        // Informasi Jumlah Foto
        $totalAll = Photo::count();
        $totalKwh = Photo::where('photo_type', 'kWh')->count();
        $totalTiang = Photo::where('photo_type', 'Tiang')->count();
        $totalTrafo = Photo::where('photo_type', 'Trafo')->count();

        // Pagination agar ribuan foto tidak dimuat sekaligus
        $photos = $query->orderBy('id', 'desc')->paginate(15)->withQueryString();
        $batches = KmzBatch::select('id', 'batch_code', 'original_filename')->latest()->get();

        return view('detection.index', compact(
            'photos',
            'batches',
            'totalAll',
            'totalKwh',
            'totalTiang',
            'totalTrafo'
        ));
    }

    /**
     * Tampilkan detail satu foto agar pengguna dapat memeriksa gambar dan hasil pembacaan teks OCR
     */
    public function show(Photo $photo)
    {
        $photo->load('batch');
        return view('detection.show', compact('photo'));
    }

    /**
     * Menyajikan foto dari penyimpanan aplikasi melalui rute yang aman
     */
    public function servePhoto(Photo $photo): BinaryFileResponse
    {
        $relativePath = $photo->photo_path;

        // Cek disk public
        if (Storage::disk('public')->exists($relativePath)) {
            $fullPath = Storage::disk('public')->path($relativePath);
        } elseif (Storage::disk('local')->exists($relativePath)) {
            $fullPath = Storage::disk('local')->path($relativePath);
        } else {
            // Jika berkas tidak ditemukan di disk, kirim fallback gambar informatif
            abort(404, 'Berkas foto tidak ditemukan pada penyimpanan server.');
        }

        $mimeType = $photo->mime_type ?: (mime_content_type($fullPath) ?: 'image/jpeg');

        return response()->file($fullPath, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . basename($photo->photo_name) . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    /**
     * Memproses permintaan deteksi AI dari antarmuka web
     */
    public function triggerAiDetection(Request $request, Photo $photo, \App\Services\YoloDetectionService $aiService, \App\Services\DetectionValidationService $validator)
    {
        $simulate = $request->boolean('simulate');
        $result = $aiService->detectPhoto($photo, $simulate);

        if ($result['success']) {
            // Evaluasi aturan bisnis validasi status foto
            $validation = $validator->validatePhoto($photo->fresh());

            return redirect()->route('detections.show', $photo->id)
                ->with('success', $result['message'] . " Status: " . $validation['status']);
        }

        return redirect()->route('detections.show', $photo->id)
            ->with('error', $result['message']);
    }

    /**
     * Memproses pembacaan teks OCR mandiri pada foto kWh
     */
    public function triggerOcr(Request $request, Photo $photo, \App\Services\YoloDetectionService $aiService, \App\Services\DetectionValidationService $validator)
    {
        $quality = $request->input('quality'); // 'poor', 'partial', or null (good)
        $result = $aiService->runOcrOnPhoto($photo, $quality);

        if ($result['success']) {
            // Evaluasi aturan bisnis validasi status foto
            $validation = $validator->validatePhoto($photo->fresh());

            return redirect()->route('detections.show', $photo->id)
                ->with('success', $result['message'] . " Status: " . $validation['status']);
        }

        return redirect()->route('detections.show', $photo->id)
            ->with('error', $result['message']);
    }

    /**
     * Memproses verifikasi dan koreksi manual oleh operator PLN
     * Aturan: Tidak menimpa teks mentah OCR asli dan mencatat riwayat audit
     */
    public function verifyManual(Request $request, Photo $photo, \App\Services\DetectionValidationService $validator)
    {
        $request->validate([
            'verified_customer_id' => 'nullable|string|max:30',
            'verified_meter_brand' => 'nullable|string|max:50',
            'verification_status' => 'required|in:Sesuai,Tidak Sesuai,Perlu Verifikasi',
            'notes' => 'nullable|string|max:255',
            'verified_by' => 'nullable|string|max:100',
        ]);

        $officer = $request->input('verified_by') ?: 'Operator PLN UP3 Tegal';
        $validator->applyManualCorrection($photo, $request->all(), $officer);

        return redirect()->route('detections.show', $photo->id)
            ->with('success', 'Data verifikasi manual & audit waktu berhasil disimpan tanpa mengubah teks mentah OCR asli.');
    }

    /**
     * Menjalankan evaluasi ulang aturan validasi terhadap foto
     */
    public function revalidate(Photo $photo, \App\Services\DetectionValidationService $validator)
    {
        $result = $validator->validatePhoto($photo);

        return redirect()->route('detections.show', $photo->id)
            ->with('success', "Evaluasi aturan validasi berhasil diperbarui: Status '{$result['status']}'.");
    }

    /**
     * Ekspor daftar hasil deteksi ke berkas Excel (.xlsx) dengan filter yang aktif
     */
    public function exportExcel(Request $request, ExcelExportService $excelService)
    {
        $filters = $request->only(['batch_id', 'photo_type', 'status', 'search']);
        return $excelService->exportDetections($filters);
    }

    /**
     * Ulangi pemrosesan deteksi AI & OCR untuk satu foto yang gagal atau belum diproses
     */
    public function retrySinglePhoto(Request $request, Photo $photo)
    {
        $simulate = $request->boolean('simulate');

        // Reset status pemrosesan
        $photo->update([
            'processing_status' => 'unprocessed',
            'detection_notes' => null,
        ]);

        try {
            ProcessPhotoDetectionJob::dispatchSync($photo, $simulate);
            $photo->refresh();

            if ($photo->processing_status === 'success') {
                return redirect()->back()->with('success', "Foto {$photo->photo_name} berhasil diproses ulang.");
            } else {
                return redirect()->back()->with('warning', "Pemrosesan ulang foto selesai dengan catatan: " . ($photo->detection_notes ?? 'Perlu verifikasi manual'));
            }
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', "Gagal memproses ulang foto: " . $e->getMessage());
        }
    }
}
