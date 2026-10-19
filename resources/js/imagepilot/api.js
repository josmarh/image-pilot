
import $ from 'jquery';

const API_BASE_URL = '/api';
const USER_STORAGE_KEY = 'imagepilot_user';
const GUEST_BATCH_TOKEN_PREFIX = 'imagepilot_guest_batch_token_';

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

function getGuestBatchToken(batchReference) {
    return localStorage.getItem(
        `${GUEST_BATCH_TOKEN_PREFIX}${batchReference}`
    );
}

function storeGuestBatchToken(batchReference, token) {
    localStorage.setItem(
        `${GUEST_BATCH_TOKEN_PREFIX}${batchReference}`,
        token
    );
}

function getBatchHeaders(batchReference) {
    const headers = getHeaders();
    const guestToken = getGuestBatchToken(batchReference);

    if (guestToken) {
        headers['X-Guest-Batch-Token'] = guestToken;
    }

    return headers;
}

export function getStoredUser() {
    const user = localStorage.getItem(USER_STORAGE_KEY);

    if (!user) {
        return null;
    }

    try {
        return JSON.parse(user);
    } catch {
        localStorage.removeItem(USER_STORAGE_KEY);

        return null;
    }
}

export function login(email, password) {
    return $.ajax({
        url: `${API_BASE_URL}/auth/login`,
        method: 'POST',
        headers: {
            Accept: 'application/json',
        },
        contentType: 'application/json',
        data: JSON.stringify({
            email,
            password,
        }),
    }).then(response => {
        const token = response?.data?.token;
        const user = response?.data?.user;

        if (!token || !user) {
            throw new Error(
                'The login response did not contain valid authentication data.'
            );
        }

        localStorage.setItem('imagepilot_token', token);
        localStorage.setItem(
            USER_STORAGE_KEY,
            JSON.stringify(user)
        );

        return response;
    });
}

export function logout() {
    const token = getToken();

    if (!token) {
        clearAuthentication();

        return $.Deferred().resolve().promise();
    }

    return $.ajax({
        url: `${API_BASE_URL}/auth/logout`,
        method: 'POST',
        headers: {
            Authorization: `Bearer ${token}`,
            Accept: 'application/json',
        },
    }).always(() => {
        clearAuthentication();
    });
}

export function clearAuthentication() {
    localStorage.removeItem('imagepilot_token');
    localStorage.removeItem(USER_STORAGE_KEY);
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
    }).then(response => {
        const batch = response?.data;

        if (batch?.batch_reference && batch?.guest_access_token) {
            storeGuestBatchToken(
                batch.batch_reference,
                batch.guest_access_token
            );
        }

        return response;
    });
}

export function getBatch(batchReference) {
    return $.ajax({
        url: `${API_BASE_URL}/image-processing/batches/${encodeURIComponent(batchReference)}`,
        method: 'GET',
        headers: getBatchHeaders(batchReference),
    });
}

export function downloadBatch(batchReference) {
    return $.ajax({
        url: `${API_BASE_URL}/image-processing/batches/${encodeURIComponent(batchReference)}/download`,
        method: 'GET',
        headers: getBatchHeaders(batchReference),
        xhrFields: {
            responseType: 'blob',
        },
    });
}

export function getBatchItems(batchReference) {
    return $.ajax({
        url: `${API_BASE_URL}/image-processing/batches/${encodeURIComponent(batchReference)}/items`,
        method: 'GET',
        headers: getBatchHeaders(batchReference),
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
            ...getBatchHeaders(batchReference),
            Accept: 'image/avif,image/webp,image/png,image/jpeg,*/*',
        },
        xhrFields: {
            responseType: 'blob',
        },
    });
}

// Handle expired or invalid authentication tokens globally.
$(document).on(
    'ajaxError.imagepilotAuth',
    function (_event, xhr, settings) {
        if (xhr.status !== 401) {
            return;
        }

        const requestUrl = new URL(
            settings.url,
            window.location.origin
        );

        if (
            requestUrl.origin !== window.location.origin ||
            !requestUrl.pathname.startsWith('/api/')
        ) {
            return;
        }

        if (requestUrl.pathname === '/api/auth/login') {
            return;
        }

        // Public, batch-specific endpoints may use guest access tokens.
        // Their authentication errors should not clear the user's account session.
        if (
            /^\/api\/image-processing\/batches\/[^/]+(?:\/|$)/.test(
                requestUrl.pathname
            )
        ) {
            return;
        }

        if (!getToken()) {
            return;
        }

        clearAuthentication();

        if (window.location.pathname !== '/login') {
            window.location.assign('/login');
        }
    }
);