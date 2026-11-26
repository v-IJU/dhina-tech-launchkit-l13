// Import pages: rows with data-import-run poll their progress until the background import is done,
// then the page reloads once to show the final result.
const rows = document.querySelectorAll('[data-import-run]');

if (rows.length) {
    const timer = setInterval(async () => {
        let running = 0;
        await Promise.all([...rows].map(async (row) => {
            const res = await fetch(row.dataset.importRun, { headers: { Accept: 'application/json' }, credentials: 'same-origin' }).catch(() => null);
            if (!res?.ok) return;
            const run = await res.json();
            row.querySelector('[data-import-bar]').style.width = `${run.percent}%`;
            row.querySelector('[data-import-status]').textContent = run.status[0].toUpperCase() + run.status.slice(1);
            row.querySelector('[data-import-summary]').textContent = `${run.processed.toLocaleString()} / ${run.total.toLocaleString()} rows · ${run.summary}`;
            if (run.status === 'pending' || run.status === 'running') running++;
        }));
        if (!running) {
            clearInterval(timer);
            window.location.reload();
        }
    }, 3000);
}
