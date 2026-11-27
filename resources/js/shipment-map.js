import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const COLORS = {
    origin: '#16a34a',
    destination: '#dc2626',
    tracking: '#2563eb',
    current: '#f59e0b',
    shipment: '#2563eb',
    delayed: '#dc2626',
};

// Marker berupa lingkaran CSS (tanpa file gambar), warnanya menurut jenis titik
function icon(type) {
    const color = COLORS[type] ?? '#6b7280';

    return L.divIcon({
        className: '',
        html: `<span style="display:block;width:16px;height:16px;border-radius:9999px;background:${color};border:2px solid #fff;box-shadow:0 0 0 1px rgba(0,0,0,.35)"></span>`,
        iconSize: [16, 16],
        iconAnchor: [8, 8],
        popupAnchor: [0, -10],
    });
}

function popupNode(title, lines) {
    const box = document.createElement('div');
    const heading = document.createElement('strong');
    heading.textContent = title;
    box.appendChild(heading);

    (lines ?? []).forEach((line) => {
        const row = document.createElement('div');
        row.textContent = line;
        box.appendChild(row);
    });

    return box;
}

function initMap(el) {
    const wrapper = el.parentElement;
    const emptyNote = wrapper.querySelector('[data-map-empty]');
    const errorNote = wrapper.querySelector('[data-map-error]');

    let data = null;
    try {
        data = JSON.parse(el.dataset.map);
    } catch (e) {
        data = null;
    }

    // Data kosong: tampilkan pesan, jangan buat peta
    if (!data || !Array.isArray(data.markers) || data.markers.length === 0) {
        el.classList.add('hidden');
        emptyNote?.classList.remove('hidden');
        return;
    }

    const map = L.map(el, { scrollWheelZoom: false });

    const tiles = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(map);

    // Kalau tile gagal dimuat (offline atau diblokir), beri tahu pengguna
    tiles.on('tileerror', () => errorNote?.classList.remove('hidden'));

    (data.lines ?? []).forEach((line) => {
        const planned = line.style === 'planned';

        L.polyline(line.points, {
            color: planned ? '#6b7280' : '#2563eb',
            weight: planned ? 2 : 3,
            opacity: planned ? 0.6 : 0.9,
            dashArray: planned ? '6 8' : null,
        }).addTo(map);
    });

    const bounds = [];

    data.markers.forEach((m) => {
        L.marker([m.lat, m.lng], { icon: icon(m.type) })
            .bindPopup(popupNode(m.title, m.lines))
            .addTo(map);
        bounds.push([m.lat, m.lng]);
    });

    map.fitBounds(bounds, { padding: [30, 30], maxZoom: 8 });
}

document.querySelectorAll('[data-shipment-map]').forEach(initMap);