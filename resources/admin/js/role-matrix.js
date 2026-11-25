// Role permission matrix: row, column and "select all" checkboxes,
// plus the live "Sidebar for this role" preview next to it.

const escapeHtml = (text) => String(text).replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch]);

/**
 * Same rules as MenuService::sidebarEntry(): an item shows when it needs no permission or the role
 * has it; a group with no visible children and no page of its own is hidden.
 */
function renderSidebarPreview(granted) {
    const card = document.querySelector('[data-sidebar-preview]');
    const tree = document.querySelector('[data-sidebar-tree]');
    if (!card || !tree) return;

    const all = card.dataset.all === '1'; // super admin sees everything
    const allowed = (permission) => all || !permission || granted.has(permission);
    let html = '';
    let shown = 0;

    for (const group of JSON.parse(tree.textContent)) {
        const items = group.items
            .filter((item) => allowed(item.permission))
            .map((item) => ({ ...item, children: item.children.filter((child) => allowed(child.permission)) }))
            .filter((item) => item.children.length > 0 || item.ownLink);
        if (!items.length) continue;

        html += `<div class="text-uppercase text-secondary fw-semibold mt-2 mb-1" style="font-size: .7rem">${escapeHtml(group.title)}</div><ul class="list-unstyled mb-2">`;
        for (const item of items) {
            shown++;
            html += `<li class="py-1"><i class="bi ${escapeHtml(item.icon)} me-2 text-secondary"></i>${escapeHtml(item.label)}`;
            if (item.children.length) {
                html += '<ul class="list-unstyled ms-4 text-secondary">' + item.children.map((c) => `<li>${escapeHtml(c.label)}</li>`).join('') + '</ul>';
            }
            html += '</li>';
        }
        html += '</ul>';
    }

    card.querySelector('[data-sidebar-preview-body]').innerHTML = shown
        ? html
        : '<p class="text-secondary mb-0">Nothing yet: tick a <strong>View</strong> box to show a menu. Without any, people with this role only see their profile.</p>';
}

const matrix = document.querySelector('[data-permission-matrix]');

if (matrix) {
    const boxes = () => [...matrix.querySelectorAll('input[name="permissions[]"]')];

    const refresh = () => {
        matrix.querySelectorAll('[data-row]').forEach((row) => {
            const items = [...row.querySelectorAll('input[name="permissions[]"]')];
            row.querySelector('[data-row-toggle]').checked = items.length > 0 && items.every((c) => c.checked);
        });
        matrix.querySelectorAll('[data-col-toggle]').forEach((col) => {
            const items = boxes().filter((c) => c.dataset.action === col.dataset.colToggle);
            col.checked = items.length > 0 && items.every((c) => c.checked);
        });
        const count = boxes().filter((c) => c.checked).length;
        document.querySelectorAll('[data-permission-count]').forEach((el) => { el.textContent = count; });
        renderSidebarPreview(new Set(boxes().filter((c) => c.checked).map((c) => c.value)));
    };

    matrix.addEventListener('change', (e) => {
        if (e.target.matches('[data-row-toggle]')) {
            e.target.closest('[data-row]').querySelectorAll('input[name="permissions[]"]').forEach((c) => { c.checked = e.target.checked; });
        }
        if (e.target.matches('[data-col-toggle]')) {
            boxes().filter((c) => c.dataset.action === e.target.dataset.colToggle).forEach((c) => { c.checked = e.target.checked; });
        }
        refresh();
    });

    document.addEventListener('click', (e) => {
        const all = e.target.closest('[data-select-all]');
        if (!all) return;
        boxes().forEach((c) => { c.checked = all.dataset.selectAll === '1'; });
        refresh();
    });

    // "Start from existing role" copies that role's permissions.
    document.querySelector('[data-copy-role]')?.addEventListener('change', (e) => {
        const option = e.target.selectedOptions[0];
        if (!option?.dataset.permissions) return;
        const wanted = new Set(JSON.parse(option.dataset.permissions));
        boxes().forEach((c) => { c.checked = wanted.has('*') || wanted.has(c.value); });
        refresh();
    });

    refresh();
}
