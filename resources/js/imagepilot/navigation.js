import $ from 'jquery';
import { getStoredUser, logout } from './api';

$(function () {
    const $guestNavigation = $('#guestNavigation');
    const $authenticatedNavigation = $('#authenticatedNavigation');
    const $batchesNavigation = $('#authenticatedBatchesNavigation');
    const $logoutButton = $('#navbarLogoutButton');

    function updateNavigation() {
        const token = localStorage.getItem('imagepilot_token');
        const user = getStoredUser();

        if (token && user) {
            $('#navbarUserName').text(user.name || 'Account');

            $guestNavigation.addClass('d-none');
            $authenticatedNavigation.removeClass('d-none');
            $batchesNavigation.removeClass('d-none');
        } else {
            $guestNavigation.removeClass('d-none');
            $authenticatedNavigation.addClass('d-none');
            $batchesNavigation.addClass('d-none');
        }
    }

    updateNavigation();

    $logoutButton.on('click', function () {
        $logoutButton.prop('disabled', true);

        logout()
            .always(function () {
                window.location.href = '/';
            });
    });
});