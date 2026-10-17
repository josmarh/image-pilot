import $ from 'jquery';
import { logout, getStoredUser } from './api';

$(function () {
    const $logoutButton = $('#logoutButton');

    if (!$logoutButton.length) {
        return;
    }

    const token = localStorage.getItem('imagepilot_token');
    const user = getStoredUser();

    if (!token || !user) {
        window.location.href = '/login';
        return;
    }

    $('#accountName').text(user.name || '—');
    $('#accountEmail').text(user.email || '—');

    $logoutButton.on('click', function () {
        const $buttonText = $('#logoutButtonText');
        const $spinner = $('#logoutSpinner');
        const $error = $('#accountError');

        $error.addClass('d-none').text('');
        $logoutButton.prop('disabled', true);
        $buttonText.text('Signing out...');
        $spinner.removeClass('d-none');

        logout()
            .done(function () {
                window.location.href = '/login';
            })
            .fail(function (xhr) {
                // A 401 means the token is already invalid.
                if (xhr.status === 401) {
                    window.location.href = '/login';
                    return;
                }

                $error
                    .text('Unable to contact the server. Please try again.')
                    .removeClass('d-none');
            })
            .always(function () {
                $logoutButton.prop('disabled', false);
                $buttonText.text('Sign out');
                $spinner.addClass('d-none');
            });
    });
});