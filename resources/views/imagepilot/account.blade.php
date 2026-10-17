@extends('layouts.app')

@section('title', 'Account | ImagePilot')

@section('content')
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4 p-md-5">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div class="rounded-circle bg-light p-3">
                                    <i class="bi bi-person-gear fs-3"
                                    aria-hidden="true"></i>
                                </div>

                                <div>
                                    <h1 class="h4 mb-1">Account Settings</h1>
                                    <p class="text-muted mb-0">
                                        Manage your ImagePilot account.
                                    </p>
                                </div>
                            </div>

                            <button
                                type="button"
                                id="logoutButton"
                                class="btn btn-outline-danger btn-sm"
                            >
                                <span id="logoutButtonText">Sign out</span>
                                <span
                                    id="logoutSpinner"
                                    class="spinner-border spinner-border-sm ms-1 d-none"
                                    aria-hidden="true"
                                ></span>
                            </button>
                        </div>

                        <hr>


                        <div id="accountError" class="alert alert-danger d-none" role="alert"></div>

                        <dl class="row mb-0">
                            <dt class="col-sm-3">Name</dt>
                            <dd class="col-sm-9" id="accountName">Loading...</dd>

                            <dt class="col-sm-3">Email</dt>
                            <dd class="col-sm-9 mb-0" id="accountEmail">Loading...</dd>
                        </dl>

                        <p class="text-muted my-4">
                            Account management features will be available
                            here in a future update.
                        </p>

                        <a href="{{ route('imagepilot.optimizer') }}"
                           class="btn btn-primary">
                            <i class="bi bi-arrow-left me-1"
                               aria-hidden="true"></i>
                            Back to Optimizer
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection