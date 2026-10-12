import $ from 'jquery';
import {
    createBatch,
    getBatch,
    getBatchItems,
    downloadBatch,
    getImagePresets,
    createImagePreset,
    updateImagePreset,
    deleteImagePreset,
} from './api';

window.$ = $;
window.jQuery = $;

$(function () {
    const MAX_FILES = 50;
    const MAX_FILE_SIZE = 10 * 1024 * 1024;

    const ALLOWED_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    let selectedFiles = [];

    const $imageInput = $('#imageInput');
    const $dropzone = $('#imageDropzone');
    const $chooseImagesBtn = $('#chooseImagesBtn');

    const $selectedImagesSection = $('#selectedImagesSection');
    const $selectedImagesList = $('#selectedImagesList');
    const $selectedCount = $('#selectedCount');

    const $settingsSection = $('#settingsSection');
    const $optimizeSection = $('#optimizeSection');

    const $clearImagesBtn = $('#clearImagesBtn');

    const $quality = $('#quality');
    const $qualityValue = $('#qualityValue');

    /*
     * Open file selector
     */
    $chooseImagesBtn.on('click', function (event) {
        event.stopPropagation();

        $imageInput.trigger('click');
    });

    /*
     * File input
     */
    $imageInput.on('change', function () {
        addFiles(Array.from(this.files));

        // Allow selecting the same file again later.
        this.value = '';
    });

    /*
     * Drag & drop
     */
    $dropzone.on('dragover', function (event) {
        event.preventDefault();
        event.stopPropagation();

        $(this).addClass('border-primary bg-primary-subtle');
    });

    $dropzone.on('dragleave', function (event) {
        event.preventDefault();
        event.stopPropagation();

        $(this).removeClass('border-primary bg-primary-subtle');
    });

    $dropzone.on('drop', function (event) {
        event.preventDefault();
        event.stopPropagation();

        $(this).removeClass('border-primary bg-primary-subtle');

        const files = Array.from(
            event.originalEvent.dataTransfer.files
        );

        addFiles(files);
    });

    /*
    * Optimize images
    */
    $('#optimizeBtn').on('click', function () {
        if (!selectedFiles.length) {
            return;
        }

        const settings = {
            width: $('#width').val() || null,
            height: $('#height').val() || null,
            resize_mode: $('#resizeMode').val(),
            output_format: $('#outputFormat').val(),
            quality: Number($('#quality').val()),
            prevent_upscaling: $('#preventUpscaling').is(':checked'),
        };

        const $button = $(this);

        $button
            .prop('disabled', true)
            .html(
                '<span class="spinner-border spinner-border-sm me-2"></span>' +
                'Uploading...'
            );

        createBatch(selectedFiles, settings)
            .done(function (response) {
                /*
                * We'll use this reference for polling
                * in the next step.
                */
                const batchReference =
                    response.data.batch_reference;

                startBatchPolling(batchReference);

                /*
                * For now, simply show that the API accepted
                * the batch.
                */
                $button
                    .removeClass('btn-primary')
                    .addClass('btn-success')
                    .html(
                        '<i class="bi bi-check-lg me-2"></i>' +
                        'Images Uploaded'
                    );
            })
            .fail(function (xhr) {
                console.error(
                    'Batch upload failed:',
                    xhr
                );

                let message =
                    'Unable to upload images. Please try again.';

                if (
                    xhr.responseJSON &&
                    xhr.responseJSON.message
                ) {
                    message = xhr.responseJSON.message;
                }

                showError(message);

                $button
                    .prop('disabled', false)
                    .html(
                        '<i class="bi bi-magic me-2"></i>' +
                        'Optimize Images'
                    );
            });
    });

    $('#downloadBtn').on('click', function () {
        const batchReference = $(this).data('batch-reference');

        if (!batchReference) {
            showError('No processed batch is available for download.');

            return;
        }

        const $button = $(this);

        $button.prop('disabled', true);

        downloadBatch(batchReference)
            .done(function (blob, status, xhr) {
                const contentDisposition =
                    xhr.getResponseHeader('Content-Disposition');

                let filename =
                    `imagepilot-${batchReference}.zip`;

                if (contentDisposition) {
                    const match =
                        contentDisposition.match(
                            /filename="?([^"]+)"?/
                        );

                    if (match && match[1]) {
                        filename = match[1];
                    }
                }

                const url = window.URL.createObjectURL(blob);

                const link = document.createElement('a');

                link.href = url;
                link.download = filename;

                document.body.appendChild(link);
                link.click();
                link.remove();

                window.URL.revokeObjectURL(url);
            })
            .fail(function (xhr) {
                console.error(
                    'Unable to download batch:',
                    xhr
                );

                showError(
                    'Unable to download the processed images. Please try again.'
                );
            })
            .always(function () {
                $button.prop('disabled', false);
            });
    });

    $('#startAnotherBtn').on('click', function () {
        resetOptimizer();
    });

    function resetOptimizer() {
        selectedFiles = [];

        $('#imageInput').val('');

        $('#imageList').empty();

        $('#resultsSection').addClass('d-none');

        $('#processingSection').addClass('d-none');

        $('#optimizeSection').removeClass('d-none');

        $('#processingProgress')
            .css('width', '0%')
            .attr('aria-valuenow', 0);

        $('#processedCount').text('0');

        $('#totalCount').text('0');

        $('#progressPercentage').text('0');

        $('#resultsSummary').text('');

        $('#downloadBtn').removeData('batch-reference');

        $('#optimizeBtn')
            .prop('disabled', false)
            .removeClass('btn-success')
            .addClass('btn-primary')
            .html(
                '<i class="bi bi-magic me-2"></i>' +
                'Optimize Images'
            );

        $('#selectedImagesSection').addClass('d-none');

        $('#emptyState').removeClass('d-none');

        hideError();

        renderSelectedFiles();
    }

    function startBatchPolling(batchReference) {
        $('#processingSection').removeClass('d-none');
        $('#optimizeSection').addClass('d-none');

        $('#processingMessage').text(
            'Processing your images...'
        );

        pollBatch(batchReference);
    }

    function pollBatch(batchReference) {
        getBatch(batchReference)
            .done(function (response) {
                const data = response.data;

                const progress = data.progress;

                const percentage =
                    Number(progress.percentage) || 0;

                $('#processingProgress')
                    .css('width', `${percentage}%`)
                    .attr('aria-valuenow', percentage);

                $('#processedCount').text(
                    progress.completed + progress.failed
                );

                $('#totalCount').text(
                    progress.total
                );

                $('#progressPercentage').text(
                    percentage
                );

                if (data.status === 'completed') {
                    handleBatchCompleted(data);
                    return;
                }

                if (data.status === 'partial') {
                    handleBatchCompleted(data);
                    return;
                }

                if (data.status === 'failed') {
                    handleBatchFailed();
                    return;
                }

                setTimeout(function () {
                    pollBatch(batchReference);
                }, 2000);
            })
            .fail(function (xhr) {
                console.error(
                    'Unable to retrieve batch status:',
                    xhr
                );

                /*
                * Don't immediately fail the entire operation
                * because a temporary network error can occur.
                */
                setTimeout(function () {
                    pollBatch(batchReference);
                }, 3000);
            });
    }

    
    function handleBatchCompleted(data) {
        $('#processingSection').addClass('d-none');
        $('#resultsSection').removeClass('d-none');

        const progress = data.progress;
        const size = data.size;

        $('#summaryProcessed').text(progress.completed);
        $('#summaryTotal').text(
            `${progress.total} images total`
        );

        $('#summarySaved').text(
            formatBytes(size.saved_bytes)
        );

        $('#summaryReduction').text(
            `${size.saved_percentage}% overall reduction`
        );

        $('#summaryOutputSize').text(
            formatBytes(size.optimized_bytes)
        );

        $('#summaryOriginalSize').text(
            `Original: ${formatBytes(size.original_bytes)}`
        );

        if (data.status === 'partial') {
            $('#resultsStatus')
                .removeClass('text-bg-success')
                .addClass('text-bg-warning')
                .text('Partially completed');
        } else {
            $('#resultsStatus')
                .removeClass('text-bg-warning')
                .addClass('text-bg-success')
                .text('Completed');
        }

        $('#downloadBtn').data(
            'batch-reference',
            data.batch_reference
        );

        $('#optimizeBtn')
            .prop('disabled', false)
            .removeClass('btn-success')
            .addClass('btn-primary')
            .html(
                '<i class="bi bi-magic me-2"></i>' +
                'Optimize Images'
            );

        loadBatchItems(data.batch_reference);
    }

    function handleBatchFailed() {
        $('#processingSection').addClass('d-none');

        $('#optimizeSection').removeClass('d-none');

        $('#optimizeBtn')
            .prop('disabled', false)
            .html(
                '<i class="bi bi-magic me-2"></i>' +
                'Optimize Images'
            );

        showError(
            'Image processing failed. Please try again.'
        );
    }

    function loadBatchItems(batchReference) {
        $('#resultsList').html(`
            <div class="text-center py-4">
                <div class="spinner-border" role="status"></div>
                <div class="mt-2 text-muted">
                    Loading image results...
                </div>
            </div>
        `);

        getBatchItems(batchReference)
            .done(function (response) {
                renderBatchItems(response.data);
            })
            .fail(function (xhr) {
                console.error(
                    'Unable to load batch items:',
                    xhr
                );

                $('#resultsList').html(`
                    <div class="alert alert-danger">
                        Unable to load image results.
                        Please refresh the page and try again.
                    </div>
                `);
            });
    }

    function renderBatchItems(items) {
        if (!items.length) {
            $('#resultsList').html(`
                <div class="alert alert-warning">
                    No image results are available.
                </div>
            `);

            return;
        }

        const html = items.map(item => {
            if (item.status === 'failed') {
                return renderFailedItem(item);
            }

            return renderSuccessfulItem(item);
        }).join('');

        $('#resultsList').html(html);
    }

    function renderSuccessfulItem(item) {
        const originalSize = Number(item.original.size_bytes) || 0;
        const outputSize = Number(item.output.size_bytes) || 0;
        const savedBytes = Math.max(0, originalSize - outputSize);
        const savedPercentage = Number(item.savings.percentage) || 0;

        const originalDimensions =
            `${item.original.width} x ${item.original.height}`;

        const outputDimensions =
            `${item.output.width} x ${item.output.height}`;

        return `
            <div class="card border mb-3 result-item">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                        <div class="min-w-0">
                            <h6 class="mb-1 text-break">
                                ${escapeHtml(item.original.filename)}
                            </h6>

                            <span class="text-muted small">
                                ${escapeHtml(item.original.format.toUpperCase())}
                                · ${originalDimensions}
                            </span>
                        </div>

                        <span class="badge rounded-pill text-bg-success">
                            ${savedPercentage}% saved
                        </span>
                    </div>

                    <div class="row g-3 align-items-stretch">
                        <div class="col-12 col-md-5">
                            <div class="rounded-3 bg-body-tertiary p-3 h-100">
                                <div class="text-muted small mb-2">
                                    <i class="bi bi-file-earmark me-1"></i>
                                    Original
                                </div>

                                <h5 class="mb-1">
                                    ${formatBytes(originalSize)}
                                </h5>

                                <div class="text-muted small">
                                    ${originalDimensions}
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-2 d-flex align-items-center justify-content-center">
                            <i class="bi bi-arrow-right fs-3 text-muted d-none d-md-block"></i>
                            <i class="bi bi-arrow-down fs-3 text-muted d-md-none"></i>
                        </div>

                        <div class="col-12 col-md-5">
                            <div class="rounded-3 bg-success-subtle p-3 h-100">
                                <div class="text-success small mb-2">
                                    <i class="bi bi-file-earmark-check me-1"></i>
                                    Optimized
                                </div>

                                <h5 class="mb-1 text-success">
                                    ${formatBytes(outputSize)}
                                </h5>

                                <div class="text-muted small">
                                    ${outputDimensions}
                                    · ${escapeHtml(item.output.format.toUpperCase())}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap justify-content-between gap-2 border-top mt-3 pt-3">
                        <span class="text-muted small text-break">
                            Output: ${escapeHtml(item.output.filename)}
                        </span>

                        <span class="text-success small fw-semibold">
                            ${formatBytes(savedBytes)} saved
                        </span>
                    </div>
                </div>
            </div>
        `;
    }

    function renderFailedItem(item) {
        return `
            <div class="card mb-3 border-danger">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <h6 class="mb-1">
                            ${escapeHtml(item.original.filename)}
                        </h6>

                        <span class="badge text-bg-danger">
                            Failed
                        </span>
                    </div>

                    <div class="text-danger small mt-2">
                        ${escapeHtml(
                            item.error_message ||
                            'Unable to process this image.'
                        )}
                    </div>
                </div>
            </div>
        `;
    }

    /*
     * Add files
     */
    function addFiles(files) {
        if (!files.length) {
            return;
        }

        const validFiles = [];

        for (const file of files) {
            if (!ALLOWED_TYPES.includes(file.type)) {
                showError(
                    `${file.name}: unsupported file type.`
                );

                continue;
            }

            if (file.size > MAX_FILE_SIZE) {
                showError(
                    `${file.name}: maximum file size is 10 MB.`
                );

                continue;
            }

            if (isDuplicate(file)) {
                showError(
                    `${file.name}: this image has already been selected.`
                );

                continue;
            }

            validFiles.push(file);
        }

        if (!validFiles.length) {
            return;
        }

        const remainingSlots =
            MAX_FILES - selectedFiles.length;

        if (validFiles.length > remainingSlots) {
            showError(
                `You can select a maximum of ${MAX_FILES} images.`
            );

            validFiles.splice(remainingSlots);
        }

        selectedFiles.push(...validFiles);

        renderSelectedFiles();
    }

    /*
     * Duplicate detection
     */
    function isDuplicate(file) {
        return selectedFiles.some(existingFile =>
            existingFile.name === file.name &&
            existingFile.size === file.size &&
            existingFile.lastModified === file.lastModified
        );
    }

    /*
     * Render selected images
     */
    function renderSelectedFiles() {
        $selectedImagesList.empty();

        selectedFiles.forEach((file, index) => {
            const objectUrl = URL.createObjectURL(file);

            const size = formatBytes(file.size);

            const $item = $(`
                <div class="list-group-item px-0 py-3">
                    <div class="d-flex align-items-center gap-3">

                        <div class="flex-shrink-0">
                            <img
                                src="${objectUrl}"
                                alt=""
                                class="rounded border"
                                style="
                                    width: 64px;
                                    height: 64px;
                                    object-fit: cover;
                                "
                            >
                        </div>

                        <div class="flex-grow-1 min-width-0">

                            <div
                                class="fw-semibold text-truncate"
                                title="${escapeHtml(file.name)}"
                            >
                                ${escapeHtml(file.name)}
                            </div>

                            <div class="small text-muted">
                                ${size}
                            </div>

                        </div>

                        <button
                            type="button"
                            class="btn btn-sm btn-outline-danger remove-image-btn"
                            data-index="${index}"
                            title="Remove image"
                        >
                            <i class="bi bi-x-lg"></i>
                        </button>

                    </div>
                </div>
            `);

            $item.find('img').on('load', function () {
                URL.revokeObjectURL(objectUrl);
            });

            $selectedImagesList.append($item);
        });

        updateUI();
    }

    /*
     * Remove individual image
     */
    $selectedImagesList.on(
        'click',
        '.remove-image-btn',
        function () {
            const index = Number($(this).data('index'));

            if (
                Number.isInteger(index) &&
                index >= 0 &&
                index < selectedFiles.length
            ) {
                selectedFiles.splice(index, 1);

                renderSelectedFiles();
            }
        }
    );

    /*
     * Clear all
     */
    $clearImagesBtn.on('click', function () {
        selectedFiles = [];

        renderSelectedFiles();
    });

    /*
     * Quality slider
     */
    $quality.on('input', function () {
        $qualityValue.text($(this).val());
    });

    /*
     * Saved optimization presets
     */
    let imagePresets = [];

    const presetControls = `
        <div id="imagePresetControls" class="card border-0 bg-body-tertiary mb-4">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h6 class="mb-1">
                            <i class="bi bi-sliders me-2"></i>Optimization Presets
                        </h6>
                        <div class="small text-muted">
                            Apply a saved configuration or save your current settings.
                        </div>
                    </div>

                    <button type="button" id="savePresetBtn"
                        class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-bookmark-plus me-1"></i>
                        Save Current Settings
                    </button>
                </div>

                <label for="imagePresetSelect" class="form-label small">
                    Saved preset
                </label>

                <select id="imagePresetSelect" class="form-select">
                    <option value="">Choose a preset...</option>
                </select>

                <div class="d-flex flex-wrap gap-2 mt-3">
                    <button type="button" id="updatePresetBtn"
                        class="btn btn-sm btn-outline-secondary" disabled>
                        <i class="bi bi-arrow-repeat me-1"></i>
                        Update Selected
                    </button>

                    <button type="button" id="deletePresetBtn"
                        class="btn btn-sm btn-outline-danger" disabled>
                        <i class="bi bi-trash me-1"></i>
                        Delete Selected
                    </button>
                </div>

                <div id="presetFeedback" class="small mt-2"
                    role="status" aria-live="polite"></div>
            </div>
        </div>
    `;

    $settingsSection.prepend(presetControls);

    function loadImagePresets(selectedPresetId = null) {
        setPresetFeedback('Loading presets...', 'text-muted');

        getImagePresets()
            .done(function (response) {
                imagePresets = Array.isArray(response.data)
                    ? response.data
                    : [];

                renderPresetOptions(selectedPresetId);

                setPresetFeedback(
                    imagePresets.length
                        ? `${imagePresets.length} preset${imagePresets.length === 1 ? '' : 's'} available.`
                        : 'No presets found yet. Save your current settings to create one.',
                    'text-muted'
                );
            })
            .fail(function (xhr) {
                console.error('Unable to load image presets:', xhr);

                imagePresets = [];
                renderPresetOptions();

                setPresetFeedback(
                    getApiErrorMessage(
                        xhr,
                        'Unable to load presets. Please check that you are signed in.'
                    ),
                    'text-danger'
                );
            });
    }

    function renderPresetOptions(selectedPresetId = null) {
        const $select = $('#imagePresetSelect');

        $select.empty().append(
            '<option value="">Choose a preset...</option>'
        );

        imagePresets.forEach(function (preset) {
            const label = `${preset.name} — ${
                preset.is_system ? 'System' : 'Custom'
            }`;

            $select.append(
                $('<option></option>')
                    .val(String(preset.id))
                    .text(label)
            );
        });

        if (selectedPresetId !== null) {
            $select.val(String(selectedPresetId));
        }

        updatePresetActionButtons();
    }

    function getSelectedPreset() {
        const selectedId = $('#imagePresetSelect').val();

        if (!selectedId) {
            return null;
        }

        return imagePresets.find(
            preset => String(preset.id) === String(selectedId)
        ) || null;
    }

    function updatePresetActionButtons() {
        const preset = getSelectedPreset();
        const canManage = Boolean(preset && !preset.is_system);

        $('#updatePresetBtn').prop('disabled', !canManage);
        $('#deletePresetBtn').prop('disabled', !canManage);
    }

    function applyPreset(preset) {
        const settings = preset && preset.settings
            ? preset.settings
            : {};

        $('#width').val(settings.width ?? '');
        $('#height').val(settings.height ?? '');

        if (settings.resize_mode) {
            $('#resizeMode').val(settings.resize_mode);
        }

        if (settings.output_format) {
            $('#outputFormat').val(settings.output_format);
        }

        if (
            settings.quality !== null &&
            settings.quality !== undefined
        ) {
            $('#quality')
                .val(settings.quality)
                .trigger('input');
        }

        setPresetFeedback(
            `Applied "${preset.name}". Review the settings before optimizing.`,
            'text-success'
        );
    }

    function collectCurrentPresetSettings() {
        return {
            width: $('#width').val() === ''
                ? null
                : Number($('#width').val()),

            height: $('#height').val() === ''
                ? null
                : Number($('#height').val()),

            resize_mode: $('#resizeMode').val(),
            output_format: $('#outputFormat').val(),
            quality: Number($('#quality').val()),
        };
    }

    function setPresetFeedback(
        message,
        className = 'text-muted'
    ) {
        $('#presetFeedback')
            .removeClass('text-muted text-success text-danger')
            .addClass(className)
            .text(message);
    }

    function getApiErrorMessage(xhr, fallback) {
        if (xhr && xhr.responseJSON) {
            if (xhr.responseJSON.message) {
                return xhr.responseJSON.message;
            }

            if (xhr.responseJSON.errors) {
                const firstError = Object.values(
                    xhr.responseJSON.errors
                ).flat()[0];

                if (firstError) {
                    return firstError;
                }
            }
        }

        return fallback;
    }

    function openPresetModal(mode, preset = null) {
        $('#imagePresetModal').remove();

        const isUpdate = mode === 'update';

        const modalMarkup = `
            <div class="modal fade" id="imagePresetModal"
                tabindex="-1"
                aria-labelledby="imagePresetModalTitle"
                aria-hidden="true">

                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="imagePresetModalTitle">
                                ${isUpdate
                                    ? 'Update Preset'
                                    : 'Save Current Settings'}
                            </h5>

                            <button type="button" class="btn-close"
                                data-bs-dismiss="modal"
                                aria-label="Close"></button>
                        </div>

                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="imagePresetName"
                                    class="form-label">
                                    Preset name
                                </label>

                                <input type="text"
                                    class="form-control"
                                    id="imagePresetName"
                                    maxlength="255"
                                    required>
                            </div>

                            <div class="mb-0">
                                <label for="imagePresetDescription"
                                    class="form-label">
                                    Description
                                    <span class="text-muted">(optional)</span>
                                </label>

                                <textarea class="form-control"
                                    id="imagePresetDescription"
                                    rows="3"
                                    maxlength="2000"></textarea>
                            </div>

                            <div id="imagePresetModalError"
                                class="small text-danger mt-3"
                                role="alert"></div>

                            <p class="small text-muted mt-3 mb-0">
                                This saves the current dimensions, resize
                                mode, output format, and quality.
                            </p>
                        </div>

                        <div class="modal-footer">
                            <button type="button"
                                class="btn btn-outline-secondary"
                                data-bs-dismiss="modal">
                                Cancel
                            </button>

                            <button type="button"
                                class="btn btn-primary"
                                id="confirmSavePresetBtn">
                                ${isUpdate ? 'Update Preset' : 'Save Preset'}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        $('body').append(modalMarkup);

        if (preset) {
            $('#imagePresetName').val(preset.name || '');
            $('#imagePresetDescription').val(
                preset.description || ''
            );
        }

        const modalElement = document.getElementById(
            'imagePresetModal'
        );

        if (!window.bootstrap || !window.bootstrap.Modal) {
            setPresetFeedback(
                'Bootstrap modal support is unavailable. Check that Bootstrap JavaScript is loaded.',
                'text-danger'
            );

            $('#imagePresetModal').remove();
            return;
        }

        const modal = new window.bootstrap.Modal(modalElement);

        modal.show();

        $('#confirmSavePresetBtn').on('click', function () {
            const name = $('#imagePresetName').val().trim();
            const description = $(
                '#imagePresetDescription'
            ).val().trim();

            if (!name) {
                $('#imagePresetModalError').text(
                    'Please enter a preset name.'
                );

                $('#imagePresetName').trigger('focus');
                return;
            }

            const payload = {
                name,
                description: description || null,
                ...collectCurrentPresetSettings(),
            };

            const $button = $(this);

            $button
                .prop('disabled', true)
                .text(isUpdate ? 'Updating...' : 'Saving...');

            $('#imagePresetModalError').text('');

            const request = isUpdate
                ? updateImagePreset(preset.id, payload)
                : createImagePreset(payload);

            request
                .done(function (response) {
                    const savedPreset = response.data || response;

                    modal.hide();

                    loadImagePresets(savedPreset.id);

                    setPresetFeedback(
                        isUpdate
                            ? 'Preset updated successfully.'
                            : 'Preset saved successfully.',
                        'text-success'
                    );
                })
                .fail(function (xhr) {
                    console.error(
                        'Unable to save image preset:',
                        xhr
                    );

                    $('#imagePresetModalError').text(
                        getApiErrorMessage(
                            xhr,
                            'Unable to save this preset. Please try again.'
                        )
                    );
                })
                .always(function () {
                    $button
                        .prop('disabled', false)
                        .text(
                            isUpdate
                                ? 'Update Preset'
                                : 'Save Preset'
                        );
                });
        });

        modalElement.addEventListener(
            'hidden.bs.modal',
            function () {
                modal.dispose();
                modalElement.remove();
            },
            { once: true }
        );
    }

    $('#imagePresetSelect').on('change', function () {
        const preset = getSelectedPreset();

        if (preset) {
            applyPreset(preset);
        } else {
            setPresetFeedback(
                'Choose a preset to apply its settings.',
                'text-muted'
            );
        }

        updatePresetActionButtons();
    });

    $('#savePresetBtn').on('click', function () {
        openPresetModal('create');
    });

    $('#updatePresetBtn').on('click', function () {
        const preset = getSelectedPreset();

        if (!preset || preset.is_system) {
            return;
        }

        openPresetModal('update', preset);
    });

    $('#deletePresetBtn').on('click', function () {
        const preset = getSelectedPreset();

        if (!preset || preset.is_system) {
            return;
        }

        if (!window.confirm(
            `Delete the custom preset "${preset.name}"? This cannot be undone.`
        )) {
            return;
        }

        const $button = $(this);

        $button.prop('disabled', true);

        deleteImagePreset(preset.id)
            .done(function () {
                setPresetFeedback(
                    `Deleted "${preset.name}".`,
                    'text-success'
                );

                loadImagePresets();
            })
            .fail(function (xhr) {
                console.error(
                    'Unable to delete image preset:',
                    xhr
                );

                setPresetFeedback(
                    getApiErrorMessage(
                        xhr,
                        'Unable to delete this preset. Please try again.'
                    ),
                    'text-danger'
                );
            })
            .always(function () {
                updatePresetActionButtons();
            });
    });

    loadImagePresets();

    /*
     * Update UI visibility
     */
    function updateUI() {
        const hasFiles = selectedFiles.length > 0;

        $selectedImagesSection.toggleClass(
            'd-none',
            !hasFiles
        );

        $settingsSection.toggleClass(
            'd-none',
            !hasFiles
        );

        $optimizeSection.toggleClass(
            'd-none',
            !hasFiles
        );

        $selectedCount.text(selectedFiles.length);
    }

    /*
     * Error message
     */
    function showError(message) {
        const $alert = $(`
            <div
                class="alert alert-warning alert-dismissible fade show mt-3"
                role="alert"
            >
                ${escapeHtml(message)}

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Close"
                ></button>
            </div>
        `);

        $dropzone.closest('.card').after($alert);

        setTimeout(function () {
            $alert.alert('close');
        }, 5000);
    }

    /*
     * Format bytes
     */
    function formatBytes(bytes) {
        if (bytes === 0) {
            return '0 Bytes';
        }

        const units = [
            'Bytes',
            'KB',
            'MB',
            'GB',
        ];

        const exponent = Math.floor(
            Math.log(bytes) / Math.log(1024)
        );

        const unitIndex = Math.min(
            exponent,
            units.length - 1
        );

        const value =
            bytes / Math.pow(1024, unitIndex);

        return `${value.toFixed(
            unitIndex === 0 ? 0 : 2
        )} ${units[unitIndex]}`;
    }

    /*
     * Escape user-controlled text before inserting into HTML.
     */
    function escapeHtml(value) {
        return $('<div>')
            .text(value)
            .html();
    }

    /*
     * Initial state
     */
    updateUI();
});