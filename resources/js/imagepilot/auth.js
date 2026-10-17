import $ from 'jquery';
import { login } from './api';

$(function () {
    const $loginForm = $('#loginForm');

    if (!$loginForm.length) {
        return;
    }

    // Redirect users who are already logged in.
    if (localStorage.getItem('imagepilot_token')) {
        window.location.href = '/account';
        return;
    }

    const $loginButton = $('#loginButton');
    const $loginButtonText = $('#loginButtonText');
    const $loginSpinner = $('#loginSpinner');
    const $loginError = $('#loginError');

    function showError(message) {
        $loginError
            .text(message)
            .removeClass('d-none');
    }

    function setLoading(loading) {
        $loginButton.prop('disabled', loading);

        $loginButtonText.text(
            loading ? 'Signing in...' : 'Sign in'
        );

        $loginSpinner.toggleClass('d-none', !loading);
    }

    $loginForm.on('submit', function (event) {
        event.preventDefault();

        $loginError
            .addClass('d-none')
            .text('');

        setLoading(true);

        const email = $('#email').val().trim();
        const password = $('#password').val();

        login(email, password)
            .done(function () {
                // The login helper stores the token and user.
                window.location.href = '/account';
            })
            .fail(function (xhr) {
                const message =
                    xhr.responseJSON?.message ||
                    'Unable to sign in. Please try again.';

                showError(message);
            })
            .always(function () {
                setLoading(false);
            });
    });
});