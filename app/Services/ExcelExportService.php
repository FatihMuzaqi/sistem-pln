<?php

namespace App\Services;

use App\Models\Photo;
use Illuminate\Database\Eloquent\Builder;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Service Ekspor Laporan Excel (.xlsx) Hasil Deteksi Foto kWh
 * PT PLN (Persero) UP3 Tegal
 */
class ExcelExportService
{
    /**
     * Menghasilkan berkas Excel (.xlsx) terformat resmi PLN dan mengirimkannya via HTTP Streamed Response
     *
     * @param array $filters ['batch_id' => ..., 'photo_type' => ..., 'status' => ..., 'search' => ...]
     * @return StreamedResponse
     */
    public function exportDetections(array $filters = []): StreamedResponse
    {
        // 1. Ambil data dengan eager loading relasi batch
        $query = Photo::with('batch')->orderBy('id', 'asc');

        if (!empty($filters['batch_id'])) {
            $query->where('batch_id', $filters['batch_id']);
        }

        if (!empty($filters['photo_type'])) {
            $query->where('photo_type', $filters['photo_type']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('photo_name', 'like', "%{$search}%")
                  ->orWhere('customer_id', 'like', "%{$search}%")
                  ->orWhere('candidate_customer_id', 'like', "%{$search}%")
                  ->orWhere('verified_customer_id', 'like', "%{$search}%")
                  ->orWhere('placemark_name', 'like', "%{$search}%");
            });
        }

        $photos = $query->get();

        // 2. Buat dokumen Spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Hasil Deteksi Foto');

        // Nonaktifkan grid lines default jika diinginkan, atau aktifkan
        $sheet->setShowGridLines(true);

        // 3. Judul & Header Resmi PLN
        $sheet->setCellValue('A1', 'PT PLN (PERSERO) UP3 TEGAL');
        $sheet->setCellValue('A2', 'LAPORAN HASIL DETEKSI & IDENTIFIKASI FOTO KWH METER');
        $downloadDate = now()->translatedFormat('d F Y, H:i:s') . ' WIB';
        $sheet->setCellValue('A3', "Waktu Cetak: {$downloadDate} | Total Data: " . count($photos) . " Foto");

        // Styling Judul
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setARGB('005596');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11)->getColor()->setARGB('1E293B');
        $sheet->getStyle('A3')->getFont()->setItalic(true)->setSize(9)->getColor()->setARGB('64748B');

        // 4. Baris Header Tabel (Baris ke-5)
        $headers = [
            'A5' => 'No.',
            'B5' => 'Kode Batch',
            'C5' => 'Nama File Foto',
            'D5' => 'Jenis Objek',
            'E5' => 'ID Pelanggan',
            'F5' => 'Merek kWh',
            'G5' => 'Tingkat Deteksi',
            'H5' => 'Status Verifikasi',
            'I5' => 'Alasan Status',
            'J5' => 'Diverifikasi Oleh',
            'K5' => 'Waktu Verifikasi',
            'L5' => 'Status Pemrosesan',
            'M5' => 'Klasifikasi Data',
            'N5' => 'Koordinat GPS',
            'O5' => 'Placemark KML',
            'P5' => 'Waktu Ekstraksi',
        ];

        foreach ($headers as $cell => $text) {
            $sheet->setCellValue($cell, $text);
        }

        // Styling Header Tabel (Warna Biru Khas PLN #005596)
        $headerRange = 'A5:P5';
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['argb' => 'FFFFFF'],
                'size' => 10,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => '005596'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => '003762'],
                ],
            ],
        ]);
        $sheet->getRowDimension(5)->setRowHeight(26);

        // 5. Isi Data Baris per Baris
        $row = 6;
        $no = 1;

        foreach ($photos as $photo) {
            $sheet->setCellValue("A{$row}", $no);
            $sheet->setCellValue("B{$row}", $photo->batch ? $photo->batch->batch_code : '-');
            $sheet->setCellValue("C{$row}", $photo->photo_name);
            $sheet->setCellValue("D{$row}", $photo->photo_type);

            // ID Pelanggan diisi dengan tipe data Explicit STRING agar tidak diubah menjadi notasi ilmiah (5.21E+10) oleh Excel
            $customerId = $photo->display_customer_id;
            $sheet->setCellValueExplicit("E{$row}", $customerId, DataType::TYPE_STRING);

            $sheet->setCellValue("F{$row}", $photo->display_meter_brand);
            $sheet->setCellValue("G{$row}", $photo->display_confidence);
            $sheet->setCellValue("H{$row}", $photo->display_status);
            $sheet->setCellValue("I{$row}", $photo->status_reason ?: '-');
            $sheet->setCellValue("J{$row}", $photo->verified_by ?: '-');
            $sheet->setCellValue("K{$row}", $photo->verified_at ? $photo->verified_at->format('d/m/Y H:i') : '-');
            $sheet->setCellValue("L{$row}", ucfirst($photo->processing_status));
            $sheet->setCellValue("M{$row}", $photo->is_simulation ? 'Data Simulasi' : 'Data Produksi');

            // Koordinat GPS
            $coords = ($photo->latitude && $photo->longitude) ? "{$photo->latitude}, {$photo->longitude}" : '-';
            $sheet->setCellValue("N{$row}", $coords);
            $sheet->setCellValue("O{$row}", $photo->placemark_name ?: '-');
            $sheet->setCellValue("P{$row}", $photo->created_at ? $photo->created_at->format('d/m/Y H:i') : '-');

            // Alignment kolom
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("D{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("F{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("G{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("H{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("L{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("M{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Zebra striping untuk kenyamanan pembacaan baris
            if ($row % 2 === 1) {
                $sheet->getStyle("A{$row}:P{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('F8FAFC');
            }

            $row++;
            $no++;
        }

        // Border tipis untuk seluruh tabel
        $lastRow = max(6, $row - 1);
        $sheet->getStyle("A6:P{$lastRow}")->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)
            ->getColor()->setARGB('E2E8F0');

        // 6. Atur Lebar Kolom Otomatis (Auto-fit)
        foreach (range('A', 'P') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // 7. Hasilkan HTTP Streamed Response
        $fileName = 'Laporan_Deteksi_PLN_UP3_Tegal_' . now()->format('Ymd_His') . '.xlsx';

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
