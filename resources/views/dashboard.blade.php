@extends('layouts.app')

@section('title', 'Dashboard Statistik Hasil Deteksi')
@section('header_title', 'Dashboard Statistik')
@section('header_subtitle', 'Analisis Real-Time Hasil Deteksi Objek & Validasi Status Foto')

@section('content')
<!-- 1. Welcome & Header Banner -->
<div class="card-custom mb-4 overflow-hidden" style="background: linear-gradient(135deg, #005596 0%, #003762 100%); color: #ffffff;">
    <div class="card-body p-4 p-md-5">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-3" style="background: rgba(253, 203, 2, 0.2); border: 1px solid rgba(253, 203, 2, 0.4); color: #fdcb02; font-size: 0.8rem; font-weight: 600;">
                    <i class="bi bi-bar-chart-fill"></i> Data Agregasi Real-Time MySQL
                </div>
                <h2 class="fw-bold mb-2 text-white">Dashboard Statistik Deteksi Foto kWh</h2>
                <p class="text-white-50 mb-4 fs-6" style="max-width: 650px;">
                    Statistik inventarisasi objek lapangan dan status verifikasi kWh meter yang dihitung langsung dari basis data, tanpa angka rekayasa atau simulasi.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('kmz.create') }}" class="btn btn-pln-yellow d-inline-flex align-items-center gap-2">
                        <i class="bi bi-cloud-arrow-up-fill"></i> Upload File KMZ Baru
                    </a>
                    <a href="{{ route('detections.index') }}" class="btn btn-outline-light d-inline-flex align-items-center gap-2">
                        <i class="bi bi-table"></i> Buka Tabel Hasil Deteksi
                    </a>
                </div>
            </div>
            <div class="col-lg-4 d-none d-lg-block text-end">
                <div class="d-inline-block p-4 rounded-circle" style="background: rgba(255, 255, 255, 0.08); border: 2px dashed rgba(255, 255, 255, 0.2);">
                    <i class="bi bi-graph-up-arrow text-warning" style="font-size: 4.5rem;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 2. Panel Filter Statistik (Berdasarkan Batch KMZ & Rentang Tanggal) -->
<div class="card-custom mb-4">
    <div class="card-body p-3 p-md-4">
        <form method="GET" action="{{ route('dashboard') }}" class="row g-3 align-items-end">
            <!-- Filter Batch KMZ -->
            <div class="col-md-5">
                <label for="batch_id" class="form-label fw-semibold text-dark" style="font-size: 0.82rem;">
                    <i class="bi bi-folder2-open text-primary me-1"></i>Filter Berdasarkan Batch KMZ
                </label>
                <select name="batch_id" id="batch_id" class="form-select font-monospace" style="font-size: 0.85rem;">
                    <option value="">-- Seluruh Batch KMZ (Semua Data) --</option>
                    @foreach($batchOptions as $b)
                        <option value="{{ $b->id }}" {{ request('batch_id') == $b->id ? 'selected' : '' }}>
                            {{ $b->batch_code }} &mdash; {{ Str::limit($b->original_filename, 30) }} ({{ $b->created_at->format('d/m/Y') }})
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Filter Tanggal Mulai -->
            <div class="col-sm-6 col-md-3">
                <label for="start_date" class="form-label fw-semibold text-dark" style="font-size: 0.82rem;">
                    <i class="bi bi-calendar-event text-primary me-1"></i>Tanggal Mulai
                </label>
                <input type="date" name="start_date" id="start_date" class="form-control" style="font-size: 0.85rem;" value="{{ request('start_date') }}">
            </div>

            <!-- Filter Tanggal Selesai -->
            <div class="col-sm-6 col-md-2">
                <label for="end_date" class="form-label fw-semibold text-dark" style="font-size: 0.82rem;">
                    <i class="bi bi-calendar-check text-primary me-1"></i>Tanggal Selesai
                </label>
                <input type="date" name="end_date" id="end_date" class="form-control" style="font-size: 0.85rem;" value="{{ request('end_date') }}">
            </div>

            <!-- Tombol Terapkan & Reset -->
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100 btn-sm py-2" title="Terapkan filter statistik">
                    <i class="bi bi-filter me-1"></i> Filter
                </button>
                @if(request()->hasAny(['batch_id', 'start_date', 'end_date']))
                    <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm py-2" title="Reset semua filter">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                @endif
            </div>
        </form>

        <!-- Indikator Filter Aktif -->
        @if(request()->hasAny(['batch_id', 'start_date', 'end_date']))
            <div class="mt-3 pt-3 border-top d-flex flex-wrap align-items-center gap-2" style="font-size: 0.8rem;">
                <span class="text-muted"><i class="bi bi-funnel-fill text-primary me-1"></i>Filter Aktif:</span>
                @if($selectedBatch)
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                        Batch: {{ $selectedBatch->batch_code }} ({{ $selectedBatch->original_filename }})
                    </span>
                @endif
                @if(request('start_date'))
                    <span class="badge bg-light text-dark border">
                        Dari: {{ \Carbon\Carbon::parse(request('start_date'))->translatedFormat('d M Y') }}
                    </span>
                @endif
                @if(request('end_date'))
                    <span class="badge bg-light text-dark border">
                        Sampai: {{ \Carbon\Carbon::parse(request('end_date'))->translatedFormat('d M Y') }}
                    </span>
                @endif
                <a href="{{ route('dashboard') }}" class="text-danger text-decoration-none ms-1">
                    <i class="bi bi-x-circle me-1"></i>Hapus Filter
                </a>
            </div>
        @endif
    </div>
</div>

@if(!$hasData)
    <!-- 3. Tampilan Kondisi Ketika Belum Ada Data (Empty State) -->
    <div class="card-custom mb-4 p-5 text-center">
        <div class="py-4">
            <div class="d-inline-flex p-4 rounded-circle bg-light text-secondary mb-3">
                <i class="bi bi-pie-chart fs-1 text-muted"></i>
            </div>
            <h4 class="fw-bold text-dark mb-2">Belum Ada Data Statistik untuk Ditampilkan</h4>
            <p class="text-muted mx-auto mb-4" style="max-width: 500px; font-size: 0.9rem;">
                Tidak ada data foto yang ditemukan sesuai dengan kriteria filter yang Anda pilih, atau belum ada berkas KMZ yang diunggah ke sistem.
            </p>
            <div class="d-flex justify-content-center gap-2">
                @if(request()->hasAny(['batch_id', 'start_date', 'end_date']))
                    <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Filter Pencarian
                    </a>
                @endif
                <a href="{{ route('kmz.create') }}" class="btn btn-pln">
                    <i class="bi bi-cloud-arrow-up-fill me-1"></i> Unggah File KMZ Baru
                </a>
            </div>
        </div>
    </div>
@else
    <!-- 4. Komponen 8 Kartu Statistik Utama -->
    <div class="mb-4">
        <!-- Bagian A: 4 Kartu Inventarisasi Objek Lapangan -->
        <div class="d-flex align-items-center justify-content-between mb-2">
            <h6 class="fw-bold text-dark text-uppercase mb-0" style="font-size: 0.78rem; letter-spacing: 0.5px;">
                <i class="bi bi-boxes text-primary me-1"></i>Inventarisasi Objek Lapangan (YOLO Detection)
            </h6>
            <span class="badge bg-light text-muted border">Dihitung dari Database MySQL</span>
        </div>
        <div class="row g-3 mb-4">
            <!-- 1. Total Seluruh Foto -->
            <div class="col-sm-6 col-xl-3">
                <div class="card-custom p-3 h-100 border-start border-4 border-primary">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-secondary fw-semibold" style="font-size: 0.82rem;">1. Total Seluruh Foto</span>
                        <div class="p-2 rounded bg-primary-subtle text-primary">
                            <i class="bi bi-images fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-dark" id="stat-total-photos">{{ number_format($stats['total_photos']) }}</h3>
                    <div class="text-muted" style="font-size: 0.75rem;">
                        <i class="bi bi-check2-circle text-success me-1"></i>100% populasi arsip foto
                    </div>
                </div>
            </div>

            <!-- 2. Jumlah Foto kWh -->
            <div class="col-sm-6 col-xl-3">
                <div class="card-custom p-3 h-100 border-start border-4 border-info">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-secondary fw-semibold" style="font-size: 0.82rem;">2. Jumlah Foto kWh</span>
                        <div class="p-2 rounded bg-info-subtle text-info">
                            <i class="bi bi-speedometer2 fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-primary" id="stat-total-kwh">{{ number_format($stats['total_kwh']) }}</h3>
                    <div class="text-muted" style="font-size: 0.75rem;">
                        <span class="fw-bold text-primary">{{ $stats['percentages']['kwh'] }}%</span> dari total populasi
                    </div>
                </div>
            </div>

            <!-- 3. Jumlah Foto Tiang -->
            <div class="col-sm-6 col-xl-3">
                <div class="card-custom p-3 h-100 border-start border-4 border-secondary">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-secondary fw-semibold" style="font-size: 0.82rem;">3. Jumlah Foto Tiang</span>
                        <div class="p-2 rounded bg-secondary-subtle text-secondary">
                            <i class="bi bi-reception-4 fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-secondary" id="stat-total-tiang">{{ number_format($stats['total_tiang']) }}</h3>
                    <div class="text-muted" style="font-size: 0.75rem;">
                        <span class="fw-bold text-secondary">{{ $stats['percentages']['tiang'] }}%</span> &bull; Aset Distribusi (Status: -)
                    </div>
                </div>
            </div>

            <!-- 4. Jumlah Foto Trafo -->
            <div class="col-sm-6 col-xl-3">
                <div class="card-custom p-3 h-100 border-start border-4 border-warning">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-secondary fw-semibold" style="font-size: 0.82rem;">4. Jumlah Foto Trafo</span>
                        <div class="p-2 rounded bg-warning-subtle text-warning-emphasis">
                            <i class="bi bi-lightning-charge-fill fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-dark" id="stat-total-trafo">{{ number_format($stats['total_trafo']) }}</h3>
                    <div class="text-muted" style="font-size: 0.75rem;">
                        <span class="fw-bold text-dark">{{ $stats['percentages']['trafo'] }}%</span> &bull; Gardu Distribusi (Status: -)
                    </div>
                </div>
            </div>
        </div>

        <!-- Bagian B: 4 Kartu Status Validasi & Verifikasi Foto kWh -->
        <div class="d-flex align-items-center justify-content-between mb-2">
            <h6 class="fw-bold text-dark text-uppercase mb-0" style="font-size: 0.78rem; letter-spacing: 0.5px;">
                <i class="bi bi-shield-check text-success me-1"></i>Status Validasi & Pemeriksaan (Khusus Meter kWh)
            </h6>
            <span class="badge bg-light text-muted border">Tiang & Trafo Tidak Dihitung Sesuai/Tidak Sesuai</span>
        </div>
        <div class="row g-3 mb-4">
            <!-- 5. Foto kWh dengan Status Sesuai -->
            <div class="col-sm-6 col-xl-3">
                <div class="card-custom p-3 h-100 border-start border-4 border-success">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-secondary fw-semibold" style="font-size: 0.82rem;">5. Foto kWh Sesuai</span>
                        <div class="p-2 rounded bg-success-subtle text-success">
                            <i class="bi bi-check-circle-fill fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-success" id="stat-kwh-sesuai">{{ number_format($stats['kwh_sesuai']) }}</h3>
                    <div class="text-muted" style="font-size: 0.75rem;">
                        <span class="fw-bold text-success">{{ $stats['percentages']['sesuai'] }}%</span> dari total meter kWh
                    </div>
                </div>
            </div>

            <!-- 6. Foto kWh dengan Status Tidak Sesuai -->
            <div class="col-sm-6 col-xl-3">
                <div class="card-custom p-3 h-100 border-start border-4 border-danger">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-secondary fw-semibold" style="font-size: 0.82rem;">6. Foto kWh Tidak Sesuai</span>
                        <div class="p-2 rounded bg-danger-subtle text-danger">
                            <i class="bi bi-x-circle-fill fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-danger" id="stat-kwh-tidak-sesuai">{{ number_format($stats['kwh_tidak_sesuai']) }}</h3>
                    <div class="text-muted" style="font-size: 0.75rem;">
                        <span class="fw-bold text-danger">{{ $stats['percentages']['tidak_sesuai'] }}%</span> anomali fisik meter
                    </div>
                </div>
            </div>

            <!-- 7. Jumlah Foto Perlu Verifikasi -->
            <div class="col-sm-6 col-xl-3">
                <div class="card-custom p-3 h-100 border-start border-4 border-warning">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-secondary fw-semibold" style="font-size: 0.82rem;">7. Perlu Verifikasi</span>
                        <div class="p-2 rounded bg-warning-subtle text-warning-emphasis">
                            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-warning-emphasis" id="stat-perlu-verifikasi">{{ number_format($stats['perlu_verifikasi']) }}</h3>
                    <div class="text-muted" style="font-size: 0.75rem;">
                        <span class="fw-bold text-warning-emphasis">{{ $stats['percentages']['perlu_verifikasi'] }}%</span> butuh periksa manual
                    </div>
                </div>
            </div>

            <!-- 8. Jumlah Foto Gagal Diproses -->
            <div class="col-sm-6 col-xl-3">
                <div class="card-custom p-3 h-100 border-start border-4 border-dark">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-secondary fw-semibold" style="font-size: 0.82rem;">8. Gagal Diproses</span>
                        <div class="p-2 rounded bg-dark-subtle text-dark">
                            <i class="bi bi-slash-circle-fill fs-5"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-dark" id="stat-failed-photos">{{ number_format($stats['failed_photos']) }}</h3>
                    <div class="text-muted" style="font-size: 0.75rem;">
                        Error berkas rusak atau worker
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 5. Visualisasi Grafik Statistik (Chart.js) -->
    <div class="row g-4 mb-4">
        <!-- Grafik 1: Distribusi Proporsi Jenis Objek Foto -->
        <div class="col-lg-5">
            <div class="card-custom h-100">
                <div class="card-header-clean d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-pie-chart text-primary"></i>
                        <span class="fw-bold text-dark">Proporsi Jenis Objek Foto</span>
                    </div>
                    <span class="badge bg-light text-secondary border">Total {{ number_format($stats['total_photos']) }} Foto</span>
                </div>
                <div class="card-body p-4 d-flex flex-column align-items-center justify-content-center">
                    <div style="position: relative; height: 230px; width: 100%; max-width: 280px;">
                        <canvas id="objectTypeChart"></canvas>
                    </div>

                    <!-- Legenda Rincian Angka -->
                    <div class="w-100 mt-3 pt-3 border-top">
                        <div class="row text-center g-2" style="font-size: 0.82rem;">
                            <div class="col-4">
                                <span class="d-inline-block rounded-circle me-1" style="width: 10px; height: 10px; background-color: #005596;"></span>
                                <span class="fw-semibold text-dark">kWh Meter</span>
                                <div class="font-monospace fw-bold text-primary">{{ number_format($stats['total_kwh']) }} ({{ $stats['percentages']['kwh'] }}%)</div>
                            </div>
                            <div class="col-4">
                                <span class="d-inline-block rounded-circle me-1" style="width: 10px; height: 10px; background-color: #64748b;"></span>
                                <span class="fw-semibold text-dark">Tiang</span>
                                <div class="font-monospace fw-bold text-secondary">{{ number_format($stats['total_tiang']) }} ({{ $stats['percentages']['tiang'] }}%)</div>
                            </div>
                            <div class="col-4">
                                <span class="d-inline-block rounded-circle me-1" style="width: 10px; height: 10px; background-color: #fdcb02;"></span>
                                <span class="fw-semibold text-dark">Trafo</span>
                                <div class="font-monospace fw-bold text-dark">{{ number_format($stats['total_trafo']) }} ({{ $stats['percentages']['trafo'] }}%)</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Grafik 2: Komposisi Status Verifikasi Foto kWh -->
        <div class="col-lg-7">
            <div class="card-custom h-100">
                <div class="card-header-clean d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-bar-chart-line text-success"></i>
                        <span class="fw-bold text-dark">Status Verifikasi Foto kWh</span>
                    </div>
                    <span class="badge bg-light text-secondary border">{{ number_format($stats['total_kwh']) }} Foto kWh Terdaftar</span>
                </div>
                <div class="card-body p-4 d-flex flex-column justify-content-between">
                    <div style="position: relative; height: 230px; width: 100%;">
                        <canvas id="kwhStatusChart"></canvas>
                    </div>

                    <!-- Catatan Operasional PLN -->
                    <div class="mt-3 p-2 bg-light rounded border text-muted" style="font-size: 0.78rem;">
                        <i class="bi bi-info-circle-fill text-primary me-1"></i>
                        <strong>Aturan Bisnis PLN UP3 Tegal:</strong> Objek <strong>Tiang</strong> ({{ $stats['total_tiang'] }}) dan <strong>Trafo</strong> ({{ $stats['total_trafo'] }}) menggunakan status <code>"-"</code> sehingga tidak dimasukkan ke dalam grafik status meter ini.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 6. Status Antrean Pipeline Pemrosesan Foto -->
    <div class="card-custom mb-4">
        <div class="card-header-clean d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-activity text-primary fs-5"></i>
                <span class="fw-bold text-dark">Status Alur Pemrosesan Foto Lapangan</span>
            </div>
            <span class="badge bg-light text-secondary border">Real-Time Queue</span>
        </div>
        <div class="card-body p-3">
            <div class="row g-3 text-center">
                <div class="col-6 col-md-3">
                    <div class="p-3 rounded border bg-light">
                        <div class="text-secondary mb-1" style="font-size: 0.82rem;"><i class="bi bi-circle me-1"></i>Belum Diproses</div>
                        <h4 class="fw-bold text-secondary mb-0">{{ number_format($stats['unprocessed_photos']) }}</h4>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="p-3 rounded border border-primary-subtle bg-primary-subtle">
                        <div class="text-primary mb-1" style="font-size: 0.82rem;"><i class="bi bi-arrow-repeat me-1"></i>Sedang Diproses</div>
                        <h4 class="fw-bold text-primary mb-0">{{ number_format($stats['processing_photos']) }}</h4>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="p-3 rounded border border-success-subtle bg-success-subtle">
                        <div class="text-success mb-1" style="font-size: 0.82rem;"><i class="bi bi-check-circle me-1"></i>Berhasil Diproses</div>
                        <h4 class="fw-bold text-success mb-0">{{ number_format($stats['success_photos']) }}</h4>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="p-3 rounded border border-danger-subtle bg-danger-subtle">
                        <div class="text-danger mb-1" style="font-size: 0.82rem;"><i class="bi bi-x-circle me-1"></i>Gagal Diproses</div>
                        <h4 class="fw-bold text-danger mb-0">{{ number_format($stats['failed_photos']) }}</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif

<!-- 7. Tabel Rangkuman Batch Terkini & Foto Terkini -->
<div class="row g-4">
    <!-- Riwayat Batch Terakhir -->
    <div class="col-lg-7">
        <div class="card-custom h-100">
            <div class="card-header-clean d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-clock-history text-primary"></i>
                    <span class="fw-bold text-dark">Batch KMZ Terkini</span>
                </div>
                <a href="{{ route('kmz.index') }}" class="btn btn-sm btn-outline-secondary" style="font-size: 0.78rem;">
                    Lihat Semua ({{ $totalBatches }})
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                    <thead class="table-light">
                        <tr>
                            <th>Kode Batch</th>
                            <th>Nama File Asli</th>
                            <th>Foto</th>
                            <th>Status</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentBatches as $batch)
                            <tr>
                                <td>
                                    <span class="fw-semibold text-primary font-monospace">{{ $batch->batch_code }}</span>
                                    <div class="text-muted" style="font-size: 0.72rem;">{{ $batch->created_at->format('d/m/Y H:i') }}</div>
                                </td>
                                <td>
                                    <div class="text-truncate" style="max-width: 180px;" title="{{ $batch->original_filename }}">
                                        {{ $batch->original_filename }}
                                    </div>
                                    <span class="badge bg-light text-muted border" style="font-size: 0.7rem;">{{ $batch->formatted_file_size }}</span>
                                </td>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                        {{ $batch->total_photos_extracted }} Foto
                                    </span>
                                </td>
                                <td>
                                    <span class="badge {{ $batch->status_badge_class }}">
                                        {{ $batch->status_label }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('kmz.show', $batch->id) }}" class="btn btn-sm btn-light border" title="Lihat Rincian Batch">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">
                                    <i class="bi bi-inbox fs-3 d-block mb-1"></i>
                                    Belum ada berkas KMZ yang diunggah.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Preview Foto Terkini -->
    <div class="col-lg-5">
        <div class="card-custom h-100">
            <div class="card-header-clean d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-images text-primary"></i>
                    <span class="fw-bold text-dark">Foto Hasil Ekstraksi Terkini</span>
                </div>
                <a href="{{ route('detections.index') }}" class="btn btn-sm btn-outline-secondary" style="font-size: 0.78rem;">
                    Buka Tabel Deteksi
                </a>
            </div>
            <div class="card-body p-3">
                <div class="row g-2">
                    @forelse($recentPhotos as $photo)
                        <div class="col-6 col-sm-3 col-lg-6">
                            <div class="border rounded p-2 h-100 d-flex flex-column bg-light-subtle">
                                <div class="position-relative mb-2 rounded overflow-hidden" style="height: 95px; background-color: #e2e8f0;">
                                    <img src="{{ $photo->photo_url }}" alt="{{ $photo->photo_name }}" 
                                         class="w-100 h-100 object-fit-cover"
                                         onerror="this.onerror=null; this.src='https://placehold.co/400x300/005596/white?text=Foto+Tersimpan';">
                                    <span class="position-absolute top-0 start-0 m-1">
                                        {!! $photo->photo_type_badge !!}
                                    </span>
                                    @if($photo->is_simulation)
                                        <span class="position-absolute bottom-0 start-0 m-1 badge bg-secondary" style="font-size: 0.65rem;">
                                            Simulasi
                                        </span>
                                    @endif
                                </div>
                                <div class="text-truncate fw-semibold" style="font-size: 0.78rem;" title="{{ $photo->photo_name }}">
                                    {{ $photo->photo_name }}
                                </div>
                                <div class="mt-auto pt-1 d-flex align-items-center justify-content-between" style="font-size: 0.72rem;">
                                    <span class="text-muted">{{ $photo->display_status }}</span>
                                    <a href="{{ route('detections.show', $photo->id) }}" class="text-primary text-decoration-none fw-semibold">
                                        Detail &raquo;
                                    </a>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-12 text-center py-4 text-muted">
                            <i class="bi bi-image fs-3 d-block mb-1"></i>
                            Belum ada foto yang diekstrak.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<!-- Chart.js 4.4 CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    @if($hasData)
        // 1. Grafik Donut Proporsi Jenis Objek (kWh, Tiang, Trafo)
        const objectCtx = document.getElementById('objectTypeChart');
        if (objectCtx && typeof Chart !== 'undefined') {
            new Chart(objectCtx, {
                type: 'doughnut',
                data: {
                    labels: ['kWh Meter', 'Tiang Listrik', 'Transformator (Trafo)'],
                    datasets: [{
                        data: [
                            {{ $stats['total_kwh'] }},
                            {{ $stats['total_tiang'] }},
                            {{ $stats['total_trafo'] }}
                        ],
                        backgroundColor: ['#005596', '#64748b', '#fdcb02'],
                        borderWidth: 2,
                        borderColor: '#ffffff',
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = {{ $stats['total_photos'] ?: 1 }};
                                    const val = context.raw || 0;
                                    const pct = ((val / total) * 100).toFixed(1);
                                    return ` ${context.label}: ${val} foto (${pct}%)`;
                                }
                            }
                        }
                    },
                    cutout: '68%'
                }
            });
        }

        // 2. Grafik Batang Status Verifikasi Khusus Foto kWh
        const kwhStatusCtx = document.getElementById('kwhStatusChart');
        if (kwhStatusCtx && typeof Chart !== 'undefined') {
            new Chart(kwhStatusCtx, {
                type: 'bar',
                data: {
                    labels: ['Sesuai', 'Perlu Verifikasi', 'Tidak Sesuai'],
                    datasets: [{
                        label: 'Jumlah Foto kWh',
                        data: [
                            {{ $stats['kwh_sesuai'] }},
                            {{ $stats['perlu_verifikasi'] }},
                            {{ $stats['kwh_tidak_sesuai'] }}
                        ],
                        backgroundColor: ['#198754', '#eab308', '#dc3545'],
                        borderRadius: 6,
                        maxBarThickness: 50
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const totalKwh = {{ $stats['total_kwh'] ?: 1 }};
                                    const val = context.raw || 0;
                                    const pct = ((val / totalKwh) * 100).toFixed(1);
                                    return ` ${val} Foto (${pct}% dari meter kWh)`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0,
                                stepSize: 1
                            },
                            grid: {
                                color: '#f1f5f9'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }
    @endif
});
</script>
@endpush
