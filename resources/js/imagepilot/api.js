
import $ from 'jquery';

const API_BASE_URL = '/api';

function getToken() {
    return localStorage.getItem('imagepilot_token');
}

function getHeaders() {
    const token = getToken();

    return token
        ? {
            Authorization: `Bearer ${token}`,
            Accept: 'application/json',
        }
        : {
            Accept: 'application/json',
        };
}

/**
 * Upload images and create a processing batch.
 */
export function createBatch(files, settings) {
    const formData = new FormData();

    files.forEach(file => {
        formData.append('images[]', file);
    });

    Object.entries(settings).forEach(([key, value]) => {
        if (value === null) {
            return;
        }

        if (typeof value === 'boolean') {
            formData.append(
                `settings[${key}]`,
                value ? '1' : '0'
            );

            return;
        }

        formData.append(`settings[${key}]`, value);
    });

    return $.ajax({
        url: `${API_BASE_URL}/image-processing/batches`,
        method: 'POST',
        headers: getHeaders(),
        data: formData,
        processData: false,
        contentType: false,
    });
}

export function getBatch(batchReference) {
    return $.ajax({
        url: `${API_BASE_URL}/image-processing/batches/${encodeURIComponent(batchReference)}`,
        method: 'GET',
        headers: getHeaders(),
    });
}

export function downloadBatch(batchReference) {
    const token = getToken();

    return $.ajax({
        url: `${API_BASE_URL}/image-processing/batches/${encodeURIComponent(batchReference)}/download`,
        method: 'GET',
        headers: token
            ? { Authorization: `Bearer ${token}` }
            : {},
        xhrFields: {
            responseType: 'blob',
        },
    });
}

export function getBatchItems(batchReference) {
    return $.ajax({
        url: `${API_BASE_URL}/image-processing/batches/${encodeURIComponent(batchReference)}/items`,
        method: 'GET',
        headers: getHeaders(),
    });
}

/**
 * Retrieve system presets and the authenticated user's custom presets.
 */
export function getImagePresets() {
    return $.ajax({
        url: `${API_BASE_URL}/image-presets`,
        method: 'GET',
        headers: getHeaders(),
    });
}

/**
 * Create a custom optimization preset.
 */
export function createImagePreset(preset) {
    return $.ajax({
        url: `${API_BASE_URL}/image-presets`,
        method: 'POST',
        headers: {
            ...getHeaders(),
            'Content-Type': 'application/json',
        },
        data: JSON.stringify(preset),
    });
}

/**
 * Update an existing custom preset.
 */
export function updateImagePreset(presetId, preset) {
    return $.ajax({
        url: `${API_BASE_URL}/image-presets/${encodeURIComponent(presetId)}`,
        method: 'PUT',
        headers: {
            ...getHeaders(),
            'Content-Type': 'application/json',
        },
        data: JSON.stringify(preset),
    });
}

/**
 * Delete a custom preset.
 */
export function deleteImagePreset(presetId) {
    return $.ajax({
        url: `${API_BASE_URL}/image-presets/${encodeURIComponent(presetId)}`,
        method: 'DELETE',
        headers: getHeaders(),
    });
}


/**
 * Retrieve the authenticated user's paginated batch history.
 */
export function getBatches(page = 1) {
    return $.ajax({
        url: `${API_BASE_URL}/image-processing/batches`,
        method: 'GET',
        headers: getHeaders(),
        data: { page },
    });
}

export function getImagePreview(batchReference, itemId, type) {
    return $.ajax({
        url: `${API_BASE_URL}/image-processing/batches/${encodeURIComponent(batchReference)}/items/${encodeURIComponent(itemId)}/preview/${encodeURIComponent(type)}`,
        method: 'GET',
        headers: {
            ...getHeaders(),
            Accept: 'image/avif,image/webp,image/png,image/jpeg,*/*',
        },
        xhrFields: {
            responseType: 'blob',
        },
    });
}
