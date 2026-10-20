 @extends('layouts.app')

@section('title', 'Create Account | ImagePilot')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-7 col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <i class="bi bi-images text-primary fs-1"></i>
                        <h1 class="h3 mt-3 mb-2">Create your account</h1>
                        <p class="text-secondary mb-0">
                            Sign up to save your presets and access your batch history.
                        </p>
                    </div>

                    <div
                        id="registerError"
                        class="alert alert-danger d-none"
                        role="alert"
                    ></div>

                    <form id="registerForm">
                        <div class="mb-3">
                            <label for="name" class="form-label">
                                Full name
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                id="name"
                                name="name"
                                autocomplete="name"
                                maxlength="255"
                                required
                            >
                        </div>

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
                                maxlength="255"
                                required
                            >
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">
                                Password
                            </label>

                            <input
                                type="password"
                                class="form-control"
                                id="password"
                                name="password"
                                autocomplete="new-password"
                                minlength="8"
                                required
                            >
                        </div>

                        <div class="mb-4">
                            <label for="password_confirmation" class="form-label">
                                Confirm password
                            </label>

                            <input
                                type="password"
                                class="form-control"
                                id="password_confirmation"
                                name="password_confirmation"
                                autocomplete="new-password"
                                minlength="8"
                                required
                            >
                        </div>

                        <button
                            type="submit"
                            id="registerButton"
                            class="btn btn-primary w-100"
                        >
                            <span id="registerButtonText">Create account</span>
                            <span
                                id="registerSpinner"
                                class="spinner-border spinner-border-sm ms-2 d-none"
                                aria-hidden="true"
                            ></span>
                        </button>
                    </form>

                    <p class="text-center text-secondary mt-4 mb-0">
                        Already have an account?
                        <a href="/login">Sign in</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    @vite('resources/js/imagepilot/auth.js')
@endpush