<?php

namespace Tests\Unit;

use App\Models\Photo;
use App\Services\DetectionConfidenceService;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Unit Test untuk Fitur "Tingkat Deteksi" (Detection Confidence)
 * PT PLN (Persero) UP3 Tegal
 */
class DetectionConfidenceTest extends TestCase
{
    protected DetectionConfidenceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DetectionConfidenceService();
    }

    /**
     * Uji 1: Nilai Confidence Kosong (Null / Not Available)
     * - Validator mengembalikan null
     * - Tampilan persentase mengembalikan "-"
     */
    public function test_empty_confidence_returns_null_and_displays_dash(): void
    {
        // 1. Validator mentah menerima null dan string kosong
        $this->assertNull($this->service->validateRawConfidence(null));
        $this->assertNull($this->service->validateRawConfidence(''));

        // 2. Format tampilan jika deteksi tidak tersedia harus "-"
        $this->assertEquals('-', $this->service->formatToPercentage(null));

        // 3. Penyimpanan basis data untuk confidence kosong mengembalikan null
        $this->assertNull($this->service->prepareForStorage(null));

        // 4. Deteksi kosong dianggap di bawah ambang batas (memerlukan pemeriksaan)
        $this->assertTrue($this->service->isBelowThreshold(null));
    }

    /**
     * Uji 2: Nilai Confidence Valid Berada pada Rentang 0 sampai 1
     * - Menguji batas bawah (0.0), nilai tengah (0.5), nilai tipikal (0.8950), dan batas atas (1.0)
     * - Memastikan pembulatan persentase konsisten 2 digit desimal
     */
    public function test_valid_confidence_values_within_range_zero_to_one(): void
    {
        // Kasus A: Nilai tipikal YOLO (0.8950)
        $val1 = $this->service->validateRawConfidence(0.8950);
        $this->assertEquals(0.8950, $val1);
        $this->assertEquals('89.50%', $this->service->formatToPercentage($val1));

        // Kasus B: Batas Bawah (0.0)
        $valZero = $this->service->validateRawConfidence(0.0);
        $this->assertEquals(0.0, $valZero);
        $this->assertEquals('0.00%', $this->service->formatToPercentage($valZero));

        // Kasus C: Batas Atas (1.0)
        $valOne = $this->service->validateRawConfidence(1.0);
        $this->assertEquals(1.0, $valOne);
        $this->assertEquals('100.00%', $this->service->formatToPercentage($valOne));

        // Kasus D: Nilai string numerik valid (misal '0.9425')
        $valString = $this->service->validateRawConfidence('0.9425');
        $this->assertEquals(0.9425, $valString);
        $this->assertEquals('94.25%', $this->service->formatToPercentage($valString));

        // Kasus E: Pembulatan konsisten 2 desimal (0.88556 -> 88.56%)
        $this->assertEquals('88.56%', $this->service->formatToPercentage(0.88556));
    }

    /**
     * Uji 3: Nilai di Luar Rentang yang Diperbolehkan Melemparkan InvalidArgumentException
     * - Nilai negatif (< 0.0)
     * - Nilai melebihi 1.0 (> 1.0)
     * - Nilai non-numerik
     */
    public function test_confidence_values_outside_allowed_range_throw_exception(): void
    {
        // A. Nilai Negatif pada validateRawConfidence
        $this->expectException(InvalidArgumentException::class);
        $this->service->validateRawConfidence(-0.15);
    }

    public function test_confidence_greater_than_one_throws_exception_in_raw_validation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        // Nilai 1.05 berada di luar rentang [0.0, 1.0]
        $this->service->validateRawConfidence(1.05);
    }

    public function test_confidence_percentage_exceeding_100_in_raw_validation_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        // Nilai 95.0 tidak valid untuk raw confidence (harus 0.95)
        $this->service->validateRawConfidence(95.0);
    }

    public function test_non_numeric_confidence_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->validateRawConfidence('delapan-puluh-lima');
    }

    public function test_negative_confidence_in_formatter_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->formatToPercentage(-5.0);
    }

    public function test_confidence_exceeding_100_in_formatter_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->formatToPercentage(105.5);
    }

    /**
     * Uji 4: Nilai Numerik Asli Disimpan Tanpa Teks Persentase (%)
     */
    public function test_original_numeric_value_stored_without_percent_symbol(): void
    {
        $rawConfidence = 0.8950;
        $dbValue = $this->service->prepareForStorage($rawConfidence);

        $this->assertIsFloat($dbValue);
        $this->assertEquals(0.8950, $dbValue);
        $this->assertNotEquals('89.50%', $dbValue);
    }

    /**
     * Uji 5: Ambang Batas Keyakinan Bersifat Konfiguratif
     * - Foto di bawah ambang batas ditandai perlu diperiksa
     * - Foto di atas ambang batas lolos pemeriksaan keyakinan
     */
    public function test_configurable_threshold_for_flagging_photos(): void
    {
        // Uji dengan ambang batas kustom 75.00%
        $threshold = 75.00;

        // 70% (0.70) berada di bawah ambang batas -> Perlu diperiksa
        $this->assertTrue($this->service->isBelowThreshold(0.70, $threshold));
        $this->assertTrue($this->service->isBelowThreshold(70.0, $threshold));

        // 80% (0.80) berada di atas ambang batas -> Lolos
        $this->assertFalse($this->service->isBelowThreshold(0.80, $threshold));
        $this->assertFalse($this->service->isBelowThreshold(80.0, $threshold));

        // Uji ambang batas lebih ketat (90.00%)
        $this->assertTrue($this->service->isBelowThreshold(0.85, 90.00));
        $this->assertFalse($this->service->isBelowThreshold(0.95, 90.00));
    }

    /**
     * Uji 6: Integritas Filosofi - Confidence 95% Bukan Akurasi 95%
     */
    public function test_confidence_definition_clarifies_model_probability_not_accuracy(): void
    {
        $explanation = $this->service->getExplanation();

        $this->assertStringContainsString('Tingkat Deteksi', $explanation);
        $this->assertStringContainsString('confidence', strtolower($explanation));
        $this->assertStringContainsString('bukan jaminan', $explanation);
        $this->assertStringContainsString('95%', $explanation);
    }

    /**
     * Uji 7: Pemisahan Confidence Deteksi YOLO dan Confidence OCR
     * - Memastikan nilai confidence deteksi tidak tertimpa oleh confidence OCR
     */
    public function test_detection_confidence_is_separated_from_ocr_confidence(): void
    {
        $yoloConfidence = 0.8950; // YOLO visual
        $ocrConfidence = 92.50;   // OCR teks meter

        $formattedYolo = $this->service->formatToPercentage($yoloConfidence);
        $formattedOcr = $this->service->formatToPercentage($ocrConfidence);

        $this->assertEquals('89.50%', $formattedYolo);
        $this->assertEquals('92.50%', $formattedOcr);
        $this->assertNotEquals($formattedYolo, $formattedOcr);
    }

    /**
     * Uji 8: Eloquent Model Accessor Photo untuk Display Confidence & Pemisahan OCR
     */
    public function test_photo_model_accessors_for_display_confidence(): void
    {
        // 1. Foto dengan confidence kosong (null) -> Menampilkan "-"
        $photoNull = new Photo([
            'photo_type' => 'kWh',
            'detection_confidence' => null,
            'processing_status' => 'unprocessed',
        ]);
        $this->assertEquals('-', $photoNull->display_confidence);
        $this->assertNull($photoNull->confidence_percentage_value);

        // 2. Foto dengan nilai confidence rasio (0.8950) -> Menampilkan "89.50%"
        $photoRatio = new Photo([
            'photo_type' => 'kWh',
            'detection_confidence' => 0.8950,
            'processing_status' => 'success',
        ]);
        $this->assertEquals('89.50%', $photoRatio->display_confidence);
        $this->assertEquals(89.50, $photoRatio->confidence_percentage_value);

        // 3. Foto dengan nilai confidence persentase (96.50) -> Menampilkan "96.50%"
        $photoPercent = new Photo([
            'photo_type' => 'kWh',
            'detection_confidence' => 96.50,
            'processing_status' => 'success',
        ]);
        $this->assertEquals('96.50%', $photoPercent->display_confidence);
        $this->assertEquals(96.50, $photoPercent->confidence_percentage_value);

        // 4. Verifikasi pemisahan dari OCR confidence
        $photoWithOcr = new Photo([
            'photo_type' => 'kWh',
            'detection_confidence' => 0.8840,
            'ocr_confidence' => 94.20,
            'processing_status' => 'success',
        ]);
        // Confidence deteksi tetap 88.40%, tidak terpengaruh OCR
        $this->assertEquals('88.40%', $photoWithOcr->display_confidence);
        $this->assertEquals('94.20%', $photoWithOcr->display_ocr_confidence);
        $this->assertNotEquals($photoWithOcr->display_confidence, $photoWithOcr->display_ocr_confidence);
    }
}
