<?php

namespace App\Http\Controllers;

use App\Models\KmzBatch;
use App\Models\Photo;
use App\Services\DashboardStatisticsService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Tampilkan halaman dashboard utama statistik sistem deteksi foto kWh
     */
    public function index(Request $request, DashboardStatisticsService $statsService)
    {
        $filters = $request->only(['batch_id', 'start_date', 'end_date']);

        // 1. Dapatkan seluruh komponen statistik database melalui service terintegrasi
        $stats = $statsService->getStatistics($filters);

        // 2. Daftar opsi Batch KMZ untuk dropdown filter
        $batchOptions = $statsService->getBatchOptions();
        $totalBatches = KmzBatch::count();

        // 3. Batch yang sedang difilter (jika ada)
        $selectedBatch = !empty($filters['batch_id']) ? KmzBatch::find($filters['batch_id']) : null;

        // 4. Batch Terkini (5 terakhir)
        $recentBatches = KmzBatch::withCount(['photos', 'kwhPhotos', 'tiangPhotos', 'trafoPhotos'])
            ->latest()
            ->take(5)
            ->get();

        // 5. Foto Terkini (8 foto terakhir, mengikuti filter jika ada)
        $photosQuery = Photo::with('batch')->latest();
        if (!empty($filters['batch_id'])) {
            $photosQuery->where('batch_id', $filters['batch_id']);
        }
        if (!empty($filters['start_date'])) {
            $photosQuery->whereDate('created_at', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $photosQuery->whereDate('created_at', '<=', $filters['end_date']);
        }
        $recentPhotos = $photosQuery->take(8)->get();

        // Variabel penunjang untuk template Blade
        return view('dashboard', [
            'stats' => $stats,
            'filters' => $filters,
            'batchOptions' => $batchOptions,
            'selectedBatch' => $selectedBatch,
            'totalBatches' => $totalBatches,
            'recentBatches' => $recentBatches,
            'recentPhotos' => $recentPhotos,
            
            // Kompatibilitas langsung ke variabel nama deskriptif
            'totalPhotos' => $stats['total_photos'],
            'totalKwh' => $stats['total_kwh'],
            'totalTiang' => $stats['total_tiang'],
            'totalTrafo' => $stats['total_trafo'],
            'kwhSesuai' => $stats['kwh_sesuai'],
            'kwhTidakSesuai' => $stats['kwh_tidak_sesuai'],
            'perluVerifikasi' => $stats['perlu_verifikasi'],
            'failedCount' => $stats['failed_photos'],
            'unprocessedCount' => $stats['unprocessed_photos'],
            'processingCount' => $stats['processing_photos'],
            'successCount' => $stats['success_photos'],
            'hasData' => $stats['has_data'],
        ]);
    }
}
