@extends('layouts.app')

@section('title', 'Detail Batch ' . $batch->batch_code)
@section('header_title', 'Rincian Batch: ' . $batch->batch_code)
@section('header_subtitle', 'Informasi Arsip KMZ dan Galeri Foto yang Diekstrak')

@section('content')
<!-- Header Information Card -->
<div class="card-custom mb-4">
    <div class="card-header-clean d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('kmz.index') }}" class="btn btn-sm btn-light border me-1" title="Kembali ke Riwayat">
                <i class="bi bi-arrow-left"></i>
            </a>
            <span class="fw-bold text-dark fs-6 font-monospace">{{ $batch->batch_code }}</span>
            <span class="badge {{ $batch->status_badge_class }} ms-1">{{ $batch->status_label }}</span>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            @if($batch->total_photos_extracted > 0)
                <a href="{{ route('kmz.export', $batch->id) }}" class="btn btn-sm btn-success d-flex align-items-center gap-1 shadow-sm" title="Unduh Hasil Deteksi dalam format Excel">
                    <i class="bi bi-file-earmark-excel-fill"></i> Ekspor Excel
                </a>
            @endif

            @if($batch->failed_photos_count > 0 && !$batch->is_processing)
                <form action="{{ route('kmz.retry_failed', $batch->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Jalankan pemrosesan ulang untuk {{ $batch->failed_photos_count }} foto yang gagal?')">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-warning d-flex align-items-center gap-1">
                        <i class="bi bi-arrow-clockwise"></i> Ulangi Foto Gagal ({{ $batch->failed_photos_count }})
                    </button>
                </form>
            @endif

            @if($batch->pending_photos_count > 0 && !$batch->is_processing)
                <form action="{{ route('kmz.process', $batch->id) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-primary d-flex align-items-center gap-1">
                        <i class="bi bi-play-circle-fill"></i> Proses Batch
                    </button>
                </form>
            @endif

            <a href="{{ route('kmz.download', $batch->id) }}" class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1">
                <i class="bi bi-download"></i> Unduh KMZ Asli
            </a>
            <a href="{{ route('kmz.create') }}" class="btn btn-sm btn-pln-yellow d-flex align-items-center gap-1">
                <i class="bi bi-cloud-arrow-up-fill"></i> Upload Baru
            </a>
        </div>
    </div>
    <div class="card-body p-4">
        <div class="row g-4">
            <div class="col-md-6 col-lg-3">
                <div class="text-muted mb-1" style="font-size: 0.8rem;">Nama Berkas Asli</div>
                <div class="fw-bold text-dark text-break" style="font-size: 0.95rem;">
                    <i class="bi bi-file-earmark-zip me-1 text-primary"></i>{{ $batch->original_filename }}
                </div>
                <div class="text-muted mt-1" style="font-size: 0.78rem;">Ukuran: {{ $batch->formatted_file_size }}</div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="text-muted mb-1" style="font-size: 0.8rem;">Hasil Ekstraksi Foto</div>
                <div class="fw-bold text-dark fs-5">
                    {{ $batch->total_photos_extracted }} <span class="fs-6 fw-normal text-muted">/ {{ $batch->total_files_found }} berkas</span>
                </div>
                <div class="text-muted mt-1" style="font-size: 0.78rem;">
                    {{ $batch->kwh_photos_count }} kWh &bull; {{ $batch->tiang_photos_count }} Tiang &bull; {{ $batch->trafo_photos_count }} Trafo
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="text-muted mb-1" style="font-size: 0.8rem;">Berkas KML & Koordinat</div>
                <div>
                    @if($batch->kml_found)
                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                            <i class="bi bi-check-circle me-1"></i>KML Terdeteksi
                        </span>
                        <div class="text-muted mt-1" style="font-size: 0.78rem;">
                            {{ $batch->kml_placemarks_count }} Placemark terdaftar
                        </div>
                    @else
                        <span class="badge bg-secondary-subtle text-muted border border-secondary-subtle">
                            Tidak Ada File KML
                        </span>
                    @endif
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="text-muted mb-1" style="font-size: 0.8rem;">Waktu Proses & Catatan</div>
                <div class="fw-semibold text-dark" style="font-size: 0.88rem;">
                    <i class="bi bi-calendar3 me-1 text-secondary"></i>{{ $batch->created_at->format('d M Y, H:i:s') }}
                </div>
                @if($batch->notes)
                    <div class="text-muted mt-1 text-truncate" style="font-size: 0.78rem;" title="{{ $batch->notes }}">
                        <em>{{ $batch->notes }}</em>
                    </div>
                @endif
            </div>
        </div>

        @if($batch->error_message)
            <div class="alert alert-danger mt-3 mb-0" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><strong>Laporan Error Ekstraksi:</strong> {{ $batch->error_message }}
            </div>
        @endif
    </div>
</div>

<!-- Progres Pemrosesan AI & Antrean Batch -->
<div class="card-custom mb-4">
    <div class="card-header-clean d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-cpu text-primary fs-5"></i>
            <span class="fw-bold text-dark">Status & Progres Pemrosesan AI / OCR</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge {{ $batch->status_badge_class }}">{{ $batch->status_label }}</span>
            <span class="fw-bold font-monospace fs-6 {{ $batch->progress_percentage == 100 ? 'text-success' : 'text-primary' }}">{{ $batch->progress_percentage }}%</span>
        </div>
    </div>
    <div class="card-body p-4">
        <div class="progress mb-3" style="height: 12px;">
            <div class="progress-bar {{ $batch->is_processing ? 'progress-bar-striped progress-bar-animated bg-primary' : ($batch->status === 'selesai' ? 'bg-success' : ($batch->status === 'gagal' ? 'bg-danger' : 'bg-primary')) }}"
                 role="progressbar"
                 style="width: {{ $batch->progress_percentage }}%;"
                 aria-valuenow="{{ $batch->progress_percentage }}"
                 aria-valuemin="0"
                 aria-valuemax="100">
            </div>
        </div>

        <div class="row g-2 text-center" style="font-size: 0.82rem;">
            <div class="col-6 col-md-3">
                <div class="p-2 border rounded bg-light">
                    <span class="text-muted d-block" style="font-size: 0.72rem;">Total Foto</span>
                    <strong class="text-dark fs-6">{{ $batch->total_photos_extracted }}</strong>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-2 border rounded bg-light border-start border-3 border-success">
                    <span class="text-success d-block" style="font-size: 0.72rem;"><i class="bi bi-check-circle-fill me-1"></i>Berhasil Diproses</span>
                    <strong class="text-success fs-6">{{ $batch->success_photos_count }}</strong>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-2 border rounded bg-light border-start border-3 border-warning">
                    <span class="text-warning-emphasis d-block" style="font-size: 0.72rem;"><i class="bi bi-hourglass-split me-1"></i>Menunggu Antrean</span>
                    <strong class="text-dark fs-6">{{ $batch->pending_photos_count }}</strong>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-2 border rounded bg-light border-start border-3 border-danger">
                    <span class="text-danger d-block" style="font-size: 0.72rem;"><i class="bi bi-exclamation-triangle-fill me-1"></i>Gagal Diproses</span>
                    <strong class="text-danger fs-6">{{ $batch->failed_photos_count }}</strong>
                </div>
            </div>
        </div>

        @if($batch->is_processing)
            <div class="mt-3 alert alert-info mb-0 d-flex align-items-center gap-2 py-2 px-3" style="font-size: 0.82rem;">
                <span class="spinner-border spinner-border-sm text-info"></span>
                <span>Sistem sedang memproses foto pada antrean latar belakang (Queue Worker). Halaman dapat disegarkan untuk memantau progres terbaru.</span>
            </div>
        @endif
    </div>
</div>

<!-- Photos Grid -->
<div class="card-custom">
    <div class="card-header-clean d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-images text-primary fs-5"></i>
            <span class="fw-bold text-dark">Daftar Foto yang Diekstrak dari Batch Ini</span>
        </div>
        <span class="badge bg-light text-secondary border">{{ $photos->total() }} Foto Tersedia</span>
    </div>
    <div class="card-body p-4">
        @if($photos->count() > 0)
            <div class="row g-3">
                @foreach($photos as $photo)
                    <div class="col-sm-6 col-md-4 col-lg-3">
                        <div class="card h-100 border rounded-3 overflow-hidden shadow-sm">
                            <!-- Image Display -->
                            <div class="position-relative bg-light" style="height: 180px; overflow: hidden;">
                                <img src="{{ $photo->photo_url }}" alt="{{ $photo->photo_name }}" 
                                     class="w-100 h-100 object-fit-cover"
                                     onerror="this.onerror=null; this.src='https://placehold.co/400x300/005596/white?text=Foto+Tersimpan';">
                                
                                <span class="position-absolute top-0 start-0 m-2">
                                    {!! $photo->photo_type_badge !!}
                                </span>

                                <span class="position-absolute top-0 end-0 m-2">
                                    {!! $photo->processing_status_badge !!}
                                </span>

                                @if($photo->is_simulation)
                                    <span class="position-absolute bottom-0 start-0 m-2 badge bg-dark" style="font-size: 0.65rem;">
                                        [SIMULASI]
                                    </span>
                                @endif
                            </div>

                            <!-- Image Card Body -->
                            <div class="card-body p-3 d-flex flex-column">
                                <h6 class="fw-bold text-dark text-truncate mb-1" title="{{ $photo->photo_name }}">
                                    {{ $photo->photo_name }}
                                </h6>

                                @if($photo->placemark_name)
                                    <div class="text-muted text-truncate mb-2" style="font-size: 0.78rem;" title="{{ $photo->placemark_name }}">
                                        <i class="bi bi-geo-alt-fill text-danger me-1"></i>{{ $photo->placemark_name }}
                                    </div>
                                @endif

                                @if($photo->photo_type === 'kWh')
                                    <div class="mb-1" style="font-size: 0.78rem;">
                                        <span class="text-muted">IDPEL:</span>
                                        <strong class="font-monospace text-dark">{{ $photo->customer_id ?: '-' }}</strong>
                                    </div>
                                    <div class="mb-2">
                                        {!! $photo->display_status_badge !!}
                                    </div>
                                @elseif($photo->photo_type)
                                    <div class="mb-2 text-muted" style="font-size: 0.78rem;">
                                        Aset: <strong class="text-secondary">{{ $photo->photo_type }}</strong>
                                    </div>
                                @endif

                                <div class="mt-auto pt-2 border-top d-flex align-items-center justify-content-between" style="font-size: 0.78rem;">
                                    <span class="text-muted">{{ $photo->formatted_file_size }}</span>
                                    <div class="d-flex align-items-center gap-1">
                                        @if($photo->processing_status === 'failed')
                                            <form action="{{ route('detections.retry', $photo->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-warning py-0 px-2" title="Ulangi deteksi foto ini">
                                                    <i class="bi bi-arrow-clockwise"></i>
                                                </button>
                                            </form>
                                        @endif
                                        <a href="{{ route('detections.show', $photo->id) }}" class="btn btn-sm btn-outline-primary py-0 px-2">
                                            Detail &raquo;
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if($photos->hasPages())
                <div class="mt-4 pt-3 border-top">
                    {{ $photos->links() }}
                </div>
            @endif
        @else
            <div class="text-center py-5 text-muted">
                <i class="bi bi-image fs-1 d-block mb-2"></i>
                Tidak ada foto yang berhasil diekstrak pada batch ini.
                @if($batch->status === 'failed')
                    <div class="text-danger mt-1">Periksa laporan kesalahan di atas.</div>
                @endif
            </div>
        @endif
    </div>
</div>
@endsection
