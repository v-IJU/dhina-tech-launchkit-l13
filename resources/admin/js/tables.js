// Table helpers: select-all checkboxes, bulk action bar, auto-submit filters.

document.addEventListener('change', (e) => {
    const all = e.target.closest('[data-check-all]');
    if (all) {
        const table = all.closest('[data-bulk]');
        table?.querySelectorAll('[data-row-check]:not(:disabled)').forEach((c) => { c.checked = all.checked; });
        updateBulk(table);
    }
    if (e.target.matches('[data-row-check]')) {
        updateBulk(e.target.closest('[data-bulk]'));
    }
    if (e.target.matches('[data-autosubmit]')) {
        e.target.form?.requestSubmit();
    }
});

function updateBulk(scope) {
    if (!scope) return;
    const checked = [...scope.querySelectorAll('[data-row-check]:checked')];
    const bar = scope.querySelector('[data-bulk-bar]');
    if (!bar) return;
    bar.classList.toggle('d-none', checked.length === 0);
    bar.querySelector('[data-bulk-count]').textContent = checked.length;
    const holder = bar.querySelector('[data-bulk-ids]');
    holder.innerHTML = '';
    checked.forEach((c) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'ids[]';
        input.value = c.value;
        holder.append(input);
    });
}

document.addEventListener('click', (e) => {
    const clear = e.target.closest('[data-bulk-clear]');
    if (clear) {
        const scope = clear.closest('[data-bulk]');
        scope.querySelectorAll('[data-row-check], [data-check-all]').forEach((c) => { c.checked = false; });
        updateBulk(scope);
    }
});
