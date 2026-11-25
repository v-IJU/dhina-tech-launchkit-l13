// Server-side list tables (<x-admin.datatable>): search, filters, sort, per-page and
// pagination reload only the rows via fetch, keeping the URL in sync so links, the
// back button and "Export CSV" always match what you see. Without JS everything
// still works as normal links and GET forms.

const SEARCH_DELAY = 350;

document.querySelectorAll('[data-datatable]').forEach((root) => {
    const form = root.querySelector('[data-datatable-form]');
    const body = root.querySelector('[data-datatable-body]');
    let controller = null;
    let timer = null;

    async function load(url, push = true) {
        controller?.abort();
        controller = new AbortController();
        root.classList.add('is-loading');

        try {
            const response = await fetch(url, {
                headers: { 'X-DataTable': '1', Accept: 'text/html' },
                signal: controller.signal,
                credentials: 'same-origin',
            });
            // Session expired or an error page: fall back to a normal page load.
            if (!response.ok || response.redirected) {
                window.location.href = url;
                return;
            }
            const html = await response.text();
            body.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => window.bootstrap?.Tooltip.getInstance(el)?.dispose());
            body.innerHTML = html;
            if (push) history.pushState({ datatable: true }, '', url);
            syncControls(new URL(url, window.location.origin));
            root.querySelector('[data-bulk-clear]')?.click();
        } catch (error) {
            if (error.name !== 'AbortError') window.location.href = url;
        } finally {
            root.classList.remove('is-loading');
        }
    }

    // URL from the toolbar form, keeping sort / dir / per_page from the current URL.
    function formUrl() {
        const url = new URL(form.action || window.location.href, window.location.origin);
        const current = new URL(window.location.href);
        ['sort', 'dir', 'per_page', 'trashed'].forEach((key) => {
            if (current.searchParams.get(key)) url.searchParams.set(key, current.searchParams.get(key));
        });
        new FormData(form).forEach((value, key) => {
            if (['sort', 'dir', 'per_page', 'trashed'].includes(key)) return;
            if (String(value).trim() !== '') url.searchParams.set(key, value);
        });
        return url.toString();
    }

    // Keep the export link, reset link and hidden inputs in step with the URL.
    function syncControls(url) {
        const exportLink = root.querySelector('[data-datatable-export]');
        if (exportLink) {
            const exportUrl = new URL(url);
            exportUrl.searchParams.delete('page');
            exportUrl.searchParams.set('export', 'csv');
            exportLink.href = exportUrl.toString();
        }

        const filtered = [...new FormData(form).entries()]
            .some(([key, value]) => !['sort', 'dir', 'per_page', 'trashed'].includes(key) && String(value).trim() !== '');
        root.querySelector('[data-datatable-reset]')?.classList.toggle('d-none', !filtered);

        ['sort', 'dir', 'per_page', 'trashed'].forEach((key) => {
            let input = form.querySelector(`input[type=hidden][name="${key}"]`);
            const value = url.searchParams.get(key);
            if (!value) { input?.remove(); return; }
            if (!input) {
                input = Object.assign(document.createElement('input'), { type: 'hidden', name: key });
                form.prepend(input);
            }
            input.value = value;
        });
    }

    form?.addEventListener('submit', (e) => {
        e.preventDefault();
        load(formUrl());
    });

    form?.addEventListener('input', (e) => {
        if (e.target.type !== 'search') return;
        clearTimeout(timer);
        timer = setTimeout(() => load(formUrl()), SEARCH_DELAY);
    });

    form?.addEventListener('change', (e) => {
        if (e.target.matches('[data-datatable-filter]')) load(formUrl());
    });

    root.addEventListener('click', (e) => {
        const link = e.target.closest('[data-datatable-link], [data-datatable-pages] a, [data-datatable-reset]');
        if (!link || e.ctrlKey || e.metaKey || e.shiftKey) return;
        e.preventDefault();
        if (link.matches('[data-datatable-reset]')) {
            form.querySelectorAll('input:not([type=hidden]), select').forEach((el) => { el.value = ''; });
        }
        load(link.href);
    });

    root.addEventListener('change', (e) => {
        if (e.target.matches('[data-datatable-per-page]')) load(e.target.value);
    });

    // Back/forward between table states: reload the rows for that URL.
    window.addEventListener('popstate', () => load(window.location.href, false).then(() => {
        const url = new URL(window.location.href);
        form.querySelectorAll('input:not([type=hidden]), select').forEach((el) => { el.value = url.searchParams.get(el.name) ?? ''; });
        syncControls(url);
    }));
});
