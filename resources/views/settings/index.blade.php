@extends('layouts.app')

@section('title', 'Pengaturan Aplikasi')
@section('header_title', 'Pengaturan Sistem & Lingkungan')
@section('header_subtitle', 'Konfigurasi Parameter Aplikasi & Status Integrasi Server')

@section('content')
<div class="row g-4">
    <!-- Kolom Formulir Pengaturan -->
    <div class="col-lg-8">
        <div class="card-custom mb-4">
            <div class="card-header-clean d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-sliders text-primary fs-5"></i>
                    <span class="fw-bold text-dark">Parameter Konfigurasi Operasional</span>
                </div>
                <span class="badge bg-light text-secondary border">PLN UP3 Tegal</span>
            </div>
            <div class="card-body p-4">
                <form action="{{ route('settings.update') }}" method="POST">
                    @csrf

                    @foreach($settings as $group => $items)
                        <div class="mb-4">
                            <h6 class="fw-bold text-primary text-uppercase border-bottom pb-2 mb-3" style="font-size: 0.8rem; letter-spacing: 0.05em;">
                                @if($group === 'organization')
                                    <i class="bi bi-building me-1"></i> Identitas Unit Kerja
                                @elseif($group === 'upload')
                                    <i class="bi bi-cloud-arrow-up me-1"></i> Kebijakan Unggah Berkas KMZ
                                @elseif($group === 'detection')
                                    <i class="bi bi-cpu me-1"></i> Parameter Deteksi & Kecerdasan Buatan (Tahap Selanjutnya)
                                @else
                                    <i class="bi bi-gear me-1"></i> Konfigurasi Umum
                                @endif
                            </h6>

                            <div class="row g-3">
                                @foreach($items as $setting)
                                    <div class="col-md-12">
                                        <label for="{{ $setting->key }}" class="form-label fw-semibold text-dark" style="font-size: 0.88rem;">
                                            {{ $setting->label }}
                                        </label>
                                        <input type="text" name="{{ $setting->key }}" id="{{ $setting->key }}" class="form-control" value="{{ old($setting->key, $setting->value) }}">
                                        @if($setting->description)
                                            <div class="form-text" style="font-size: 0.78rem;">
                                                {{ $setting->description }}
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    <div class="pt-3 border-top d-flex justify-content-end">
                        <button type="submit" class="btn btn-pln px-4">
                            <i class="bi bi-floppy me-1"></i> Simpan Perubahan Pengaturan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Kolom Status Server & Informasi Teknis -->
    <div class="col-lg-4">
        <!-- Card Lingkungan Server -->
        <div class="card-custom mb-4">
            <div class="card-header-clean d-flex align-items-center gap-2">
                <i class="bi bi-hdd-network text-success"></i>
                <span class="fw-bold text-dark">Status Lingkungan Server</span>
            </div>
            <div class="card-body p-3" style="font-size: 0.84rem;">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Versi Laravel</span>
                        <span class="fw-bold font-monospace">{{ app()->version() }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Versi PHP</span>
                        <span class="fw-bold font-monospace">{{ PHP_VERSION }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Database Engine</span>
                        <span class="badge bg-success-subtle text-success border border-success-subtle">MySQL / MariaDB</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">PHP ZipArchive</span>
                        <span class="badge bg-success-subtle text-success border border-success-subtle">Aktif (KMZ Siap)</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Ekstensi Fileinfo / GD</span>
                        <span class="badge bg-success-subtle text-success border border-success-subtle">Aktif</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Storage Symlink</span>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Terhubung</span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Card Rencana Arsitektur AI -->
        <div class="card-custom">
            <div class="card-header-clean d-flex align-items-center gap-2">
                <i class="bi bi-lightbulb-fill text-warning"></i>
                <span class="fw-bold text-dark">Peta Jalan Pengembangan (Roadmap)</span>
            </div>
            <div class="card-body p-3" style="font-size: 0.82rem;">
                <div class="timeline">
                    <div class="mb-3 ps-3 border-start border-2 border-success">
                        <div class="fw-bold text-success"><i class="bi bi-check-circle-fill me-1"></i>Tahap 1: Pondasi & Ekstraksi KMZ</div>
                        <p class="text-muted mb-0">Arsitektur Laravel, basis data MySQL, manajemen berkas aman, ekstraksi Zip/KML, dan navigasi UI.</p>
                    </div>
                    <div class="mb-3 ps-3 border-start border-2 border-primary">
                        <div class="fw-bold text-primary"><i class="bi bi-hourglass-split me-1"></i>Tahap 2: Klasifikasi Objek (YOLO)</div>
                        <p class="text-muted mb-0">Pelatihan/integrasi model YOLO untuk memilah foto kWh, Tiang, dan Trafo secara otomatis.</p>
                    </div>
                    <div class="ps-3 border-start border-2 border-secondary">
                        <div class="fw-bold text-secondary"><i class="bi bi-circle me-1"></i>Tahap 3: Pembacaan Stan (OCR)</div>
                        <p class="text-muted mb-0">Pengenalan karakter plat nomor ID Pelanggan dan angka stan meter kWh presisi tinggi.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
