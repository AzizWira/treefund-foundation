import { initMemberDelete } from './member-delete.js';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

L.Icon.Default.mergeOptions({ iconUrl: markerIcon, iconRetinaUrl: markerIcon2x, shadowUrl: markerShadow });

const maps = new Map();
const text = (tag, value) => { const element = document.createElement(tag); element.textContent = value; return element; };
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


async function initPublicMap(scope = document) {
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

function openReview(dialog) {
    if (!dialog || dialog.open) return;
    dialog.showModal();
    requestAnimationFrame(() => dialog.querySelectorAll('[data-coordinate-map]').forEach(element => {
        if (!maps.has(element)) {
            const latitude = Number(element.dataset.lat);
            const longitude = Number(element.dataset.lng);
            const map = baseMap(element, latitude, longitude);
            L.marker([latitude, longitude]).addTo(map);
        }
        maps.get(element).invalidateSize();
    }));
}

function closeReview(dialog, returnValue = '') {
    if (!dialog.open || dialog.classList.contains('is-closing')) return;
    if (matchMedia('(prefers-reduced-motion: reduce)').matches) {
        dialog.close(returnValue);
        return;
    }
    let fallback;
    const finish = () => {
        clearTimeout(fallback);
        dialog.removeEventListener('animationend', onAnimationEnd);
        if (dialog.open) dialog.close(returnValue);
        dialog.classList.remove('is-closing');
    };
    const onAnimationEnd = event => {
        if (event.target === dialog && event.animationName === 'review-drawer-out') finish();
    };
    dialog.addEventListener('animationend', onAnimationEnd);
    dialog.classList.add('is-closing');
    fallback = setTimeout(finish, 280);
}

document.querySelectorAll('dialog.review-dialog').forEach(dialog => {
    dialog.addEventListener('cancel', event => {
        event.preventDefault();
        closeReview(dialog);
    });
});
document.addEventListener('submit', event => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || form.method !== 'dialog') return;
    const dialog = form.closest('dialog.review-dialog');
    if (!dialog) return;
    event.preventDefault();
    closeReview(dialog, event.submitter?.value ?? '');
});

const sidebar = document.getElementById('sidebar');
const scrim = document.querySelector('[data-sidebar-close]');
function setSidebar(open) {
    sidebar?.classList.toggle('open', open);
    if (scrim) scrim.hidden = !open;
    document.querySelector('[data-sidebar]')?.setAttribute('aria-expanded', String(open));
}
document.addEventListener('click', event => {
    const trigger = event.target.closest('[data-sidebar],[data-sidebar-close],[data-public-menu],[data-demo-account]');
    if (trigger?.hasAttribute('data-sidebar')) setSidebar(!sidebar?.classList.contains('open'));
    else if (trigger?.hasAttribute('data-sidebar-close')) setSidebar(false);
    else if (trigger?.hasAttribute('data-public-menu')) {
        const open = document.getElementById('website-nav')?.classList.toggle('open');
        trigger.setAttribute('aria-expanded', String(!!open));
    } else if (trigger?.hasAttribute('data-demo-account')) {
        const form = document.querySelector('form[action="/login"]');
        if (form) { form.elements.email.value = trigger.dataset.demoAccount; form.elements.password.value = 'TreeFund!2026'; form.requestSubmit(); }
    }
    const review = event.target.closest('[data-review-target]');
    if (review) {
        const dialog = document.getElementById(review.dataset.reviewTarget);
        openReview(dialog);
    }
    const back = event.target.closest('[data-back]');
    if (back) { if (history.length > 1) history.back(); else location.assign('/dashboard'); }
    const anchor = event.target.closest('a[href^="#"]');
    if (anchor && anchor.hash.length > 1) {
        const destination = document.getElementById(decodeURIComponent(anchor.hash.slice(1)));
        if (destination) { event.preventDefault(); destination.scrollIntoView({behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block: 'start'}); }
    }
});
document.addEventListener('keydown', event => { if (event.key === 'Escape') setSidebar(false); });
if (document.getElementById('plot-preview-map')) window.TreeFund.miniMap('plot-preview-map');
initPublicMap();
const focusedReview = new URLSearchParams(location.search).get('review');
const reviewTarget = /^[1-9][0-9]*$/.test(focusedReview || '') ? 'review-' + focusedReview : (location.hash.startsWith('#review-') ? location.hash.slice(1) : null);
if (reviewTarget) {
    const dialog = document.getElementById(reviewTarget);
    if (dialog instanceof HTMLDialogElement) openReview(dialog);
}

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


document.addEventListener('click', event => {
    const button = event.target.closest('[data-gps]');
    if (button && !button.disabled) recordGPS(button);
});

document.addEventListener('click', event => {
    const detailsTrigger = event.target.closest('[data-open-details]');
    if (!detailsTrigger) return;
    const section = document.getElementById(detailsTrigger.dataset.openDetails);
    const details = section?.querySelector('details');
    if (!details) return;
    event.preventDefault();
    details.open = true;
    section.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block: 'start' });
});

initMemberDelete(document, closeReview);
