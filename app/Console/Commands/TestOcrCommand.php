<?php

namespace App\Console\Commands;

use App\Models\Photo;
use App\Services\YoloDetectionService;
use Illuminate\Console\Command;

class TestOcrCommand extends Command
{
    protected $signature = 'pln:test-ocr {photo_id? : ID Foto yang akan diuji OCR}';

    protected $description = 'Uji pembacaan OCR teks tulisan kWh meter pada beberapa variasi kualitas foto';

    public function handle(YoloDetectionService $aiService): int
    {
        $this->info("================================================================================");
        $this->info(" PLN UP3 TEGAL - PENGUJIAN OCR TULISAN PLAT METER KWH (VARIASI KUALITAS)");
        $this->info("================================================================================");

        $health = $aiService->checkHealth();
        if (!$health['online']) {
            $this->error("Layanan AI Python belum aktif pada http://127.0.0.1:8001");
            return self::FAILURE;
        }

        $photo = Photo::where('photo_type', 'kWh')->first();
        if (!$photo) {
            $this->error("Tidak ada foto kWh di basis data.");
            return self::FAILURE;
        }

        $testCases = [
            [
                'label' => 'Kasus 1: Foto Tajam & Pencahayaan Bagus (Optimal)',
                'param' => null,
                'desc' => 'Foto plat meter dengan fokus tinggi dan pencahayaan merata.',
            ],
            [
                'label' => 'Kasus 2: Foto Sudut Miring / ID Terpotong (Sedang)',
                'param' => 'partial',
                'desc' => 'Sudut pengambilan miring atau nomor seri meter terpotong frame.',
            ],
            [
                'label' => 'Kasus 3: Foto Buram / Silau Cahaya (Rendah)',
                'param' => 'poor',
                'desc' => 'Foto buram karena gerakan tangan atau pantulan cahaya matahari/lampu.',
            ],
        ];

        foreach ($testCases as $index => $tc) {
            $this->info("\n[" . ($index + 1) . "] " . $tc['label']);
            $this->line("    Karakteristik: " . $tc['desc']);

            $res = $aiService->runOcrOnPhoto($photo, $tc['param']);
            $ocr = $res['ocr_data'] ?? [];

            $this->table(
                ['Parameter Uji', 'Hasil Nilai'],
                [
                    ['Status Pembacaan', $ocr['ocr_status'] ?? '-'],
                    ['Confidence OCR', isset($ocr['ocr_confidence']) ? $ocr['ocr_confidence'] . '%' : '-'],
                    ['Kandidat ID Pelanggan (11-12 digit)', $ocr['candidate_customer_id'] ?? '[Tidak Ditemukan]'],
                    ['Kandidat Merek kWh Meter', $ocr['candidate_meter_brand'] ?? '[Tidak Teridentifikasi]'],
                    ['Penanda Perlu Verifikasi Manual', ($ocr['needs_verification'] ?? false) ? 'YA (Perlu Konfirmasi Petugas)' : 'TIDAK (Valid)'],
                    ['Teks Mentah (Raw OCR Text)', str_replace("\n", " | ", substr($ocr['raw_text'] ?? '-', 0, 80)) . '...'],
                    ['Alasan / Catatan Sistem', $ocr['verification_reason'] ?? '-'],
                ]
            );
        }

        // Uji Kasus 4: Objek Non-kWh (Tiang / Trafo)
        $this->info("\n[4] Kasus 4: Objek Non-kWh (Tiang Listrik / Trafo Distribusi)");
        $tiang = Photo::whereIn('photo_type', ['Tiang', 'Trafo'])->first();
        if ($tiang) {
            $resNonKwh = $aiService->runOcrOnPhoto($tiang);
            $this->line("    Hasil: " . $resNonKwh['message']);
            $this->line("    ID Pelanggan Tampilan : " . $tiang->display_customer_id);
            $this->line("    Merek kWh Tampilan    : " . $tiang->display_meter_brand);
        }

        $this->info("\n================================================================================");
        $this->warn(" Catatan: Pengujian ini mengevaluasi kinerja aturan parser dan heuristik.");
        $this->warn(" Jangan mengklaim tingkat akurasi produksi keseluruhan dari sampel kecil.");
        $this->info("================================================================================");

        return self::SUCCESS;
    }
}
