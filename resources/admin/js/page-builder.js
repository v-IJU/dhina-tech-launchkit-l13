// Page builder: add, reorder, duplicate and remove content blocks.
// Field names use a unique token per block (blocks[TOKEN][data][field]);
// the server saves blocks in the order they appear on screen.
import Sortable from 'sortablejs';

const list = document.querySelector('[data-blocks]');

if (list) {
    const token = () => `n${Date.now().toString(36)}${Math.random().toString(36).slice(2, 6)}`;

    const refresh = () => {
        [...list.children].forEach((block, i) => {
            block.querySelector('[data-block-number]').textContent = i + 1;
        });
        document.querySelector('[data-blocks-empty]')?.classList.toggle('d-none', list.children.length > 0);
    };

    // Copy live values into HTML attributes so outerHTML carries them when duplicating.
    const freeze = (block) => {
        block.querySelectorAll('input').forEach((i) => {
            if (i.type === 'checkbox') i.toggleAttribute('checked', i.checked);
            else i.setAttribute('value', i.value);
        });
        block.querySelectorAll('textarea').forEach((t) => { t.textContent = t.value; });
        block.querySelectorAll('select').forEach((s) => {
            [...s.options].forEach((o) => o.toggleAttribute('selected', o.selected));
        });
    };

    Sortable.create(list, { handle: '.drag-handle', animation: 150, onEnd: refresh });

    document.addEventListener('click', (e) => {
        const add = e.target.closest('[data-add-block]');
        if (add) {
            const tpl = document.querySelector(`[data-block-template="${add.dataset.addBlock}"]`);
            list.insertAdjacentHTML('beforeend', tpl.innerHTML.replaceAll('__TOKEN__', token()));
            refresh();
            list.lastElementChild.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        const block = e.target.closest('[data-block]');
        if (!block || !list.contains(block)) return;

        if (e.target.closest('[data-block-remove]')) block.remove();
        if (e.target.closest('[data-block-up]') && block.previousElementSibling) block.previousElementSibling.before(block);
        if (e.target.closest('[data-block-down]') && block.nextElementSibling) block.nextElementSibling.after(block);
        if (e.target.closest('[data-block-duplicate]')) {
            freeze(block);
            const clone = block.cloneNode(true);
            clone.querySelectorAll('trix-toolbar').forEach((t) => t.remove());
            clone.querySelectorAll('trix-editor').forEach((t) => t.removeAttribute('toolbar'));
            const html = clone.outerHTML.replaceAll(block.dataset.token, token());
            block.insertAdjacentHTML('afterend', html);
        }
        refresh();
    });

    refresh();
}
