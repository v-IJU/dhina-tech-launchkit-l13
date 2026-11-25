// SaaS signup page: lower-case the subdomain as you type and say whether it's free.

const input = document.querySelector('[data-signup] [data-check-url]');
const hint = document.querySelector('[data-subdomain-hint]');

if (input && hint) {
    let timer;
    input.addEventListener('input', () => {
        input.value = input.value.toLowerCase().replace(/[^a-z0-9-]/g, '');
        clearTimeout(timer);
        if (input.value.length < 3) return;
        timer = setTimeout(async () => {
            try {
                const res = await fetch(`${input.dataset.checkUrl}?subdomain=${encodeURIComponent(input.value)}`, { headers: { Accept: 'application/json' } });
                const data = await res.json();
                hint.textContent = data.message;
                hint.classList.toggle('text-red-600', !data.available);
                hint.classList.toggle('text-emerald-700', data.available);
                hint.classList.remove('text-slate-500');
            } catch {
                // offline: the server checks again on submit
            }
        }, 350);
    });
}
