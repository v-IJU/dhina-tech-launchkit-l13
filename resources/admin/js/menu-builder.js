// Menu builder: drag items to reorder; the new order is saved immediately.
import Sortable from 'sortablejs';

document.querySelectorAll('[data-menu-sortable]').forEach((list) => {
    Sortable.create(list, {
        handle: '.drag-handle',
        animation: 150,
        onEnd: async () => {
            const ids = [...list.querySelectorAll(':scope > [data-id]')].map((el) => el.dataset.id);
            await fetch(list.dataset.menuSortable, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ ids }),
            });
        },
    });
});
