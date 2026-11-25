// Sidebar collapse, mobile menu, dark mode and sidebar search.
const root = document.documentElement;
const body = document.body;

const store = {
    get: (k) => { try { return localStorage.getItem(k); } catch { return null; } },
    set: (k, v) => { try { localStorage.setItem(k, v); } catch { /* storage disabled */ } },
};

if (store.get('sidebar-collapsed') === '1' && window.innerWidth >= 992) {
    body.classList.add('sidebar-collapsed');
}

const syncThemeIcon = () => {
    const dark = root.getAttribute('data-bs-theme') === 'dark';
    document.querySelectorAll('[data-theme-icon]').forEach((i) => { i.className = dark ? 'bi bi-sun' : 'bi bi-moon'; });
};
syncThemeIcon();

document.addEventListener('click', (e) => {
    if (e.target.closest('[data-sidebar-toggle]')) {
        if (window.innerWidth < 992) {
            body.classList.toggle('sidebar-open');
        } else {
            body.classList.toggle('sidebar-collapsed');
            store.set('sidebar-collapsed', body.classList.contains('sidebar-collapsed') ? '1' : '0');
        }
    }
    if (e.target.closest('.sidebar-backdrop')) {
        body.classList.remove('sidebar-open');
    }
    if (e.target.closest('[data-theme-toggle]')) {
        const next = root.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
        root.setAttribute('data-bs-theme', next);
        store.set('theme', next);
        syncThemeIcon();
    }
});

// Top bar search: filters the sidebar and jumps to the first match on Enter.
const search = document.querySelector('[data-menu-search]');
if (search) {
    const results = document.querySelector('[data-menu-results]');
    const links = [...document.querySelectorAll('.app-sidebar a[href]')]
        .filter((a) => a.textContent.trim() && !a.hasAttribute('data-bs-toggle'))
        .map((a) => ({ label: a.textContent.trim(), href: a.href, group: a.closest('[data-group]')?.dataset.group ?? '' }));

    const render = () => {
        const q = search.value.trim().toLowerCase();
        if (!q) { results.classList.remove('show'); results.innerHTML = ''; return; }
        const found = links.filter((l) => l.label.toLowerCase().includes(q) || l.group.toLowerCase().includes(q)).slice(0, 8);
        results.innerHTML = '';
        if (!found.length) {
            const empty = document.createElement('div');
            empty.className = 'dropdown-item-text text-secondary';
            empty.textContent = 'No matching pages';
            results.append(empty);
        }
        found.forEach((l) => {
            const a = document.createElement('a');
            a.className = 'dropdown-item d-flex justify-content-between gap-3';
            a.href = l.href;
            a.innerHTML = '<span></span><small class="text-secondary"></small>';
            a.firstChild.textContent = l.label;
            a.lastChild.textContent = l.group;
            results.append(a);
        });
        results.classList.add('show');
    };

    search.addEventListener('input', render);
    search.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            const first = results.querySelector('a');
            if (first) window.location = first.href;
        }
        if (e.key === 'Escape') { search.value = ''; render(); }
    });
    document.addEventListener('click', (e) => { if (!e.target.closest('[data-menu-search-wrap]')) results.classList.remove('show'); });
    document.addEventListener('keydown', (e) => {
        if (e.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName) && !document.activeElement.isContentEditable) {
            e.preventDefault();
            search.focus();
        }
    });
}
