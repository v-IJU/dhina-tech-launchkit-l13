// Dashboard charts. Data comes from data-chart attributes rendered by the server.
import { Chart, LineController, LineElement, PointElement, LinearScale, CategoryScale, Filler, Tooltip, Legend, DoughnutController, ArcElement } from 'chart.js';

Chart.register(LineController, LineElement, PointElement, LinearScale, CategoryScale, Filler, Tooltip, Legend, DoughnutController, ArcElement);

const css = getComputedStyle(document.documentElement);
const grid = () => css.getPropertyValue('--bs-border-color').trim() || '#e1e6ef';
const text = () => css.getPropertyValue('--bs-secondary-color').trim() || '#566176';

document.querySelectorAll('canvas[data-chart="line"]').forEach((canvas) => {
    const data = JSON.parse(canvas.dataset.values);
    new Chart(canvas, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: data.series.map((s, i) => ({
                label: s.label,
                data: s.values,
                borderColor: s.color,
                backgroundColor: i === 0 ? 'rgba(37, 99, 235, 0.12)' : 'transparent',
                fill: i === 0,
                tension: 0.35,
                borderWidth: 2.5,
                pointRadius: 0,
                pointHoverRadius: 4,
                borderDash: i === 0 ? [] : [5, 4],
            })),
        },
        options: {
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'bottom', labels: { color: text(), usePointStyle: true, boxHeight: 6 } } },
            scales: {
                x: { grid: { display: false }, ticks: { color: text(), maxTicksLimit: 8 } },
                y: { beginAtZero: true, grid: { color: grid() }, ticks: { color: text(), precision: 0 } },
            },
        },
    });
});

document.querySelectorAll('canvas[data-chart="doughnut"]').forEach((canvas) => {
    const data = JSON.parse(canvas.dataset.values);
    new Chart(canvas, {
        type: 'doughnut',
        data: { labels: data.labels, datasets: [{ data: data.values, backgroundColor: data.colors, borderWidth: 0 }] },
        options: { cutout: '72%', plugins: { legend: { display: false } } },
    });
});
