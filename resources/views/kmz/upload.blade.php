@extends('layouts.app')

@section('title', 'Upload File KMZ')
@section('header_title', 'Unggah File KMZ')
@section('header_subtitle', 'Ekstraksi Otomatis Foto & Metadata KML Google Earth')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <!-- Main Upload Card -->
        <div class="card-custom mb-4">
            <div class="card-header-clean d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-cloud-arrow-up-fill text-primary fs-5"></i>
                    <span class="fw-bold text-dark">Formulir Unggah Berkas KMZ Survey</span>
                </div>
                <span class="badge bg-light text-secondary border">Maks. {{ $maxSizeMb }} MB</span>
            </div>
            <div class="card-body p-4">
                <form action="{{ route('kmz.store') }}" method="POST" enctype="multipart/form-data" id="upload-form">
                    @csrf

                    <!-- Dropzone / File Picker Container -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold text-dark">Pilih File KMZ (*.kmz) <span class="text-danger">*</span></label>
                        <div class="p-4 text-center rounded-3 border-2 border-dashed bg-light position-relative" id="drop-area" style="border: 2px dashed #cbd5e1; cursor: pointer; transition: all 0.2s ease;">
                            <input type="file" name="kmz_file" id="kmz_file" class="position-absolute top-0 start-0 w-100 h-100 opacity-0" accept=".kmz,.zip" required style="cursor: pointer;">
                            
                            <div id="drop-placeholder">
                                <div class="mb-3">
                                    <i class="bi bi-file-earmark-zip text-primary" style="font-size: 3.5rem;"></i>
                                </div>
                                <h6 class="fw-bold text-dark mb-1">Klik untuk memilih atau seret berkas KMZ ke sini</h6>
                                <p class="text-muted mb-2" style="font-size: 0.82rem;">Format yang didukung: <strong>.kmz</strong> (Google Earth Archive)</p>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Ukuran Maksimal {{ $maxSizeMb }} Megabytes</span>
                            </div>

                            <div id="file-selected-info" class="d-none">
                                <div class="p-3 bg-white rounded-3 shadow-sm border d-inline-flex align-items-center gap-3 text-start">
                                    <i class="bi bi-file-earmark-check-fill text-success fs-2"></i>
                                    <div>
                                        <div class="fw-bold text-dark" id="selected-filename">-</div>
                                        <div class="text-muted" style="font-size: 0.78rem;" id="selected-filesize">-</div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-danger ms-2" id="btn-clear-file">
                                        <i class="bi bi-x"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Catatan / Keterangan Tambahan -->
                    <div class="mb-4">
                        <label for="notes" class="form-label fw-semibold text-dark">Catatan Survey / Lokasi (Opsional)</label>
                        <textarea name="notes" id="notes" rows="3" class="form-control" placeholder="Contoh: Survey Saluran Udara Tegangan Menengah (SUTM) Penyulang TGL-03 Jl. Gajah Mada">{{ old('notes') }}</textarea>
                        <div class="form-text" style="font-size: 0.78rem;">Catatan ini akan tersimpan pada batch pemrosesan untuk memudahkan pelacakan data lapangan.</div>
                    </div>

                    <!-- Tombol Submit -->
                    <div class="d-flex align-items-center justify-content-between pt-3 border-top">
                        <a href="{{ route('kmz.index') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Batal & Kembali
                        </a>
                        <button type="submit" class="btn btn-pln px-4 py-2" id="btn-submit">
                            <span id="btn-text"><i class="bi bi-cloud-arrow-up-fill me-1"></i> Mulai Ekstraksi KMZ</span>
                            <span id="btn-loading" class="d-none">
                                <span class="spinner-border spinner-border-sm me-2" role="status"></span>Sedang Mengekstrak Arsip...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Panduan & Standar Keamanan Proyek -->
        <div class="card-custom">
            <div class="card-header-clean d-flex align-items-center gap-2">
                <i class="bi bi-shield-check text-success"></i>
                <span class="fw-bold text-dark">Standar Keamanan & Prosedur Ekstraksi PLN</span>
            </div>
            <div class="card-body p-4" style="font-size: 0.85rem;">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="d-flex align-items-start gap-2">
                            <i class="bi bi-file-earmark-lock text-primary fs-5 mt-1"></i>
                            <div>
                                <strong class="text-dark">Penyimpanan Berkas Asli:</strong>
                                <p class="text-muted mb-0">Arsip KMZ asli disimpan di direktori terisolasi server dan tidak akan ditimpa untuk kebutuhan validasi audit data lapangan.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex align-items-start gap-2">
                            <i class="bi bi-images text-success fs-5 mt-1"></i>
                            <div>
                                <strong class="text-dark">Validasi Ekstensi & MIME:</strong>
                                <p class="text-muted mb-0">Sistem memverifikasi integritas gambar nyata (JPG, PNG, WEBP) serta membuang berkas asing atau executable berbahaya.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex align-items-start gap-2">
                            <i class="bi bi-geo-alt-fill text-danger fs-5 mt-1"></i>
                            <div>
                                <strong class="text-dark">Ekstraksi Koordinat KML:</strong>
                                <p class="text-muted mb-0">Titik koordinat (Latitude & Longitude) dan nama placemark yang ada dalam <code>doc.kml</code> ditautkan langsung dengan foto terkait.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex align-items-start gap-2">
                            <i class="bi bi-shield-slash text-warning fs-5 mt-1"></i>
                            <div>
                                <strong class="text-dark">Anti Path Traversal:</strong>
                                <p class="text-muted mb-0">Arsitektur sistem mencegah penulisan file ke luar direktori aplikasi guna menjamin keamanan server lokal UP3 Tegal.</p>
                            </div>
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
    document.addEventListener('DOMContentLoaded', function () {
        const fileInput = document.getElementById('kmz_file');
        const dropArea = document.getElementById('drop-area');
        const placeholder = document.getElementById('drop-placeholder');
        const fileInfo = document.getElementById('file-selected-info');
        const fileNameEl = document.getElementById('selected-filename');
        const fileSizeEl = document.getElementById('selected-filesize');
        const clearBtn = document.getElementById('btn-clear-file');
        const form = document.getElementById('upload-form');
        const btnSubmit = document.getElementById('btn-submit');
        const btnText = document.getElementById('btn-text');
        const btnLoading = document.getElementById('btn-loading');

        function formatBytes(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        fileInput.addEventListener('change', function () {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                fileNameEl.textContent = file.name;
                fileSizeEl.textContent = formatBytes(file.size);
                placeholder.classList.add('d-none');
                fileInfo.classList.remove('d-none');
                dropArea.style.borderColor = '#005596';
                dropArea.style.backgroundColor = '#eef6fc';
            }
        });

        clearBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            fileInput.value = '';
            placeholder.classList.remove('d-none');
            fileInfo.classList.add('d-none');
            dropArea.style.borderColor = '#cbd5e1';
            dropArea.style.backgroundColor = '#f8fafc';
        });

        form.addEventListener('submit', function () {
            btnSubmit.disabled = true;
            btnText.classList.add('d-none');
            btnLoading.classList.remove('d-none');
        });
    });
</script>
@endpush
