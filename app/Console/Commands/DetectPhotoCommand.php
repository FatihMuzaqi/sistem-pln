<?php

namespace App\Console\Commands;

use App\Models\Photo;
use App\Services\YoloDetectionService;
use Illuminate\Console\Command;

class DetectPhotoCommand extends Command
{
    protected $signature = 'pln:detect-photo 
                            {photo_id? : ID Foto yang akan dideteksi} 
                            {--simulate : Izinkan mode pengujian integrasi alur data}';

    protected $description = 'Kirim foto dari Laravel ke Layanan AI Python dan simpan hasil deteksi ke database MySQL';

    public function handle(YoloDetectionService $aiService): int
    {
        $this->info("============================================================");
        $this->info(" PLN UP3 TEGAL - DEMONSTRASI INTEGRASI LARAVEL & AI PYTHON");
        $this->info("============================================================");

        // 1. Periksa Status Layanan AI
        $this->line("1. Memeriksa ketersediaan Layanan AI Python...");
        $health = $aiService->checkHealth();

        if (!$health['online']) {
            $this->error("   [GAGAL] " . $health['message']);
            $this->warn("   Jalankan server AI Python terlebih dahulu dengan perintah:");
            $this->warn("   python ai_service/app.py");
            return self::FAILURE;
        }

        $this->info("   [BERHASIL] Layanan AI Aktif!");
        $this->line("   - Kesiapan Produksi : " . ($health['is_production_ready'] ? 'SIAP' : 'BELUM DILATIH'));
        $this->line("   - Model Bobot       : " . ($health['data']['model_path'] ?? '-'));
        $this->line("   - Keterangan        : " . $health['message']);
        $this->newLine();

        // 2. Pilih Foto dari Database
        $photoId = $this->argument('photo_id');
        $photo = null;

        if ($photoId) {
            $photo = Photo::find($photoId);
            if (!$photo) {
                $this->error("Foto dengan ID #{$photoId} tidak ditemukan di basis data.");
                return self::FAILURE;
            }
        } else {
            // Ambil foto pertama yang berstatus belum diproses atau sampel simulasi
            $photo = Photo::where('processing_status', 'unprocessed')->first()
                  ?? Photo::first();

            if (!$photo) {
                $this->error("Tidak ada data foto di basis data untuk diuji.");
                return self::FAILURE;
            }
        }

        $this->line("2. Menyiapkan Foto dari Basis Data:");
        $this->table(
            ['ID', 'Nama Foto', 'Jenis Awal', 'Status Awal', 'Path Penyimpanan'],
            [[
                $photo->id,
                $photo->photo_name,
                $photo->photo_type,
                $photo->processing_status,
                $photo->photo_path,
            ]]
        );
        $this->newLine();

        // 3. Kirim Foto ke Layanan AI Python
        $simulate = $this->option('simulate');
        $this->line("3. Mengirim foto ke endpoint API Python ({$photo->photo_name})...");
        
        $result = $aiService->detectPhoto($photo, $simulate);

        $this->newLine();
        $this->info("4. Hasil Respon dari Layanan AI Python:");
        $this->line("   - Status Proses  : " . ($result['success'] ? 'SUKSES' : 'GAGAL / MODEL BELUM SIAP'));
        $this->line("   - Pesan Layanan  : " . $result['message']);
        $this->line("   - Produksi Ready : " . ($result['is_production_ready'] ? 'YA' : 'TIDAK'));
        if (!empty($result['is_simulation'])) {
            $this->warn("   - Label Data     : [DATA SIMULASI INTEGRASI]");
        }
        $this->newLine();

        // 4. Verifikasi Pembaruan di Basis Data MySQL
        $updatedPhoto = $photo->fresh();
        $this->info("5. Verifikasi Data Tersimpan di MySQL:");
        $this->table(
            ['ID', 'Nama Foto', 'Jenis Terdeteksi', 'Confidence (%)', 'Status Verifikasi', 'Processing Status', 'Catatan Deteksi'],
            [[
                $updatedPhoto->id,
                $updatedPhoto->photo_name,
                $updatedPhoto->photo_type,
                $updatedPhoto->detection_confidence ? $updatedPhoto->detection_confidence . '%' : '-',
                $updatedPhoto->status,
                $updatedPhoto->processing_status,
                $updatedPhoto->detection_notes,
            ]]
        );

        $this->info("============================================================");
        $this->info(" Demonstrasi alur satu foto berhasil diselesaikan!");
        $this->info("============================================================");

        return self::SUCCESS;
    }
}
