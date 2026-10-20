import $ from 'jquery';
import { login, register } from './api';

$(function () {
    const $loginForm = $('#loginForm');
    const $registerForm = $('#registerForm');

    if (!$loginForm.length && !$registerForm.length) {
        return;
    }

    // Redirect users who are already logged in.
    if (localStorage.getItem('imagepilot_token')) {
        window.location.href = '/account';
        return;
    }

    function getErrorMessage(xhr, fallback) {
        const response = xhr.responseJSON;

        if (response?.errors) {
            const firstError = Object.values(response.errors)
                .flat()
                .find(message => typeof message === 'string');

            if (firstError) {
                return firstError;
            }
        }

        return response?.message || fallback;
    }

    function setLoading($button, $buttonText, $spinner, loading, labels) {
        $button.prop('disabled', loading);
        $buttonText.text(loading ? labels.loading : labels.idle);
        $spinner.toggleClass('d-none', !loading);
    }

    function showError($error, message) {
        $error.text(message).removeClass('d-none');
    }

    if ($loginForm.length) {
        const $button = $('#loginButton');
        const $buttonText = $('#loginButtonText');
        const $spinner = $('#loginSpinner');
        const $error = $('#loginError');

        $loginForm.on('submit', function (event) {
            event.preventDefault();

            $error.addClass('d-none').text('');

            setLoading(
                $button,
                $buttonText,
                $spinner,
                true,
                { loading: 'Signing in...', idle: 'Sign in' }
            );

            const email = String($('#email').val()).trim();
            const password = $('#password').val();

            login(email, password)
                .done(function () {
                    window.location.href = '/account';
                })
                .fail(function (xhr) {
                    showError(
                        $error,
                        getErrorMessage(
                            xhr,
                            'Unable to sign in. Please try again.'
                        )
                    );
                })
                .always(function () {
                    setLoading(
                        $button,
                        $buttonText,
                        $spinner,
                        false,
                        { loading: 'Signing in...', idle: 'Sign in' }
                    );
                });
        });
    }

    if ($registerForm.length) {
        const $button = $('#registerButton');
        const $buttonText = $('#registerButtonText');
        const $spinner = $('#registerSpinner');
        const $error = $('#registerError');

        $registerForm.on('submit', function (event) {
            event.preventDefault();

            $error.addClass('d-none').text('');

            const name = String($('#name').val()).trim();
            const email = String($('#email').val()).trim();
            const password = $('#password').val();
            const passwordConfirmation = $(
                '#password_confirmation'
            ).val();

            if (password !== passwordConfirmation) {
                showError($error, 'The passwords do not match.');
                return;
            }

            setLoading(
                $button,
                $buttonText,
                $spinner,
                true,
                {
                    loading: 'Creating account...',
                    idle: 'Create account',
                }
            );

            register(name, email, password, passwordConfirmation)
                .done(function () {
                    window.location.href = '/account';
                })
                .fail(function (xhr) {
                    showError(
                        $error,
                        getErrorMessage(
                            xhr,
                            'Unable to create your account. Please try again.'
                        )
                    );
                })
                .always(function () {
                    setLoading(
                        $button,
                        $buttonText,
                        $spinner,
                        false,
                        {
                            loading: 'Creating account...',
                            idle: 'Create account',
                        }
                    );
                });
        });
    }
});