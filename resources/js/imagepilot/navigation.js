import $ from 'jquery';
import { getStoredUser } from './api';

$(function () {
    const $guestNavigation = $('#guestNavigation');
    const $authenticatedNavigation = $('#authenticatedNavigation');

    if (
        !$guestNavigation.length ||
        !$authenticatedNavigation.length
    ) {
        return;
    }

    const token = localStorage.getItem('imagepilot_token');
    const user = getStoredUser();

    if (token && user) {
        $('#navbarUserName').text(user.name || 'Account');

        $guestNavigation.addClass('d-none');
        $authenticatedNavigation.removeClass('d-none');
    } else {
        $guestNavigation.removeClass('d-none');
        $authenticatedNavigation.addClass('d-none');
    }
});