<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Service Penanganan Fitur "Tingkat Deteksi" (Detection Confidence)
 * PT PLN (Persero) UP3 Tegal
 *
 * DEFINISI:
 * Tingkat Deteksi adalah confidence model YOLO terhadap kelas objek yang diprediksi,
 * bukan jaminan bahwa hasilnya benar secara faktual di lapangan.
 *
 * PENTING:
 * Confidence 95% TIDAK BERARTI akurasi model adalah 95%. Nilai persentase semata-mata
 * mencerminkan probabilitas keyakinan model visual berdasarkan bobot inferensi.
 */
class DetectionConfidenceService
{
    /**
     * Keterangan edukatif resmi mengenai arti confidence model
     */
    public const EXPLANATION = 'Tingkat Deteksi adalah derajat keyakinan (confidence) model visual YOLO terhadap kelas objek yang diprediksi (rentang 0% - 100%), bukan jaminan bahwa hasil deteksi benar secara faktual di lapangan. Misalnya, confidence 95% tidak berarti akurasi model adalah 95%.';

    /**
     * Ambang batas default (dalam persen 0.00 - 100.00)
     */
    protected float $defaultThreshold;

    public function __construct(?float $defaultThreshold = null)
    {
        if ($defaultThreshold !== null) {
            $this->defaultThreshold = $defaultThreshold;
        } elseif (function_exists('config') && function_exists('app') && app()->bound('config')) {
            $this->defaultThreshold = (float) config('pln_rules.active_rules.min_yolo_confidence', 75.00);
        } else {
            $this->defaultThreshold = 75.00;
        }
    }

    /**
     * Validasi nilai confidence mentah dari model YOLO (wajib berada pada rentang 0.0 sampai 1.0)
     *
     * @param mixed $confidence
     * @return float|null Mengembalikan float jika valid, atau null jika confidence kosong
     * @throws InvalidArgumentException Jika nilai bukan numerik atau berada di luar rentang [0.0, 1.0]
     */
    public function validateRawConfidence(mixed $confidence): ?float
    {
        if ($confidence === null || $confidence === '') {
            return null;
        }

        if (!is_numeric($confidence)) {
            throw new InvalidArgumentException("Nilai confidence harus berupa numerik. Nilai diberikan: '" . (string) $confidence . "'");
        }

        $val = (float) $confidence;

        if ($val < 0.0 || $val > 1.0) {
            throw new InvalidArgumentException("Nilai confidence model YOLO harus berada pada rentang 0 sampai 1. Nilai diberikan: {$val}");
        }

        return $val;
    }

    /**
     * Format nilai confidence menjadi persentase untuk tampilan antarmuka
     *
     * Ketentuan:
     * - Menggunakan pembulatan yang konsisten (default 2 digit desimal)
     * - Jika deteksi tidak tersedia (null), tampilkan "-"
     * - Menerima nilai dalam skala rasio (0.0 s.d 1.0) maupun skala persentase (0.0 s.d 100.0)
     *
     * @param float|null $confidence
     * @param int $decimals Jumlah digit desimal (default 2)
     * @return string
     * @throws InvalidArgumentException Jika nilai berada di luar rentang yang diizinkan (< 0 atau > 100)
     */
    public function formatToPercentage(?float $confidence, int $decimals = 2): string
    {
        if ($confidence === null) {
            return '-';
        }

        if ($confidence < 0.0) {
            throw new InvalidArgumentException("Nilai confidence tidak boleh bernilai negatif: {$confidence}");
        }

        // Jika nilai berada pada rentang probabilitas standar [0.0, 1.0], ubah ke persentase
        if ($confidence <= 1.0 && $confidence >= 0.0) {
            $percentage = $confidence * 100.0;
        } elseif ($confidence <= 100.0) {
            // Jika nilai sudah berupa angka persentase [1.0001, 100.0]
            $percentage = $confidence;
        } else {
            throw new InvalidArgumentException("Nilai confidence melebihi batas maksimum 100%: {$confidence}");
        }

        return number_format(round($percentage, $decimals), $decimals) . '%';
    }

    /**
     * Menyiapkan nilai numerik asli untuk disimpan di basis data MySQL
     *
     * Ketentuan:
     * - Menyimpan nilai numerik asli (bukan string/teks persentase berkarakter '%')
     * - Mempertahankan presisi hingga 4 digit desimal (misal 0.8950)
     *
     * @param float|null $validatedConfidence
     * @return float|null
     */
    public function prepareForStorage(?float $validatedConfidence): ?float
    {
        if ($validatedConfidence === null) {
            return null;
        }

        return round($validatedConfidence, 4);
    }

    /**
     * Mengambil ambang batas konfigurasi minimum confidence YOLO
     * Dapat disesuaikan melalui konfigurasi tanpa mengubah kode program
     */
    public function getThreshold(): float
    {
        if (function_exists('config') && function_exists('app') && app()->bound('config')) {
            return (float) config('pln_rules.active_rules.min_yolo_confidence', $this->defaultThreshold);
        }

        return $this->defaultThreshold;
    }

    /**
     * Memeriksa apakah nilai confidence berada di bawah ambang batas pemeriksaan
     *
     * @param float|null $confidence Nilai confidence (skala 0..1 atau 0..100)
     * @param float|null $customThreshold Ambang batas opsional (dalam skala persen, default dari config)
     * @return bool
     */
    public function isBelowThreshold(?float $confidence, ?float $customThreshold = null): bool
    {
        if ($confidence === null) {
            return true;
        }

        $threshold = $customThreshold ?? $this->getThreshold();

        // Normalisasi confidence ke skala persen (0.00 - 100.00)
        $percentage = ($confidence <= 1.0 && $confidence >= 0.0) ? ($confidence * 100.0) : $confidence;

        return $percentage < $threshold;
    }

    /**
     * Mendapatkan teks penjelasan resmi fitur Tingkat Deteksi untuk tampilan antarmuka
     */
    public function getExplanation(): string
    {
        return self::EXPLANATION;
    }
}
