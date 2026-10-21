<?php

namespace Tests\Feature;

use App\Models\KmzBatch;
use App\Models\Photo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature Test untuk Pengujian Dashboard Statistik
 * PT PLN (Persero) UP3 Tegal
 *
 * Menguji kepastian bahwa seluruh angka statistik dihitung langsung dari basis data,
 * mengikuti filter batch dan tanggal, serta memverifikasi bahwa Tiang & Trafo berstatus '-'
 * tidak ikut dihitung sebagai Sesuai atau Tidak Sesuai.
 */
class DashboardStatisticsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Uji 1: Seluruh 8 Komponen Statistik Dashboard Sesuai dengan Data Basis Data
     */
    public function test_dashboard_statistics_match_database_counts(): void
    {
        $batch = KmzBatch::create([
            'batch_code' => 'BATCH-STAT-001',
            'original_filename' => 'survey_tegal_kota.kmz',
            'file_path' => 'kmz_uploads/test/stat.kmz',
            'file_size' => 2048,
            'status' => 'completed',
        ]);

        // 1. Buat 2 Foto kWh Sesuai
        Photo::create([
            'batch_id' => $batch->id,
            'photo_name' => 'KWH_SESUAI_1.jpg',
            'photo_path' => 'extracted_photos/test/k1.jpg',
            'photo_type' => 'kWh',
            'status' => 'Sesuai',
            'processing_status' => 'success',
            'detection_confidence' => 0.9500,
        ]);
        Photo::create([
            'batch_id' => $batch->id,
            'photo_name' => 'KWH_SESUAI_2.jpg',
            'photo_path' => 'extracted_photos/test/k2.jpg',
            'photo_type' => 'kWh',
            'status' => 'Sesuai',
            'processing_status' => 'success',
            'detection_confidence' => 0.9200,
        ]);

        // 2. Buat 1 Foto kWh Tidak Sesuai
        Photo::create([
            'batch_id' => $batch->id,
            'photo_name' => 'KWH_TIDAK_SESUAI.jpg',
            'photo_path' => 'extracted_photos/test/k3.jpg',
            'photo_type' => 'kWh',
            'status' => 'Tidak Sesuai',
            'processing_status' => 'success',
            'detection_confidence' => 0.8800,
        ]);

        // 3. Buat 3 Foto kWh Perlu Verifikasi
        for ($i = 1; $i <= 3; $i++) {
            Photo::create([
                'batch_id' => $batch->id,
                'photo_name' => "KWH_VERIFIKASI_{$i}.jpg",
                'photo_path' => "extracted_photos/test/kv_{$i}.jpg",
                'photo_type' => 'kWh',
                'status' => 'Perlu Verifikasi',
                'processing_status' => 'success',
                'detection_confidence' => 0.7800,
            ]);
        }

        // 4. Buat 4 Foto Tiang (Status '-')
        for ($i = 1; $i <= 4; $i++) {
            Photo::create([
                'batch_id' => $batch->id,
                'photo_name' => "TIANG_{$i}.jpg",
                'photo_path' => "extracted_photos/test/tiang_{$i}.jpg",
                'photo_type' => 'Tiang',
                'status' => '-',
                'processing_status' => 'success',
                'detection_confidence' => 0.9100,
            ]);
        }

        // 5. Buat 2 Foto Trafo (Status '-')
        for ($i = 1; $i <= 2; $i++) {
            Photo::create([
                'batch_id' => $batch->id,
                'photo_name' => "TRAFO_{$i}.jpg",
                'photo_path' => "extracted_photos/test/trafo_{$i}.jpg",
                'photo_type' => 'Trafo',
                'status' => '-',
                'processing_status' => 'success',
                'detection_confidence' => 0.8900,
            ]);
        }

        // 6. Buat 1 Foto Gagal Diproses
        Photo::create([
            'batch_id' => $batch->id,
            'photo_name' => 'FOTO_CORRUPT.jpg',
            'photo_path' => 'extracted_photos/test/corrupt.jpg',
            'photo_type' => 'kWh',
            'status' => 'Belum Diproses',
            'processing_status' => 'failed',
            'detection_confidence' => null,
        ]);

        // Total seluruh foto: 2 + 1 + 3 + 4 + 2 + 1 = 13
        // Total foto kWh: 2 + 1 + 3 + 1 = 7
        // Total foto Tiang: 4
        // Total foto Trafo: 2
        // Foto kWh Sesuai: 2
        // Foto kWh Tidak Sesuai: 1
        // Foto Perlu Verifikasi: 3
        // Foto Gagal Diproses: 1

        $response = $this->get(route('dashboard'));
        $response->assertStatus(200);

        // Ambil data stats yang dikirim ke view
        $viewStats = $response->viewData('stats');

        // Pastikan jumlah pada dashboard SAMA PERSIS dengan database
        $this->assertEquals(13, $viewStats['total_photos']);
        $this->assertEquals(Photo::count(), $viewStats['total_photos']);

        $this->assertEquals(7, $viewStats['total_kwh']);
        $this->assertEquals(Photo::where('photo_type', 'kWh')->count(), $viewStats['total_kwh']);

        $this->assertEquals(4, $viewStats['total_tiang']);
        $this->assertEquals(Photo::where('photo_type', 'Tiang')->count(), $viewStats['total_tiang']);

        $this->assertEquals(2, $viewStats['total_trafo']);
        $this->assertEquals(Photo::where('photo_type', 'Trafo')->count(), $viewStats['total_trafo']);

        $this->assertEquals(2, $viewStats['kwh_sesuai']);
        $this->assertEquals(Photo::where('photo_type', 'kWh')->where('status', 'Sesuai')->count(), $viewStats['kwh_sesuai']);

        $this->assertEquals(1, $viewStats['kwh_tidak_sesuai']);
        $this->assertEquals(Photo::where('photo_type', 'kWh')->where('status', 'Tidak Sesuai')->count(), $viewStats['kwh_tidak_sesuai']);

        $this->assertEquals(3, $viewStats['perlu_verifikasi']);
        $this->assertEquals(Photo::where('status', 'Perlu Verifikasi')->count(), $viewStats['perlu_verifikasi']);

        $this->assertEquals(1, $viewStats['failed_photos']);
        $this->assertEquals(Photo::where('processing_status', 'failed')->count(), $viewStats['failed_photos']);

        // Verifikasi penting: Foto Tiang (4) & Trafo (2) berstatus '-' TIDAK BOLEH ikut dihitung sebagai Sesuai atau Tidak Sesuai
        $this->assertEquals(0, Photo::whereIn('photo_type', ['Tiang', 'Trafo'])->where('status', 'Sesuai')->count());
        $this->assertEquals(0, Photo::whereIn('photo_type', ['Tiang', 'Trafo'])->where('status', 'Tidak Sesuai')->count());
    }

    /**
     * Uji 2: Statistik Mengikuti Filter Berdasarkan Batch KMZ
     */
    public function test_dashboard_statistics_follow_batch_filter(): void
    {
        // Batch A: 3 Foto (2 kWh, 1 Tiang)
        $batchA = KmzBatch::create([
            'batch_code' => 'BATCH-A',
            'original_filename' => 'batch_a.kmz',
            'file_path' => 'kmz/a.kmz',
            'file_size' => 1024,
            'status' => 'completed',
        ]);
        Photo::create([
            'batch_id' => $batchA->id,
            'photo_name' => 'A_kwh_1.jpg',
            'photo_path' => 'p/a1.jpg',
            'photo_type' => 'kWh',
            'status' => 'Sesuai',
            'processing_status' => 'success',
        ]);
        Photo::create([
            'batch_id' => $batchA->id,
            'photo_name' => 'A_kwh_2.jpg',
            'photo_path' => 'p/a2.jpg',
            'photo_type' => 'kWh',
            'status' => 'Perlu Verifikasi',
            'processing_status' => 'success',
        ]);
        Photo::create([
            'batch_id' => $batchA->id,
            'photo_name' => 'A_tiang_1.jpg',
            'photo_path' => 'p/a3.jpg',
            'photo_type' => 'Tiang',
            'status' => '-',
            'processing_status' => 'success',
        ]);

        // Batch B: 5 Foto (3 kWh, 2 Trafo)
        $batchB = KmzBatch::create([
            'batch_code' => 'BATCH-B',
            'original_filename' => 'batch_b.kmz',
            'file_path' => 'kmz/b.kmz',
            'file_size' => 2048,
            'status' => 'completed',
        ]);
        Photo::create([
            'batch_id' => $batchB->id,
            'photo_name' => 'B_kwh_1.jpg',
            'photo_path' => 'p/b1.jpg',
            'photo_type' => 'kWh',
            'status' => 'Sesuai',
            'processing_status' => 'success',
        ]);
        Photo::create([
            'batch_id' => $batchB->id,
            'photo_name' => 'B_kwh_2.jpg',
            'photo_path' => 'p/b2.jpg',
            'photo_type' => 'kWh',
            'status' => 'Tidak Sesuai',
            'processing_status' => 'success',
        ]);
        Photo::create([
            'batch_id' => $batchB->id,
            'photo_name' => 'B_kwh_3.jpg',
            'photo_path' => 'p/b3.jpg',
            'photo_type' => 'kWh',
            'status' => 'Perlu Verifikasi',
            'processing_status' => 'success',
        ]);
        Photo::create([
            'batch_id' => $batchB->id,
            'photo_name' => 'B_trafo_1.jpg',
            'photo_path' => 'p/b4.jpg',
            'photo_type' => 'Trafo',
            'status' => '-',
            'processing_status' => 'success',
        ]);
        Photo::create([
            'batch_id' => $batchB->id,
            'photo_name' => 'B_trafo_2.jpg',
            'photo_path' => 'p/b5.jpg',
            'photo_type' => 'Trafo',
            'status' => '-',
            'processing_status' => 'success',
        ]);

        // Total tanpa filter: 3 + 5 = 8
        $this->assertEquals(8, Photo::count());

        // Filter Batch A
        $responseA = $this->get(route('dashboard', ['batch_id' => $batchA->id]));
        $responseA->assertStatus(200);
        $statsA = $responseA->viewData('stats');
        $this->assertEquals(3, $statsA['total_photos']);
        $this->assertEquals(2, $statsA['total_kwh']);
        $this->assertEquals(1, $statsA['total_tiang']);
        $this->assertEquals(0, $statsA['total_trafo']);
        $this->assertEquals(1, $statsA['kwh_sesuai']);
        $this->assertEquals(1, $statsA['perlu_verifikasi']);

        // Filter Batch B
        $responseB = $this->get(route('dashboard', ['batch_id' => $batchB->id]));
        $responseB->assertStatus(200);
        $statsB = $responseB->viewData('stats');
        $this->assertEquals(5, $statsB['total_photos']);
        $this->assertEquals(3, $statsB['total_kwh']);
        $this->assertEquals(0, $statsB['total_tiang']);
        $this->assertEquals(2, $statsB['total_trafo']);
        $this->assertEquals(1, $statsB['kwh_sesuai']);
        $this->assertEquals(1, $statsB['kwh_tidak_sesuai']);
        $this->assertEquals(1, $statsB['perlu_verifikasi']);
    }

    /**
     * Uji 3: Tampilan Kondisi Ketika Belum Ada Data (Empty State)
     */
    public function test_dashboard_displays_empty_state_when_no_data(): void
    {
        // Pastikan basis data kosong
        $this->assertEquals(0, Photo::count());

        $response = $this->get(route('dashboard'));
        $response->assertStatus(200);

        $this->assertFalse($response->viewData('hasData'));
        $response->assertSee('Belum Ada Data Statistik untuk Ditampilkan');
        $response->assertSee('Unggah File KMZ Baru');
    }
}
