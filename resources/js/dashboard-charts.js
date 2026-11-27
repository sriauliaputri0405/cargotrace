import Chart from 'chart.js/auto';

const STATUS_COLORS = ['#9ca3af', '#38bdf8', '#818cf8', '#3b82f6', '#22c55e'];
const PALETTE = ['#3b82f6', '#f97316', '#6b7280', '#10b981', '#8b5cf6', '#ef4444', '#eab308'];

// Baca data dari atribut. Kalau kosong, tampilkan pesan "belum ada data".
function load(id) {
    const canvas = document.getElementById(id);
    if (!canvas) return null;

    let data = null;
    try {
        data = JSON.parse(canvas.dataset.chart);
    } catch (e) {
        data = null;
    }

    const empty = !data || !Array.isArray(data.values) || data.values.every((v) => v === 0);

    if (empty) {
        canvas.classList.add('hidden');
        canvas.parentElement.querySelector('[data-chart-empty]')?.classList.remove('hidden');
        return null;
    }

    return { canvas, data };
}

const status = load('chart-status');
if (status) {
    new Chart(status.canvas, {
        type: 'bar',
        data: {
            labels: status.data.labels,
            datasets: [{
                label: 'Shipments',
                data: status.data.values,
                backgroundColor: STATUS_COLORS,
                borderRadius: 4,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
        },
    });
}

const carrier = load('chart-carrier');
if (carrier) {
    new Chart(carrier.canvas, {
        type: 'doughnut',
        data: {
            labels: carrier.data.labels,
            datasets: [{
                data: carrier.data.values,
                backgroundColor: carrier.data.labels.map((_, i) => PALETTE[i % PALETTE.length]),
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'right', labels: { boxWidth: 12 } },
                tooltip: {
                    callbacks: {
                        label: (ctx) => {
                            const i = ctx.dataIndex;
                            return `${ctx.label}: ${carrier.data.values[i]} terlambat dari ${carrier.data.totals[i]} (${carrier.data.rates[i]}%)`;
                        },
                    },
                },
            },
        },
    });
}