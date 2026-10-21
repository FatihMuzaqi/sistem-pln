<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use App\Models\KmzBatch;
use App\Models\Photo;
use Illuminate\Database\Seeder;

class SimulationDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * PERHATIAN: Seluruh data pada seeder ini adalah DATA SIMULASI semata
     * untuk kebutuhan pengujian antarmuka dan visualisasi alur sistem.
     */
    public function run(): void
    {
        // 1. Inisialisasi Pengaturan Aplikasi Default
        $defaultSettings = [
            [
                'key' => 'unit_name',
                'value' => 'PLN UP3 Tegal',
                'group' => 'organization',
                'label' => 'Unit Pelaksana Pelayanan Pelanggan',
                'description' => 'Nama unit induk/pelaksana PLN pengguna aplikasi ini.',
            ],
            [
                'key' => 'app_version',
                'value' => '1.0.0-Beta (Magang UP3 Tegal)',
                'group' => 'general',
                'label' => 'Versi Aplikasi',
                'description' => 'Versi rilis modul aplikasi web.',
            ],
            [
                'key' => 'max_upload_size_mb',
                'value' => '50',
                'group' => 'upload',
                'label' => 'Batas Maksimum File KMZ (MB)',
                'description' => 'Ukuran maksimal file KMZ yang diizinkan untuk diunggah.',
            ],
            [
                'key' => 'allowed_extensions',
                'value' => 'kmz',
                'group' => 'upload',
                'label' => 'Ekstensi File yang Diizinkan',
                'description' => 'Daftar ekstensi arsip yang diterima sistem.',
            ],
            [
                'key' => 'confidence_threshold',
                'value' => '75.00',
                'group' => 'detection',
                'label' => 'Ambang Batas Keyakinan Deteksi (%)',
                'description' => 'Batas minimum nilai keyakinan deteksi objek untuk status sukses.',
            ],
            [
                'key' => 'pipeline_status',
                'value' => 'idle',
                'group' => 'detection',
                'label' => 'Status AI Pipeline (YOLO & OCR)',
                'description' => 'Status kesiapan worker modul deteksi (tahap lanjutan).',
            ],
        ];

        foreach ($defaultSettings as $setting) {
            AppSetting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }

        // 2. Buat Contoh Batch KMZ Simulasi (diberi label jelas sebagai data simulasi)
        $batch = KmzBatch::updateOrCreate(
            ['batch_code' => 'BATCH-SIMULASI-001'],
            [
                'original_filename' => 'SAMPLE_SURVEY_TEGAL_SIMULASI.kmz',
                'file_path' => 'kmz_uploads/simulasi/SAMPLE_SURVEY_TEGAL_SIMULASI.kmz',
                'file_size' => 14680064, // ~14 MB
                'status' => 'completed',
                'total_files_found' => 8,
                'total_photos_extracted' => 5,
                'kml_found' => true,
                'kml_placemarks_count' => 5,
                'error_message' => null,
                'notes' => '[DATA SIMULASI] Digunakan sebagai data uji coba antarmuka dan validasi tampilan tabel.',
            ]
        );

        // 3. Buat Foto-Foto Contoh Simulasi
        $simulationPhotos = [
            [
                'batch_id' => $batch->id,
                'photo_name' => '[SIMULASI] FOTO_KWH_001.jpg',
                'photo_path' => 'extracted_photos/simulasi/kwh_sample_1.jpg',
                'photo_type' => 'kWh',
                'customer_id' => '52103488912',
                'meter_brand' => 'Melcoinda',
                'detection_confidence' => 96.50,
                'status' => 'Sesuai',
                'processing_status' => 'success',
                'placemark_name' => 'Gardu Distribusi TGL-041 / Pelanggan A',
                'latitude' => -6.8694000,
                'longitude' => 109.1402000,
                'file_size' => 2048500,
                'mime_type' => 'image/jpeg',
                'ocr_result' => '04582.4',
                'detection_notes' => '[SIMULASI] Deteksi plat meter kWh jelas, pembacaan stan terkonfirmasi sesuai standar PLN.',
                'is_simulation' => true,
            ],
            [
                'batch_id' => $batch->id,
                'photo_name' => '[SIMULASI] FOTO_TIANG_002.jpg',
                'photo_path' => 'extracted_photos/simulasi/tiang_sample_1.jpg',
                'photo_type' => 'Tiang',
                'customer_id' => null, // Tiang tidak memiliki customer_id
                'meter_brand' => null, // Tiang tidak memiliki meter_brand
                'detection_confidence' => 92.15,
                'status' => '-', // Non-kWh status "-"
                'processing_status' => 'success',
                'placemark_name' => 'Tiang Distribusi TM-12 Jl. Gajah Mada',
                'latitude' => -6.8712000,
                'longitude' => 109.1425000,
                'file_size' => 1845200,
                'mime_type' => 'image/jpeg',
                'ocr_result' => null,
                'detection_notes' => '[SIMULASI] Objek tiang beton distribusi terdeteksi tegak lurus.',
                'is_simulation' => true,
            ],
            [
                'batch_id' => $batch->id,
                'photo_name' => '[SIMULASI] FOTO_TRAFO_003.jpg',
                'photo_path' => 'extracted_photos/simulasi/trafo_sample_1.jpg',
                'photo_type' => 'Trafo',
                'customer_id' => null, // Trafo tidak memiliki customer_id
                'meter_brand' => null, // Trafo tidak memiliki meter_brand
                'detection_confidence' => 88.40,
                'status' => '-', // Non-kWh status "-"
                'processing_status' => 'success',
                'placemark_name' => 'Trafo Distribusi 160 kVA TGL-018',
                'latitude' => -6.8735000,
                'longitude' => 109.1448000,
                'file_size' => 2201400,
                'mime_type' => 'image/jpeg',
                'ocr_result' => null,
                'detection_notes' => '[SIMULASI] Fisik trafo tiang gantung teridentifikasi.',
                'is_simulation' => true,
            ],
            [
                'batch_id' => $batch->id,
                'photo_name' => '[SIMULASI] FOTO_KWH_004.jpg',
                'photo_path' => 'extracted_photos/simulasi/kwh_sample_2.jpg',
                'photo_type' => 'kWh',
                'customer_id' => null,
                'meter_brand' => null,
                'detection_confidence' => null,
                'status' => 'Belum Diproses',
                'processing_status' => 'unprocessed',
                'placemark_name' => 'Pelanggan R1 Jl. Werkudoro',
                'latitude' => -6.8751000,
                'longitude' => 109.1472000,
                'file_size' => 1954300,
                'mime_type' => 'image/jpeg',
                'ocr_result' => null,
                'detection_notes' => '[SIMULASI] Foto baru diekstrak dari arsip KMZ, menunggu proses analisis.',
                'is_simulation' => true,
            ],
            [
                'batch_id' => $batch->id,
                'photo_name' => '[SIMULASI] FOTO_KWH_005_BLUR.jpg',
                'photo_path' => 'extracted_photos/simulasi/kwh_sample_blur.jpg',
                'photo_type' => 'kWh',
                'customer_id' => null,
                'meter_brand' => null,
                'detection_confidence' => 34.20,
                'status' => 'Tidak Sesuai',
                'processing_status' => 'failed',
                'placemark_name' => 'Pelanggan B2 Pasar Pagi Tegal',
                'latitude' => -6.8682000,
                'longitude' => 109.1387000,
                'file_size' => 954000,
                'mime_type' => 'image/jpeg',
                'ocr_result' => null,
                'detection_notes' => '[SIMULASI] Kualitas foto rendah (buram/silau), teks plat meter tidak terbaca.',
                'is_simulation' => true,
            ],
            [
                'batch_id' => $batch->id,
                'photo_name' => '[SIMULASI] FOTO_KWH_006_VERIFY.jpg',
                'photo_path' => 'extracted_photos/simulasi/kwh_sample_1.jpg',
                'photo_type' => 'kWh',
                'customer_id' => '52108877231',
                'meter_brand' => 'Itron',
                'detection_confidence' => 68.40,
                'status' => 'Perlu Verifikasi',
                'processing_status' => 'processing',
                'placemark_name' => 'Pelanggan I1 Kawasan Industri Tegal',
                'latitude' => -6.8665000,
                'longitude' => 109.1352000,
                'file_size' => 2105000,
                'mime_type' => 'image/jpeg',
                'ocr_result' => '01290.?',
                'detection_notes' => '[SIMULASI] Angka stan terakhir tertutup pantulan cahaya, membutuhkan verifikasi manual operator.',
                'is_simulation' => true,
            ],
        ];

        foreach ($simulationPhotos as $photoData) {
            Photo::updateOrCreate(
                [
                    'batch_id' => $photoData['batch_id'],
                    'photo_name' => $photoData['photo_name'],
                ],
                $photoData
            );
        }
    }
}
