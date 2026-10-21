<?php

namespace App\Services;

use App\Models\KmzBatch;
use App\Models\Photo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Service Pengelola Statistik & Agregasi Data Dashboard
 * PT PLN (Persero) UP3 Tegal
 *
 * Menggunakan query agregasi SQL tunggal yang sangat efisien untuk ribuan foto.
 */
class DashboardStatisticsService
{
    /**
     * Menghitung statistik lengkap berdasarkan filter batch dan rentang tanggal
     *
     * @param array $filters ['batch_id' => ..., 'start_date' => ..., 'end_date' => ...]
     * @return array
     */
    public function getStatistics(array $filters = []): array
    {
        $batchId = !empty($filters['batch_id']) ? (int) $filters['batch_id'] : null;
        $startDate = !empty($filters['start_date']) ? $filters['start_date'] : null;
        $endDate = !empty($filters['end_date']) ? $filters['end_date'] : null;

        // Query dasar dengan eager-loading kondisi filter
        $query = Photo::query();

        if ($batchId) {
            $query->where('batch_id', $batchId);
        }

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        // Eksekusi satu query agregasi tunggal di level MySQL untuk performa optimal
        $raw = $query->selectRaw("
            COUNT(*) as total_photos,
            SUM(CASE WHEN photo_type = 'kWh' THEN 1 ELSE 0 END) as total_kwh,
            SUM(CASE WHEN photo_type = 'Tiang' THEN 1 ELSE 0 END) as total_tiang,
            SUM(CASE WHEN photo_type = 'Trafo' THEN 1 ELSE 0 END) as total_trafo,
            SUM(CASE WHEN photo_type = 'kWh' AND status = 'Sesuai' THEN 1 ELSE 0 END) as kwh_sesuai,
            SUM(CASE WHEN photo_type = 'kWh' AND status = 'Tidak Sesuai' THEN 1 ELSE 0 END) as kwh_tidak_sesuai,
            SUM(CASE WHEN photo_type = 'kWh' AND status = 'Perlu Verifikasi' THEN 1 ELSE 0 END) as perlu_verifikasi,
            SUM(CASE WHEN processing_status = 'failed' THEN 1 ELSE 0 END) as failed_photos,
            SUM(CASE WHEN processing_status = 'unprocessed' THEN 1 ELSE 0 END) as unprocessed_photos,
            SUM(CASE WHEN processing_status = 'processing' THEN 1 ELSE 0 END) as processing_photos,
            SUM(CASE WHEN processing_status = 'success' THEN 1 ELSE 0 END) as success_photos,
            SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END) as verified_by_officer
        ")->first();

        $totalPhotos = (int) ($raw->total_photos ?? 0);
        $totalKwh = (int) ($raw->total_kwh ?? 0);
        $totalTiang = (int) ($raw->total_tiang ?? 0);
        $totalTrafo = (int) ($raw->total_trafo ?? 0);
        $kwhSesuai = (int) ($raw->kwh_sesuai ?? 0);
        $kwhTidakSesuai = (int) ($raw->kwh_tidak_sesuai ?? 0);
        $perluVerifikasi = (int) ($raw->perlu_verifikasi ?? 0);
        $failedPhotos = (int) ($raw->failed_photos ?? 0);
        $unprocessedPhotos = (int) ($raw->unprocessed_photos ?? 0);
        $processingPhotos = (int) ($raw->processing_photos ?? 0);
        $successPhotos = (int) ($raw->success_photos ?? 0);
        $verifiedByOfficer = (int) ($raw->verified_by_officer ?? 0);

        // Hitung persentase proporsi untuk visualisasi
        $kwhPercentage = $totalPhotos > 0 ? round(($totalKwh / $totalPhotos) * 100, 1) : 0;
        $tiangPercentage = $totalPhotos > 0 ? round(($totalTiang / $totalPhotos) * 100, 1) : 0;
        $trafoPercentage = $totalPhotos > 0 ? round(($totalTrafo / $totalPhotos) * 100, 1) : 0;

        // Persentase verifikasi khusus untuk objek kWh (karena Tiang & Trafo berstatus '-')
        $sesuaiPercentage = $totalKwh > 0 ? round(($kwhSesuai / $totalKwh) * 100, 1) : 0;
        $tidakSesuaiPercentage = $totalKwh > 0 ? round(($kwhTidakSesuai / $totalKwh) * 100, 1) : 0;
        $perluVerifikasiPercentage = $totalKwh > 0 ? round(($perluVerifikasi / $totalKwh) * 100, 1) : 0;

        return [
            // 8 Komponen Utama Dashboard yang Diwajibkan
            'total_photos' => $totalPhotos,
            'total_kwh' => $totalKwh,
            'total_tiang' => $totalTiang,
            'total_trafo' => $totalTrafo,
            'kwh_sesuai' => $kwhSesuai,
            'kwh_tidak_sesuai' => $kwhTidakSesuai,
            'perlu_verifikasi' => $perluVerifikasi,
            'failed_photos' => $failedPhotos,

            // Metrik Pipeline Pendukung
            'unprocessed_photos' => $unprocessedPhotos,
            'processing_photos' => $processingPhotos,
            'success_photos' => $successPhotos,
            'verified_by_officer' => $verifiedByOfficer,

            // Persentase Proporsi
            'percentages' => [
                'kwh' => $kwhPercentage,
                'tiang' => $tiangPercentage,
                'trafo' => $trafoPercentage,
                'sesuai' => $sesuaiPercentage,
                'tidak_sesuai' => $tidakSesuaiPercentage,
                'perlu_verifikasi' => $perluVerifikasiPercentage,
            ],

            // Indikator Data Kosong
            'has_data' => $totalPhotos > 0,
        ];
    }

    /**
     * Mendapatkan daftar opsi Batch KMZ untuk dropdown filter
     */
    public function getBatchOptions()
    {
        return KmzBatch::orderBy('id', 'desc')
            ->get(['id', 'batch_code', 'original_filename', 'created_at']);
    }
}
