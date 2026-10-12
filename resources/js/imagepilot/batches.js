
import $ from 'jquery';

import {
    getBatches,
    getBatch,
    getBatchItems,
    downloadBatch,
} from './api';

$(function () {
    const $page = $('#batchesList');

    // Do not initialize batch-history behavior on other pages.
    if (!$('#batchesTableBody').length) {
        return;
    }

    let currentPage = 1;
    let lastPage = 1;
    let activeBatchReference = null;
    let requestVersion = 0;

    const $alert = $('#batchesAlert');
    const $loading = $('#batchesLoading');
    const $empty = $('#batchesEmpty');
    const $details = $('#batchDetailsSection');

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, character => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        })[character]);
    }

    function formatBytes(bytes) {
        const value = Number(bytes) || 0;

        if (value < 1024) {
            return `${value} B`;
        }

        const units = ['KB', 'MB', 'GB', 'TB'];
        let size = value / 1024;
        let unitIndex = 0;

        while (size >= 1024 && unitIndex < units.length - 1) {
            size /= 1024;
            unitIndex++;
        }

        return `${size.toFixed(2)} ${units[unitIndex]}`;
    }

    function formatDate(value) {
        if (!value) {
            return '—';
        }

        const date = new Date(value);

        if (Number.isNaN(date.getTime())) {
            return '—';
        }

        return date.toLocaleString();
    }

    function titleCase(value) {
        return String(value || 'unknown')
            .replace(/[_-]+/g, ' ')
            .replace(/\b\w/g, character => character.toUpperCase());
    }

    function statusBadge(status) {
        const classes = {
            completed: 'text-bg-success',
            partial: 'text-bg-warning',
            failed: 'text-bg-danger',
            processing: 'text-bg-primary',
            pending: 'text-bg-secondary',
        };

        const normalized = String(status || 'unknown').toLowerCase();
        const badgeClass = classes[normalized] || 'text-bg-secondary';

        return `<span class="badge ${badgeClass}">${escapeHtml(titleCase(normalized))}</span>`;
    }

    function showAlert(message, type = 'danger', target = $alert) {
        target
            .removeClass('d-none alert-success alert-danger alert-warning alert-info')
            .addClass(`alert-${type}`)
            .text(message);
    }

    function hideAlert(target = $alert) {
        target.addClass('d-none').text('');
    }

    function responseData(response) {
        // Supports both a normal resource collection and an already-unwrapped response.
        return response?.data ?? response;
    }

    function pick(object, keys, fallback = null) {
        for (const key of keys) {
            if (object?.[key] !== undefined && object[key] !== null) {
                return object[key];
            }
        }

        return fallback;
    }

    function canDownload(status) {
        return ['completed', 'partial'].includes(
            String(status || '').toLowerCase()
        );
    }

    function renderSummary(batches) {
        const completed = batches.filter(batch =>
            String(batch.status).toLowerCase() === 'completed'
        ).length;

        const saved = batches.reduce((total, batch) => {
            const size = batch.size || {};
            return total + (Number(size.saved_bytes) || 0);
        }, 0);

        $('#batchesCount').text(batches.length);
        $('#completedBatchesCount').text(completed);
        $('#batchesSaved').text(formatBytes(saved));
    }

    function renderBatches(batches) {
        renderSummary(batches);

        if (!batches.length) {
            $page.addClass('d-none');
            $empty.removeClass('d-none');
            return;
        }

        $empty.addClass('d-none');
        $page.removeClass('d-none');

        const rows = batches.map(batch => {
            const reference = String(batch.batch_reference || '');
            const progress = batch.progress || {};
            const size = batch.size || {};

            const total = Number(progress.total) || 0;
            const completed = Number(progress.completed) || 0;
            const failed = Number(progress.failed) || 0;
            const percentage = Math.max(
                0,
                Math.min(100, Number(progress.percentage) || 0)
            );

            const savedBytes = Number(size.saved_bytes) || 0;
            const savedPercentage = Number(size.saved_percentage) || 0;
            const status = String(batch.status || 'unknown').toLowerCase();

            const downloadButton = canDownload(status)
                ? `<button type="button"
                           class="btn btn-sm btn-outline-success js-download-batch"
                           data-reference="${escapeHtml(reference)}"
                           title="Download ZIP"
                           aria-label="Download ZIP">
                       <i class="bi bi-download"></i>
                   </button>`
                : '';

            return `
                <tr>
                    <td>
                        <div class="fw-semibold">${escapeHtml(reference.slice(0, 8) || 'Batch')}</div>
                        <small class="text-muted">${escapeHtml(total)} image(s)</small>
                    </td>
                    <td>${statusBadge(status)}</td>
                    <td style="min-width: 150px;">
                        <div class="d-flex justify-content-between small mb-1">
                            <span>${completed + failed}/${total}</span>
                            <span>${percentage.toFixed(0)}%</span>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar"
                                 role="progressbar"
                                 style="width: ${percentage}%"
                                 aria-valuenow="${percentage}"
                                 aria-valuemin="0"
                                 aria-valuemax="100"></div>
                        </div>
                        ${failed > 0
                            ? `<small class="text-danger">${failed} failed</small>`
                            : ''}
                    </td>
                    <td>
                        <div class="fw-semibold">${formatBytes(savedBytes)}</div>
                        <small class="text-muted">${savedPercentage.toFixed(1)}% saved</small>
                    </td>
                    <td><small>${escapeHtml(formatDate(batch.created_at))}</small></td>
                    <td class="text-end text-nowrap">
                        <button type="button"
                                class="btn btn-sm btn-outline-primary js-view-batch"
                                data-reference="${escapeHtml(reference)}">
                            View
                        </button>
                        ${downloadButton}
                    </td>
                </tr>
            `;
        });

        $('#batchesTableBody').html(rows.join(''));
    }

    function renderPagination(meta) {
        currentPage = Number(meta?.current_page) || 1;
        lastPage = Number(meta?.last_page) || 1;

        $('#batchesPrevBtn').prop('disabled', currentPage <= 1);
        $('#batchesNextBtn').prop('disabled', currentPage >= lastPage);

        const from = Number(meta?.from) || 0;
        const to = Number(meta?.to) || 0;
        const total = Number(meta?.total) || 0;

        $('#batchesPaginationInfo').text(
            total ? `Showing ${from}-${to} of ${total} batches` : 'No batches found'
        );
    }

    let batchesRequestInFlight = false;

    function loadBatches(page = 1, { silent = false } = {}) {
        if (batchesRequestInFlight) {
            return;
        }

        batchesRequestInFlight = true;

        const version = ++requestVersion;

        if (!silent) {
            hideAlert();
            $empty.addClass('d-none');
            $page.addClass('d-none');
            $loading.removeClass('d-none');
        }

        getBatches(page)
            .done(response => {
                if (version !== requestVersion) {
                    return;
                }

                const batches = responseData(response);
                const list = Array.isArray(batches) ? batches : [];

                renderBatches(list);
                renderPagination(response?.meta || {});

                // Keep an open details panel up to date too.
                if (activeBatchReference) {
                    refreshOpenBatchDetails();
                }
            })
            .fail(xhr => {
                if (version !== requestVersion) {
                    return;
                }

                // Avoid interrupting the user with transient polling errors.
                if (!silent) {
                    if (xhr.status === 401 || xhr.status === 403) {
                        showAlert(
                            'Please sign in again to view your batch history.'
                        );
                    } else {
                        showAlert(
                            xhr.responseJSON?.message ||
                            'Unable to load your batch history. Please try again.'
                        );
                    }

                    $('#batchesPaginationInfo').text('');
                }
            })
            .always(() => {
                batchesRequestInFlight = false;

                if (version === requestVersion && !silent) {
                    $loading.addClass('d-none');
                }
            });
    }

    
    function renderBatchItems(items) {
        if (!Array.isArray(items) || !items.length) {
            $('#batchItemsTableBody').html(`
                <tr>
                    <td colspan="5" class="text-center text-muted py-4">
                        No image items were found for this batch.
                    </td>
                </tr>
            `);
            return;
        }

        const rows = items.map(item => {
            const original = item.original || {};
            const output = item.output || {};
            const status = String(item.status || 'unknown').toLowerCase();

            const originalFilename = original.filename || 'Unnamed image';
            const originalSize = Number(original.size_bytes) || 0;

            const outputSize = output.size_bytes;
            const errorMessage = item.error_message || '';

            // Prefer the output filename when available.
            const displayFilename = output.filename || originalFilename;

            const outputSizeDisplay =
                outputSize !== null && outputSize !== undefined
                    ? formatBytes(outputSize)
                    : '—';

            const messageDisplay = errorMessage
                ? `<small class="text-danger">${escapeHtml(errorMessage)}</small>`
                : '<span class="text-muted">—</span>';

            return `
                <tr>
                    <td>
                        <div class="fw-semibold text-break">
                            ${escapeHtml(displayFilename)}
                        </div>
                        ${output.filename && output.filename !== originalFilename
                            ? `<small class="text-muted text-break">Original: ${escapeHtml(originalFilename)}</small>`
                            : ''}
                    </td>

                    <td>${statusBadge(status)}</td>

                    <td>${formatBytes(originalSize)}</td>

                    <td>${outputSizeDisplay}</td>

                    <td class="text-break">
                        ${messageDisplay}
                    </td>
                </tr>
            `;
        });

        $('#batchItemsTableBody').html(rows.join(''));
    }


    function refreshOpenBatchDetails() {
        const reference = activeBatchReference;

        if (!reference) {
            return;
        }

        getBatch(reference)
            .done(response => {
                if (activeBatchReference !== reference) {
                    return;
                }

                const batch = responseData(response);
                const progress = batch.progress || {};
                const size = batch.size || {};

                const newStatus = String(batch.status || 'unknown').toLowerCase();
                const previousStatus = $('#detailStatus').attr('data-status');

                $('#detailStatus')
                    .attr('data-status', newStatus)
                    .html(statusBadge(newStatus));

                $('#detailImages').text(
                    `${Number(progress.completed) || 0} completed, ` +
                    `${Number(progress.failed) || 0} failed, ` +
                    `${Number(progress.total) || 0} total`
                );

                $('#detailOriginalSize').text(
                    formatBytes(size.original_bytes)
                );

                $('#detailOptimizedSize').text(
                    formatBytes(size.optimized_bytes)
                );

                if (canDownload(newStatus)) {
                    $('#detailDownloadBtn').removeClass('d-none');
                } else {
                    $('#detailDownloadBtn').addClass('d-none');
                }

                // Refresh individual image results when the batch status changes.
                if (previousStatus && previousStatus !== newStatus) {
                    getBatchItems(reference)
                        .done(itemsResponse => {
                            if (activeBatchReference === reference) {
                                renderBatchItems(responseData(itemsResponse));
                            }
                        });
                }
            });
    }


    function openBatch(reference) {
        activeBatchReference = reference;

        hideAlert($('#batchDetailsAlert'));
        $('#batchDetailsReference').text(reference);
        $('#batchDetailsContent').addClass('d-none');
        $('#batchDetailsLoading').removeClass('d-none');
        $('#batchItemsLoading').removeClass('d-none');
        $('#batchItemsTableBody').empty();
        $('#detailDownloadBtn').addClass('d-none');

        $details.removeClass('d-none');

        getBatch(reference)
            .done(response => {
                if (activeBatchReference !== reference) {
                    return;
                }

                const batch = responseData(response);
                const progress = batch.progress || {};
                const size = batch.size || {};

                const initialStatus = String(batch.status || 'unknown').toLowerCase();
                $('#detailStatus')
                    .attr('data-status', initialStatus)
                    .html(statusBadge(initialStatus));

                $('#detailImages').text(
                    `${Number(progress.completed) || 0} completed, ` +
                    `${Number(progress.failed) || 0} failed, ` +
                    `${Number(progress.total) || 0} total`
                );
                $('#detailOriginalSize').text(formatBytes(size.original_bytes));
                $('#detailOptimizedSize').text(formatBytes(size.optimized_bytes));

                if (canDownload(batch.status)) {
                    $('#detailDownloadBtn').removeClass('d-none');
                }

                $('#batchDetailsContent').removeClass('d-none');
            })
            .fail(xhr => {
                if (activeBatchReference === reference) {
                    showAlert(
                        xhr.responseJSON?.message || 'Unable to load this batch.',
                        'danger',
                        $('#batchDetailsAlert')
                    );
                }
            })
            .always(() => {
                if (activeBatchReference === reference) {
                    $('#batchDetailsLoading').addClass('d-none');
                }
            });

        getBatchItems(reference)
            .done(response => {
                if (activeBatchReference !== reference) {
                    return;
                }

                renderBatchItems(responseData(response));
            })
            .fail(xhr => {
                if (activeBatchReference === reference) {
                    showAlert(
                        xhr.responseJSON?.message || 'Unable to load image details.',
                        'danger',
                        $('#batchDetailsAlert')
                    );
                }
            })
            .always(() => {
                if (activeBatchReference === reference) {
                    $('#batchItemsLoading').addClass('d-none');
                }
            });

        if (typeof $details[0]?.scrollIntoView === 'function') {
            $details[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    function saveBlob(blob, filename) {
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');

        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        link.remove();

        URL.revokeObjectURL(url);
    }

    function handleDownload(reference, $button) {
        const originalHtml = $button.html();

        $button.prop('disabled', true).html(
            '<span class="spinner-border spinner-border-sm" role="status"></span>'
        );

        downloadBatch(reference)
            .done(blob => {
                if (!(blob instanceof Blob) || blob.size === 0) {
                    showAlert('The download returned an empty file.');
                    return;
                }

                saveBlob(blob, `imagepilot-${reference}.zip`);
            })
            .fail(xhr => {
                let message = 'Unable to download this batch.';

                if (xhr.responseJSON?.message) {
                    message = xhr.responseJSON.message;
                } else if (xhr.status === 401 || xhr.status === 403) {
                    message = 'Please sign in again before downloading.';
                } else if (xhr.status === 409) {
                    message = 'This batch has not finished processing yet.';
                } else if (xhr.status === 404) {
                    message = 'No processed files are available for this batch.';
                }

                showAlert(message);
            })
            .always(() => {
                $button.prop('disabled', false).html(originalHtml);
            });
    }

    $('#refreshBatchesBtn').on('click', () => loadBatches(currentPage));

    $('#batchesPrevBtn').on('click', () => {
        if (currentPage > 1) {
            loadBatches(currentPage - 1);
        }
    });

    $('#batchesNextBtn').on('click', () => {
        if (currentPage < lastPage) {
            loadBatches(currentPage + 1);
        }
    });

    $('#batchesTableBody').on('click', '.js-view-batch', function () {
        openBatch($(this).data('reference'));
    });

    $('#batchesTableBody').on('click', '.js-download-batch', function () {
        handleDownload($(this).data('reference'), $(this));
    });

    $('#detailDownloadBtn').on('click', function () {
        if (activeBatchReference) {
            handleDownload(activeBatchReference, $(this));
        }
    });

    $('#closeBatchDetailsBtn').on('click', () => {
        activeBatchReference = null;
        $details.addClass('d-none');
    });

    
    loadBatches(1);

    const batchPollingTimer = window.setInterval(() => {
        if (document.visibilityState === 'visible') {
            loadBatches(currentPage, { silent: true });
        }
    }, 5000);

    // Refresh immediately when the user returns to this tab.
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            loadBatches(currentPage, { silent: true });
        }
    });

    // Stop polling when the page is unloaded.
    window.addEventListener('beforeunload', () => {
        window.clearInterval(batchPollingTimer);
    });

});
