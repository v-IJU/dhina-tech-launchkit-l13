// Admin panel entry point. Each file below is small and self-contained:
// find the feature you want to change by its file name.
import * as bootstrap from 'bootstrap';
import 'trix';

import './js/layout';
import './js/confirm';
import './js/tables';
import './js/datatable';
import './js/forms';
import './js/media-picker';
import './js/role-matrix';
import './js/page-builder';
import './js/menu-builder';
import './js/dashboard';
import './js/realtime';
import './js/passkeys';
import './js/imports';

// Module scripts: every app/Modules/*/Resources/js/admin/*.js is included automatically,
// so a module's JavaScript lives (and is deleted) with the module.
import.meta.glob('../../app/Modules/*/Resources/js/admin/*.js', { eager: true });

window.bootstrap = bootstrap;

// Show flash toasts rendered by the server.
document.querySelectorAll('.toast[data-autoshow]').forEach((el) => bootstrap.Toast.getOrCreateInstance(el).show());

// Bootstrap tooltips, created on first hover so rows loaded later (data tables) get them too.
document.addEventListener('mouseover', (e) => {
    const el = e.target.closest?.('[data-bs-toggle="tooltip"]');
    if (el && !bootstrap.Tooltip.getInstance(el)) bootstrap.Tooltip.getOrCreateInstance(el).show();
});

// Uploads are handled by the Media library, not inside the editor.
document.addEventListener('trix-file-accept', (e) => e.preventDefault());
