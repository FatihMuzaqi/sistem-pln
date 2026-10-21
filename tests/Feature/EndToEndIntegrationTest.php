<?php

namespace Tests\Feature;

use App\Jobs\ProcessPhotoDetectionJob;
use App\Models\KmzBatch;
use App\Models\Photo;
use App\Services\BatchProcessorService;
use App\Services\DetectionValidationService;
use App\Services\ExcelExportService;
use App\Services\KmzExtractorService;
use App\Services\YoloDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/**
 * End-To-End Integration Test
 * Sistem Deteksi dan Identifikasi Foto kWh Berbasis Web
 * PT PLN (Persero) UP3 Tegal
 */
class EndToEndIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
    }

    /**
     * Helper untuk membuat file zip KMZ buatan dalam memory
     */
    protected function createFakeKmz(array $files): UploadedFile
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'kmz_test_') . '.kmz';
        $zip = new ZipArchive();
        $zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        return new UploadedFile($tempPath, 'test_jaringan_tegal.kmz', 'application/vnd.google-earth.kmz', null, true);
    }

    /**
     * UJI 1: Alur Lengkap Upload KMZ -> Pembuatan Batch -> Ekstraksi -> Masuk Antrean (Queue)
     */
    public function test_full_pipeline_upload_kmz_and_queue_dispatch(): void
    {
        Queue::fake();

        $dummyImage = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $kmlContent = '<?xml version="1.0" encoding="UTF-8"?><kml xmlns="http://www.opengis.net/kml/2.2"><Document><Placemark><name>Tiang_PLN_Tegal_01</name></Placemark></Document></kml>';

        $kmzFile = $this->createFakeKmz([
            'doc.kml' => $kmlContent,
            'images/meteran_kwh_01.jpg' => $dummyImage,
            'images/tiang_beton_02.jpg' => $dummyImage,
            'images/trafo_gardu_03.jpg' => $dummyImage,
        ]);

        $response = $this->post(route('kmz.store'), [
            'kmz_file' => $kmzFile,
            'notes' => 'Pengujian Integrasi Penuh UP3 Tegal',
        ]);

        $response->assertRedirect();

        // 1. Verifikasi batch dibuat di database dengan status awal yang benar
        $batch = KmzBatch::first();
        $this->assertNotNull($batch);
        $this->assertEquals('test_jaringan_tegal.kmz', $batch->original_filename);
        $this->assertEquals(3, $batch->total_photos_extracted);
        $this->assertTrue(in_array($batch->status, ['menunggu', 'berjalan', 'completed', 'selesai']));

        // 2. Verifikasi 3 foto tersimpan di database
        $this->assertDatabaseCount('photos', 3);
        $this->assertDatabaseHas('photos', ['photo_name' => 'meteran_kwh_01.jpg']);
        $this->assertDatabaseHas('photos', ['photo_name' => 'tiang_beton_02.jpg']);
        $this->assertDatabaseHas('photos', ['photo_name' => 'trafo_gardu_03.jpg']);

        // 3. Verifikasi Job dipasok ke Queue untuk setiap foto
        Queue::assertPushed(ProcessPhotoDetectionJob::class, 3);
    }

    /**
     * UJI 2: Eksekusi Deteksi YOLO, Cabang Alur kWh (Jalankan OCR) vs Non-kWh (Tiang/Trafo)
     */
    public function test_photo_processing_pipeline_yolo_and_ocr_branching(): void
    {
        $batch = KmzBatch::create([
            'batch_code' => 'BATCH-TEST-001',
            'original_filename' => 'survei.kmz',
            'file_path' => 'kmz/test.kmz',
            'file_size' => 1024,
            'total_files_found' => 3,
            'total_photos_extracted' => 3,
            'status' => 'menunggu',
        ]);

        // Mock fake storage photo
        Storage::disk('public')->put('photos/test_kwh.jpg', 'fake-image-bytes');
        Storage::disk('public')->put('photos/test_tiang.jpg', 'fake-image-bytes');

        $kwhPhoto = Photo::create([
            'batch_id' => $batch->id,
            'photo_name' => 'test_kwh.jpg',
            'photo_path' => 'photos/test_kwh.jpg',
            'processing_status' => 'unprocessed',
        ]);

        $tiangPhoto = Photo::create([
            'batch_id' => $batch->id,
            'photo_name' => 'test_tiang.jpg',
            'photo_path' => 'photos/test_tiang.jpg',
            'processing_status' => 'unprocessed',
        ]);

        // Simulasikan AI Service response untuk kWh dan Tiang
        Http::fake([
            '*/api/v1/detect*' => Http::response([
                'status' => 'success',
                'is_production_ready' => false,
                'is_simulation' => true,
                'detections' => [
                    ['class' => 'kWh', 'confidence' => 0.945, 'bbox' => [100, 100, 300, 300]]
                ]
            ], 200),
            '*/api/v1/ocr*' => Http::response([
                'status' => 'success',
                'raw_text' => "52103488912\nMELCOINDA\n12345",
                'customer_id' => '52103488912',
                'meter_brand' => 'MELCOINDA',
                'ocr_confidence' => 0.88,
                'reading_status' => 'success',
                'needs_manual_verification' => false
            ], 200),
        ]);

        $jobKwh = new ProcessPhotoDetectionJob($kwhPhoto, true); // simulate = true
        app()->call([$jobKwh, 'handle']);

        $kwhPhoto->refresh();
        $this->assertEquals('kWh', $kwhPhoto->photo_type);
        $this->assertNotNull($kwhPhoto->detection_confidence);
        $this->assertGreaterThan(0, $kwhPhoto->detection_confidence);
        $this->assertNotEmpty($kwhPhoto->raw_ocr_text);
        $this->assertNotNull($kwhPhoto->customer_id);
        $this->assertNotNull($kwhPhoto->meter_brand);
        $this->assertEquals('success', $kwhPhoto->processing_status);
        $this->assertTrue(in_array($kwhPhoto->status, ['Sesuai', 'Perlu Verifikasi', 'Tidak Sesuai']));

        // Untuk foto Tiang: YOLO menghasilkan kelas Tiang, IDPEL & Brand tetap kosong / null
        $tiangPhoto->update(['photo_type' => 'Tiang', 'detection_confidence' => 0.92]);
        $validator = app(DetectionValidationService::class);
        $validator->validatePhoto($tiangPhoto);

        $tiangPhoto->refresh();
        $this->assertEquals('Tiang', $tiangPhoto->photo_type);
        $this->assertNull($tiangPhoto->customer_id);
        $this->assertNull($tiangPhoto->meter_brand);
        $this->assertEquals('-', $tiangPhoto->display_status);
        $this->assertEquals('-', $tiangPhoto->display_customer_id);
        $this->assertEquals('-', $tiangPhoto->display_meter_brand);
    }

    /**
     * UJI 3: Penanganan Foto Gagal Tanpa Menghentikan Seluruh Batch
     */
    public function test_failed_photo_handling_does_not_break_batch(): void
    {
        $batch = KmzBatch::create([
            'batch_code' => 'BATCH-TEST-FAIL',
            'original_filename' => 'corrupted.kmz',
            'file_path' => 'kmz/corrupted.kmz',
            'file_size' => 1024,
            'total_files_found' => 2,
            'total_photos_extracted' => 2,
            'status' => 'berjalan',
        ]);

        $successPhoto = Photo::create([
            'batch_id' => $batch->id,
            'photo_name' => 'valid.jpg',
            'photo_path' => 'photos/valid.jpg',
            'photo_type' => 'kWh',
            'detection_confidence' => 0.88,
            'status' => 'Sesuai',
            'processing_status' => 'success',
        ]);

        // Foto dengan path rusak/tidak ada di storage sehingga deteksi gagal
        $failedPhoto = Photo::create([
            'batch_id' => $batch->id,
            'photo_name' => 'corrupt.jpg',
            'photo_path' => 'photos/non_existent.jpg',
            'processing_status' => 'unprocessed',
        ]);

        $jobFail = new ProcessPhotoDetectionJob($failedPhoto, false);
        app()->call([$jobFail, 'handle']);

        $failedPhoto->refresh();
        $batch->refresh();

        // 1. Foto yang gagal ditandai failed
        $this->assertEquals('failed', $failedPhoto->processing_status);
        $this->assertNotNull($failedPhoto->detection_notes);

        // 2. Batch tidak crash, dan dapat menghitung foto gagal
        $this->assertEquals(1, $batch->failed_photos_count);
        $this->assertEquals(1, $batch->success_photos_count);
        $this->assertEquals(0, $batch->pending_photos_count);
        $this->assertEquals('selesai', $batch->status);
    }

    /**
     * UJI 4: Fasilitas Retry / Pengulangan Foto yang Gagal
     */
    public function test_retry_failed_photos_mechanism(): void
    {
        $batch = KmzBatch::create([
            'batch_code' => 'BATCH-TEST-RETRY',
            'original_filename' => 'retry_test.kmz',
            'file_path' => 'kmz/retry.kmz',
            'file_size' => 1024,
            'total_files_found' => 1,
            'total_photos_extracted' => 1,
            'status' => 'selesai',
        ]);

        $failedPhoto = Photo::create([
            'batch_id' => $batch->id,
            'photo_name' => 'kwh_retry.jpg',
            'photo_path' => 'photos/kwh_retry.jpg',
            'processing_status' => 'failed',
            'detection_notes' => 'Connection timeout ke AI Service',
        ]);

        Storage::disk('public')->put('photos/kwh_retry.jpg', 'valid-bytes');

        Http::fake([
            '*/api/v1/detect*' => Http::response([
                'status' => 'success',
                'is_production_ready' => false,
                'is_simulation' => true,
                'detections' => [
                    ['class' => 'kWh', 'confidence' => 0.91, 'bbox' => [100, 100, 300, 300]]
                ]
            ], 200),
            '*/api/v1/ocr*' => Http::response([
                'status' => 'success',
                'raw_text' => "52103488912\nMELCOINDA\n12345",
                'customer_id' => '52103488912',
                'meter_brand' => 'MELCOINDA',
                'ocr_confidence' => 0.85,
                'reading_status' => 'success',
                'needs_manual_verification' => false
            ], 200),
        ]);

        // Test retry single photo route
        $response = $this->post(route('detections.retry', $failedPhoto->id), [
            'simulate' => true,
        ]);

        $response->assertRedirect();
        $failedPhoto->refresh();

        // Status foto telah pulih menjadi success setelah di-retry
        $this->assertEquals('success', $failedPhoto->processing_status);
        $this->assertNull($failedPhoto->detection_notes);
    }

    /**
     * UJI 5: Pencegahan Pemrosesan Batch Ganda (Concurrency Protection)
     */
    public function test_prevents_duplicate_batch_processing_while_running(): void
    {
        $batch = KmzBatch::create([
            'batch_code' => 'BATCH-TEST-LOCK',
            'original_filename' => 'locked.kmz',
            'file_path' => 'kmz/lock.kmz',
            'file_size' => 1024,
            'total_files_found' => 1,
            'total_photos_extracted' => 1,
            'status' => 'berjalan', // Sedang aktif berjalan
        ]);

        $processor = app(BatchProcessorService::class);
        $result = $processor->dispatchBatch($batch);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('sedang berjalan', $result['message']);
    }

    /**
     * UJI 6: Konsistensi Data Antara Database, Dashboard, dan Tabel Deteksi
     */
    public function test_consistency_across_database_table_and_dashboard(): void
    {
        $batch = KmzBatch::create([
            'batch_code' => 'BATCH-CONSISTENCY',
            'original_filename' => 'consist.kmz',
            'file_path' => 'kmz/c.kmz',
            'file_size' => 2048,
            'total_files_found' => 3,
            'total_photos_extracted' => 3,
            'status' => 'selesai',
        ]);

        Photo::create([
            'batch_id' => $batch->id,
            'photo_name' => 'kwh_1.jpg',
            'photo_path' => 'p/1.jpg',
            'photo_type' => 'kWh',
            'status' => 'Sesuai',
            'customer_id' => '521034567890',
            'meter_brand' => 'ITRON',
            'detection_confidence' => 0.95,
            'processing_status' => 'success',
        ]);

        Photo::create([
            'batch_id' => $batch->id,
            'photo_name' => 'tiang_1.jpg',
            'photo_path' => 'p/2.jpg',
            'photo_type' => 'Tiang',
            'status' => null,
            'detection_confidence' => 0.88,
            'processing_status' => 'success',
        ]);

        Photo::create([
            'batch_id' => $batch->id,
            'photo_name' => 'trafo_1.jpg',
            'photo_path' => 'p/3.jpg',
            'photo_type' => 'Trafo',
            'status' => null,
            'detection_confidence' => 0.90,
            'processing_status' => 'success',
        ]);

        // Verifikasi Dashboard
        $dashResponse = $this->get(route('dashboard'));
        $dashResponse->assertStatus(200);
        $dashResponse->assertSee('3'); // Total 3 foto

        // Verifikasi Tabel Hasil Deteksi
        $tableResponse = $this->get(route('detections.index'));
        $tableResponse->assertStatus(200);
        $tableResponse->assertSee('kwh_1.jpg');
        $tableResponse->assertSee('521034567890');
        $tableResponse->assertSee('ITRON');
        $tableResponse->assertSee('Tiang');
        $tableResponse->assertSee('Trafo');
    }

    /**
     * UJI 7: Ekspor Laporan Excel (.xlsx) Resmi PLN UP3 Tegal
     */
    public function test_excel_export_generates_valid_spreadsheet_stream(): void
    {
        $batch = KmzBatch::create([
            'batch_code' => 'BATCH-EXCEL-01',
            'original_filename' => 'survei_excel.kmz',
            'file_path' => 'kmz/excel.kmz',
            'file_size' => 1024,
            'total_files_found' => 1,
            'total_photos_extracted' => 1,
            'status' => 'selesai',
        ]);

        Photo::create([
            'batch_id' => $batch->id,
            'photo_name' => 'meter_export.jpg',
            'photo_path' => 'photos/meter_export.jpg',
            'photo_type' => 'kWh',
            'customer_id' => '521098765432',
            'meter_brand' => 'MELCOINDA',
            'detection_confidence' => 0.92,
            'status' => 'Sesuai',
            'processing_status' => 'success',
        ]);

        // 1. Ekspor per batch
        $responseBatch = $this->get(route('kmz.export', $batch->id));
        $responseBatch->assertStatus(200);
        $this->assertEquals(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $responseBatch->headers->get('Content-Type')
        );
        $this->assertStringContainsString('attachment;', $responseBatch->headers->get('Content-Disposition'));

        // 2. Ekspor global hasil deteksi dengan filter
        $responseGlobal = $this->get(route('detections.export', ['photo_type' => 'kWh']));
        $responseGlobal->assertStatus(200);
        $this->assertEquals(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $responseGlobal->headers->get('Content-Type')
        );
    }
}
