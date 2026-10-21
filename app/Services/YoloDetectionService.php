<?php

namespace App\Services;

use App\Models\Photo;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class YoloDetectionService
{
    protected string $baseUrl;
    protected int $timeout;
    protected string $apiKey;
    protected array $targetClasses;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('ai.base_url', 'http://127.0.0.1:8001'), '/');
        $this->timeout = (int) config('ai.timeout', 15);
        $this->apiKey = config('ai.api_key', 'pln-tegal-internal-token-2026');
        $this->targetClasses = config('ai.target_classes', ['kWh', 'Tiang', 'Trafo']);
    }

    /**
     * Memeriksa kesehatan dan kesiapan model layanan AI Python
     */
    public function checkHealth(): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'X-API-Key' => $this->apiKey,
                ])
                ->get("{$this->baseUrl}/api/v1/health");

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'online' => true,
                    'is_production_ready' => (bool) ($data['is_production_ready'] ?? false),
                    'model_loaded' => (bool) ($data['model_loaded'] ?? false),
                    'data' => $data,
                    'message' => $data['notice'] ?? 'Layanan AI Python aktif dan terhubung.',
                ];
            }

            return [
                'online' => false,
                'is_production_ready' => false,
                'model_loaded' => false,
                'data' => [],
                'message' => "Layanan AI mengembalikan kode HTTP {$response->status()}.",
            ];
        } catch (ConnectionException $e) {
            return [
                'online' => false,
                'is_production_ready' => false,
                'model_loaded' => false,
                'data' => [],
                'message' => "Tidak dapat terhubung ke Layanan AI Python di {$this->baseUrl} (Koneksi ditolak / Service belum aktif).",
            ];
        } catch (Exception $e) {
            return [
                'online' => false,
                'is_production_ready' => false,
                'model_loaded' => false,
                'data' => [],
                'message' => "Kesalahan saat menghubungi layanan AI: " . $e->getMessage(),
            ];
        }
    }

    /**
     * Mengirimkan foto dari Laravel ke API Python dan menyimpan hasil deteksi ke basis data
     *
     * @param Photo $photo
     * @param bool $simulateIntegration Mengizinkan pengujian jalur komunikasi data bolak-balik
     * @return array
     */
    public function detectPhoto(Photo $photo, bool $simulateIntegration = false): array
    {
        // 1. Dapatkan lokasi fisik berkas foto tanpa mengubah berkas aslinya
        $relativePath = $photo->photo_path;
        $disk = Storage::disk('public')->exists($relativePath) ? 'public' : 'local';

        if (!Storage::disk($disk)->exists($relativePath)) {
            $errorMsg = "Berkas fisik foto tidak ditemukan pada storage '{$relativePath}'.";
            $photo->update([
                'processing_status' => 'failed',
                'detection_notes' => $errorMsg,
            ]);

            return [
                'success' => false,
                'message' => $errorMsg,
                'photo' => $photo,
            ];
        }

        $fileContent = Storage::disk($disk)->get($relativePath);
        $fileName = basename($photo->photo_name);

        // 2. Kirim permintaan HTTP Multipart ke Layanan AI Python
        try {
            $request = Http::timeout($this->timeout)
                ->withHeaders([
                    'X-API-Key' => $this->apiKey,
                    'X-Image-Name' => $fileName,
                    'X-Simulation-Test' => $simulateIntegration ? 'true' : 'false',
                ])
                ->attach('image', $fileContent, $fileName);

            $response = $request->post("{$this->baseUrl}/api/v1/detect");

            if (!$response->successful()) {
                $errorMsg = "Layanan AI mengembalikan kesalahan HTTP {$response->status()}: " . $response->body();
                Log::warning("AI Detection failed for photo #{$photo->id}: {$errorMsg}");

                $photo->update([
                    'processing_status' => 'failed',
                    'detection_notes' => "Gagal memproses deteksi pada API Python (HTTP {$response->status()}).",
                ]);

                return [
                    'success' => false,
                    'message' => $errorMsg,
                    'photo' => $photo,
                ];
            }

            $result = $response->json();

            // 3. Tangani kasus: Model kustom belum dilatih/tersedia (Kejujuran Arsitektur)
            if (isset($result['status']) && $result['status'] === 'custom_model_not_found') {
                $notice = $result['message'] ?? 'Model khusus belum tersedia.';
                
                $photo->update([
                    'processing_status' => 'unprocessed',
                    'detection_notes' => $notice,
                ]);

                return [
                    'success' => false,
                    'is_production_ready' => false,
                    'message' => $notice,
                    'photo' => $photo->fresh(),
                ];
            }

            // 4. Proses Hasil Deteksi yang Diterima
            $detections = $result['detections'] ?? [];

            if (empty($detections)) {
                $photo->update([
                    'processing_status' => 'failed',
                    'detection_confidence' => 0.00,
                    'detection_notes' => 'Tidak ada objek PLN (kWh, Tiang, Trafo) yang terdeteksi dengan tingkat keyakinan yang memadai.',
                ]);

                return [
                    'success' => true,
                    'is_production_ready' => (bool) ($result['is_production_ready'] ?? false),
                    'message' => 'Tidak ada objek target yang terdeteksi.',
                    'photo' => $photo->fresh(),
                ];
            }

            // Ambil deteksi dengan keyakinan tertinggi
            $bestDetection = $detections[0];
            $rawConfidence = $bestDetection['confidence'] ?? null;
            $predictedClass = (string) ($bestDetection['class'] ?? 'kWh');
            $bbox = $bestDetection['bbox'] ?? [];

            // Validasi: Pastikan target kelas valid (kWh, Tiang, atau Trafo)
            if (!in_array($predictedClass, $this->targetClasses)) {
                $predictedClass = 'kWh';
            }

            // 1 & 2. Validasi bahwa confidence berada pada rentang 0 sampai 1
            $confidenceService = app(\App\Services\DetectionConfidenceService::class);
            $validatedConfidence = $confidenceService->validateRawConfidence($rawConfidence);

            // 3. Ubah nilai menjadi persentase untuk ditampilkan
            $displayPercentage = $confidenceService->formatToPercentage($validatedConfidence);

            // 4. Simpan nilai numerik aslinya di database
            $dbConfidence = $confidenceService->prepareForStorage($validatedConfidence);

            // Tentukan status awal:
            // Sesuai aturan: "Jangan membuat status Sesuai sebelum aturan pemeriksaan diterapkan."
            // Foto kWh diarahkan ke 'Perlu Verifikasi' untuk verifikasi operator, sedangkan non-kWh diberi status '-'
            $initialStatus = ($predictedClass === 'kWh') ? 'Perlu Verifikasi' : '-';

            // Catatan deteksi transparan
            $simulationNotice = !empty($result['is_simulation']) ? '[SIMULASI INTEGRASI] ' : '';
            $notes = "{$simulationNotice}Terdeteksi: {$predictedClass} (Tingkat Keyakinan Model: {$displayPercentage}). " .
                     "Bounding Box: [" . implode(', ', array_map(fn($v) => round($v, 1), $bbox)) . "].";

            // 5. Data OCR Jika Tersedia (Khusus Foto kWh)
            $ocrData = $result['ocr'] ?? null;
            $updatePayload = [
                'photo_type' => $predictedClass,
                'detection_confidence' => $dbConfidence,
                'status' => $initialStatus,
                'processing_status' => 'success',
                'crop_box' => !empty($bbox) ? $bbox : null,
                'detection_notes' => $notes,
            ];

            if ($predictedClass === 'kWh' && is_array($ocrData)) {
                $updatePayload['raw_ocr_text'] = $ocrData['raw_text'] ?? null;
                $updatePayload['ocr_confidence'] = isset($ocrData['ocr_confidence']) ? (float) $ocrData['ocr_confidence'] : null;
                $updatePayload['ocr_status'] = $ocrData['ocr_status'] ?? 'needs_verification';
                $updatePayload['candidate_customer_id'] = $ocrData['candidate_customer_id'] ?? null;
                $updatePayload['candidate_meter_brand'] = $ocrData['candidate_meter_brand'] ?? null;
                $updatePayload['needs_verification'] = (bool) ($ocrData['needs_verification'] ?? false);
                $updatePayload['ocr_result'] = $ocrData['candidate_customer_id'] ?? $updatePayload['candidate_meter_brand'];
                
                // Tambahkan keterangan alasan verifikasi pada catatan
                if (!empty($ocrData['verification_reason'])) {
                    $updatePayload['detection_notes'] .= " OCR: " . $ocrData['verification_reason'];
                }
            } else {
                // Untuk foto non-kWh (Tiang & Trafo), OCR dilewati
                $updatePayload['ocr_status'] = 'skipped';
                $updatePayload['candidate_customer_id'] = null;
                $updatePayload['candidate_meter_brand'] = null;
                $updatePayload['raw_ocr_text'] = null;
                $updatePayload['ocr_confidence'] = null;
                $updatePayload['needs_verification'] = false;
            }

            // 6. Simpan Hasil Deteksi & OCR ke Basis Data MySQL
            $photo->update($updatePayload);

            return [
                'success' => true,
                'is_production_ready' => (bool) ($result['is_production_ready'] ?? false),
                'is_simulation' => (bool) ($result['is_simulation'] ?? false),
                'message' => "Berhasil memproses deteksi: {$predictedClass} ({$dbConfidence}%)",
                'photo' => $photo->fresh(),
                'detection_data' => $bestDetection,
                'ocr_data' => $ocrData,
            ];
        } catch (ConnectionException $e) {
            $errorMsg = "Koneksi ke Layanan AI Python gagal (Timeout/Offline): " . $e->getMessage();
            Log::error($errorMsg);

            $photo->update([
                'processing_status' => 'failed',
                'detection_notes' => 'Gagal menghubungi Layanan AI Python (Connection Timeout/Offline). Pastikan server Python aktif.',
            ]);

            return [
                'success' => false,
                'message' => $errorMsg,
                'photo' => $photo,
            ];
        } catch (Exception $e) {
            $errorMsg = "Terjadi kesalahan internal saat deteksi: " . $e->getMessage();
            Log::error($errorMsg);

            $photo->update([
                'processing_status' => 'failed',
                'detection_notes' => $errorMsg,
            ]);

            return [
                'success' => false,
                'message' => $errorMsg,
                'photo' => $photo,
            ];
        }
    }

    /**
     * Menjalankan proses pembacaan OCR mandiri pada foto kWh
     */
    public function runOcrOnPhoto(Photo $photo, ?string $sampleQuality = null): array
    {
        if ($photo->photo_type !== 'kWh') {
            return [
                'success' => false,
                'message' => 'OCR hanya dijalankan untuk foto berjenis kWh meter (foto Tiang dan Trafo dilewati).',
                'photo' => $photo,
            ];
        }

        $relativePath = $photo->photo_path;
        $disk = Storage::disk('public')->exists($relativePath) ? 'public' : 'local';

        if (!Storage::disk($disk)->exists($relativePath)) {
            return [
                'success' => false,
                'message' => 'Berkas fisik foto tidak ditemukan pada storage.',
                'photo' => $photo,
            ];
        }

        $fileContent = Storage::disk($disk)->get($relativePath);
        $fileName = basename($photo->photo_name);

        try {
            $request = Http::timeout($this->timeout)
                ->withHeaders([
                    'X-API-Key' => $this->apiKey,
                    'X-Image-Name' => $fileName,
                    'X-OCR-Sample' => $sampleQuality ?? '',
                ])
                ->attach('image', $fileContent, $fileName);

            $response = $request->post("{$this->baseUrl}/api/v1/ocr");

            if (!$response->successful()) {
                $errorMsg = "Layanan OCR mengembalikan kesalahan HTTP {$response->status()}: " . $response->body();
                $photo->update([
                    'ocr_status' => 'failed',
                    'detection_notes' => 'Gagal memproses OCR pada API Python.',
                ]);
                return [
                    'success' => false,
                    'message' => $errorMsg,
                    'photo' => $photo,
                ];
            }

            $ocrData = $response->json();
            $photo->update([
                'raw_ocr_text' => $ocrData['raw_text'] ?? null,
                'ocr_confidence' => isset($ocrData['ocr_confidence']) ? (float) $ocrData['ocr_confidence'] : null,
                'ocr_status' => $ocrData['ocr_status'] ?? 'needs_verification',
                'candidate_customer_id' => $ocrData['candidate_customer_id'] ?? null,
                'candidate_meter_brand' => $ocrData['candidate_meter_brand'] ?? null,
                'needs_verification' => (bool) ($ocrData['needs_verification'] ?? false),
                'ocr_result' => $ocrData['candidate_customer_id'] ?? $ocrData['candidate_meter_brand'],
                'detection_notes' => "OCR (" . ($ocrData['ocr_status'] ?? '-') . "): " . ($ocrData['verification_reason'] ?? ''),
            ]);

            return [
                'success' => true,
                'message' => 'Pembacaan OCR berhasil dijalankan.',
                'photo' => $photo->fresh(),
                'ocr_data' => $ocrData,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Gagal menghubungi Layanan OCR: ' . $e->getMessage(),
                'photo' => $photo,
            ];
        }
    }
}
