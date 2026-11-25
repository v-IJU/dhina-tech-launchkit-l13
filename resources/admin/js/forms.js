// Small form helpers used across admin pages.

const slugify = (s) => s.toLowerCase().normalize('NFKD').replace(/[̀-ͯ]/g, '')
    .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');

// <input data-slug-from="#title">: fills itself from the title until edited by hand.
document.querySelectorAll('[data-slug-from]').forEach((slug) => {
    const source = document.querySelector(slug.dataset.slugFrom);
    if (!source) return;
    let touched = slug.value !== '';
    slug.addEventListener('input', () => { touched = slug.value !== ''; });
    source.addEventListener('input', () => {
        if (!touched) {
            slug.placeholder = slugify(source.value);
            if (slug.dataset.slugFill !== undefined) slug.value = slugify(source.value);
        }
        document.querySelectorAll('[data-slug-preview]').forEach((el) => { el.textContent = slug.value || slugify(source.value) || '…'; });
    });
});

// <input data-counter="60">: shows "12 / 60 characters" in [data-counter-for="id"].
document.querySelectorAll('[data-counter]').forEach((input) => {
    const out = document.querySelector(`[data-counter-for="${input.id}"]`);
    const max = Number(input.dataset.counter);
    const update = () => {
        if (!out) return;
        out.textContent = `${input.value.length} / ${max} characters`;
        out.classList.toggle('text-danger', input.value.length > max);
    };
    input.addEventListener('input', update);
    update();
});

// Live Google preview on the post / page form.
document.querySelectorAll('[data-preview-source]').forEach((input) => {
    const target = document.querySelector(input.dataset.previewSource);
    const fallback = input.dataset.previewFallback ? document.querySelector(input.dataset.previewFallback) : null;
    const update = () => { if (target) target.textContent = input.value || fallback?.value || target.dataset.default || ''; };
    input.addEventListener('input', update);
    fallback?.addEventListener('input', update);
});

// Show / hide password.
document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-toggle-password]');
    if (!btn) return;
    const input = document.querySelector(btn.dataset.togglePassword);
    input.type = input.type === 'password' ? 'text' : 'password';
    btn.querySelector('.bi')?.classList.toggle('bi-eye-slash');
});

// Password strength meter: <input data-strength="#meter">.
const score = (p) => [p.length >= 8, p.length >= 12, /[a-z]/.test(p) && /[A-Z]/.test(p), /\d/.test(p), /[^A-Za-z0-9]/.test(p)].filter(Boolean).length;
const levels = [['', 0, 'secondary'], ['Weak', 20, 'danger'], ['Weak', 40, 'danger'], ['Fair', 60, 'warning'], ['Good', 80, 'primary'], ['Strong', 100, 'success']];

document.querySelectorAll('[data-strength]').forEach((input) => {
    const meter = document.querySelector(input.dataset.strength);
    const update = () => {
        const [label, width, color] = levels[input.value ? score(input.value) : 0];
        meter.querySelector('.progress-bar').style.width = `${width}%`;
        meter.querySelector('.progress-bar').className = `progress-bar bg-${color}`;
        meter.querySelector('[data-strength-label]').textContent = label;
    };
    input.addEventListener('input', update);
    update();
});

// Generate a strong password into the fields listed in data-generate-password.
document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-generate-password]');
    if (!btn) return;
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%&*?';
    const bytes = crypto.getRandomValues(new Uint32Array(14));
    let pass = 'Aa1!';
    bytes.forEach((b) => { pass += chars[b % chars.length]; });
    btn.dataset.generatePassword.split(',').forEach((sel) => {
        const input = document.querySelector(sel.trim());
        if (!input) return;
        input.value = pass;
        input.type = 'text';
        input.dispatchEvent(new Event('input'));
    });
});

// Copy to clipboard: <button data-copy="text to copy">.
document.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-copy]');
    if (!btn) return;
    try {
        await navigator.clipboard.writeText(btn.dataset.copy);
        const original = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check2"></i>';
        setTimeout(() => { btn.innerHTML = original; }, 1200);
    } catch { /* clipboard not available */ }
});

// Show a field only when a select has a value: <div data-show-when="#status" data-show-value="scheduled">.
document.querySelectorAll('[data-show-when]').forEach((el) => {
    const source = document.querySelector(el.dataset.showWhen);
    const update = () => el.classList.toggle('d-none', source.value !== el.dataset.showValue);
    source?.addEventListener('change', update);
    if (source) update();
});

// Live preview of an uploaded image file: <input type="file" data-image-preview="#preview">.
document.addEventListener('change', (e) => {
    const input = e.target.closest('[data-image-preview]');
    if (!input || !input.files?.[0]) return;
    const box = document.querySelector(input.dataset.imagePreview);
    const img = document.createElement('img');
    img.src = URL.createObjectURL(input.files[0]);
    img.alt = '';
    box.innerHTML = '';
    box.append(img);
});
