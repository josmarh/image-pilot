<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <meta
        name="csrf-token"
        content="{{ csrf_token() }}"
    >

    <title>
        @yield('title', 'ImagePilot')
    </title>

    {{-- Bootstrap --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Bootstrap Icons --}}
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >

    @stack('styles')
</head>

<body class="bg-light">

    {{-- Navigation --}}
    <nav class="navbar navbar-expand-lg bg-white border-bottom">
        <div class="container">

            <a
                class="navbar-brand fw-bold"
                href="{{ url('/') }}"
            >
                <i class="bi bi-images me-2"></i>
                ImagePilot
            </a>

            <button
                class="navbar-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#mainNavigation"
                aria-controls="mainNavigation"
                aria-expanded="false"
                aria-label="Toggle navigation"
            >
                <span class="navbar-toggler-icon"></span>
            </button>

            <div
                class="collapse navbar-collapse"
                id="mainNavigation"
            >
                <ul class="navbar-nav ms-auto align-items-lg-center">

                    <li class="nav-item">
                        <a
                            class="nav-link {{ request()->routeIs('imagepilot.optimizer') ? 'active' : '' }}"
                            href="{{ route('imagepilot.optimizer') }}"
                        >
                            Optimizer
                        </a>
                    </li>

                    <li class="nav-item">
                        <a
                            class="nav-link {{ request()->routeIs('imagepilot.batches') ? 'active' : '' }}"
                            href="{{ route('imagepilot.batches') }}"
                        >
                            My Batches
                        </a>
                    </li>

                    <li class="nav-item ms-lg-2">
                        <a
                            href="{{ route('imagepilot.account') }}"
                            class="btn btn-outline-primary btn-sm"
                        >
                            <i class="bi bi-person me-1"></i>
                            Account
                        </a>
                    </li>

                </ul>
            </div>

        </div>
    </nav>

    {{-- Flash Messages --}}
    <main>

        @if (session('success'))
            <div class="container pt-3">
                <div
                    class="alert alert-success alert-dismissible fade show"
                    role="alert"
                >
                    {{ session('success') }}

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                        aria-label="Close"
                    ></button>
                </div>
            </div>
        @endif

        @if (session('error'))
            <div class="container pt-3">
                <div
                    class="alert alert-danger alert-dismissible fade show"
                    role="alert"
                >
                    {{ session('error') }}

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                        aria-label="Close"
                    ></button>
                </div>
            </div>
        @endif

        @yield('content')

    </main>

    {{-- Footer --}}
    <footer class="border-top bg-white mt-5">
        <div class="container py-4">

            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">

                <div class="text-muted small">
                    &copy; {{ date('Y') }} ImagePilot.
                    All rights reserved.
                </div>

                <div class="text-muted small">
                    Bulk image optimization made simple.
                </div>

            </div>

        </div>
    </footer>

    @stack('scripts')

</body>
</html>