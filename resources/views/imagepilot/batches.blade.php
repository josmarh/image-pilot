
@extends('layouts.app')

@section('title', 'My Batches')

@section('content')
<div class="container py-4" id="batchesContainer">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="fw-bold mb-1">My Batches</h1>
            <p class="text-muted mb-0">
                View your previous image optimization jobs and download completed results.
            </p>
        </div>

        <a href="{{ route('imagepilot.optimizer') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-2"></i>
            Optimize Images
        </a>
    </div>

    <div id="batchesAlert" class="alert d-none" role="alert"></div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <div class="text-muted small">Total batches on this page</div>
                    <h3 id="batchesCount" class="mb-0">—</h3>
                </div>

                <div class="col-12 col-md-4">
                    <div class="text-muted small">Completed batches</div>
                    <h3 id="completedBatchesCount" class="mb-0">—</h3>
                </div>

                <div class="col-12 col-md-4">
                    <div class="text-muted small">Storage saved on this page</div>
                    <h3 id="batchesSaved" class="mb-0">—</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 p-4">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="fw-semibold mb-0">Batch History</h5>

                <button
                    type="button"
                    id="refreshBatchesBtn"
                    class="btn btn-sm btn-outline-secondary"
                >
                    <i class="bi bi-arrow-clockwise me-1"></i>
                    Refresh
                </button>
            </div>
        </div>

        <div class="card-body p-4">
            <div id="batchesLoading" class="text-center py-5 d-none">
                <div class="spinner-border text-primary mb-3" role="status"></div>
                <p class="text-muted mb-0">Loading your batches...</p>
            </div>

            <div id="batchesEmpty" class="text-center py-5 d-none">
                <i class="bi bi-images display-4 text-muted"></i>
                <h5 class="mt-3">No batches yet</h5>
                <p class="text-muted">
                    Your image optimization history will appear here.
                </p>
                <a href="{{ route('imagepilot.optimizer') }}" class="btn btn-primary">
                    Optimize your first images
                </a>
            </div>

            <div id="batchesList" class="d-none">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Batch</th>
                                <th>Status</th>
                                <th>Progress</th>
                                <th>Storage Saved</th>
                                <th>Date</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="batchesTableBody"></tbody>
                    </table>
                </div>

                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3">
                    <small id="batchesPaginationInfo" class="text-muted"></small>

                    <div class="btn-group" role="group" aria-label="Batch pagination">
                        <button
                            type="button"
                            id="batchesPrevBtn"
                            class="btn btn-outline-secondary btn-sm"
                        >
                            Previous
                        </button>
                        <button
                            type="button"
                            id="batchesNextBtn"
                            class="btn btn-outline-secondary btn-sm"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Batch details --}}
    <div id="batchDetailsSection" class="card border-0 shadow-sm mt-4 d-none">
        <div class="card-header bg-white border-0 p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h5 class="fw-semibold mb-1">Batch Details</h5>
                    <small id="batchDetailsReference" class="text-muted"></small>
                </div>

                <button
                    type="button"
                    id="closeBatchDetailsBtn"
                    class="btn btn-sm btn-outline-secondary"
                >
                    Close
                </button>
            </div>
        </div>

        <div class="card-body p-4">
            <div id="batchDetailsLoading" class="text-center py-4 d-none">
                <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                Loading batch details...
            </div>

            <div id="batchDetailsAlert" class="alert d-none" role="alert"></div>

            <div id="batchDetailsContent" class="d-none">
                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-3">
                        <div class="text-muted small">Status</div>
                        <div id="detailStatus" class="fw-semibold">—</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-muted small">Images</div>
                        <div id="detailImages" class="fw-semibold">—</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-muted small">Original Size</div>
                        <div id="detailOriginalSize" class="fw-semibold">—</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-muted small">Optimized Size</div>
                        <div id="detailOptimizedSize" class="fw-semibold">—</div>
                    </div>
                </div>

                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <h6 class="fw-semibold mb-0">Processed Images</h6>

                    <button
                        type="button"
                        id="detailDownloadBtn"
                        class="btn btn-success btn-sm d-none"
                    >
                        <i class="bi bi-download me-1"></i>
                        Download ZIP
                    </button>
                </div>

                <div id="batchItemsLoading" class="text-muted small mb-3 d-none">
                    Loading image details...
                </div>

                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Status</th>
                                <th>Original Size</th>
                                <th>Output Size</th>
                                <th>Message</th>
                            </tr>
                        </thead>
                        <tbody id="batchItemsTableBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
