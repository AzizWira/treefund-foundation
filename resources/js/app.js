import { initPaymentWatch } from './payment-watch';
import { initRevisionUI } from './revision-ui';
import { initFilterNavigation } from './filter-navigation';
import { initPaymentMethods, paymentOptions } from './payment-methods';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

L.Icon.Default.mergeOptions({ iconUrl: markerIcon, iconRetinaUrl: markerIcon2x, shadowUrl: markerShadow });
const maps = new Map();
const csrf = () => document.querySelector('meta[name=csrf-token]')?.content;
const money = value => 'Rp ' + new Intl.NumberFormat('id-ID').format(value);
const text = (tag, value) => { const element = document.createElement(tag); element.textContent = value; return element; };
let evidenceObserver;
let paymentWatch;

function baseMap(element, latitude = -8.12849, longitude = 114.28391, zoom = 12) {
    if (maps.has(element)) return maps.get(element);
    const map = L.map(element, { scrollWheelZoom: false, zoomAnimation: false }).setView([latitude, longitude], zoom);
    maps.set(element, map);
    element._treefundMap = map;
    const tiles = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}', {
        attribution: 'Tiles &copy; Esri &mdash; Sources: Esri, HERE, Garmin, Intermap, USGS, FAO, NPS, NRCAN, GeoBase, IGN, Kadaster NL, Ordnance Survey, Esri Japan, METI, Esri China (Hong Kong), OpenStreetMap contributors, and the GIS User Community',
        maxZoom: 19,
    }).addTo(map);
    let note;
    tiles.on('tileerror', () => {
        if (note) return;
        note = document.createElement('div'); note.className = 'map-network-note'; note.setAttribute('role', 'status');
        note.append(text('span', 'Peta dasar belum dapat dimuat. Data lokasi tetap tersedia.'));
        const retry = text('button', 'Coba ulang'); retry.type = 'button'; retry.className = 'text-link';
        retry.addEventListener('click', () => { note.remove(); note = null; tiles.redraw(); });
        note.append(retry); element.append(note); L.DomEvent.disableClickPropagation(note);
    });
    return map;
}

function fitMap(map, markers, maxZoom = 15) {
    if (markers.length) map.fitBounds(L.featureGroup(markers).getBounds().pad(.2), { maxZoom });
}

window.TreeFund = {
    miniMap(id) {
        const element = document.getElementById(id);
        if (!element || maps.has(element)) return;
        const map = baseMap(element);
        L.marker([-8.12849, 114.28391]).addTo(map);
    },
};

async function initMaps(scope) {
    scope.querySelectorAll('[data-coordinate-map]').forEach(element => {
        if (element.closest('dialog') && !element.closest('dialog').open) return;
        const map = baseMap(element, Number(element.dataset.lat), Number(element.dataset.lng));
        L.marker([Number(element.dataset.lat), Number(element.dataset.lng)]).addTo(map);
    });
    if (scope.querySelector('#plot-preview-map')) window.TreeFund.miniMap('plot-preview-map');
    const overview = scope.querySelector('[data-overview-map]');
    if (overview) {
        const map = baseMap(overview, -7.5, 112, 7);
        const rows = JSON.parse(scope.querySelector('#overview-map-data').textContent);
        const markers = rows.map(row => {
            const popup = document.createElement('div'); popup.append(text('strong', row.code), text('p', row.plot));
            return L.marker([row.latitude, row.longitude]).addTo(map).bindPopup(popup);
        });
        fitMap(map, markers);
    }
    const treeElement = scope.querySelector('#tree-map');
    if (treeElement) {
        const map = baseMap(treeElement, -7.5, 112, 7);
        try {
            const response = await fetch(treeElement.dataset.mapUrl, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error();
            const rows = await response.json();
            if (!treeElement.isConnected) return;
            const selected = Number(new URLSearchParams(location.search).get('tree'));
            const markers = rows.map(row => {
                const popup = document.createElement('div');
                popup.append(text('strong', row.species), text('p', row.code), text('p', row.plot), text('small', 'Diperbarui ' + row.updated_at));
                const marker = L.marker([row.latitude, row.longitude]).addTo(map).bindPopup(popup);
                marker.on('click', () => { if (selected !== row.id) location.assign('/trees?tree=' + encodeURIComponent(row.id)); });
                if (selected === row.id) { marker.openPopup(); map.setView([row.latitude, row.longitude], 15); }
                return marker;
            });
            if (!rows.some(row => row.id === selected)) fitMap(map, markers);
            scope.querySelector('[data-map-fit]')?.addEventListener('click', () => fitMap(map, markers));
        } catch {
            if (treeElement.isConnected) { const note = text('p', 'Peta belum dapat memuat titik. Foto dan timeline tetap dapat dibaca.'); note.className = 'map-network-note'; note.setAttribute('role', 'status'); treeElement.append(note); }
        }
    }
    const publicElement = scope.querySelector('#public-map');
    if (publicElement) {
        const status = scope.querySelector('[data-public-map-status]');
        const map = baseMap(publicElement, -5, 117, 5);
        try {
            const response = await fetch(publicElement.dataset.mapUrl, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error();
            const rows = await response.json();
            if (!publicElement.isConnected) return;
            const markers = rows.map(row => {
                const popup = document.createElement('div');
                popup.append(text('strong', row.name), text('p', row.location), text('p', row.group), text('small', row.species.join(', ') + ' · Tersedia ' + row.available + ' pohon'));
                const link = text('a', 'Lihat penawaran →'); link.href = '/catalog?q=' + encodeURIComponent(row.name); popup.append(link);
                return L.marker([row.latitude, row.longitude]).addTo(map).bindPopup(popup);
            });
            fitMap(map, markers, 12);
            scope.querySelector('[data-public-map-fit]')?.addEventListener('click', () => fitMap(map, markers, 12));
            status.textContent = rows.length + ' lahan terverifikasi tersedia pada katalog. Klik penanda untuk melihat penawaran.';
        } catch {
            if (status?.isConnected) status.textContent = 'Data peta belum dapat dimuat. Penawaran tetap tersedia pada katalog.';
        }
    }
}

function loadEvidence(image) {
    const source = image.dataset.evidenceSrc;
    if (!source) return;
    image.src = source;
    image.removeAttribute('data-evidence-src');
}

function openReview(button) {
    const dialog = document.getElementById(button.dataset.reviewTarget);
    if (!dialog || dialog.open) return;
    dialog.showModal();
    dialog.querySelectorAll('[data-evidence-src]').forEach(loadEvidence);
    requestAnimationFrame(() => dialog.querySelectorAll('[data-coordinate-map]').forEach(element => {
        if (!maps.has(element)) {
            const map = baseMap(element, Number(element.dataset.lat), Number(element.dataset.lng));
            L.marker([Number(element.dataset.lat), Number(element.dataset.lng)]).addTo(map);
        }
        maps.get(element).invalidateSize();
    }));
}

function initializePage(scope = document) {
    initRevisionUI(scope);
    initPaymentMethods(scope);
    scope.querySelectorAll('[data-checkout]').forEach(form => {
        const quantity = form.querySelector('[data-quantity]');
        const update = () => scope.querySelector('[data-total]').textContent = money(Number(quantity.value) * Number(form.dataset.price));
        quantity.addEventListener('input', update); update();
    });
    evidenceObserver?.disconnect();
    if ('IntersectionObserver' in window) {
        evidenceObserver = new IntersectionObserver(entries => entries.forEach(entry => {
            if (entry.isIntersecting) { loadEvidence(entry.target); evidenceObserver.unobserve(entry.target); }
        }), { rootMargin: '100px 0px' });
        scope.querySelectorAll('[data-evidence-src]').forEach(image => evidenceObserver.observe(image));
    } else scope.querySelectorAll('[data-evidence-src]').forEach(loadEvidence);
    initMaps(scope);
    const focused = new URLSearchParams(location.search).get('review');
    const target = /^[1-9][0-9]*$/.test(focused || '') ? 'review-' + focused : (location.hash.startsWith('#review-') ? location.hash.slice(1) : null);
    if (target) { const button = scope.querySelector(`[data-review-target="${CSS.escape(target)}"]`); if (button) openReview(button); }
    if (location.hash.startsWith('#edit-')) openDetails(location.hash.slice(1));
}

function openDetails(id) {
    const section = document.getElementById(id);
    const details = section?.querySelector('details');
    if (!details) return;
    details.open = true;
    section.scrollIntoView({ block: 'start', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' });
}

const sidebar = document.getElementById('sidebar');
const scrim = document.querySelector('[data-sidebar-close]');
function setSidebar(open) {
    sidebar?.classList.toggle('open', open);
    if (scrim) scrim.hidden = !open;
    document.querySelector('[data-sidebar]')?.setAttribute('aria-expanded', String(open));
}

document.addEventListener('click', event => {
    const button = event.target.closest('[data-sidebar],[data-sidebar-close],[data-review-target],[data-open-details],[data-gps],[data-public-menu]');
    if (button?.hasAttribute('data-sidebar')) setSidebar(!sidebar?.classList.contains('open'));
    else if (button?.hasAttribute('data-sidebar-close')) setSidebar(false);
    else if (button?.hasAttribute('data-review-target')) openReview(button);
    else if (button?.hasAttribute('data-open-details')) { event.preventDefault(); openDetails(button.dataset.openDetails); }
    else if (button?.hasAttribute('data-gps')) recordGPS(button);
    else if (button?.hasAttribute('data-public-menu')) {
        const open = document.getElementById('website-nav')?.classList.toggle('open');
        button.setAttribute('aria-expanded', String(!!open));
    }
    if (innerWidth <= 700 && sidebar?.classList.contains('open') && !sidebar.contains(event.target) && !event.target.closest('[data-sidebar]')) setSidebar(false);
    const decision = event.target.closest('button[name="decision"]');
    if (decision) { const note = decision.form?.querySelector('[name="review_note"],[name="note"]'); if (note) note.required = decision.value === 'revision'; }
    const demo = event.target.closest('[data-demo-account]');
    if (demo) {
        const form = document.querySelector('form[action="/login"]');
        if (form) { form.elements.email.value = demo.dataset.demoAccount; form.elements.password.value = 'TreeFund!2026'; form.requestSubmit(); }
    }
    const pay = event.target.closest('[data-pay]');
    if (pay) payOrder(pay);
    const back = event.target.closest('[data-back]');
    if (back) { if (history.length > 1) history.back(); else location.assign('/dashboard'); }
});
document.addEventListener('keydown', event => { if (event.key === 'Escape') setSidebar(false); });

function recordGPS(button) {
    const form = button.closest('form');
    const status = form?.querySelector('[data-gps-status]');
    if (!form || !navigator.geolocation) { if (status) status.textContent = 'GPS tidak tersedia. Masukkan koordinat secara manual.'; return; }
    button.disabled = true;
    if (status) status.textContent = 'Membaca lokasi perangkat…';
    navigator.geolocation.getCurrentPosition(position => {
        form.querySelector('[name=latitude]').value = position.coords.latitude.toFixed(7);
        form.querySelector('[name=longitude]').value = position.coords.longitude.toFixed(7);
        if (status) status.textContent = 'Koordinat terbaca. Akurasi perangkat sekitar ' + Math.round(position.coords.accuracy) + ' meter; tetap diperiksa Admin.';
        button.disabled = false;
    }, () => { button.disabled = false; if (status) status.textContent = 'Lokasi belum dapat diakses. Periksa izin browser atau masukkan koordinat secara manual.'; }, { enableHighAccuracy: true, timeout: 15000 });
}

document.addEventListener('change', event => {
    const input = event.target;
    if (!input.matches('input[type=file]')) return;
    input.setCustomValidity('');
    input.parentElement.querySelector('.photo-preview')?.remove();
    const file = input.files?.[0];
    if (!file) return;
    const max = Number(input.dataset.maxMb || (input.name === 'document' ? 10 : 5));
    if (file.size > max * 1024 * 1024) { input.setCustomValidity('Ukuran berkas maksimal ' + max + ' MB.'); input.reportValidity(); return; }
    if (!file.type.startsWith('image/')) return;
    const image = document.createElement('img'); image.className = 'photo-preview'; image.alt = 'Pratinjau berkas yang dipilih';
    const url = URL.createObjectURL(file); image.onload = () => URL.revokeObjectURL(url); image.src = url; input.parentElement.append(image);
});

async function payOrder(button) {
    if (button.disabled) return;
    const message = document.querySelector('[data-payment-message]');
    button.disabled = true; message.textContent = 'Menyiapkan pembayaran Sandbox…';
    try {
        const response = await fetch(button.dataset.pay, { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' } });
        const data = await response.json();
        if (!response.ok) throw new Error(Object.values(data.errors ?? {}).flat()[0] ?? data.message ?? 'Pembayaran belum dapat dibuka.');
        if (!window.snap) throw new Error('Snap Midtrans belum dimuat. Periksa koneksi internet dan client key.');
        paymentWatch?.start();
        window.snap.pay(data.token, {
            ...paymentOptions(button.dataset.method, button.dataset.bank),
            onSuccess: () => { message.textContent = 'Pembayaran diterima. Mengonfirmasi status ke server…'; paymentWatch?.check(); button.disabled = false; },
            onPending: () => { message.textContent = 'Menunggu pembayaran. Status diperiksa otomatis.'; paymentWatch?.start(); button.disabled = false; },
            onError: () => { message.textContent = 'Pembayaran belum berhasil. Periksa status pesanan sebelum mencoba kembali.'; button.disabled = false; },
            onClose: () => { message.textContent = 'Popup ditutup. Penutupan popup tidak membatalkan pembayaran.'; button.disabled = false; },
        });
    } catch (error) { message.textContent = error.message; button.disabled = false; }
}

initializePage();
paymentWatch = initPaymentWatch({ csrf });
initFilterNavigation({
    beforeReplace(main) {
        evidenceObserver?.disconnect();
        for (const [element, map] of maps) if (main.contains(element)) { map.remove(); maps.delete(element); }
    },
    afterReplace: initializePage,
});
window.addEventListener('pagehide', () => evidenceObserver?.disconnect());
window.addEventListener('pageshow', event => {
    if (event.persisted) document.querySelectorAll('[data-evidence-src]').forEach(image => evidenceObserver?.observe(image));
});
