@extends('layouts.app')

@section('title', 'Detail Foto: ' . $photo->photo_name)
@section('header_title', 'Rincian Objek & Pembacaan OCR Tulisan kWh')
@section('header_subtitle', 'Analisis Visi Komputer YOLO dan Pengenalan Karakter Tulisan Plat Meter')

@section('content')
<!-- Alert Penanda Perlu Verifikasi Manual -->
@if($photo->photo_type === 'kWh' && ($photo->needs_verification || $photo->status === 'Perlu Verifikasi'))
    <div class="alert alert-warning border-warning d-flex align-items-center justify-content-between p-3 mb-4 rounded-3 shadow-sm" role="alert">
        <div class="d-flex align-items-center gap-3">
            <i class="bi bi-exclamation-triangle-fill fs-3 text-warning-emphasis"></i>
            <div>
                <strong class="text-dark">Foto Memerlukan Verifikasi Manual Operator!</strong>
                <div class="text-muted" style="font-size: 0.85rem;">
                    {{ $photo->status_reason ?: ($photo->detection_notes ?: 'Kualitas foto atau teks plat meter membutuhkan konfirmasi petugas sebelum status dinyatakan Sesuai.') }}
                </div>
            </div>
        </div>
        <a href="#manual-verification-card" class="btn btn-sm btn-warning text-dark fw-semibold text-nowrap">
            <i class="bi bi-pencil-square me-1"></i> Verifikasi Sekarang
        </a>
    </div>
@endif

<div class="row g-4">
    <!-- Kolom Kiri: Tampilan Foto Asli & Crop Area Bounding Box -->
    <div class="col-lg-6">
        <!-- Card Foto Utama -->
        <div class="card-custom mb-4">
            <div class="card-header-clean d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-image text-primary"></i>
                    <span class="fw-bold text-dark text-truncate" style="max-width: 250px;" title="{{ $photo->photo_name }}">{{ $photo->photo_name }}</span>
                </div>
                <div class="d-flex gap-1">
                    {!! $photo->photo_type_badge !!}
                    {!! $photo->display_status_badge !!}
                </div>
            </div>
            <div class="card-body p-3 text-center bg-dark rounded-bottom d-flex align-items-center justify-content-center" style="min-height: 380px;">
                <img src="{{ route('detections.image', $photo->id) }}" alt="{{ $photo->photo_name }}" 
                     class="img-fluid rounded shadow-sm" style="max-height: 480px; object-fit: contain;"
                     onerror="this.onerror=null; this.src='https://placehold.co/600x400/005596/white?text=Foto+Tersimpan+di+Storage';">
            </div>
            <div class="p-3 bg-light border-top d-flex align-items-center justify-content-between" style="font-size: 0.8rem;">
                <span class="text-muted"><i class="bi bi-hdd me-1"></i>Penyimpanan: <code>{{ $photo->photo_path }}</code></span>
                <a href="{{ route('detections.image', $photo->id) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-box-arrow-up-right me-1"></i> Buka Foto Asli
                </a>
            </div>
        </div>

        <!-- Card Bounding Box Crop (Hanya jika foto kWh dan ada crop_box) -->
        @if($photo->photo_type === 'kWh' && $photo->crop_box)
            <div class="card-custom mb-4">
                <div class="card-header-clean d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-bounding-box text-primary"></i>
                        <span class="fw-bold text-dark">Area Crop kWh Meter (Target OCR)</span>
                    </div>
                    <span class="badge bg-light text-secondary border">Bounding Box Terdeteksi</span>
                </div>
                <div class="card-body p-3">
                    <div class="p-2 bg-light rounded border text-muted" style="font-size: 0.82rem;">
                        <strong>Koordinat Bounding Box YOLO:</strong>
                        <span class="font-monospace text-dark ms-1">
                            [x1: {{ round($photo->crop_box[0] ?? 0, 1) }}, y1: {{ round($photo->crop_box[1] ?? 0, 1) }}, x2: {{ round($photo->crop_box[2] ?? 0, 1) }}, y2: {{ round($photo->crop_box[3] ?? 0, 1) }}]
                        </span>
                    </div>
                    <div class="form-text mt-2" style="font-size: 0.76rem;">
                        <i class="bi bi-info-circle me-1"></i>Sistem memotong area plat meter sesuai kotak pembatas ini sebelum menjalankan pembacaan karakter OCR agar noise latar belakang tiang/dinding tereliminasi.
                    </div>
                </div>
            </div>
        @endif

        <!-- Card Informasi Spasial / KML -->
        <div class="card-custom mb-4">
            <div class="card-header-clean d-flex align-items-center gap-2">
                <i class="bi bi-geo-alt-fill text-danger"></i>
                <span class="fw-bold text-dark">Metadata Lapangan & KML</span>
            </div>
            <div class="card-body p-3">
                <table class="table table-sm table-borderless align-middle mb-0" style="font-size: 0.85rem;">
                    <tbody>
                        <tr>
                            <td class="text-muted" style="width: 150px;">Batch KMZ Asal</td>
                            <td>: 
                                <a href="{{ route('kmz.show', $photo->batch_id) }}" class="fw-bold text-primary font-monospace text-decoration-none">
                                    {{ $photo->batch->batch_code ?? 'Batch #' . $photo->batch_id }}
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">Placemark Google Earth</td>
                            <td class="fw-semibold text-dark">: {{ $photo->placemark_name ?: '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Koordinat GPS</td>
                            <td class="font-monospace">: 
                                @if($photo->latitude && $photo->longitude)
                                    <a href="https://maps.google.com/?q={{ $photo->latitude }},{{ $photo->longitude }}" target="_blank" class="text-decoration-none text-primary">
                                        <i class="bi bi-box-arrow-up-right me-1"></i>{{ $photo->latitude }}, {{ $photo->longitude }}
                                    </a>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Kolom Kanan: Rincian Deteksi YOLO & Pembacaan OCR Tulisan kWh -->
    <div class="col-lg-6">
        <!-- Card 1: Data Identifikasi & Deteksi YOLO -->
        <div class="card-custom mb-4">
            <div class="card-header-clean d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-cpu text-primary fs-5"></i>
                    <span class="fw-bold text-dark">Hasil Deteksi Objek (YOLO)</span>
                </div>
                {!! $photo->photo_type_badge !!}
            </div>
            <div class="card-body p-4">
                <table class="table table-sm table-borderless align-middle mb-0" style="font-size: 0.88rem;">
                    <tbody>
                        <tr>
                            <td class="text-muted" style="width: 190px;">Jenis Objek</td>
                            <td class="fw-bold text-dark">: {!! $photo->photo_type_badge !!}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Tingkat Deteksi (YOLO)</td>
                            <td>: 
                                @if($photo->detection_confidence !== null)
                                    @php
                                        $pct = $photo->confidence_percentage_value;
                                        $minThresh = config('pln_rules.active_rules.min_yolo_confidence', 75.00);
                                    @endphp
                                    <span class="fw-bold font-monospace {{ $pct >= 80 ? 'text-success' : ($pct >= $minThresh ? 'text-primary' : 'text-danger') }}" style="font-size: 0.95rem;">
                                        {{ $photo->display_confidence }}
                                    </span>
                                    <span class="badge bg-light text-secondary border ms-1" style="font-size: 0.72rem;">Skor Keyakinan Probabilitas</span>
                                @else
                                    <span class="text-muted fw-bold">-</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">Status Verifikasi</td>
                            <td>: {!! $photo->display_status_badge !!}</td>
                        </tr>
                        @if($photo->detection_notes)
                            <tr>
                                <td class="text-muted align-top">Catatan Sistem</td>
                                <td class="text-secondary" style="font-size: 0.82rem;">: {{ $photo->detection_notes }}</td>
                            </tr>
                        @endif
                    </tbody>
                </table>

                <!-- Keterangan Makna Tingkat Deteksi -->
                <div class="mt-3 p-2 bg-light rounded border text-muted" style="font-size: 0.78rem;">
                    <i class="bi bi-info-circle-fill text-primary me-1"></i>
                    <strong>Definisi Tingkat Deteksi:</strong> Persentase di atas merupakan derajat keyakinan (confidence) model visual YOLO terhadap kelas objek yang diprediksi, bukan jaminan bahwa hasilnya benar secara faktual di lapangan. Misalnya, confidence 95% tidak berarti akurasi model adalah 95%. Ambang batas pemeriksaan saat ini: <strong>{{ config('pln_rules.active_rules.min_yolo_confidence', 75) }}%</strong>.
                </div>
            </div>
        </div>

        <!-- Card Validasi & Status Aturan PLN -->
        <div class="card-custom mb-4 border-primary">
            <div class="card-header-clean d-flex align-items-center justify-content-between bg-light">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-shield-check text-primary fs-5"></i>
                    <span class="fw-bold text-dark">Modul Validasi Status & Aturan Bisnis PLN</span>
                </div>
                {!! $photo->display_status_badge !!}
            </div>
            <div class="card-body p-4">
                <!-- Alasan Status -->
                <div class="p-3 mb-3 rounded-3 {{ $photo->status === 'Sesuai' ? 'bg-success-subtle border border-success-subtle' : ($photo->status === 'Perlu Verifikasi' ? 'bg-warning-subtle border border-warning-subtle' : 'bg-light border') }}">
                    <div class="d-flex align-items-start gap-2">
                        <i class="bi {{ $photo->status === 'Sesuai' ? 'bi-check-circle-fill text-success' : ($photo->status === 'Perlu Verifikasi' ? 'bi-exclamation-triangle-fill text-warning' : 'bi-info-circle-fill text-primary') }} fs-5 mt-0"></i>
                        <div>
                            <div class="fw-bold text-dark" style="font-size: 0.88rem;">Penjelasan Alasan Status:</div>
                            <div class="text-secondary" style="font-size: 0.83rem;">
                                {{ $photo->status_reason ?: 'Belum dievaluasi oleh sistem aturan validasi PLN.' }}
                            </div>
                        </div>
                    </div>
                </div>

                @if($photo->photo_type === 'kWh')
                    <!-- Audit Jejak Verifikasi Manual -->
                    <div class="mb-3 p-3 bg-light rounded-3 border">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="fw-semibold text-dark" style="font-size: 0.82rem;">
                                <i class="bi bi-person-badge text-primary me-1"></i>Audit & Riwayat Verifikasi Petugas
                            </span>
                            @if($photo->is_verified)
                                <span class="badge bg-success-subtle text-success border border-success-subtle">
                                    <i class="bi bi-check2-circle me-1"></i>Tervalidasi Operator
                                </span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary border">Belum Diverifikasi</span>
                            @endif
                        </div>
                        <div class="row g-2 text-muted" style="font-size: 0.8rem;">
                            <div class="col-sm-6">
                                <span class="text-muted">Petugas Pemeriksa:</span>
                                <div class="fw-semibold text-dark">{{ $photo->verified_by ?: '-' }}</div>
                            </div>
                            <div class="col-sm-6">
                                <span class="text-muted">Waktu Verifikasi:</span>
                                <div class="fw-semibold text-dark">
                                    {{ $photo->verified_at ? $photo->verified_at->translatedFormat('d F Y, H:i:s') . ' WIB' : '-' }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Rincian Evaluasi Aturan Bisnis -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark mb-2" style="font-size: 0.82rem;">
                            <i class="bi bi-list-check text-primary me-1"></i>Kondisi Aturan Bisnis yang Berlaku:
                        </label>
                        <div class="list-group list-group-flush border rounded-3" style="font-size: 0.80rem;">
                            <!-- Aturan 1: Deteksi Objek -->
                            <div class="list-group-item d-flex align-items-center justify-content-between py-2">
                                <div>
                                    <i class="bi bi-check-circle-fill text-success me-2"></i>
                                    <strong>Objek Foto:</strong> Terdeteksi sebagai kWh meter
                                </div>
                                <span class="badge bg-light text-secondary border font-monospace">{{ $photo->display_confidence }}</span>
                            </div>
                            <!-- Aturan 2: Format ID Pelanggan -->
                            <div class="list-group-item d-flex align-items-center justify-content-between py-2">
                                <div>
                                    @if($photo->candidate_customer_id || $photo->customer_id)
                                        <i class="bi bi-check-circle-fill text-success me-2"></i>
                                    @else
                                        <i class="bi bi-x-circle-fill text-danger me-2"></i>
                                    @endif
                                    <strong>Format ID Pelanggan:</strong> 11 - 12 digit numerik struktural
                                </div>
                                <span class="badge bg-light text-secondary border font-monospace">{{ $photo->customer_id ?: 'Belum Ada' }}</span>
                            </div>
                            <!-- Aturan 3: Merek Terdaftar -->
                            <div class="list-group-item d-flex align-items-center justify-content-between py-2">
                                <div>
                                    @if($photo->meter_brand)
                                        <i class="bi bi-check-circle-fill text-success me-2"></i>
                                    @else
                                        <i class="bi bi-exclamation-circle-fill text-warning me-2"></i>
                                    @endif
                                    <strong>Merek Plat Meter:</strong> Terdaftar di katalog PLN
                                </div>
                                <span class="badge bg-light text-secondary border">{{ $photo->meter_brand ?: 'Belum Ada' }}</span>
                            </div>
                            <!-- Aturan 4: Kebenaran Faktual -->
                            <div class="list-group-item py-2 bg-light">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <i class="bi bi-clock-history text-secondary me-2"></i>
                                        <strong>Integritas Data Induk (AP2T / DIL):</strong>
                                    </div>
                                    <span class="badge bg-secondary-subtle text-secondary border">Pending Integrasi API</span>
                                </div>
                                <div class="text-muted mt-1" style="font-size: 0.72rem;">
                                    Format 11-12 digit hanya memvalidasi bentuk struktural angka. Verifikasi kebenaran faktual kepemilikan pelanggan memerlukan akses resmi ke database AP2T PLN.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tombol Revalidate -->
                    <form action="{{ route('detections.revalidate', $photo->id) }}" method="POST" class="d-flex justify-content-end">
                        @csrf
                        <button type="submit" class="btn btn-outline-primary btn-sm" title="Jalankan kembali aturan validasi DetectionValidationService">
                            <i class="bi bi-arrow-repeat me-1"></i> Evaluasi Ulang Aturan Validasi
                        </button>
                    </form>
                @else
                    <div class="text-muted" style="font-size: 0.82rem;">
                        <i class="bi bi-info-circle me-1"></i>Foto merupakan aset infrastruktur (<strong>{{ $photo->photo_type }}</strong>). Sesuai aturan operasional PLN UP3 Tegal, status otomatis diset ke <code>"-"</code> dan tidak memerlukan verifikasi meter pelanggan.
                    </div>
                @endif
            </div>
        </div>

        <!-- Card 2: Hasil Pembacaan OCR Tulisan kWh (Khusus Objek kWh) -->
        @if($photo->photo_type === 'kWh')
            <div class="card-custom mb-4 border-primary">
                <div class="card-header-clean d-flex align-items-center justify-content-between bg-primary-subtle text-primary">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-fonts fs-5"></i>
                        <span class="fw-bold">Hasil Pembacaan OCR Tulisan Plat Meter</span>
                    </div>
                    {!! $photo->display_ocr_status_badge !!}
                </div>
                <div class="card-body p-4">
                    <table class="table table-sm table-borderless align-middle mb-3" style="font-size: 0.88rem;">
                        <tbody>
                            <!-- Kandidat ID Pelanggan -->
                            <tr>
                                <td class="text-muted" style="width: 190px;">Kandidat ID Pelanggan</td>
                                <td>: 
                                    @if($photo->candidate_customer_id)
                                        <span class="font-monospace fw-bold text-primary fs-6">{{ $photo->candidate_customer_id }}</span>
                                        <span class="badge bg-light text-secondary border ms-1" style="font-size: 0.7rem;">{{ strlen($photo->candidate_customer_id) }} digit</span>
                                    @else
                                        <span class="text-muted fst-italic">Tidak ditemukan (11-12 digit)</span>
                                    @endif
                                </td>
                            </tr>

                            <!-- Kandidat Merek kWh -->
                            <tr>
                                <td class="text-muted">Kandidat Merek kWh</td>
                                <td>: 
                                    @if($photo->candidate_meter_brand)
                                        <span class="badge bg-light text-dark border fs-6">{{ $photo->candidate_meter_brand }}</span>
                                    @else
                                        <span class="text-muted fst-italic">Belum teridentifikasi</span>
                                    @endif
                                </td>
                            </tr>

                            <!-- Confidence OCR -->
                            <tr>
                                <td class="text-muted">Confidence Pembacaan OCR</td>
                                <td>: 
                                    @if($photo->ocr_confidence !== null)
                                        <span class="fw-bold font-monospace {{ $photo->ocr_confidence >= 75 ? 'text-success' : 'text-warning-emphasis' }}">
                                            {{ $photo->display_ocr_confidence }}
                                        </span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                            </tr>

                            <!-- Status Pembacaan OCR -->
                            <tr>
                                <td class="text-muted">Status Pembacaan OCR</td>
                                <td>: {!! $photo->display_ocr_status_badge !!}</td>
                            </tr>

                            <!-- Penanda Verifikasi -->
                            <tr>
                                <td class="text-muted">Penanda Perlu Verifikasi</td>
                                <td>: 
                                    @if($photo->needs_verification)
                                        <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-circle-fill me-1"></i>YA (Perlu Periksa Manual)</span>
                                    @else
                                        <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-check-circle-fill me-1"></i>Terverifikasi Otomatis</span>
                                    @endif
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Teks Mentah Hasil OCR -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark d-flex align-items-center justify-content-between" style="font-size: 0.8rem;">
                            <span><i class="bi bi-file-earmark-text me-1"></i>Teks Mentah Hasil OCR (Raw Text):</span>
                            <span class="badge bg-light text-muted border">Asli / Tidak Diubah</span>
                        </label>
                        <div class="p-3 bg-dark text-light rounded-3 font-monospace" style="font-size: 0.78rem; max-height: 140px; overflow-y: auto; white-space: pre-wrap;">
{{ $photo->raw_ocr_text ?: 'Belum ada teks mentah yang diekstrak. Jalankan pembacaan OCR di bawah.' }}
                        </div>
                        <div class="form-text" style="font-size: 0.74rem;">
                            <i class="bi bi-shield-check text-success me-1"></i>Teks mentah ini dipertahankan utuh dan tidak akan tertimpa ketika operator melakukan koreksi manual.
                        </div>
                    </div>

                    <!-- Tombol Trigger OCR -->
                    <div class="pt-3 border-top">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <form action="{{ route('detections.ocr', $photo->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-pln btn-sm">
                                    <i class="bi bi-fonts me-1"></i> Jalankan Pembacaan OCR
                                </button>
                            </form>

                            <!-- Dropdown Uji Sampel Kualitas Berbeda -->
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    <i class="bi bi-sliders me-1"></i> Uji Variasi Kualitas Foto
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                    <li><h6 class="dropdown-header">Uji Ekstraksi & Filter Anti-Tebak</h6></li>
                                    <li>
                                        <form action="{{ route('detections.ocr', $photo->id) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="dropdown-item"><i class="bi bi-check2-circle text-success me-2"></i>Kualitas Bagus (Jelas)</button>
                                        </form>
                                    </li>
                                    <li>
                                        <form action="{{ route('detections.ocr', $photo->id) }}" method="POST">
                                            @csrf
                                            <input type="hidden" name="quality" value="partial">
                                            <button type="submit" class="dropdown-item"><i class="bi bi-exclamation-triangle text-warning me-2"></i>Kualitas Sedang / Miring</button>
                                        </form>
                                    </li>
                                    <li>
                                        <form action="{{ route('detections.ocr', $photo->id) }}" method="POST">
                                            @csrf
                                            <input type="hidden" name="quality" value="poor">
                                            <button type="submit" class="dropdown-item"><i class="bi bi-x-circle text-danger me-2"></i>Kualitas Buram / Silau</button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <!-- Notice Foto Non-kWh (Tiang & Trafo) -->
            <div class="card-custom mb-4 p-4 text-center bg-light">
                <i class="bi bi-info-circle fs-3 text-secondary d-block mb-2"></i>
                <h6 class="fw-bold text-dark mb-1">OCR Tulisan Dinonaktifkan untuk Objek Non-kWh</h6>
                <p class="text-muted mb-0" style="font-size: 0.85rem;">
                    Foto terdeteksi sebagai <strong>{{ $photo->photo_type }}</strong>. Sesuai aturan operasional PLN UP3 Tegal, kolom ID Pelanggan dan Merek kWh meter ditampilkan sebagai <code>"-"</code> dan OCR tidak dieksekusi.
                </p>
            </div>
        @endif

        <!-- Card 3: Form Koreksi & Verifikasi Manual Operator (Penyimpanan Terpisah) -->
        @if($photo->photo_type === 'kWh')
            <div class="card-custom mb-4" id="manual-verification-card">
                <div class="card-header-clean d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-pencil-square text-success fs-5"></i>
                        <span class="fw-bold text-dark">Formulir Verifikasi & Koreksi Manual Operator</span>
                    </div>
                    @if($photo->is_verified)
                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                            <i class="bi bi-check2-all me-1"></i>Sudah Diverifikasi
                        </span>
                    @endif
                </div>
                <div class="card-body p-4">
                    <form action="{{ route('detections.verify', $photo->id) }}" method="POST">
                        @csrf

                        @if($photo->verified_at)
                            <div class="alert alert-light border py-2 px-3 mb-3 d-flex align-items-center justify-content-between" style="font-size: 0.78rem;">
                                <span class="text-muted">
                                    <i class="bi bi-clock-history me-1"></i>Terakhir diverifikasi: <strong>{{ $photo->verified_at->translatedFormat('d F Y, H:i') }} WIB</strong> oleh <strong>{{ $photo->verified_by ?? 'Operator' }}</strong>
                                </span>
                            </div>
                        @endif

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="verified_customer_id" class="form-label fw-semibold text-dark" style="font-size: 0.82rem;">
                                    ID Pelanggan (Hasil Koreksi)
                                </label>
                                <input type="text" name="verified_customer_id" id="verified_customer_id" 
                                       class="form-control font-monospace" 
                                       placeholder="Contoh: 52103488912" 
                                       value="{{ old('verified_customer_id', $photo->verified_customer_id ?: $photo->candidate_customer_id) }}">
                                <div class="form-text" style="font-size: 0.72rem;">Standar PLN 11 - 12 digit angka.</div>
                            </div>

                            <div class="col-md-6">
                                <label for="verified_meter_brand" class="form-label fw-semibold text-dark" style="font-size: 0.82rem;">
                                    Merek kWh Meter (Hasil Koreksi)
                                </label>
                                <select name="verified_meter_brand" id="verified_meter_brand" class="form-select">
                                    <option value="">-- Pilih atau Isi Merek --</option>
                                    @php
                                        $knownBrands = ["Melcoinda", "Itron", "Sanxing", "Actaris", "Star", "Hexing", "Landis+Gyr", "Meeco", "Fuji Dharma", "SmartMeter"];
                                        $selectedBrand = old('verified_meter_brand', $photo->verified_meter_brand ?: $photo->candidate_meter_brand);
                                    @endphp
                                    @foreach($knownBrands as $brand)
                                        <option value="{{ $brand }}" {{ $selectedBrand === $brand ? 'selected' : '' }}>{{ $brand }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label for="verification_status" class="form-label fw-semibold text-dark" style="font-size: 0.82rem;">
                                    Status Verifikasi <span class="text-danger">*</span>
                                </label>
                                <select name="verification_status" id="verification_status" class="form-select" required>
                                    <option value="Sesuai" {{ $photo->status === 'Sesuai' ? 'selected' : '' }}>Sesuai (Diverifikasi)</option>
                                    <option value="Perlu Verifikasi" {{ $photo->status === 'Perlu Verifikasi' ? 'selected' : '' }}>Perlu Verifikasi</option>
                                    <option value="Tidak Sesuai" {{ $photo->status === 'Tidak Sesuai' ? 'selected' : '' }}>Tidak Sesuai</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label for="verified_by" class="form-label fw-semibold text-dark" style="font-size: 0.82rem;">
                                    Petugas Pemeriksa / NIP
                                </label>
                                <input type="text" name="verified_by" id="verified_by" class="form-control" placeholder="Petugas Pemeriksa PLN" value="{{ old('verified_by', $photo->verified_by ?: 'Petugas Pemeriksa PLN UP3 Tegal') }}">
                                <div class="form-text" style="font-size: 0.72rem;">Dicatat ke jejak audit.</div>
                            </div>

                            <div class="col-md-4">
                                <label for="notes" class="form-label fw-semibold text-dark" style="font-size: 0.82rem;">
                                    Catatan Petugas (Opsional)
                                </label>
                                <input type="text" name="notes" id="notes" class="form-control" placeholder="Catatan fisik meter atau stan" value="{{ old('notes') }}">
                            </div>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-success btn-sm px-4">
                                <i class="bi bi-check-circle me-1"></i> Simpan Koreksi Manual
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <!-- Card 4: Panel Pemicu Layanan AI YOLO -->
        <div class="card-custom mb-4 border-primary-subtle">
            <div class="card-header-clean d-flex align-items-center justify-content-between bg-primary-subtle text-primary">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-cpu-fill fs-5"></i>
                    <span class="fw-bold">Layanan AI Python (YOLO + Auto-OCR)</span>
                </div>
                <span class="badge bg-primary text-white">Port: 8001</span>
            </div>
            <div class="card-body p-3">
                <div class="d-flex flex-wrap gap-2">
                    <!-- Form 1: Deteksi Produksi Nyata -->
                    <form action="{{ route('detections.detect', $photo->id) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-pln btn-sm d-flex align-items-center gap-2">
                            <i class="bi bi-play-circle-fill"></i> Jalankan Deteksi Model Nyata
                        </button>
                    </form>

                    <!-- Form 2: Uji Jalur Komunikasi (Simulasi) -->
                    <form action="{{ route('detections.detect', $photo->id) }}" method="POST">
                        @csrf
                        <input type="hidden" name="simulate" value="1">
                        <button type="submit" class="btn btn-outline-primary btn-sm d-flex align-items-center gap-2">
                            <i class="bi bi-arrow-repeat"></i> Uji Alur Komunikasi (Simulasi)
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Tombol Kembali -->
        <div class="d-flex align-items-center justify-content-between">
            <a href="{{ route('detections.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar Deteksi
            </a>
            <a href="{{ route('kmz.show', $photo->batch_id) }}" class="btn btn-light border">
                <i class="bi bi-archive me-1"></i> Buka Batch Terkait
            </a>
        </div>
    </div>
</div>
@endsection
