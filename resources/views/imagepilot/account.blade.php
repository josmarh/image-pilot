@extends('layouts.app')

@section('title', 'Account | ImagePilot')

@section('content')
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4 p-md-5">
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

                        <hr>

                        <p class="text-muted">
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