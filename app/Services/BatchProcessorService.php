<?php

namespace App\Services;

use App\Jobs\ProcessPhotoDetectionJob;
use App\Models\KmzBatch;
use App\Models\Photo;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Service Orkestrasi Pemrosesan Batch KMZ & Pengelolaan Antrean
 * PT PLN (Persero) UP3 Tegal
 */
class BatchProcessorService
{
    protected YoloDetectionService $aiService;
    protected DetectionValidationService $validator;

    public function __construct(YoloDetectionService $aiService, DetectionValidationService $validator)
    {
        $this->aiService = $aiService;
        $this->validator = $validator;
    }

    /**
     * Memasukkan seluruh foto yang belum diproses dalam batch ke antrean asinkron (Queue)
     * Dilengkapi proteksi pencegahan eksekusi ganda tanpa kontrol
     */
    public function dispatchBatch(KmzBatch $batch, bool $simulate = false): array
    {
        // 1. Cegah batch yang sama diproses berulang tanpa kontrol
        if ($batch->is_processing) {
            return [
                'success' => false,
                'message' => "Batch '{$batch->batch_code}' sedang berjalan dalam antrean pemrosesan. Harap tunggu hingga proses selesai.",
            ];
        }

        $pendingPhotos = $batch->photos()
            ->where('processing_status', 'unprocessed')
            ->get();

        if ($pendingPhotos->isEmpty()) {
            return [
                'success' => false,
                'message' => "Tidak ada foto baru yang menunggu pemrosesan pada batch ini.",
            ];
        }

        // Ubah status batch menjadi 'berjalan'
        $batch->update([
            'status' => 'berjalan',
            'error_message' => null,
        ]);

        // Masukkan setiap foto ke dalam antrean (Laravel Queue)
        foreach ($pendingPhotos as $photo) {
            ProcessPhotoDetectionJob::dispatch($photo, $simulate);
        }

        return [
            'success' => true,
            'count' => $pendingPhotos->count(),
            'message' => "Sebanyak {$pendingPhotos->count()} foto pada batch '{$batch->batch_code}' berhasil dimasukkan ke antrean pemrosesan.",
        ];
    }

    /**
     * Memproses batch secara langsung/sinkron (untuk pengujian atau batch kecil)
     */
    public function processBatchSynchronously(KmzBatch $batch, bool $simulate = false, ?int $limit = null): array
    {
        // Proteksi eksekusi ganda
        if ($batch->is_processing) {
            return [
                'success' => false,
                'message' => "Batch sedang dalam proses berjalan.",
            ];
        }

        $query = $batch->photos()->where('processing_status', 'unprocessed');
        if ($limit) {
            $query->take($limit);
        }
        $photos = $query->get();

        if ($photos->isEmpty()) {
            return [
                'success' => false,
                'message' => "Tidak ada foto yang perlu diproses.",
            ];
        }

        $batch->update(['status' => 'berjalan', 'error_message' => null]);

        $success = 0;
        $failed = 0;

        foreach ($photos as $photo) {
            $photo->update(['processing_status' => 'processing']);

            try {
                // Deteksi YOLO
                $result = $this->aiService->detectPhoto($photo, $simulate);

                if ($result['success']) {
                    $refreshed = $photo->fresh();
                    // OCR jika kWh
                    if ($refreshed->photo_type === 'kWh' && empty($refreshed->raw_ocr_text)) {
                        $this->aiService->runOcrOnPhoto($refreshed);
                    }
                    // Validasi aturan
                    $this->validator->validatePhoto($refreshed->fresh());
                    $success++;
                } else {
                    $photo->update([
                        'processing_status' => 'failed',
                        'detection_notes' => $result['message'] ?? 'Deteksi AI gagal.',
                    ]);
                    $failed++;
                }
            } catch (Exception $e) {
                Log::warning("Error sinkron foto #{$photo->id}: " . $e->getMessage());
                $photo->update([
                    'processing_status' => 'failed',
                    'detection_notes' => 'Kendala eksekusi: ' . $e->getMessage(),
                ]);
                $failed++;
            }
        }

        // Perbarui status akhir batch
        $batch->refresh();
        if ($batch->pending_photos_count === 0) {
            if ($batch->failed_photos_count === 0) {
                $batch->update(['status' => 'selesai']);
            } elseif ($batch->success_photos_count > 0) {
                $batch->update([
                    'status' => 'selesai',
                    'notes' => "Selesai: {$batch->success_photos_count} berhasil, {$batch->failed_photos_count} gagal.",
                ]);
            } else {
                $batch->update(['status' => 'gagal', 'error_message' => 'Seluruh foto gagal diproses.']);
            }
        }

        return [
            'success' => true,
            'processed_count' => $photos->count(),
            'success_count' => $success,
            'failed_count' => $failed,
            'message' => "Pemrosesan selesai: {$success} berhasil, {$failed} gagal.",
        ];
    }

    /**
     * Mengulangi pemrosesan foto yang gagal tanpa memproses ulang foto yang sudah berhasil
     */
    public function retryFailedPhotos(KmzBatch $batch, bool $simulate = false, bool $sync = false): array
    {
        if ($batch->is_processing) {
            return [
                'success' => false,
                'message' => "Batch sedang dalam proses. Harap tunggu hingga selesai sebelum mengulang foto gagal.",
            ];
        }

        $failedPhotos = $batch->photos()
            ->where('processing_status', 'failed')
            ->get();

        if ($failedPhotos->isEmpty()) {
            return [
                'success' => false,
                'message' => "Tidak ada foto gagal yang ditemukan pada batch '{$batch->batch_code}'.",
            ];
        }

        // Reset status foto gagal menjadi 'unprocessed'
        foreach ($failedPhotos as $photo) {
            $photo->update([
                'processing_status' => 'unprocessed',
                'detection_notes' => 'Dijadwalkan untuk diproses ulang oleh petugas.',
            ]);
        }

        if ($sync) {
            return $this->processBatchSynchronously($batch, $simulate);
        }

        return $this->dispatchBatch($batch, $simulate);
    }
}
