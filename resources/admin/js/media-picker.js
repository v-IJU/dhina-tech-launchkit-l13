// Media picker: choose an image from the Media library for any form field.
// Markup is rendered by <x-admin.form.media name="featured_image" />.
import { Modal } from 'bootstrap';

const modalEl = document.getElementById('mediaPickerModal');
let target = null;

async function load(query = '') {
    const grid = modalEl.querySelector('[data-picker-grid]');
    grid.innerHTML = '<div class="text-secondary p-4">Loading…</div>';
    const url = new URL(modalEl.dataset.url, window.location.origin);
    if (query) url.searchParams.set('q', query);
    const res = await fetch(url, { headers: { Accept: 'application/json' } });
    const items = (await res.json()).data ?? [];
    grid.innerHTML = '';
    if (!items.length) {
        grid.innerHTML = '<div class="text-secondary p-4">No images yet. Upload some in the Media library.</div>';
    }
    items.forEach((item) => {
        const col = document.createElement('div');
        col.className = 'col-6 col-md-3';
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'media-tile w-100 p-0 text-start';
        btn.innerHTML = '<span class="media-thumb"><img alt=""></span><span class="d-block small p-2 text-truncate"></span>';
        btn.querySelector('img').src = item.thumb;
        btn.querySelector('img').alt = item.alt || '';
        btn.lastChild.textContent = item.name;
        btn.addEventListener('click', () => choose(item));
        col.append(btn);
        grid.append(col);
    });
}

function choose(item) {
    if (!target) return;
    const wrap = target.closest('[data-media-field]');
    wrap.querySelector('input[type="hidden"]').value = item.url;
    const preview = wrap.querySelector('.image-preview');
    preview.innerHTML = '';
    const img = document.createElement('img');
    img.src = item.url;
    img.alt = '';
    preview.append(img);
    Modal.getOrCreateInstance(modalEl).hide();
}

document.addEventListener('click', (e) => {
    const open = e.target.closest('[data-media-open]');
    if (open && modalEl) {
        target = open;
        Modal.getOrCreateInstance(modalEl).show();
        load();
    }
    const clear = e.target.closest('[data-media-clear]');
    if (clear) {
        const wrap = clear.closest('[data-media-field]');
        wrap.querySelector('input[type="hidden"]').value = '';
        wrap.querySelector('.image-preview').innerHTML = '<span><i class="bi bi-image fs-3"></i></span>';
    }
});

let timer;
modalEl?.querySelector('[data-picker-search]')?.addEventListener('input', (e) => {
    clearTimeout(timer);
    timer = setTimeout(() => load(e.target.value), 250);
});
