<?php

namespace App\Jobs;

use App\Models\Photo;
use App\Services\DetectionValidationService;
use App\Services\YoloDetectionService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Queue Job Pemrosesan Asinkron Setiap Foto Hasil Ekstraksi KMZ
 * PT PLN (Persero) UP3 Tegal
 *
 * Menjalankan:
 * 1. Deteksi objek YOLO (kWh, Tiang, Trafo)
 * 2. Pembacaan OCR stan plat meter jika terdeteksi kWh
 * 3. Evaluasi aturan validasi status PLN
 * 4. Pembaruan progres batch secara aman dan defensif
 */
class ProcessPhotoDetectionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 60;

    public Photo $photo;
    public bool $simulate;

    public function __construct(Photo $photo, bool $simulate = false)
    {
        $this->photo = $photo;
        $this->simulate = $simulate;
    }

    /**
     * Eksekusi pekerjaan pemrosesan foto
     */
    public function handle(YoloDetectionService $aiService, DetectionValidationService $validator): void
    {
        $photo = $this->photo->fresh();
        if (!$photo) {
            return;
        }

        $batch = $photo->batch;

        // Tandai status foto menjadi 'processing' (sedang diproses)
        $photo->update(['processing_status' => 'processing']);

        // Pastikan status batch tercatat 'berjalan'
        if ($batch && $batch->status !== 'berjalan') {
            $batch->update(['status' => 'berjalan']);
        }

        try {
            // 1. Eksekusi deteksi objek YOLO via Python AI Service
            $detectResult = $aiService->detectPhoto($photo, $this->simulate);

            if ($detectResult['success']) {
                $refreshedPhoto = $photo->fresh();

                // 2. Jika objek terdeteksi sebagai kWh meter, jalankan OCR
                if ($refreshedPhoto->photo_type === 'kWh' && empty($refreshedPhoto->raw_ocr_text)) {
                    $aiService->runOcrOnPhoto($refreshedPhoto);
                }

                // 3. Terapkan aturan bisnis validasi status PLN UP3 Tegal
                $validator->validatePhoto($refreshedPhoto->fresh());
            } else {
                // Tangani kegagalan deteksi tanpa menghentikan batch
                $safeMessage = Str::limit($detectResult['message'] ?? 'Deteksi objek AI tidak berhasil.', 200);
                $photo->update([
                    'processing_status' => 'failed',
                    'detection_notes' => $safeMessage,
                ]);
            }
        } catch (Exception $e) {
            // Catat log error secara aman tanpa membocorkan token/informasi sensitif
            $cleanError = preg_replace('/(password|token|key|secret)=([^\s&]+)/i', '$1=***', $e->getMessage());
            Log::warning("Gagal memproses foto ID #{$photo->id} pada batch #{$photo->batch_id}: {$cleanError}");

            $photo->update([
                'processing_status' => 'failed',
                'detection_notes' => 'Kendala pemrosesan antrean: ' . Str::limit($cleanError, 150),
            ]);
        } finally {
            // 4. Periksa apakah seluruh foto dalam batch ini telah selesai diproses
            if ($batch) {
                $batch->refresh();
                $pendingCount = $batch->pending_photos_count;

                if ($pendingCount === 0) {
                    $failedCount = $batch->failed_photos_count;
                    $successCount = $batch->success_photos_count;

                    if ($failedCount === 0) {
                        $batch->update(['status' => 'selesai']);
                    } elseif ($successCount > 0) {
                        $batch->update([
                            'status' => 'selesai',
                            'notes' => "Pemrosesan selesai. ({$successCount} berhasil, {$failedCount} gagal diproses)",
                        ]);
                    } else {
                        $batch->update([
                            'status' => 'gagal',
                            'error_message' => 'Seluruh foto dalam batch ini gagal diproses oleh worker.',
                        ]);
                    }
                }
            }
        }
    }
}
