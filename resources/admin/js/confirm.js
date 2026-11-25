// Confirmation dialog for dangerous actions.
// Usage: <form data-confirm="Delete this user?" data-confirm-button="Delete user"> ... </form>
// or:    <button form="..." data-confirm="...">
import { Modal } from 'bootstrap';

const modalEl = document.getElementById('confirmModal');
let pendingForm = null;

document.addEventListener('submit', (e) => {
    const form = e.target;
    const message = form.dataset.confirm || e.submitter?.dataset.confirm;
    if (!message || form.dataset.confirmed === '1' || !modalEl) return;

    e.preventDefault();
    pendingForm = form;
    pendingForm.pendingSubmitter = e.submitter ?? null;
    modalEl.querySelector('[data-confirm-title]').textContent = message;
    modalEl.querySelector('[data-confirm-body]').textContent = form.dataset.confirmBody || e.submitter?.dataset.confirmBody || 'This action cannot be undone.';
    modalEl.querySelector('[data-confirm-ok]').textContent = form.dataset.confirmButton || e.submitter?.dataset.confirmButton || 'Confirm';
    Modal.getOrCreateInstance(modalEl).show();
});

modalEl?.querySelector('[data-confirm-ok]').addEventListener('click', () => {
    if (!pendingForm) return;
    pendingForm.dataset.confirmed = '1';
    Modal.getOrCreateInstance(modalEl).hide();
    pendingForm.requestSubmit(pendingForm.pendingSubmitter);
});
