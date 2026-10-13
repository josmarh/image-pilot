@extends('layouts.app')

@section('title', 'Image Optimizer')

@section('content')
<div class="container py-4">

    {{-- Header --}}
    <div class="text-center mb-4">
        <h1 class="fw-bold mb-2">Optimize your images</h1>

        <p class="text-muted mb-0">
            Compress, resize and convert multiple images at once.
        </p>
    </div>

    {{-- Upload Area --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">

            <div
                id="imageDropzone"
                class="border border-2 rounded-3 p-5 text-center"
                style="border-style: dashed !important; cursor: pointer;"
            >
                <div class="mb-3">
                    <i class="bi bi-cloud-arrow-up fs-1 text-primary"></i>
                </div>

                <h5 class="fw-semibold">
                    Drag & drop your images here
                </h5>

                <p class="text-muted mb-3">
                    or
                </p>

                <button
                    type="button"
                    id="chooseImagesBtn"
                    class="btn btn-primary px-4"
                >
                    Choose Images
                </button>

                <input
                    type="file"
                    id="imageInput"
                    class="d-none"
                    accept=".jpg,.jpeg,.png,.webp"
                    multiple
                >

                <div class="small text-muted mt-3">
                    JPG, PNG or WebP · Maximum 50 images
                </div>
            </div>

        </div>
    </div>

    {{-- Selected Images --}}
    <div
        id="selectedImagesSection"
        class="card border-0 shadow-sm mb-4 d-none"
    >
        <div class="card-header bg-white border-0 pt-4 px-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-1 fw-semibold">Selected Images</h5>

                    <small class="text-muted">
                        <span id="selectedCount">0</span> images selected
                    </small>
                </div>

                <button
                    type="button"
                    id="clearImagesBtn"
                    class="btn btn-sm btn-outline-danger"
                >
                    Clear All
                </button>
            </div>
        </div>

        <div class="card-body px-4">
            <div id="selectedImagesList" class="list-group list-group-flush">
                {{-- Images inserted by JavaScript --}}
            </div>
        </div>
    </div>

    {{-- Optimization Settings --}}
    <div
        id="settingsSection"
        class="card border-0 shadow-sm mb-4 d-none"
    >
        <div class="card-header bg-white border-0 pt-4 px-4">
            <h5 class="fw-semibold mb-1">
                Optimization Settings
            </h5>

            <small class="text-muted">
                Configure how your images should be processed.
            </small>
        </div>

        <div class="card-body p-4">

            <div class="row g-3">

                {{-- Width --}}
                <div class="col-md-6">
                    <label for="width" class="form-label">
                        Width
                    </label>

                    <input
                        type="number"
                        id="width"
                        class="form-control"
                        min="1"
                        max="12000"
                        placeholder="e.g. 1200"
                    >
                </div>

                {{-- Height --}}
                <div class="col-md-6">
                    <label for="height" class="form-label">
                        Height
                    </label>

                    <input
                        type="number"
                        id="height"
                        class="form-control"
                        min="1"
                        max="12000"
                        placeholder="e.g. 1200"
                    >
                </div>

                {{-- Resize Mode --}}
                <div class="col-md-6">
                    <label for="resizeMode" class="form-label">
                        Resize Mode
                    </label>

                    <select
                        id="resizeMode"
                        class="form-select"
                    >
                        <option value="contain">
                            Contain — Preserve entire image
                        </option>

                        <option value="cover">
                            Cover — Fill dimensions
                        </option>
                    </select>
                </div>

                {{-- Output Format --}}
                <div class="col-md-6">
                    <label for="outputFormat" class="form-label">
                        Output Format
                    </label>

                    <select
                        id="outputFormat"
                        class="form-select"
                    >
                        <option value="webp" selected>WebP</option>
                        <option value="jpg">JPG</option>
                        <option value="png">PNG</option>
                    </select>
                </div>

                {{-- Quality --}}
                <div class="col-12">
                    <label for="quality" class="form-label">
                        Quality:
                        <strong id="qualityValue">80</strong>
                    </label>

                    <input
                        type="range"
                        id="quality"
                        class="form-range"
                        min="0"
                        max="100"
                        value="80"
                    >
                </div>

                {{-- Prevent Upscaling --}}
                <div class="col-12">
                    <div class="form-check">
                        <input
                            type="checkbox"
                            id="preventUpscaling"
                            class="form-check-input"
                            checked
                        >

                        <label
                            for="preventUpscaling"
                            class="form-check-label"
                        >
                            Prevent image upscaling
                        </label>
                    </div>
                </div>

            </div>

            {{-- Advanced Settings --}}
            <div class="mt-4">
                <button
                    class="btn btn-link text-decoration-none px-0"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#advancedSettings"
                    aria-expanded="false"
                >
                    <i class="bi bi-sliders me-1"></i>
                    Advanced Settings
                </button>

                <div
                    id="advancedSettings"
                    class="collapse mt-3"
                >
                    <div class="alert alert-light border mb-0">
                        {{-- Metadata information --}}
                        <div class="mb-4">
                            <div class="d-flex align-items-start gap-2">
                                <i class="bi bi-shield-check text-success fs-5"></i>
                                <div>
                                    <div class="fw-semibold">Metadata removal</div>
                                    <div class="small text-muted">
                                        Images are re-encoded during optimization, which
                                        generally removes embedded metadata such as EXIF.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr>

                        {{-- Watermark toggle --}}
                        <div class="form-check form-switch mb-3">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                id="watermarkEnabled"
                                role="switch"
                            >

                            <label
                                class="form-check-label fw-semibold"
                                for="watermarkEnabled"
                            >
                                Add text watermark
                            </label>

                            <div class="form-text">
                                Add a text watermark to your optimized images.
                            </div>
                        </div>

                        {{-- Watermark configuration --}}
                        <div id="watermarkSettings" class="d-none">
                            <div class="row g-3">
                                {{-- Watermark text --}}
                                <div class="col-12">
                                    <label for="watermarkText" class="form-label">
                                        Watermark Text
                                    </label>

                                    <input
                                        type="text"
                                        id="watermarkText"
                                        class="form-control"
                                        maxlength="200"
                                        placeholder="e.g. © Your Brand"
                                    >

                                    <div class="form-text">
                                        Enter the text you want displayed on your images.
                                    </div>
                                </div>

                                {{-- Position --}}
                                <div class="col-md-6">
                                    <label for="watermarkPosition" class="form-label">
                                        Position
                                    </label>

                                    <select
                                        id="watermarkPosition"
                                        class="form-select"
                                    >
                                        <option value="top-left">Top left</option>
                                        <option value="top-center">Top center</option>
                                        <option value="top-right">Top right</option>
                                        <option value="center">Center</option>
                                        <option value="bottom-left">Bottom left</option>
                                        <option value="bottom-center">Bottom center</option>
                                        <option value="bottom-right" selected>
                                            Bottom right
                                        </option>
                                    </select>
                                </div>

                                {{-- Color --}}
                                <div class="col-md-6">
                                    <label for="watermarkColor" class="form-label">
                                        Text Color
                                    </label>

                                    <input
                                        type="color"
                                        id="watermarkColor"
                                        class="form-control form-control-color w-100"
                                        value="#FFFFFF"
                                        title="Choose watermark text color"
                                    >
                                </div>

                                {{-- Opacity --}}
                                <div class="col-md-6">
                                    <label for="watermarkOpacity" class="form-label">
                                        Opacity:
                                        <strong id="watermarkOpacityValue">70%</strong>
                                    </label>

                                    <input
                                        type="range"
                                        id="watermarkOpacity"
                                        class="form-range"
                                        min="0"
                                        max="100"
                                        value="70"
                                    >
                                </div>

                                {{-- Font size --}}
                                <div class="col-md-6">
                                    <label for="watermarkSize" class="form-label">
                                        Font Size (px)
                                    </label>

                                    <input
                                        type="number"
                                        id="watermarkSize"
                                        class="form-control"
                                        min="8"
                                        max="200"
                                        value="24"
                                    >
                                </div>

                                {{-- Margin --}}
                                <div class="col-md-6">
                                    <label for="watermarkMargin" class="form-label">
                                        Edge Margin (px)
                                    </label>

                                    <input
                                        type="number"
                                        id="watermarkMargin"
                                        class="form-control"
                                        min="0"
                                        max="1000"
                                        value="20"
                                    >
                                </div>
                            </div>

                            <div class="alert alert-info small mt-3 mb-0">
                                <i class="bi bi-info-circle me-1"></i>
                                Watermarks are applied after resizing. A watermark may
                                extend beyond the image boundaries if its text is too
                                large for the selected image dimensions.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    {{-- Optimize Button --}}
    <div
        id="optimizeSection"
        class="text-center d-none"
    >
        <button
            type="button"
            id="optimizeBtn"
            class="btn btn-primary btn-lg px-5"
        >
            <i class="bi bi-magic me-2"></i>
            Optimize Images
        </button>
    </div>

    {{-- Processing Section --}}
    <div
        id="processingSection"
        class="card border-0 shadow-sm mt-4 d-none"
    >
        <div class="card-body p-4">

            <div class="text-center mb-4">
                <div
                    class="spinner-border text-primary mb-3"
                    role="status"
                ></div>

                <h5 class="fw-semibold mb-1">
                    Processing your images...
                </h5>

                <p
                    id="processingMessage"
                    class="text-muted mb-0"
                >
                    Uploading images...
                </p>
            </div>

            <div class="progress" style="height: 10px;">
                <div
                    id="processingProgress"
                    class="progress-bar progress-bar-striped progress-bar-animated"
                    role="progressbar"
                    style="width: 0%;"
                ></div>
            </div>

            <div class="d-flex justify-content-between mt-2">
                <small class="text-muted">
                    <span id="processedCount">0</span>
                    /
                    <span id="totalCount">0</span>
                </small>

                <small class="fw-semibold">
                    <span id="progressPercentage">0</span>%
                </small>
            </div>

        </div>
    </div>

    {{-- Results Section --}}
    <div id="resultsSection" class="card border-0 shadow-sm mt-4 d-none">
        <div class="card-body">
            <div class="container-fluid px-0">

                <!-- Results heading -->
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                    <div>
                        <h3 class="mb-1">Optimization Results</h3>
                        <p class="text-muted mb-0">
                            Here's how your images performed.
                        </p>
                    </div>

                    <span id="resultsStatus" class="badge rounded-pill text-bg-success px-3 py-2">
                        Completed
                    </span>
                </div>

                <!-- Summary cards -->
                <div class="row g-3 mb-4">

                    <div class="col-12 col-md-4">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="rounded-3 bg-primary-subtle text-primary p-3">
                                        <i class="bi bi-images fs-4"></i>
                                    </div>

                                    <div>
                                        <div class="text-muted small">
                                            Images processed
                                        </div>
                                        <h3 id="summaryProcessed" class="mb-0">0</h3>
                                        <small id="summaryTotal" class="text-muted">
                                            0 images total
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-4">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="rounded-3 bg-success-subtle text-success p-3">
                                        <i class="bi bi-database-down fs-4"></i>
                                    </div>

                                    <div>
                                        <div class="text-muted small">
                                            Storage saved
                                        </div>
                                        <h3 id="summarySaved" class="mb-0">0 B</h3>
                                        <small id="summaryReduction" class="text-muted">
                                            0% reduction
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-4">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="rounded-3 bg-info-subtle text-info p-3">
                                        <i class="bi bi-file-earmark-check fs-4"></i>
                                    </div>

                                    <div>
                                        <div class="text-muted small">
                                            Optimized output
                                        </div>
                                        <h3 id="summaryOutputSize" class="mb-0">0 B</h3>
                                        <small id="summaryOriginalSize" class="text-muted">
                                            Original: 0 B
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Per-image results -->
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                            <div>
                                <h5 class="mb-1">Processed Images</h5>
                                <p class="text-muted small mb-0">
                                    Compare original files with their optimized versions.
                                </p>
                            </div>
                        </div>

                        <div id="resultsList"></div>
                    </div>
                </div>

                <!-- Keep your existing action buttons below this point -->

                <div class="text-center">
                    <p id="resultsSummary" class="text-muted"></p>

                    <a
                        href="#"
                        id="downloadBtn"
                        class="btn btn-success px-4"
                    >
                        <i class="bi bi-download me-2"></i>
                        Download ZIP
                    </a>

                    <button
                        type="button"
                        id="startAnotherBtn"
                        class="btn btn-outline-secondary px-4 ms-2"
                    >
                        Optimize More Images
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
