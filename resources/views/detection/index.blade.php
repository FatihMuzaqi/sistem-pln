@extends('layouts.app')

@section('title', 'Hasil Deteksi Foto')
@section('header_title', 'Hasil Deteksi Foto')
@section('header_subtitle', 'Inventarisasi dan Verifikasi Foto kWh Meter, Tiang Listrik, dan Trafo')

@section('content')
<!-- Header Ringkasan & Jumlah Foto -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card-custom p-3 text-center">
            <span class="text-secondary fw-semibold" style="font-size: 0.8rem;">Total Seluruh Foto</span>
            <h4 class="fw-bold text-dark mt-1 mb-0">{{ number_format($totalAll) }}</h4>
            <div class="text-muted" style="font-size: 0.72rem;">Tersimpan di basis data</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card-custom p-3 text-center border-start border-4 border-primary">
            <span class="text-primary fw-semibold" style="font-size: 0.8rem;"><i class="bi bi-speedometer2 me-1"></i>Foto kWh Meter</span>
            <h4 class="fw-bold text-primary mt-1 mb-0">{{ number_format($totalKwh) }}</h4>
            <div class="text-muted" style="font-size: 0.72rem;">Objek pembacaan stan</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card-custom p-3 text-center border-start border-4 border-secondary">
            <span class="text-secondary fw-semibold" style="font-size: 0.8rem;"><i class="bi bi-reception-4 me-1"></i>Foto Tiang Listrik</span>
            <h4 class="fw-bold text-secondary mt-1 mb-0">{{ number_format($totalTiang) }}</h4>
            <div class="text-muted" style="font-size: 0.72rem;">Aset jaringan distribusi</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card-custom p-3 text-center border-start border-4 border-warning">
            <span class="text-warning-emphasis fw-semibold" style="font-size: 0.8rem;"><i class="bi bi-lightning-charge me-1"></i>Foto Trafo</span>
            <h4 class="fw-bold text-dark mt-1 mb-0">{{ number_format($totalTrafo) }}</h4>
            <div class="text-muted" style="font-size: 0.72rem;">Gardu distribusi</div>
        </div>
    </div>
</div>

<!-- Card Filter & Pencarian Berdasarkan Nama Foto -->
<div class="card-custom mb-4">
    <div class="card-body p-3">
        <form action="{{ route('detections.index') }}" method="GET" class="row g-2 align-items-center">
            <!-- Pencarian Berdasarkan Nama Foto -->
            <div class="col-md-4">
                <label for="search" class="form-label text-muted mb-1" style="font-size: 0.75rem;">Cari Nama Foto</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" id="search" class="form-control border-start-0 ps-0" placeholder="Ketik nama berkas foto..." value="{{ request('search') }}">
                </div>
            </div>

            <!-- Filter Jenis Foto (kWh, Tiang, Trafo) -->
            <div class="col-md-3">
                <label for="photo_type" class="form-label text-muted mb-1" style="font-size: 0.75rem;">Jenis Foto</label>
                <select name="photo_type" id="photo_type" class="form-select">
                    <option value="">-- Semua Jenis Foto --</option>
                    <option value="kWh" {{ request('photo_type') === 'kWh' ? 'selected' : '' }}>kWh Meter</option>
                    <option value="Tiang" {{ request('photo_type') === 'Tiang' ? 'selected' : '' }}>Tiang Listrik</option>
                    <option value="Trafo" {{ request('photo_type') === 'Trafo' ? 'selected' : '' }}>Trafo Distribusi</option>
                </select>
            </div>

            <!-- Filter Status -->
            <div class="col-md-3">
                <label for="status" class="form-label text-muted mb-1" style="font-size: 0.75rem;">Status Verifikasi</label>
                <select name="status" id="status" class="form-select">
                    <option value="">-- Semua Status --</option>
                    <option value="Sesuai" {{ request('status') === 'Sesuai' ? 'selected' : '' }}>Sesuai</option>
                    <option value="Tidak Sesuai" {{ request('status') === 'Tidak Sesuai' ? 'selected' : '' }}>Tidak Sesuai</option>
                    <option value="Perlu Verifikasi" {{ request('status') === 'Perlu Verifikasi' ? 'selected' : '' }}>Perlu Verifikasi</option>
                    <option value="Belum Diproses" {{ request('status') === 'Belum Diproses' ? 'selected' : '' }}>Belum Diproses</option>
                    <option value="-" {{ request('status') === '-' ? 'selected' : '' }}>- (Non-kWh)</option>
                </select>
            </div>

            <!-- Tombol Aksi -->
            <div class="col-md-2 d-flex align-items-end gap-2" style="margin-top: auto;">
                <button type="submit" class="btn btn-pln flex-grow-1" title="Terapkan Filter">
                    <i class="bi bi-funnel-fill me-1"></i> Filter
                </button>
                @if(request()->hasAny(['search', 'photo_type', 'status', 'batch_id']))
                    <a href="{{ route('detections.index') }}" class="btn btn-outline-secondary" title="Reset Semua Filter">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>
</div>

<!-- Informasi Status Filter Aktif & Jumlah Data -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div class="text-secondary" style="font-size: 0.88rem;">
        Menampilkan <strong>{{ $photos->count() }}</strong> dari total <strong>{{ $photos->total() }}</strong> foto
        @if(request('search'))
            dengan kata kunci: <em>"{{ request('search') }}"</em>
        @endif
        @if(request('photo_type'))
            &bull; Jenis: <span class="badge bg-light text-dark border">{{ request('photo_type') }}</span>
        @endif
        @if(request('status'))
            &bull; Status: <span class="badge bg-light text-dark border">{{ request('status') }}</span>
        @endif
    </div>

    <div class="text-muted" style="font-size: 0.78rem;">
        <i class="bi bi-shield-check text-success me-1"></i>Data bersumber langsung dari MySQL Database
    </div>
</div>

<!-- Tabel Utama Hasil Deteksi Foto -->
<div class="card-custom">
    <div class="card-header-clean d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-table text-primary fs-5"></i>
            <span class="fw-bold text-dark">Tabel Hasil Deteksi Foto</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('detections.export', request()->query()) }}" class="btn btn-sm btn-success d-flex align-items-center gap-1 shadow-sm" title="Ekspor data hasil deteksi yang sedang ditampilkan ke Excel">
                <i class="bi bi-file-earmark-excel-fill"></i> Ekspor Excel (.xlsx)
            </a>
            <span class="badge bg-light text-secondary border">Halaman {{ $photos->currentPage() }} dari {{ $photos->lastPage() ?: 1 }}</span>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
            <thead class="table-light">
                <tr>
                    <th class="text-center" style="width: 55px;">No.</th>
                    <th style="min-width: 220px;">Nama Foto</th>
                    <th style="width: 120px;">Jenis Foto</th>
                    <th style="min-width: 140px;">ID Pelanggan</th>
                    <th style="min-width: 130px;">Merek kWh</th>
                    <th style="min-width: 150px;" title="Tingkat Keyakinan Model Visual YOLO (Bukan Garansi Akurasi Faktual)">
                        Tingkat Deteksi <i class="bi bi-info-circle text-muted" style="font-size: 0.75rem;"></i>
                    </th>
                    <th style="min-width: 150px;">Status</th>
                    <th class="text-end" style="width: 110px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($photos as $photo)
                    <tr>
                        <!-- 1. No. (Otomatis berdasarkan pagination & urutan) -->
                        <td class="text-center text-muted fw-semibold">
                            {{ $photos->firstItem() + $loop->index }}
                        </td>

                        <!-- 2. Nama Foto (Tautan untuk membuka foto dari penyimpanan aman) -->
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <a href="{{ route('detections.image', $photo->id) }}" 
                                   target="_blank" 
                                   class="fw-semibold text-primary text-decoration-none text-truncate"
                                   style="max-width: 260px;"
                                   title="Klik untuk membuka foto asli dari penyimpanan aman">
                                    <i class="bi bi-file-earmark-image me-1"></i>{{ $photo->photo_name }}
                                </a>
                                @if($photo->is_simulation)
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle" style="font-size: 0.65rem;" title="Data Simulasi Pengujian">
                                        Simulasi
                                    </span>
                                @endif
                            </div>
                            <div class="text-muted" style="font-size: 0.72rem;">
                                @if($photo->batch)
                                    <span class="font-monospace">{{ $photo->batch->batch_code }}</span>
                                @endif
                                @if($photo->placemark_name)
                                    &bull; <i class="bi bi-geo-alt text-danger"></i>{{ Str::limit($photo->placemark_name, 25) }}
                                @endif
                            </div>
                        </td>

                        <!-- 3. Jenis Foto (kWh, Tiang, Trafo) -->
                        <td>
                            {!! $photo->photo_type_badge !!}
                        </td>

                        <!-- 4. ID Pelanggan (Hanya untuk foto kWh, Tiang & Trafo = '-') -->
                        <td>
                            @if($photo->photo_type === 'kWh')
                                @if($photo->customer_id)
                                    <span class="font-monospace fw-bold text-dark">{{ $photo->customer_id }}</span>
                                @else
                                    <span class="text-muted fst-italic">Belum terbaca</span>
                                @endif
                            @else
                                <span class="text-muted fw-bold">-</span>
                            @endif
                        </td>

                        <!-- 5. Merek kWh (Hanya untuk foto kWh, Tiang & Trafo = '-') -->
                        <td>
                            @if($photo->photo_type === 'kWh')
                                @if($photo->meter_brand)
                                    <span class="badge bg-light text-dark border">{{ $photo->meter_brand }}</span>
                                @else
                                    <span class="text-muted fst-italic">Belum terdeteksi</span>
                                @endif
                            @else
                                <span class="text-muted fw-bold">-</span>
                            @endif
                        </td>

                        <!-- 6. Tingkat Deteksi (Persentase confidence valid, atau '-') -->
                        <td>
                            @if($photo->detection_confidence !== null)
                                @php
                                    $pct = $photo->confidence_percentage_value;
                                    $minThresh = config('pln_rules.active_rules.min_yolo_confidence', 75.00);
                                @endphp
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress flex-grow-1" style="height: 6px; width: 45px;">
                                        <div class="progress-bar {{ $pct >= 80 ? 'bg-success' : ($pct >= $minThresh ? 'bg-primary' : ($pct >= 50 ? 'bg-warning' : 'bg-danger')) }}" 
                                             role="progressbar" 
                                             style="width: {{ min(100, $pct) }}%;">
                                        </div>
                                    </div>
                                    <span class="fw-bold font-monospace {{ $pct >= 80 ? 'text-success' : ($pct >= $minThresh ? 'text-primary' : ($pct >= 50 ? 'text-warning-emphasis' : 'text-danger')) }}" 
                                          style="font-size: 0.82rem;" 
                                          title="Tingkat keyakinan model visual YOLO (bukan jaminan akurasi faktual)">
                                        {{ $photo->display_confidence }}
                                    </span>
                                </div>
                            @else
                                <span class="text-muted fw-bold">-</span>
                            @endif
                        </td>

                        <!-- 7. Status (Sesuai, Tidak Sesuai, Perlu Verifikasi, atau '-' untuk non-kWh) -->
                        <td>
                            {!! $photo->display_status_badge !!}
                            @if($photo->status_reason && $photo->photo_type === 'kWh')
                                <div class="text-muted mt-1" style="font-size: 0.70rem; line-height: 1.25;" title="{{ $photo->status_reason }}">
                                    <i class="bi bi-info-circle text-primary me-1"></i>{{ Str::limit($photo->status_reason, 34) }}
                                </div>
                            @endif
                            @if($photo->is_verified)
                                <div class="mt-1">
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size: 0.65rem;" title="Diverifikasi oleh {{ $photo->verified_by ?? 'Operator' }} pada {{ $photo->verified_at ? $photo->verified_at->format('d/m/Y H:i') : '-' }}">
                                        <i class="bi bi-person-check me-1"></i>Diverifikasi Petugas
                                    </span>
                                </div>
                            @endif
                        </td>

                        <!-- Aksi / Detail -->
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                @if($photo->processing_status === 'failed')
                                    <form action="{{ route('detections.retry', $photo->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Ulangi pemrosesan deteksi dan OCR untuk foto {{ $photo->photo_name }}?')">
                                        @csrf
                                        <button type="submit" class="btn btn-warning border text-dark" title="Ulangi Deteksi Foto Gagal">
                                            <i class="bi bi-arrow-clockwise"></i>
                                        </button>
                                    </form>
                                @endif
                                <button type="button" 
                                        class="btn btn-light border text-primary" 
                                        onclick="openQuickInspectModal('{{ route('detections.image', $photo->id) }}', '{{ $photo->photo_name }}', '{{ $photo->photo_type }}', '{{ $photo->display_customer_id }}', '{{ $photo->display_meter_brand }}', '{{ $photo->display_confidence }}', '{{ $photo->display_status }}', '{{ $photo->ocr_result ?: '-' }}', '{{ route('detections.show', $photo->id) }}')" 
                                        title="Pratinjau Cepat & Hasil OCR">
                                    <i class="bi bi-zoom-in"></i>
                                </button>
                                <a href="{{ route('detections.show', $photo->id) }}" class="btn btn-light border text-secondary" title="Buka Halaman Detail Penuh">
                                    <i class="bi bi-box-arrow-up-right"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            Tidak ada data foto yang sesuai dengan kriteria pencarian dan filter.
                            <div class="mt-2">
                                <a href="{{ route('detections.index') }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i> Tampilkan Semua Data
                                </a>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    @if($photos->hasPages())
        <div class="card-footer bg-white py-3 border-top d-flex flex-column flex-sm-row align-items-center justify-content-between gap-2">
            <div class="text-muted" style="font-size: 0.8rem;">
                Menampilkan baris {{ $photos->firstItem() }} sampai {{ $photos->lastItem() }} dari {{ $photos->total() }} foto
            </div>
            <div>
                {{ $photos->links() }}
            </div>
        </div>
    @endif

    <!-- Keterangan Edukatif Tingkat Deteksi -->
    <div class="card-footer bg-light border-top py-2 px-3 d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-2 text-muted" style="font-size: 0.78rem;">
        <div>
            <i class="bi bi-info-circle-fill text-primary me-1"></i>
            <strong>Catatan Tingkat Deteksi:</strong> Persentase merupakan tingkat keyakinan (confidence) model visual YOLO terhadap kelas objek yang diprediksi, bukan jaminan kebenaran faktual aset di lapangan (misal: confidence 95% tidak berarti akurasi model 95%).
        </div>
        <span class="badge bg-white text-secondary border text-nowrap">Ambang Batas Verifikasi: {{ config('pln_rules.active_rules.min_yolo_confidence', 75) }}%</span>
    </div>
</div>

<!-- Modal Pratinjau Cepat & Pemeriksaan Teks OCR -->
<div class="modal fade" id="quickInspectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header py-2 px-3 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-image text-primary fs-5"></i>
                    <h6 class="modal-title fw-bold text-dark mb-0" id="inspectPhotoName">Pemeriksaan Foto</h6>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="row g-0">
                    <!-- Sisi Kiri: Foto Gambar -->
                    <div class="col-md-7 bg-dark text-center d-flex align-items-center justify-content-center p-2" style="min-height: 340px;">
                        <img src="" id="inspectPhotoImg" class="img-fluid rounded" style="max-height: 420px; object-fit: contain;" alt="Foto Deteksi">
                    </div>

                    <!-- Sisi Kanan: Metadata & Hasil Pembacaan Teks OCR -->
                    <div class="col-md-5 p-3 d-flex flex-column bg-light-subtle">
                        <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">Informasi Deteksi & OCR</h6>
                        
                        <div class="mb-2" style="font-size: 0.84rem;">
                            <span class="text-muted d-block" style="font-size: 0.74rem;">Jenis Foto:</span>
                            <span class="fw-bold text-dark" id="inspectPhotoType">-</span>
                        </div>

                        <div class="mb-2" style="font-size: 0.84rem;">
                            <span class="text-muted d-block" style="font-size: 0.74rem;">ID Pelanggan (kWh):</span>
                            <span class="fw-bold text-primary font-monospace" id="inspectCustomerId">-</span>
                        </div>

                        <div class="mb-2" style="font-size: 0.84rem;">
                            <span class="text-muted d-block" style="font-size: 0.74rem;">Merek kWh Meter:</span>
                            <span class="fw-semibold text-dark" id="inspectMeterBrand">-</span>
                        </div>

                        <div class="mb-2" style="font-size: 0.84rem;">
                            <span class="text-muted d-block" style="font-size: 0.74rem;">Tingkat Deteksi (Confidence):</span>
                            <span class="fw-bold text-dark font-monospace" id="inspectConfidence">-</span>
                        </div>

                        <div class="mb-3" style="font-size: 0.84rem;">
                            <span class="text-muted d-block" style="font-size: 0.74rem;">Status Verifikasi:</span>
                            <span class="fw-bold" id="inspectStatus">-</span>
                        </div>

                        <div class="p-2 rounded bg-white border mb-3">
                            <span class="text-muted d-block mb-1" style="font-size: 0.74rem;">
                                <i class="bi bi-fonts me-1"></i>Hasil Pembacaan Teks OCR (Angka Stan):
                            </span>
                            <span class="fw-bold font-monospace text-success fs-6" id="inspectOcrText">-</span>
                        </div>

                        <div class="mt-auto pt-2 border-top d-flex gap-2">
                            <a href="#" id="inspectDetailLink" class="btn btn-sm btn-pln flex-grow-1">
                                <i class="bi bi-box-arrow-up-right me-1"></i> Rincian Lengkap
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">
                                Tutup
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function openQuickInspectModal(imgUrl, name, type, customerId, meterBrand, confidence, status, ocr, detailUrl) {
        document.getElementById('inspectPhotoImg').src = imgUrl;
        document.getElementById('inspectPhotoName').textContent = name;
        document.getElementById('inspectPhotoType').textContent = type;
        document.getElementById('inspectCustomerId').textContent = customerId;
        document.getElementById('inspectMeterBrand').textContent = meterBrand;
        document.getElementById('inspectConfidence').textContent = confidence;
        document.getElementById('inspectStatus').textContent = status;
        document.getElementById('inspectOcrText').textContent = ocr;
        document.getElementById('inspectDetailLink').href = detailUrl;

        const modal = new bootstrap.Modal(document.getElementById('quickInspectModal'));
        modal.show();
    }
</script>
@endpush
