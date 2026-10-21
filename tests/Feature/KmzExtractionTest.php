<?php

namespace Tests\Feature;

use App\Models\KmzBatch;
use App\Models\Photo;
use App\Services\KmzExtractorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class KmzExtractionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
    }

    /**
     * Uji 1: Halaman Dashboard dapat diakses dengan respons 200 OK
     */
    public function test_dashboard_page_can_be_rendered(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertStatus(200);
        $response->assertSee('Sistem Deteksi dan Identifikasi Foto kWh');
        $response->assertSee('PLN UP3 TEGAL');
    }

    /**
     * Uji 2: Halaman Riwayat KMZ dapat diakses dengan baik
     */
    public function test_kmz_history_page_can_be_rendered(): void
    {
        $response = $this->get(route('kmz.index'));
        $response->assertStatus(200);
        $response->assertSee('Riwayat Pemrosesan KMZ');
    }

    /**
     * Uji 3: Halaman Form Upload KMZ dapat dibuka
     */
    public function test_kmz_upload_page_can_be_rendered(): void
    {
        $response = $this->get(route('kmz.create'));
        $response->assertStatus(200);
        $response->assertSee('Formulir Unggah Berkas KMZ');
    }

    /**
     * Uji 4: Halaman Daftar Deteksi mematuhi aturan 7 kolom & format bisnis PLN
     */
    public function test_detection_table_columns_and_business_rules(): void
    {
        $batch = KmzBatch::create([
            'batch_code' => 'BATCH-TEST-001',
            'original_filename' => 'survey_test.kmz',
            'file_path' => 'kmz_uploads/test/survey_test.kmz',
            'file_size' => 1024,
            'status' => 'completed',
        ]);

        // Foto 1: kWh dengan data lengkap terverifikasi
        $kwhPhoto = Photo::create([
            'batch_id' => $batch->id,
            'photo_name' => 'FOTO_KWH_001.jpg',
            'photo_path' => 'extracted_photos/test/kwh_sample_1.jpg',
            'photo_type' => 'kWh',
            'customer_id' => '52103488912',
            'meter_brand' => 'Melcoinda',
            'detection_confidence' => 96.50,
            'status' => 'Sesuai',
            'processing_status' => 'success',
            'is_simulation' => true,
        ]);

        // Foto 2: Tiang (customer_id & meter_brand harus '-' dan status '-')
        $tiangPhoto = Photo::create([
            'batch_id' => $batch->id,
            'photo_name' => 'FOTO_TIANG_002.jpg',
            'photo_path' => 'extracted_photos/test/tiang_sample_1.jpg',
            'photo_type' => 'Tiang',
            'customer_id' => null,
            'meter_brand' => null,
            'detection_confidence' => 92.15,
            'status' => '-',
            'processing_status' => 'success',
            'is_simulation' => true,
        ]);

        // Foto 3: kWh belum diproses
        $unprocessedPhoto = Photo::create([
            'batch_id' => $batch->id,
            'photo_name' => 'FOTO_KWH_UNPROCESSED.jpg',
            'photo_path' => 'extracted_photos/test/kwh_sample_2.jpg',
            'photo_type' => 'kWh',
            'customer_id' => null,
            'meter_brand' => null,
            'detection_confidence' => null,
            'status' => 'Belum Diproses',
            'processing_status' => 'unprocessed',
            'is_simulation' => true,
        ]);

        $response = $this->get(route('detections.index'));
        $response->assertStatus(200);

        // Verifikasi nama kolom
        $response->assertSee('No.');
        $response->assertSee('Nama Foto');
        $response->assertSee('Jenis Foto');
        $response->assertSee('ID Pelanggan');
        $response->assertSee('Merek kWh');
        $response->assertSee('Tingkat Deteksi');
        $response->assertSee('Status');

        // Verifikasi tautan aman untuk nama foto
        $response->assertSee(route('detections.image', $kwhPhoto->id));
        $response->assertSee(route('detections.image', $tiangPhoto->id));

        // Verifikasi data kWh
        $response->assertSee('52103488912');
        $response->assertSee('Melcoinda');
        $response->assertSee('96.50%');
        $response->assertSee('Sesuai');

        // Verifikasi aturan Tiang
        $this->assertEquals('-', $tiangPhoto->display_customer_id);
        $this->assertEquals('-', $tiangPhoto->display_meter_brand);
        $this->assertEquals('-', $tiangPhoto->display_status);

        // Verifikasi aturan deteksi tidak tersedia (tampilkan '-')
        $this->assertEquals('-', $unprocessedPhoto->display_confidence);
        $this->assertEquals('Belum Diproses', $unprocessedPhoto->display_status);
    }

    /**
     * Uji 5: Pencarian dan Filter Hasil Deteksi
     */
    public function test_detection_search_and_filter(): void
    {
        $batch = KmzBatch::create([
            'batch_code' => 'BATCH-TEST-002',
            'original_filename' => 'survey_test.kmz',
            'file_path' => 'kmz_uploads/test/survey_test.kmz',
            'file_size' => 1024,
            'status' => 'completed',
        ]);

        Photo::create([
            'batch_id' => $batch->id,
            'photo_name' => 'KWH_PELANGGAN_CARI.jpg',
            'photo_path' => 'extracted_photos/test/kwh_cari.jpg',
            'photo_type' => 'kWh',
            'status' => 'Sesuai',
            'processing_status' => 'success',
        ]);

        Photo::create([
            'batch_id' => $batch->id,
            'photo_name' => 'TIANG_TIDAK_MUNCUL.jpg',
            'photo_path' => 'extracted_photos/test/tiang.jpg',
            'photo_type' => 'Tiang',
            'status' => '-',
            'processing_status' => 'success',
        ]);

        // Cari berdasarkan nama foto
        $searchResponse = $this->get(route('detections.index', ['search' => 'KWH_PELANGGAN_CARI']));
        $searchResponse->assertStatus(200);
        $searchResponse->assertSee('KWH_PELANGGAN_CARI.jpg');
        $searchResponse->assertDontSee('TIANG_TIDAK_MUNCUL.jpg');

        // Filter berdasarkan jenis foto Tiang
        $filterResponse = $this->get(route('detections.index', ['photo_type' => 'Tiang']));
        $filterResponse->assertStatus(200);
        $filterResponse->assertSee('TIANG_TIDAK_MUNCUL.jpg');
        $filterResponse->assertDontSee('KWH_PELANGGAN_CARI.jpg');
    }

    /**
     * Uji 6: Akses Aman Berkas Foto via Route Aplikasi
     */
    public function test_safe_photo_streaming_route(): void
    {
        $batch = KmzBatch::create([
            'batch_code' => 'BATCH-TEST-IMG',
            'original_filename' => 'test.kmz',
            'file_path' => 'kmz_uploads/test/test.kmz',
            'file_size' => 1024,
            'status' => 'completed',
        ]);

        // Simpan gambar dummy di storage public
        $fakeImagePath = 'extracted_photos/test/safe_test.jpg';
        Storage::disk('public')->put($fakeImagePath, 'FAKE_JPEG_IMAGE_CONTENT');

        $photo = Photo::create([
            'batch_id' => $batch->id,
            'photo_name' => 'safe_test.jpg',
            'photo_path' => $fakeImagePath,
            'photo_type' => 'kWh',
            'mime_type' => 'image/jpeg',
            'status' => 'Belum Diproses',
            'processing_status' => 'unprocessed',
        ]);

        $response = $this->get(route('detections.image', $photo->id));
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/jpeg');
    }

    /**
     * Uji 7: Ekstraksi Berkas KMZ Valid (Berisi KML & Gambar Asli)
     */
    public function test_valid_kmz_file_extraction_creates_batch_and_photos(): void
    {
        $tempKmzPath = tempnam(sys_get_temp_dir(), 'kmz_test_') . '.kmz';
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($tempKmzPath, ZipArchive::CREATE | ZipArchive::OVERWRITE));

        $kmlContent = '<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
  <Document>
    <Placemark>
      <name>Gardu TGL-01</name>
      <description><![CDATA[<img src="images/kwh_sample.jpg">]]></description>
      <Point>
        <coordinates>109.1402,-6.8694,0</coordinates>
      </Point>
    </Placemark>
  </Document>
</kml>';
        $zip->addFromString('doc.kml', $kmlContent);

        ob_start();
        $img = imagecreatetruecolor(60, 60);
        imagejpeg($img);
        $jpgData = ob_get_clean();
        imagedestroy($img);

        $zip->addFromString('images/kwh_sample.jpg', $jpgData);
        $zip->close();

        $uploadedFile = new UploadedFile(
            $tempKmzPath,
            'survey_lapangan_up3.kmz',
            'application/vnd.google-earth.kmz',
            null,
            true
        );

        $service = new KmzExtractorService();
        $result = $service->processKmzUpload($uploadedFile);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['photos_count']);

        $this->assertDatabaseHas('kmz_batches', [
            'original_filename' => 'survey_lapangan_up3.kmz',
            'status' => 'completed',
            'kml_found' => true,
            'total_photos_extracted' => 1,
        ]);

        $this->assertDatabaseHas('photos', [
            'photo_name' => 'kwh_sample.jpg',
            'photo_type' => 'kWh',
            'processing_status' => 'unprocessed',
        ]);

        if (file_exists($tempKmzPath)) {
            unlink($tempKmzPath);
        }
    }

    /**
     * Uji 8: Berkas KMZ Rusak (Corrupted / Bukan Arsip Valid)
     */
    public function test_corrupted_kmz_file_fails_gracefully(): void
    {
        $tempCorruptPath = tempnam(sys_get_temp_dir(), 'kmz_corrupt_') . '.kmz';
        file_put_contents($tempCorruptPath, 'INI_BUKAN_ARSIP_ZIP_MELAINKAN_FILE_TEKS_RUSAK');

        $uploadedFile = new UploadedFile(
            $tempCorruptPath,
            'corrupted_survey.kmz',
            'application/vnd.google-earth.kmz',
            null,
            true
        );

        $service = new KmzExtractorService();
        $result = $service->processKmzUpload($uploadedFile);

        $this->assertFalse($result['success']);
        $this->assertEquals('failed', $result['batch']->status);
        $this->assertNotNull($result['batch']->error_message);

        $this->assertDatabaseHas('kmz_batches', [
            'original_filename' => 'corrupted_survey.kmz',
            'status' => 'failed',
        ]);

        if (file_exists($tempCorruptPath)) {
            unlink($tempCorruptPath);
        }
    }

    /**
     * Uji 9: Arsip KMZ Tanpa Foto (Hanya File KML Saja)
     */
    public function test_kmz_archive_without_photos_handled_properly(): void
    {
        $tempEmptyKmzPath = tempnam(sys_get_temp_dir(), 'kmz_empty_') . '.kmz';
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($tempEmptyKmzPath, ZipArchive::CREATE | ZipArchive::OVERWRITE));

        $zip->addFromString('doc.kml', '<?xml version="1.0"?><kml><Document></Document></kml>');
        $zip->close();

        $uploadedFile = new UploadedFile(
            $tempEmptyKmzPath,
            'empty_photos.kmz',
            'application/vnd.google-earth.kmz',
            null,
            true
        );

        $service = new KmzExtractorService();
        $result = $service->processKmzUpload($uploadedFile);

        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['photos_count']);
        $this->assertDatabaseHas('kmz_batches', [
            'original_filename' => 'empty_photos.kmz',
            'total_photos_extracted' => 0,
            'status' => 'completed',
        ]);

        if (file_exists($tempEmptyKmzPath)) {
            unlink($tempEmptyKmzPath);
        }
    }

    /**
     * Uji 10: Eksekusi OCR pada Foto kWh (Pembacaan Teks, Ekstraksi IDPEL, dan Merek)
     */
    public function test_kwh_ocr_execution_and_parser_rules(): void
    {
        $batch = KmzBatch::create([
            'batch_code' => 'BATCH-OCR-001',
            'original_filename' => 'survey_ocr.kmz',
            'file_path' => 'kmz_uploads/test/test.kmz',
            'file_size' => 1024,
            'status' => 'completed',
        ]);

        $photoPath = 'extracted_photos/test/sample_kwh.jpg';
        Storage::disk('public')->put($photoPath, 'DUMMY_IMAGE_BYTES');

        $photo = Photo::create([
            'batch_id' => $batch->id,
            'photo_name' => 'sample_kwh.jpg',
            'photo_path' => $photoPath,
            'photo_type' => 'kWh',
            'status' => 'Belum Diproses',
            'processing_status' => 'unprocessed',
        ]);

        // Simulasikan eksekusi OCR dari Laravel Service
        $aiService = app(\App\Services\YoloDetectionService::class);
        $result = $aiService->runOcrOnPhoto($photo);

        $this->assertTrue($result['success']);

        $updated = $photo->fresh();
        // Pastikan raw text tersimpan
        $this->assertNotNull($updated->raw_ocr_text);
        // Pastikan kandidat ID Pelanggan teridentifikasi 11-12 digit
        $this->assertEquals('52103488912', $updated->candidate_customer_id);
        // Pastikan merek meter teridentifikasi Melcoinda
        $this->assertEquals('Melcoinda', $updated->candidate_meter_brand);
    }

    /**
     * Uji 11: OCR Dilewati Secara Otomatis untuk Foto Non-kWh (Tiang & Trafo)
     */
    public function test_ocr_skipped_for_tiang_and_trafo(): void
    {
        $batch = KmzBatch::create([
            'batch_code' => 'BATCH-NON-KWH',
            'original_filename' => 'survey_tiang.kmz',
            'file_path' => 'kmz_uploads/test/test.kmz',
            'file_size' => 1024,
            'status' => 'completed',
        ]);

        $tiangPhoto = Photo::create([
            'batch_id' => $batch->id,
            'photo_name' => 'tiang_distribusi.jpg',
            'photo_path' => 'extracted_photos/test/tiang.jpg',
            'photo_type' => 'Tiang',
            'status' => '-',
            'processing_status' => 'success',
        ]);

        $aiService = app(\App\Services\YoloDetectionService::class);
        $result = $aiService->runOcrOnPhoto($tiangPhoto);

        // OCR harus menolak dijalankan untuk non-kWh
        $this->assertFalse($result['success']);
        $this->assertEquals('-', $tiangPhoto->display_customer_id);
        $this->assertEquals('-', $tiangPhoto->display_meter_brand);
    }

    /**
     * Uji 12: Koreksi Manual Operator Tidak Menimpa Teks Mentah Asli
     */
    public function test_manual_operator_verification_retains_raw_ocr_text(): void
    {
        $batch = KmzBatch::create([
            'batch_code' => 'BATCH-VERIFY',
            'original_filename' => 'survey.kmz',
            'file_path' => 'kmz_uploads/test/test.kmz',
            'file_size' => 1024,
            'status' => 'completed',
        ]);

        $photo = Photo::create([
            'batch_id' => $batch->id,
            'photo_name' => 'kwh_koreksi.jpg',
            'photo_path' => 'extracted_photos/test/kwh.jpg',
            'photo_type' => 'kWh',
            'raw_ocr_text' => 'TEKS MENTAH ASLI DARI SCANNER OCR',
            'candidate_customer_id' => '52101111222',
            'candidate_meter_brand' => 'Itron',
            'ocr_status' => 'needs_verification',
            'status' => 'Perlu Verifikasi',
            'processing_status' => 'success',
        ]);

        // Operator melakukan koreksi manual
        $response = $this->post(route('detections.verify', $photo->id), [
            'verified_customer_id' => '52109999888', // Nomor yang dikoreksi
            'verified_meter_brand' => 'Melcoinda',
            'verification_status' => 'Sesuai',
            'notes' => 'Terkonfirmasi melalui fisik segel meter',
        ]);

        $response->assertRedirect(route('detections.show', $photo->id));

        $updated = $photo->fresh();
        // Teks mentah asli TIDAK BERUBAH
        $this->assertEquals('TEKS MENTAH ASLI DARI SCANNER OCR', $updated->raw_ocr_text);
        // Data koreksi tersimpan terpisah
        $this->assertEquals('52109999888', $updated->verified_customer_id);
        $this->assertEquals('Melcoinda', $updated->verified_meter_brand);
        $this->assertEquals('Sesuai', $updated->status);
        $this->assertTrue($updated->is_verified);
    }

    /**
     * Uji 13: Foto Non-kWh (Tiang & Trafo) Selalu Menggunakan Status '-' Tanpa Memeriksa Data Pelanggan
     */
    public function test_non_kwh_photos_always_validated_with_dash_status(): void
    {
        $batch = KmzBatch::create([
            'batch_code' => 'BATCH-NON-KWH-VAL',
            'original_filename' => 'survey_infrastruktur.kmz',
            'file_path' => 'kmz_uploads/test/test.kmz',
            'file_size' => 1024,
            'status' => 'completed',
        ]);

        $trafoPhoto = Photo::create([
            'batch_id' => $batch->id,
            'photo_name' => 'trafo_distribusi_01.jpg',
            'photo_path' => 'extracted_photos/test/trafo.jpg',
            'photo_type' => 'Trafo',
            'status' => 'Belum Diproses',
            'processing_status' => 'success',
        ]);

        $validator = app(\App\Services\DetectionValidationService::class);
        $result = $validator->validatePhoto($trafoPhoto);

        $this->assertEquals('-', $result['status']);
        $this->assertStringContainsString('non-kWh', $result['status_reason']);

        $updated = $trafoPhoto->fresh();
        $this->assertEquals('-', $updated->status);
        $this->assertEquals('-', $updated->display_customer_id);
        $this->assertEquals('-', $updated->display_meter_brand);
    }

    /**
     * Uji 14: Foto kWh dengan ID Pelanggan Belum Terbaca Berstatus 'Perlu Verifikasi'
     */
    public function test_kwh_without_customer_id_results_in_perlu_verifikasi_with_reason(): void
    {
        $batch = KmzBatch::create([
            'batch_code' => 'BATCH-KWH-INCOMPLETE',
            'original_filename' => 'kwh_survey.kmz',
            'file_path' => 'kmz_uploads/test/test.kmz',
            'file_size' => 1024,
            'status' => 'completed',
        ]);

        $photo = Photo::create([
            'batch_id' => $batch->id,
            'photo_name' => 'kwh_buram.jpg',
            'photo_path' => 'extracted_photos/test/kwh_buram.jpg',
            'photo_type' => 'kWh',
            'detection_confidence' => 88.5,
            'candidate_customer_id' => null, // ID belum terbaca
            'candidate_meter_brand' => 'Itron',
            'processing_status' => 'success',
        ]);

        $validator = app(\App\Services\DetectionValidationService::class);
        $result = $validator->validatePhoto($photo);

        $this->assertEquals('Perlu Verifikasi', $result['status']);
        $this->assertStringContainsString('ID pelanggan belum terbaca', $result['status_reason']);

        $updated = $photo->fresh();
        $this->assertEquals('Perlu Verifikasi', $updated->status);
        $this->assertNotNull($updated->status_reason);
    }

    /**
     * Uji 15: Format ID Valid Struktural Tetap 'Perlu Verifikasi' Karena Data AP2T Belum Dikonfirmasi
     */
    public function test_structurally_valid_kwh_remains_perlu_verifikasi_pending_factual_ap2t_confirmation(): void
    {
        $batch = KmzBatch::create([
            'batch_code' => 'BATCH-KWH-CONFIRM',
            'original_filename' => 'kwh_survey.kmz',
            'file_path' => 'kmz_uploads/test/test.kmz',
            'file_size' => 1024,
            'status' => 'completed',
        ]);

        $photo = Photo::create([
            'batch_id' => $batch->id,
            'photo_name' => 'kwh_jelas.jpg',
            'photo_path' => 'extracted_photos/test/kwh_jelas.jpg',
            'photo_type' => 'kWh',
            'detection_confidence' => 92.0,
            'candidate_customer_id' => '52103488912', // 11 digit valid struktural
            'candidate_meter_brand' => 'Melcoinda',
            'processing_status' => 'success',
        ]);

        $validator = app(\App\Services\DetectionValidationService::class);
        $result = $validator->validatePhoto($photo);

        // Jangan menganggap ID faktual benar secara otomatis tanpa DIL/AP2T
        $this->assertEquals('Perlu Verifikasi', $result['status']);
        $this->assertStringContainsString('AP2T', $result['status_reason']);
        $this->assertEquals('structural_format_valid', $result['rules_evaluated']['customer_id_check']);
    }

    /**
     * Uji 16: Koreksi Manual Operator Mencatat Audit Waktu, Nama Petugas, dan Mengubah Status
     */
    public function test_manual_verification_records_audit_trail_and_officer(): void
    {
        $batch = KmzBatch::create([
            'batch_code' => 'BATCH-AUDIT',
            'original_filename' => 'kwh_audit.kmz',
            'file_path' => 'kmz_uploads/test/test.kmz',
            'file_size' => 1024,
            'status' => 'completed',
        ]);

        $photo = Photo::create([
            'batch_id' => $batch->id,
            'photo_name' => 'kwh_audit_target.jpg',
            'photo_path' => 'extracted_photos/test/target.jpg',
            'photo_type' => 'kWh',
            'candidate_customer_id' => '52101111222',
            'candidate_meter_brand' => 'Melcoinda',
            'status' => 'Perlu Verifikasi',
            'processing_status' => 'success',
        ]);

        $validator = app(\App\Services\DetectionValidationService::class);
        $validator->applyManualCorrection($photo, [
            'verified_customer_id' => '52101111222',
            'verified_meter_brand' => 'Melcoinda',
            'verification_status' => 'Sesuai',
            'notes' => 'Telah dicocokkan dengan PK (Perjanjian Jual Beli Tenaga Listrik)',
        ], 'Ahmad - NIP 9210045B');

        $updated = $photo->fresh();

        $this->assertEquals('Sesuai', $updated->status);
        $this->assertTrue($updated->is_verified);
        $this->assertEquals('Ahmad - NIP 9210045B', $updated->verified_by);
        $this->assertNotNull($updated->verified_at);
        $this->assertStringContainsString('diverifikasi oleh petugas (Ahmad - NIP 9210045B)', $updated->status_reason);
    }

    /**
     * Uji 17: Route Revalidate Menjalankan Evaluasi Ulang Aturan Validasi
     */
    public function test_revalidate_route_updates_photo_validation(): void
    {
        $batch = KmzBatch::create([
            'batch_code' => 'BATCH-REVAL',
            'original_filename' => 'kwh_reval.kmz',
            'file_path' => 'kmz_uploads/test/test.kmz',
            'file_size' => 1024,
            'status' => 'completed',
        ]);

        $photo = Photo::create([
            'batch_id' => $batch->id,
            'photo_name' => 'kwh_reval.jpg',
            'photo_path' => 'extracted_photos/test/kwh_reval.jpg',
            'photo_type' => 'kWh',
            'detection_confidence' => 90.0,
            'candidate_customer_id' => '52103488912',
            'candidate_meter_brand' => 'Melcoinda',
            'status' => 'Belum Diproses',
            'processing_status' => 'success',
        ]);

        $response = $this->post(route('detections.revalidate', $photo->id));
        $response->assertRedirect(route('detections.show', $photo->id));

        $updated = $photo->fresh();
        $this->assertEquals('Perlu Verifikasi', $updated->status);
        $this->assertNotNull($updated->status_reason);
        $this->assertIsArray($updated->validation_rules_applied);
    }
}
