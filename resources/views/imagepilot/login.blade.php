@extends('layouts.app')

@section('title', 'Login | ImagePilot')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-7 col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <i class="bi bi-images text-primary fs-1"></i>
                        <h1 class="h3 mt-3 mb-2">Welcome back</h1>
                        <p class="text-secondary mb-0">
                            Sign in to your ImagePilot account.
                        </p>
                    </div>

                    <div
                        id="loginError"
                        class="alert alert-danger d-none"
                        role="alert"
                    ></div>

                    <form id="loginForm">
                        <div class="mb-3">
                            <label for="email" class="form-label">
                                Email address
                            </label>

                            <input
                                type="email"
                                class="form-control"
                                id="email"
                                name="email"
                                autocomplete="email"
                                required
                            >
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label">
                                Password
                            </label>

                            <input
                                type="password"
                                class="form-control"
                                id="password"
                                name="password"
                                autocomplete="current-password"
                                required
                            >
                        </div>

                        <button
                            type="submit"
                            id="loginButton"
                            class="btn btn-primary w-100"
                        >
                            <span id="loginButtonText">Sign in</span>
                            <span
                                id="loginSpinner"
                                class="spinner-border spinner-border-sm ms-2 d-none"
                                aria-hidden="true"
                            ></span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    @vite('resources/js/imagepilot/auth.js')
@endpush