// Team chat (Admin > Team chat). Two parts:
//   1. every admin page: top-bar unread badge + a toast for new messages (over Reverb)
//   2. the chat page: conversations, messages, emoji, photos/files, voice notes, typing, read ticks
// Without Reverb (BROADCAST_CONNECTION=log) the chat page polls instead, so it still works.

import { Toast } from 'bootstrap';
import './chat.css';

const meta = (name) => document.querySelector(`meta[name="${name}"]`)?.content;
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const linkify = (s) => esc(s).replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>').replace(/\n/g, '<br>');

async function api(url, { method = 'GET', body } = {}) {
    const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': meta('csrf-token') ?? '' };
    let payload = body;
    if (body && !(body instanceof FormData)) {
        headers['Content-Type'] = 'application/json';
        payload = JSON.stringify(body);
    }
    const res = await fetch(url, { method, headers, body: payload, credentials: 'same-origin' });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        const first = data.errors ? Object.values(data.errors)[0]?.[0] : null;
        throw new Error(first || data.message || `Error ${res.status}`);
    }
    return data;
}

// A short soft "ding", no sound file needed.
function ding() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.frequency.value = 880;
        gain.gain.setValueAtTime(0.06, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.25);
        osc.connect(gain).connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + 0.25);
    } catch { /* no audio, fine */ }
}

/* ---------------- 1. Every admin page: badge + toast ---------------- */

const badge = document.querySelector('[data-chat-unread]');
function setBadge(n) {
    if (!badge) return;
    badge.dataset.chatUnread = n;
    badge.textContent = n > 99 ? '99+' : n;
    badge.classList.toggle('d-none', n <= 0);
}

const chatRoot = document.querySelector('[data-chat]');
const channel = meta('user-channel');
const handlers = { message: [], read: [], typing: [], deleted: [] };

if (window.Echo && channel && badge) {
    const ch = window.Echo.private(channel);
    Object.keys(handlers).forEach((type) => ch.listen(`.chat.${type}`, (e) => handlers[type].forEach((fn) => fn(e))));

    handlers.message.push((e) => {
        if (e.message.user_id === Number(meta('user-id'))) return; // my own message from another tab
        if (chatRoot) return; // the chat page handles it
        setBadge(Number(badge.dataset.chatUnread || 0) + 1);
        ding();
        const tpl = document.getElementById('liveToast');
        if (tpl) {
            const text = e.message.type === 'text' ? e.message.body : e.message.type === 'image' ? '📷 Photo' : e.message.type === 'audio' ? '🎤 Voice note' : '📎 File';
            tpl.querySelector('[data-toast-text]').textContent = `${e.from.name}: ${String(text).slice(0, 80)}`;
            Toast.getOrCreateInstance(tpl).show();
        }
    });
}

/* ---------------- 2. The chat page ---------------- */

const EMOJI = {
    Smileys: '😀 😃 😄 😁 😆 😅 😂 🤣 😊 😇 🙂 😉 😍 🥰 😘 😋 😜 🤪 🤗 🤭 🤔 🤐 😐 😶 🙄 😏 😬 😌 😴 😷 🤒 🤯 🥳 😎 🤓 😕 😟 😮 😲 😳 🥺 😢 😭 😱 😤 😡 🤬 👻 💩'.split(' '),
    Hands: '👍 👎 👌 ✌️ 🤞 🤝 👏 🙌 👐 🙏 💪 👋 🤙 👉 👈 👆 👇 ☝️ ✋ 🖐️ 🤚 ✍️ 🫡 🫶'.split(' '),
    Hearts: '❤️ 🧡 💛 💚 💙 💜 🖤 🤍 💔 💕 💞 💖 💯 ✨ ⭐ 🌟 🔥 🎉 🎊 🎁 🏆 🥇 ✅ ❌ ⚠️ ❓ ❗'.split(' '),
    Work: '💻 🖥️ 📱 ⌨️ 🖱️ 📞 📧 📅 📌 📎 📝 📊 📈 📉 🗂️ 📁 🔒 🔑 🛠️ ⚙️ 🐞 🚀 ⏰ ⏳ ☕ 🍕 🍔 🍰 🎂 🍻'.split(' '),
};

if (chatRoot) {
    const cfg = JSON.parse(chatRoot.dataset.chat);
    const $ = (sel) => chatRoot.querySelector(sel);
    const url = (name, id) => cfg.routes[name].replace('__ID__', id);

    const list = $('[data-chat-list]');
    const filter = $('[data-chat-filter]');
    const people = $('[data-chat-people]');
    const peopleList = $('[data-chat-people-list]');
    const thread = $('[data-chat-thread]');
    const empty = $('[data-chat-empty]');
    const items = $('[data-chat-items]');
    const scroller = $('[data-chat-messages]');
    const more = $('[data-chat-more]');
    const form = $('[data-chat-form]');
    const input = $('[data-chat-input]');
    const fileInput = $('[data-chat-file]');
    const errorBox = $('[data-chat-error]');
    const status = $('[data-chat-status]');

    let current = null; // { id, user }
    let messages = [];
    let otherRead = 0;
    let typingTimer = null;
    let lastTypingSent = 0;
    let sending = false;

    const avatarHtml = (u) => `<span class="avatar" style="background:${esc(u.color)}">${u.avatar ? `<img src="${esc(u.avatar)}" alt="">` : esc(u.initials)}</span>`;

    function showError(text) {
        errorBox.textContent = text;
        errorBox.classList.toggle('d-none', !text);
    }

    /* ---- conversation list ---- */
    function renderList(conversations) {
        const unreadTotal = conversations.reduce((n, c) => n + (c.id === current?.id ? 0 : c.unread), 0);
        setBadge(unreadTotal);
        if (!conversations.length) {
            list.innerHTML = '<div class="p-4 text-center text-secondary small" data-chat-none>No chats yet.</div>';
            return;
        }
        list.innerHTML = conversations.map((c) => `
            <a href="${esc(c.url)}" class="chat-item ${c.id === current?.id ? 'active' : ''}" data-chat-open="${c.id}" data-chat-search="${esc((c.user.name + ' ' + (c.user.email || '')).toLowerCase())}">
                ${avatarHtml(c.user)}
                <span class="chat-item-body">
                    <span class="d-flex justify-content-between gap-2"><span class="fw-semibold text-truncate">${esc(c.user.name)}</span><small class="text-secondary flex-none">${esc(c.last?.time || '')}</small></span>
                    <span class="d-flex justify-content-between gap-2"><small class="text-secondary text-truncate">${c.last?.mine ? 'You: ' : ''}${esc(c.last?.text || 'No messages yet')}</small>
                    <span class="badge rounded-pill bg-primary ${c.unread && c.id !== current?.id ? '' : 'd-none'}">${c.unread}</span></span>
                </span>
            </a>`).join('');
        applyFilter();
    }

    const refreshList = async () => renderList((await api(cfg.routes.conversations)).conversations);

    function applyFilter() {
        const term = filter.value.trim().toLowerCase();
        list.querySelectorAll('[data-chat-open]').forEach((a) => a.classList.toggle('d-none', term !== '' && !a.dataset.chatSearch.includes(term)));
    }

    let searchTimer;
    filter.addEventListener('input', () => {
        applyFilter();
        if (!cfg.canStart || !people) return;
        clearTimeout(searchTimer);
        const term = filter.value.trim();
        if (term === '') {
            people.classList.add('d-none');
            return;
        }
        searchTimer = setTimeout(async () => {
            const { users } = await api(`${cfg.routes.users}?q=${encodeURIComponent(term)}`);
            people.classList.remove('d-none');
            peopleList.innerHTML = users.length
                ? users.map((u) => `<button type="button" class="chat-item w-100 text-start border-0 bg-transparent" data-chat-start="${u.id}">${avatarHtml(u)}<span class="chat-item-body"><span class="fw-semibold">${esc(u.name)}</span><small class="text-secondary text-truncate">${esc(u.role || '')} · ${esc(u.email)}</small></span></button>`).join('')
                : '<div class="px-3 py-2 small text-secondary">Nobody found with chat access.</div>';
        }, 250);
    });

    chatRoot.addEventListener('click', async (e) => {
        const start = e.target.closest('[data-chat-start]');
        if (start) {
            try {
                const { conversation } = await api(cfg.routes.start, { method: 'POST', body: { user_id: Number(start.dataset.chatStart) } });
                filter.value = '';
                people?.classList.add('d-none');
                await refreshList();
                open(conversation.id);
            } catch (err) {
                window.alert(err.message);
            }
            return;
        }
        const item = e.target.closest('[data-chat-open]');
        if (item && !e.ctrlKey && !e.metaKey) {
            e.preventDefault();
            open(Number(item.dataset.chatOpen));
        }
    });

    /* ---- one conversation ---- */
    function dayLabel(date) {
        const d = new Date(date + 'T00:00:00');
        const today = new Date();
        const yesterday = new Date(Date.now() - 864e5);
        if (d.toDateString() === today.toDateString()) return 'Today';
        if (d.toDateString() === yesterday.toDateString()) return 'Yesterday';
        return d.toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: d.getFullYear() === today.getFullYear() ? undefined : 'numeric' });
    }

    function bubble(m) {
        const mine = m.user_id === cfg.me;
        let content = '';
        if (m.type === 'deleted') content = '<span class="fst-italic text-secondary"><i class="bi bi-slash-circle me-1"></i>This message was deleted</span>';
        else if (m.type === 'image') content = `<a href="${esc(m.attachment.url)}" target="_blank" rel="noopener"><img class="chat-img" src="${esc(m.attachment.url)}" alt="${esc(m.attachment.name)}"></a>`;
        else if (m.type === 'audio') content = `<audio class="chat-audio" controls preload="metadata" src="${esc(m.attachment.url)}"></audio>`;
        else if (m.type === 'file') content = `<a class="chat-file" href="${esc(m.attachment.download)}"><i class="bi bi-file-earmark-arrow-down fs-4"></i><span class="min-w-0"><span class="d-block text-truncate fw-semibold">${esc(m.attachment.name)}</span><small>${esc(m.attachment.size)}</small></span></a>`;
        if (m.body && m.type !== 'deleted') content += `<div class="chat-text">${linkify(m.body)}</div>`;

        const ticks = mine && m.type !== 'deleted' ? `<i class="bi ${m.id <= otherRead ? 'bi-check2-all text-info' : 'bi-check2'} ms-1" data-tick="${m.id}"></i>` : '';
        const del = mine && m.type !== 'deleted' ? `<button type="button" class="chat-del" data-chat-delete="${m.id}" title="Delete for everyone" aria-label="Delete"><i class="bi bi-trash3"></i></button>` : '';

        return `<div class="chat-msg ${mine ? 'mine' : ''}" data-msg="${m.id}">${del}<div class="chat-bubble">${content}<span class="chat-meta">${esc(m.time)}${ticks}</span></div></div>`;
    }

    function render(keepScroll = false) {
        const before = scroller.scrollHeight - scroller.scrollTop;
        let html = '';
        let day = null;
        messages.forEach((m) => {
            if (m.date !== day) {
                day = m.date;
                html += `<div class="chat-day"><span>${dayLabel(day)}</span></div>`;
            }
            html += bubble(m);
        });
        items.innerHTML = html || '<div class="text-center text-secondary small py-5">Say hello 👋</div>';
        const place = () => {
            scroller.scrollTop = keepScroll ? scroller.scrollHeight - before : scroller.scrollHeight;
        };
        place();
        // Photos load after the text: keep the view pinned once they have their real height.
        items.querySelectorAll('img').forEach((img) => !img.complete && img.addEventListener('load', place, { once: true }));
    }

    function setHeader(user) {
        $('[data-chat-avatar]').outerHTML = avatarHtml(user).replace('class="avatar"', 'class="avatar" data-chat-avatar');
        $('[data-chat-name]').textContent = user.name;
        status.textContent = user.email || '';
        status.dataset.default = status.textContent;
    }

    async function open(id) {
        current = { id };
        showError('');
        empty.classList.add('d-none');
        thread.classList.remove('d-none');
        chatRoot.classList.add('chat-open');
        items.innerHTML = '<div class="text-center text-secondary py-5"><span class="spinner-border spinner-border-sm"></span></div>';
        history.replaceState(null, '', `?c=${id}`);
        list.querySelectorAll('[data-chat-open]').forEach((a) => a.classList.toggle('active', Number(a.dataset.chatOpen) === id));

        try {
            const data = await api(url('messages', id));
            if (current?.id !== id) return;
            current.user = data.conversation.user;
            messages = data.messages;
            otherRead = data.other_read;
            more.classList.toggle('d-none', !data.has_more);
            setHeader(data.conversation.user);
            render();
            input.focus();
            markRead();
        } catch (err) {
            items.innerHTML = `<div class="text-center text-danger py-5">${esc(err.message)}</div>`;
        }
    }

    more.querySelector('button').addEventListener('click', async () => {
        const { messages: older, has_more } = await api(`${url('messages', current.id)}?before=${messages[0]?.id || 0}`);
        messages = older.concat(messages);
        more.classList.toggle('d-none', !has_more);
        render(true);
    });

    async function markRead() {
        if (!current || document.hidden) return;
        try {
            await api(url('read', current.id), { method: 'POST' });
            refreshList();
        } catch { /* ignore */ }
    }

    function addMessage(m) {
        if (messages.some((x) => x.id === m.id)) return;
        messages.push(m);
        const nearBottom = scroller.scrollHeight - scroller.scrollTop - scroller.clientHeight < 150;
        render();
        if (!nearBottom && m.user_id !== cfg.me) scroller.scrollTop = scroller.scrollTop; // stay where the reader is
    }

    /* ---- sending ---- */
    async function send(formData) {
        if (!current || sending) return;
        sending = true;
        showError('');
        form.classList.add('is-sending');
        try {
            const { message } = await api(url('send', current.id), { method: 'POST', body: formData });
            addMessage(message);
            refreshList();
        } catch (err) {
            showError(err.message);
        } finally {
            sending = false;
            form.classList.remove('is-sending');
        }
    }

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const text = input.value.trim();
        if (!text) return;
        const fd = new FormData();
        fd.append('body', text);
        emojiPanel.classList.add('d-none');
        input.value = '';
        autosize();
        send(fd);
    });

    // Enter sends, Shift+Enter = new line.
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
            e.preventDefault();
            form.requestSubmit();
        }
    });

    const autosize = () => {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 140) + 'px';
    };
    input.addEventListener('input', () => {
        autosize();
        if (current && Date.now() - lastTypingSent > 3000) {
            lastTypingSent = Date.now();
            api(url('typing', current.id), { method: 'POST' }).catch(() => {});
        }
    });

    fileInput?.addEventListener('change', () => {
        const file = fileInput.files[0];
        fileInput.value = '';
        if (!file) return;
        if (file.size > cfg.maxMb * 1024 * 1024) {
            showError(`That file is larger than ${cfg.maxMb} MB.`);
            return;
        }
        const fd = new FormData();
        fd.append('attachment', file);
        if (input.value.trim()) {
            fd.append('body', input.value.trim());
            input.value = '';
            autosize();
        }
        send(fd);
    });

    // Paste a screenshot straight into the message box.
    input.addEventListener('paste', (e) => {
        const file = [...(e.clipboardData?.files || [])].find((f) => f.type.startsWith('image/'));
        if (!file || !fileInput) return;
        e.preventDefault();
        const fd = new FormData();
        fd.append('attachment', file, file.name || 'screenshot.png');
        send(fd);
    });

    /* ---- delete ---- */
    items.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-chat-delete]');
        if (!btn || !window.confirm('Delete this message for everyone?')) return;
        try {
            const { message } = await api(url('destroy', btn.dataset.chatDelete), { method: 'DELETE' });
            messages = messages.map((m) => (m.id === message.id ? message : m));
            render(true);
            refreshList();
        } catch (err) {
            showError(err.message);
        }
    });

    /* ---- emoji ---- */
    const emojiPanel = $('[data-chat-emoji-panel]');
    emojiPanel.innerHTML = Object.entries(EMOJI).map(([group, list]) => `<div class="chat-emoji-group">${esc(group)}</div><div class="chat-emoji-grid">${list.map((e) => `<button type="button" data-emoji="${e}">${e}</button>`).join('')}</div>`).join('');
    $('[data-chat-emoji]').addEventListener('click', () => emojiPanel.classList.toggle('d-none'));
    emojiPanel.addEventListener('click', (e) => {
        const b = e.target.closest('[data-emoji]');
        if (!b) return;
        const start = input.selectionStart ?? input.value.length;
        input.value = input.value.slice(0, start) + b.dataset.emoji + input.value.slice(input.selectionEnd ?? start);
        input.focus();
        input.selectionStart = input.selectionEnd = start + b.dataset.emoji.length;
        autosize();
    });
    document.addEventListener('click', (e) => {
        if (!e.target.closest('[data-chat-emoji-panel], [data-chat-emoji]')) emojiPanel.classList.add('d-none');
    });

    /* ---- voice notes ---- */
    const mic = $('[data-chat-mic]');
    const recBar = $('[data-chat-recording]');
    const row = $('[data-chat-compose-row]');
    let recorder = null;
    let chunks = [];
    let recStart = 0;
    let recTimer = null;
    let recSend = false;

    if (mic && !(navigator.mediaDevices?.getUserMedia && window.MediaRecorder)) mic.classList.add('d-none');

    mic?.addEventListener('click', async () => {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            const type = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg'].find((t) => MediaRecorder.isTypeSupported(t)) || '';
            recorder = new MediaRecorder(stream, type ? { mimeType: type } : undefined);
            chunks = [];
            recSend = false;
            recorder.ondataavailable = (e) => e.data.size && chunks.push(e.data);
            recorder.onstop = () => {
                stream.getTracks().forEach((t) => t.stop());
                clearInterval(recTimer);
                recBar.classList.add('d-none');
                row.classList.remove('d-none');
                if (!recSend || !chunks.length) return;
                const mime = recorder.mimeType || 'audio/webm';
                const ext = mime.includes('mp4') ? 'm4a' : mime.includes('ogg') ? 'ogg' : 'webm';
                const fd = new FormData();
                fd.append('attachment', new File(chunks, `voice.${ext}`, { type: mime.split(';')[0] }));
                fd.append('voice', '1');
                fd.append('duration', String(Math.round((Date.now() - recStart) / 1000)));
                send(fd);
            };
            recorder.start();
            recStart = Date.now();
            row.classList.add('d-none');
            recBar.classList.remove('d-none');
            const timeEl = $('[data-chat-rec-time]');
            recTimer = setInterval(() => {
                const s = Math.round((Date.now() - recStart) / 1000);
                timeEl.textContent = `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
                if (s >= 300) $('[data-chat-rec-send]').click(); // 5 minutes max
            }, 500);
        } catch {
            showError('Microphone not available. Allow it in the browser (HTTPS or localhost only).');
        }
    });
    $('[data-chat-rec-cancel]')?.addEventListener('click', () => recorder?.state === 'recording' && recorder.stop());
    $('[data-chat-rec-send]')?.addEventListener('click', () => {
        recSend = true;
        if (recorder?.state === 'recording') recorder.stop();
    });

    /* ---- mobile back ---- */
    $('[data-chat-back]').addEventListener('click', () => {
        chatRoot.classList.remove('chat-open');
        current = null;
        history.replaceState(null, '', '?');
    });

    /* ---- realtime (Reverb) or polling ---- */
    handlers.message.push((e) => {
        const m = e.message;
        if (current && m.conversation_id === current.id) {
            addMessage(m);
            if (m.user_id !== cfg.me) {
                markRead();
                status.textContent = status.dataset.default || '';
            }
        } else if (m.user_id !== cfg.me) {
            ding();
        }
        refreshList();
    });
    handlers.read.push((e) => {
        if (current && e.conversation_id === current.id && e.user_id !== cfg.me) {
            otherRead = Math.max(otherRead, e.message_id);
            items.querySelectorAll('[data-tick]').forEach((t) => {
                if (Number(t.dataset.tick) <= otherRead) t.className = 'bi bi-check2-all text-info ms-1';
            });
        }
    });
    handlers.typing.push((e) => {
        if (!current || e.conversation_id !== current.id) return;
        status.innerHTML = '<span class="text-success">typing…</span>';
        clearTimeout(typingTimer);
        typingTimer = setTimeout(() => (status.textContent = status.dataset.default || ''), 4000);
    });
    handlers.deleted.push((e) => {
        if (current && e.message.conversation_id === current.id) {
            messages = messages.map((m) => (m.id === e.message.id ? e.message : m));
            render(true);
        }
        refreshList();
    });

    if (!window.Echo || !cfg.realtime) {
        setInterval(async () => {
            if (document.hidden) return;
            if (current) {
                const lastId = messages[messages.length - 1]?.id || 0;
                const data = await api(`${url('messages', current.id)}?after=${lastId}`).catch(() => null);
                if (data) {
                    data.messages.forEach(addMessage);
                    if (data.other_read !== otherRead) {
                        otherRead = data.other_read;
                        render(true);
                    }
                    if (data.messages.some((m) => m.user_id !== cfg.me)) markRead();
                }
            }
        }, 4000);
        setInterval(() => !document.hidden && refreshList().catch(() => {}), 15000);
    }

    document.addEventListener('visibilitychange', () => !document.hidden && markRead());

    if (cfg.open) open(cfg.open);
}
