@extends('layouts.app')

@section('title', 'Riwayat Pemrosesan KMZ')
@section('header_title', 'Riwayat Pemrosesan KMZ')
@section('header_subtitle', 'Daftar Seluruh Berkas Survey Google Earth yang Telah Diunggah')

@section('content')
<!-- Filter & Header Actions -->
<div class="card-custom mb-4">
    <div class="card-body p-3">
        <form action="{{ route('kmz.index') }}" method="GET" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Cari kode batch atau nama berkas KMZ..." value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">-- Semua Status --</option>
                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Selesai Diekstrak</option>
                    <option value="extracting" {{ request('status') === 'extracting' ? 'selected' : '' }}>Sedang Diekstrak</option>
                    <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>Gagal</option>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-pln flex-grow-1">
                    <i class="bi bi-filter me-1"></i> Terapkan Filter
                </button>
                @if(request()->hasAny(['search', 'status']))
                    <a href="{{ route('kmz.index') }}" class="btn btn-outline-secondary" title="Reset Filter">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                @endif
                <a href="{{ route('kmz.create') }}" class="btn btn-pln-yellow text-nowrap">
                    <i class="bi bi-plus-lg me-1"></i> Upload KMZ
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Table Batches -->
<div class="card-custom">
    <div class="card-header-clean d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-archive-fill text-primary"></i>
            <span class="fw-bold text-dark">Data Arsip Batch KMZ</span>
        </div>
        <span class="badge bg-light text-secondary border">Total: {{ $batches->total() }} Batch</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
            <thead class="table-light">
                <tr>
                    <th>Kode Batch</th>
                    <th>Nama Berkas Asli</th>
                    <th>Ukuran</th>
                    <th>Hasil Ekstraksi</th>
                    <th>Metadata KML</th>
                    <th>Status</th>
                    <th>Waktu Upload</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($batches as $batch)
                    <tr>
                        <td>
                            <a href="{{ route('kmz.show', $batch->id) }}" class="fw-bold text-primary font-monospace text-decoration-none">
                                {{ $batch->batch_code }}
                            </a>
                        </td>
                        <td>
                            <div class="fw-semibold text-dark text-truncate" style="max-width: 220px;" title="{{ $batch->original_filename }}">
                                {{ $batch->original_filename }}
                            </div>
                            @if($batch->notes)
                                <div class="text-muted text-truncate" style="font-size: 0.75rem; max-width: 220px;" title="{{ $batch->notes }}">
                                    <i class="bi bi-chat-left-text me-1"></i>{{ $batch->notes }}
                                </div>
                            @endif
                        </td>
                        <td class="text-muted">{{ $batch->formatted_file_size }}</td>
                        <td>
                            <div>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                    {{ $batch->total_photos_extracted }} Foto
                                </span>
                            </div>
                            <div class="mt-1" style="font-size: 0.72rem;">
                                <span class="text-muted">{{ $batch->kwh_photos_count }} kWh &bull; {{ $batch->tiang_photos_count }} Tiang &bull; {{ $batch->trafo_photos_count }} Trafo</span>
                            </div>
                        </td>
                        <td>
                            @if($batch->kml_found)
                                <span class="badge bg-success-subtle text-success border border-success-subtle" title="{{ $batch->kml_placemarks_count }} Placemark ditemukan">
                                    <i class="bi bi-geo-alt-fill me-1"></i>KML Ada ({{ $batch->kml_placemarks_count }})
                                </span>
                            @else
                                <span class="badge bg-secondary-subtle text-muted border border-secondary-subtle">
                                    Tanpa KML
                                </span>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $batch->status_badge_class }}">
                                {{ $batch->status_label }}
                            </span>
                            @if($batch->error_message)
                                <div class="text-danger mt-1" style="font-size: 0.72rem;" title="{{ $batch->error_message }}">
                                    <i class="bi bi-exclamation-circle me-1"></i>{{ Str::limit($batch->error_message, 25) }}
                                </div>
                            @endif
                        </td>
                        <td class="text-muted" style="font-size: 0.8rem;">
                            {{ $batch->created_at->format('d/m/Y H:i') }}
                            <div class="text-muted" style="font-size: 0.72rem;">{{ $batch->created_at->diffForHumans() }}</div>
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('kmz.show', $batch->id) }}" class="btn btn-light border text-primary" title="Lihat Foto Batch">
                                    <i class="bi bi-eye-fill"></i>
                                </a>
                                <a href="{{ route('kmz.download', $batch->id) }}" class="btn btn-light border text-secondary" title="Download File KMZ Asli">
                                    <i class="bi bi-download"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            Tidak ada riwayat pemrosesan KMZ yang ditemukan.
                            <div class="mt-2">
                                <a href="{{ route('kmz.create') }}" class="btn btn-sm btn-pln">
                                    <i class="bi bi-cloud-arrow-up-fill me-1"></i> Mulai Unggah Sekarang
                                </a>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($batches->hasPages())
        <div class="card-footer bg-white py-3 border-top">
            {{ $batches->links() }}
        </div>
    @endif
</div>
@endsection
