// Customer account pages (website). Loaded automatically by resources/web/web.js.

// <form data-confirm="Are you sure?"> asks before submitting.
document.addEventListener('submit', (e) => {
    const form = e.target.closest('form[data-confirm]');
    if (form && !window.confirm(form.dataset.confirm)) {
        e.preventDefault();
    }
});

// <button data-copy="#recoveryCodes"> copies the text of that element.
document.addEventListener('click', async (e) => {
    const button = e.target.closest('[data-copy]');
    if (!button) return;
    const source = document.querySelector(button.dataset.copy);
    if (!source) return;
    try {
        await navigator.clipboard.writeText(source.innerText.trim());
        const label = button.textContent;
        button.textContent = button.dataset.copied || 'Copied';
        setTimeout(() => { button.textContent = label; }, 2000);
    } catch {
        // Clipboard blocked (http, old browser): the codes are still on screen to copy by hand.
    }
});
