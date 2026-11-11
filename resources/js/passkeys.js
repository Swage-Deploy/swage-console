import { Passkeys, UserCancelledError } from '@laravel/passkeys';

/*
 * Passkey controls on the sign-in pages and the security page. The pages mark them up with data attributes and this
 * wires them to the browser's WebAuthn prompt through Fortify's passkey routes.
 */

const showError = (el, message) => {
    const target = document.getElementById(el.dataset.error);

    if (target) {
        target.textContent = message;
        target.hidden = ! message;
    }
};

const describe = (error, fallback) => error instanceof UserCancelledError || error?.name === 'NotAllowedError'
    ? ''
    : error?.message || fallback;

/* A default passkey name such as "Chrome on Mac", so people can tell their passkeys apart later. */
const deviceName = () => {
    const ua = navigator.userAgent;
    const browser = [[/Edg/, 'Edge'], [/OPR|Opera/, 'Opera'], [/Firefox|FxiOS/, 'Firefox'], [/Chrome|CriOS/, 'Chrome'], [/Safari/, 'Safari']].find(([pattern]) => pattern.test(ua))?.[1];
    const os = [[/iPhone/, 'iPhone'], [/iPad/, 'iPad'], [/Android/, 'Android'], [/Mac/, 'Mac'], [/Windows/, 'Windows'], [/Linux/, 'Linux']].find(([pattern]) => pattern.test(ua))?.[1];

    return [browser, os].filter(Boolean).join(' on ');
};

const supported = Passkeys.isSupported();

document.querySelectorAll('[data-passkey-supported]').forEach(el => el.hidden = ! supported);
document.querySelectorAll('[data-passkey-unsupported]').forEach(el => el.hidden = supported);

document.querySelectorAll('[data-passkey-verify]').forEach(button => {
    button.addEventListener('click', async () => {
        button.disabled = true;
        showError(button, '');

        try {
            const response = await Passkeys.verify({routes: {options: button.dataset.options, submit: button.dataset.submit}});
            window.location.href = response?.redirect || button.dataset.redirect || '/';
        } catch (error) {
            showError(button, describe(error, 'That passkey could not be used. Try again or use your password.'));
            button.disabled = false;
        }
    });
});

document.querySelectorAll('form[data-passkey-register]').forEach(form => {
    form.elements.name.value ||= deviceName();

    form.addEventListener('submit', async event => {
        event.preventDefault();

        const name = form.elements.name.value.trim();
        const button = form.querySelector('button[type=submit]');

        if (! name) {
            return;
        }

        button.disabled = true;
        showError(form, '');

        try {
            await Passkeys.register({name, routes: {options: form.dataset.options, submit: form.action}});
            window.location.reload();
        } catch (error) {
            showError(form, describe(error, 'The passkey could not be added.'));
            button.disabled = false;
        }
    });
});
