<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\KmzBatch;
use App\Services\BatchProcessorService;
use App\Services\ExcelExportService;
use App\Services\KmzExtractorService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class KmzController extends Controller
{
    protected KmzExtractorService $extractorService;

    public function __construct(KmzExtractorService $extractorService)
    {
        $this->extractorService = $extractorService;
    }

    /**
     * Halaman Riwayat Pemrosesan KMZ
     */
    public function index(Request $request)
    {
        $query = KmzBatch::withCount(['photos', 'kwhPhotos', 'tiangPhotos', 'trafoPhotos']);

        // Filter status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Pencarian nama file atau kode batch
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('batch_code', 'like', "%{$search}%")
                  ->orWhere('original_filename', 'like', "%{$search}%");
            });
        }

        $batches = $query->latest()->paginate(10)->withQueryString();

        return view('kmz.index', compact('batches'));
    }

    /**
     * Halaman Form Upload KMZ
     */
    public function create()
    {
        $maxSizeMb = (int) AppSetting::getValue('max_upload_size_mb', 50);
        return view('kmz.upload', compact('maxSizeMb'));
    }

    /**
     * Memproses upload file KMZ, mengekstrak foto, dan memasukkannya ke antrean pemrosesan
     */
    public function store(Request $request, BatchProcessorService $processor)
    {
        $maxSizeMb = (int) AppSetting::getValue('max_upload_size_mb', 50);
        $maxSizeKb = $maxSizeMb * 1024;

        // Validasi ekstensi dan ukuran file
        $request->validate([
            'kmz_file' => [
                'required',
                'file',
                'max:' . $maxSizeKb,
                function ($attribute, $value, $fail) {
                    $ext = strtolower($value->getClientOriginalExtension());
                    if ($ext !== 'kmz' && $ext !== 'zip') {
                        $fail('File yang diunggah harus memiliki format .kmz');
                    }
                },
            ],
            'notes' => 'nullable|string|max:500',
        ], [
            'kmz_file.required' => 'Silakan pilih file KMZ terlebih dahulu.',
            'kmz_file.file' => 'File yang diunggah tidak valid.',
            'kmz_file.max' => "Ukuran file KMZ tidak boleh melebihi {$maxSizeMb} MB.",
        ]);

        try {
            $uploadedFile = $request->file('kmz_file');
            $result = $this->extractorService->processKmzUpload($uploadedFile);

            if ($request->filled('notes')) {
                $result['batch']->update(['notes' => $request->notes]);
            }

            if ($result['success']) {
                $batch = $result['batch'];

                // Jika ada foto yang diekstrak, masukkan ke antrean pemrosesan AI secara asinkron
                if ($batch->total_photos_extracted > 0) {
                    $processor->dispatchBatch($batch);
                }

                return redirect()->route('kmz.show', $batch->id)
                    ->with('success', $result['message'] . ' Seluruh foto telah dimasukkan ke antrean pemrosesan AI.');
            } else {
                return redirect()->route('kmz.show', $result['batch']->id)
                    ->with('error', $result['message']);
            }
        } catch (Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Terjadi kesalahan saat memproses file KMZ: ' . $e->getMessage());
        }
    }

    /**
     * Detail Batch KMZ, progres pemrosesan, dan daftar foto hasil ekstraksi
     */
    public function show(KmzBatch $kmz)
    {
        $batch = $kmz->loadCount(['photos', 'kwhPhotos', 'tiangPhotos', 'trafoPhotos']);

        $photos = $kmz->photos()
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('kmz.show', compact('batch', 'photos'));
    }

    /**
     * Memicu pemrosesan batch (Antrean Asinkron atau Sinkron)
     */
    public function processBatch(Request $request, KmzBatch $kmz, BatchProcessorService $processor)
    {
        $sync = $request->input('sync') == '1';
        $simulate = $request->input('simulate') == '1';

        if ($sync) {
            $result = $processor->processBatchSynchronously($kmz, $simulate);
        } else {
            $result = $processor->dispatchBatch($kmz, $simulate);
        }

        if ($result['success']) {
            return redirect()->route('kmz.show', $kmz->id)->with('success', $result['message']);
        }

        return redirect()->route('kmz.show', $kmz->id)->with('error', $result['message']);
    }

    /**
     * Mengulangi pemrosesan foto yang gagal dalam batch
     */
    public function retryFailed(Request $request, KmzBatch $kmz, BatchProcessorService $processor)
    {
        $sync = $request->input('sync') == '1';
        $simulate = $request->input('simulate') == '1';

        $result = $processor->retryFailedPhotos($kmz, $simulate, $sync);

        if ($result['success']) {
            return redirect()->route('kmz.show', $kmz->id)->with('success', $result['message']);
        }

        return redirect()->route('kmz.show', $kmz->id)->with('error', $result['message']);
    }

    /**
     * Ekspor seluruh foto dalam batch ini ke format Excel (.xlsx)
     */
    public function exportExcel(KmzBatch $kmz, ExcelExportService $excelService)
    {
        return $excelService->exportDetections(['batch_id' => $kmz->id]);
    }

    /**
     * Download file KMZ asli yang tersimpan aman
     */
    public function downloadOriginal(KmzBatch $kmz)
    {
        if (!Storage::disk('local')->exists($kmz->file_path)) {
            return redirect()->back()->with('error', 'File KMZ asli tidak ditemukan di penyimpanan server.');
        }

        return Storage::disk('local')->download($kmz->file_path, $kmz->original_filename);
    }
}
