<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Sistem Deteksi Foto kWh') - PLN UP3 Tegal</title>

    <!-- Google Fonts: Inter & Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        :root {
            --pln-blue-primary: #005596;
            --pln-blue-dark: #003762;
            --pln-blue-light: #0077c8;
            --pln-yellow: #fdcb02;
            --pln-yellow-dark: #e5b600;
            --pln-red: #da291c;
            --pln-bg: #f4f6fa;
            --pln-sidebar-w: 260px;
            --pln-card-border: #e2e8f0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--pln-bg);
            color: #1e293b;
            min-height: 100vh;
        }

        h1, h2, h3, h4, .brand-font {
            font-family: 'Outfit', sans-serif;
        }

        /* Sidebar Styling */
        #sidebar-wrapper {
            width: var(--pln-sidebar-w);
            min-height: 100vh;
            background: linear-gradient(180deg, #003d6d 0%, #00223f 100%);
            color: #ffffff;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1040;
            transition: all 0.3s ease;
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.08);
        }

        .sidebar-brand {
            padding: 1.25rem 1.25rem 1rem 1.25rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.12);
        }

        .sidebar-brand .logo-box {
            width: 42px;
            height: 42px;
            background-color: var(--pln-yellow);
            color: var(--pln-blue-dark);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: 0 4px 10px rgba(253, 203, 2, 0.35);
        }

        .sidebar-heading {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: rgba(255, 255, 255, 0.5);
            padding: 1.25rem 1.25rem 0.4rem;
            font-weight: 700;
        }

        .nav-link-custom {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.25rem;
            color: rgba(255, 255, 255, 0.82);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.92rem;
            border-left: 4px solid transparent;
            transition: all 0.2s ease;
        }

        .nav-link-custom i {
            font-size: 1.18rem;
            margin-right: 0.85rem;
            width: 24px;
            text-align: center;
        }

        .nav-link-custom:hover {
            color: #ffffff;
            background-color: rgba(255, 255, 255, 0.08);
            border-left-color: var(--pln-yellow);
        }

        .nav-link-custom.active {
            color: #ffffff;
            background-color: rgba(255, 255, 255, 0.14);
            border-left-color: var(--pln-yellow);
            font-weight: 600;
        }

        /* Page Content Wrapper */
        #page-content-wrapper {
            margin-left: var(--pln-sidebar-w);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
        }

        /* Top Navbar */
        .top-navbar {
            background-color: #ffffff;
            border-bottom: 1px solid var(--pln-card-border);
            height: 64px;
            display: flex;
            align-items: center;
            padding: 0 1.5rem;
            position: sticky;
            top: 0;
            z-index: 1020;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.03);
        }

        /* Cards & UI Elements */
        .card-custom {
            background: #ffffff;
            border: 1px solid var(--pln-card-border);
            border-radius: 12px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .card-custom:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .card-header-clean {
            background-color: transparent;
            border-bottom: 1px solid var(--pln-card-border);
            padding: 1rem 1.25rem;
            font-weight: 600;
        }

        .badge-kwh {
            background-color: #e0f2fe;
            color: #0369a1;
            font-weight: 600;
        }

        .badge-tiang {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: 600;
        }

        .badge-trafo {
            background-color: #fef3c7;
            color: #b45309;
            font-weight: 600;
        }

        .btn-pln {
            background-color: var(--pln-blue-primary);
            color: #ffffff;
            font-weight: 500;
            border: none;
            box-shadow: 0 2px 6px rgba(0, 85, 150, 0.25);
        }

        .btn-pln:hover {
            background-color: var(--pln-blue-dark);
            color: #ffffff;
        }

        .btn-pln-yellow {
            background-color: var(--pln-yellow);
            color: #1e293b;
            font-weight: 600;
            border: none;
        }

        .btn-pln-yellow:hover {
            background-color: var(--pln-yellow-dark);
            color: #1e293b;
        }

        /* Mobile Responsiveness */
        @media (max-width: 991.98px) {
            #sidebar-wrapper {
                margin-left: calc(-1 * var(--pln-sidebar-w));
            }
            #sidebar-wrapper.toggled {
                margin-left: 0;
            }
            #page-content-wrapper {
                margin-left: 0;
            }
        }
    </style>
    @stack('styles')
</head>
<body>
    <div class="d-flex" id="wrapper">
        <!-- Sidebar Navigation -->
        <aside id="sidebar-wrapper">
            <!-- Sidebar Brand -->
            <div class="sidebar-brand d-flex align-items-center gap-3">
                <div class="logo-box">
                    <i class="bi bi-lightning-charge-fill"></i>
                </div>
                <div>
                    <div class="fw-bold fs-6 tracking-wide text-white">PLN UP3 TEGAL</div>
                    <div class="text-white-50" style="font-size: 0.72rem;">Deteksi Foto kWh Web</div>
                </div>
            </div>

            <!-- Navigation Links -->
            <nav class="mt-3">
                <div class="sidebar-heading">Menu Utama</div>
                
                <a href="{{ route('dashboard') }}" class="nav-link-custom {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>

                <a href="{{ route('kmz.create') }}" class="nav-link-custom {{ request()->routeIs('kmz.create') ? 'active' : '' }}">
                    <i class="bi bi-cloud-arrow-up-fill"></i>
                    <span>Upload File KMZ</span>
                </a>

                <a href="{{ route('kmz.index') }}" class="nav-link-custom {{ request()->routeIs('kmz.index', 'kmz.show') ? 'active' : '' }}">
                    <i class="bi bi-clock-history"></i>
                    <span>Riwayat KMZ</span>
                </a>

                <a href="{{ route('detections.index') }}" class="nav-link-custom {{ request()->routeIs('detections.*') ? 'active' : '' }}">
                    <i class="bi bi-images"></i>
                    <span>Hasil Deteksi</span>
                </a>

                <div class="sidebar-heading">Pengaturan & Sistem</div>

                <a href="{{ route('settings.index') }}" class="nav-link-custom {{ request()->routeIs('settings.*') ? 'active' : '' }}">
                    <i class="bi bi-gear-fill"></i>
                    <span>Pengaturan Aplikasi</span>
                </a>
            </nav>

            <!-- Sidebar Footer Status -->
            <div class="p-3 mt-auto" style="position: absolute; bottom: 0; width: 100%; border-top: 1px solid rgba(255,255,255,0.08);">
                <div class="p-2 rounded" style="background: rgba(0,0,0,0.2); font-size: 0.72rem;">
                    <div class="d-flex align-items-center justify-content-between text-white-50 mb-1">
                        <span>Database MySQL</span>
                        <span class="badge bg-success" style="font-size: 0.65rem;">Terhubung</span>
                    </div>
                    <div class="text-white-50">Unit: PLN UP3 Tegal</div>
                </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <div id="page-content-wrapper">
            <!-- Top Navbar -->
            <header class="top-navbar">
                <button class="btn btn-light btn-sm me-3 border" id="sidebar-toggle" title="Toggle Navigasi">
                    <i class="bi bi-list fs-5"></i>
                </button>

                <!-- Page Header Title / Breadcrumbs -->
                <div class="d-none d-md-block">
                    <div class="fw-semibold text-secondary" style="font-size: 0.85rem;">
                        @yield('header_subtitle', 'Sistem Deteksi dan Identifikasi Foto kWh Berbasis Web')
                    </div>
                    <h5 class="mb-0 text-dark fw-bold" style="font-size: 1.1rem;">
                        @yield('header_title', 'Dashboard')
                    </h5>
                </div>

                <!-- Right Action Buttons & User Badge -->
                <div class="ms-auto d-flex align-items-center gap-2">
                    <a href="{{ route('kmz.create') }}" class="btn btn-sm btn-pln d-flex align-items-center gap-1">
                        <i class="bi bi-cloud-upload"></i>
                        <span class="d-none d-sm-inline">Upload KMZ</span>
                    </a>

                    <div class="vr mx-2 d-none d-sm-block"></div>

                    <div class="dropdown">
                        <button class="btn btn-sm btn-light border d-flex align-items-center gap-2 dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 26px; height: 26px; font-size: 0.75rem;">
                                OP
                            </div>
                            <span class="d-none d-md-inline" style="font-size: 0.85rem;">Petugas UP3 Tegal</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                            <li><h6 class="dropdown-header">Petugas Magang / Operator</h6></li>
                            <li><a class="dropdown-item" href="{{ route('settings.index') }}"><i class="bi bi-sliders me-2"></i>Konfigurasi</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><span class="dropdown-item-text text-muted" style="font-size: 0.78rem;">Sistem Internal PLN UP3 Tegal</span></li>
                        </ul>
                    </div>
                </div>
            </header>

            <!-- Alerts Container -->
            <main class="container-fluid p-3 p-md-4 flex-grow-1">
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show card-custom border-success mb-3" role="alert">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-check-circle-fill text-success fs-5"></i>
                            <div>{{ session('success') }}</div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show card-custom border-danger mb-3" role="alert">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-exclamation-triangle-fill text-danger fs-5"></i>
                            <div>{{ session('error') }}</div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if($errors->any())
                    <div class="alert alert-danger alert-dismissible fade show card-custom border-danger mb-3" role="alert">
                        <div class="d-flex align-items-start gap-2">
                            <i class="bi bi-x-circle-fill text-danger fs-5 mt-1"></i>
                            <div>
                                <div class="fw-bold mb-1">Periksa kembali data masukan Anda:</div>
                                <ul class="mb-0 ps-3">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                <!-- Yield Content -->
                @yield('content')
            </main>

            <!-- Footer -->
            <footer class="bg-white border-top py-3 px-4 mt-auto">
                <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between text-muted" style="font-size: 0.8rem;">
                    <div>
                        &copy; {{ date('Y') }} <strong>PT PLN (Persero) UP3 Tegal</strong> &mdash; Sistem Deteksi dan Identifikasi Foto kWh Berbasis Web.
                    </div>
                    <div class="mt-1 mt-sm-0">
                        <span class="badge bg-light text-secondary border">Proyek Magang Mahasiswa</span>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Sidebar Toggle Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const sidebar = document.getElementById('sidebar-wrapper');
            const toggleBtn = document.getElementById('sidebar-toggle');
            
            if (toggleBtn && sidebar) {
                toggleBtn.addEventListener('click', function () {
                    sidebar.classList.toggle('toggled');
                });
            }
        });
    </script>
    @stack('scripts')
</body>
</html>
