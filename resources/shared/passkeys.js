// Passkeys in the browser (fingerprint, Face ID, Windows Hello, phone), for the admin panel and the website.
// No package needed: modern browsers turn the server's JSON options into a WebAuthn request themselves.
//
//   <button data-passkey-login data-options-url="…" data-login-url="…" [data-remember="#remember"]>
//   <form data-passkey-register data-options-url="…" data-store-url="…">  (fields: name, password)
//   [data-passkey-supported]   hidden until the browser supports passkeys ([data-passkey-unsupported]: the opposite)
//   [data-passkey-error]       inside the button's or form's container: shows problems

export const passkeysSupported = () =>
    typeof window.PublicKeyCredential !== 'undefined' &&
    typeof window.PublicKeyCredential.parseRequestOptionsFromJSON === 'function' &&
    typeof window.PublicKeyCredential.parseCreationOptionsFromJSON === 'function';

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

async function send(url, method, body) {
    const res = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
        body: body ? JSON.stringify(body) : undefined,
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        const first = data.errors ? Object.values(data.errors)[0]?.[0] : null;
        throw new Error(first || data.message || 'Something went wrong. Please try again.');
    }
    return data;
}

function showError(root, message) {
    const box = root.querySelector('[data-passkey-error]') ?? root.parentElement?.querySelector('[data-passkey-error]');
    if (box) {
        box.textContent = message;
        box.hidden = false;
    } else {
        window.alert(message);
    }
}

// The person closed the fingerprint / Face ID dialog: not an error worth shouting about.
const cancelled = (e) => e?.name === 'NotAllowedError' || e?.name === 'AbortError';

export function initPasskeys() {
    if (!passkeysSupported()) return;

    document.querySelectorAll('[data-passkey-unsupported]').forEach((el) => { el.hidden = true; });
    document.querySelectorAll('[data-passkey-supported]').forEach((el) => {
        el.hidden = false;
        el.classList.remove('d-none', 'hidden');
    });

    document.querySelectorAll('[data-passkey-login]').forEach((button) => {
        button.addEventListener('click', async () => {
            const root = button.closest('[data-passkey-root]') ?? button.parentElement;
            button.disabled = true;
            try {
                const { options } = await send(button.dataset.optionsUrl, 'GET');
                const credential = await navigator.credentials.get({ publicKey: PublicKeyCredential.parseRequestOptionsFromJSON(options) });
                const remember = button.dataset.remember ? document.querySelector(button.dataset.remember)?.checked : false;
                const { redirect } = await send(button.dataset.loginUrl, 'POST', { credential: credential.toJSON(), remember: !!remember });
                window.location.assign(redirect);
            } catch (e) {
                if (!cancelled(e)) showError(root, e.message);
                button.disabled = false;
            }
        });
    });

    document.querySelectorAll('[data-passkey-register]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const submit = form.querySelector('[type="submit"]');
            if (submit) submit.disabled = true;
            try {
                const values = Object.fromEntries(new FormData(form));
                const { options } = await send(form.dataset.optionsUrl, 'POST', { password: values.password ?? '' });
                const credential = await navigator.credentials.create({ publicKey: PublicKeyCredential.parseCreationOptionsFromJSON(options) });
                await send(form.dataset.storeUrl, 'POST', { name: values.name ?? '', credential: credential.toJSON() });
                window.location.reload();
            } catch (e) {
                if (!cancelled(e)) showError(form, e.message);
                if (submit) submit.disabled = false;
            }
        });
    });
}
