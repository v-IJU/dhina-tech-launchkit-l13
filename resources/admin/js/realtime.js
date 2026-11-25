// Live notifications over Laravel Reverb. New notifications appear in the bell
// dropdown and as a toast without reloading the page.
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { Toast } from 'bootstrap';

const meta = (name) => document.querySelector(`meta[name="${name}"]`)?.content;
const userId = meta('user-id');
// Server settings come from <meta name="reverb"> (REVERB_* in .env), so the built JS works on any domain.
// The VITE_REVERB_* values are only a fallback.
let reverb = {};
try { reverb = JSON.parse(meta('reverb') ?? '{}'); } catch { /* keep fallback */ }
const key = reverb.key ?? import.meta.env.VITE_REVERB_APP_KEY;
const host = reverb.host ?? import.meta.env.VITE_REVERB_HOST;
const port = reverb.port ?? import.meta.env.VITE_REVERB_PORT;
const scheme = reverb.scheme ?? import.meta.env.VITE_REVERB_SCHEME ?? 'https';
const authEndpoint = meta('broadcast-auth'); // only present when BROADCAST_CONNECTION=reverb

if (userId && key && authEndpoint) {
    window.Pusher = Pusher;
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key,
        wsHost: host,
        wsPort: port || 80,
        wssPort: port || 443,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint,
    });

    window.Echo.private(meta('user-channel') || `App.Models.User.${userId}`).notification((n) => {
        const badge = document.querySelector('[data-notif-count]');
        const count = Number(badge?.dataset.notifCount || 0) + 1;
        if (badge) {
            badge.dataset.notifCount = count;
            badge.textContent = count;
            badge.classList.remove('d-none');
        }

        const list = document.querySelector('[data-notif-list]');
        if (list) {
            list.querySelector('[data-notif-empty]')?.remove();
            const a = document.createElement('a');
            a.className = 'notif-item';
            a.href = n.url || '#';
            a.innerHTML = '<span class="timeline-dot bg-primary"></span><span class="d-flex flex-column"><span class="fw-semibold"></span><small class="text-secondary">just now</small></span>';
            a.querySelector('.fw-semibold').textContent = n.message;
            list.prepend(a);
        }

        const tpl = document.getElementById('liveToast');
        if (tpl) {
            tpl.querySelector('[data-toast-text]').textContent = n.message;
            Toast.getOrCreateInstance(tpl).show();
        }
    });
}
