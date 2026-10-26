export function initPaymentWatch({
    document = globalThis.document,
    window = globalThis.window,
    navigator = globalThis.navigator,
    fetch = globalThis.fetch,
    setTimeout = globalThis.setTimeout,
    clearTimeout = globalThis.clearTimeout,
    AbortController = globalThis.AbortController,
    csrf = () => document.querySelector('meta[name=csrf-token]')?.content,
} = {}) {
    const element = document.querySelector('[data-payment-watch]');
    if (!element) return;
    const notice = document.querySelector('[data-payment-watch-status]');
    let enabled = element.dataset.paymentIssued === 'true';
    let stopped = false;
    let busy = false;
    let timer;
    let failures = 0;
    let controller = new AbortController();
    const schedule = (delay = 10000) => {
        clearTimeout(timer);
        if (enabled && !stopped) timer = setTimeout(check, delay);
    };
    async function check() {
        if (!enabled || stopped || busy) return;
        if (document.hidden || !navigator.onLine) { schedule(); return; }
        busy = true;
        let delay = 10000;
        try {
            const response = await fetch(element.dataset.syncUrl, {
                method: 'POST', credentials: 'same-origin', signal: controller.signal,
                headers: {'X-CSRF-TOKEN': csrf(), Accept: 'application/json'}
            });
            if (response.status === 401 || response.status === 419) {
                stopped = true;
                notice.textContent = 'Sesi berakhir. Muat ulang halaman untuk melanjutkan pemeriksaan.';
                return;
            }
            if (response.status === 429) {
                delay = Math.max(15000, Number(response.headers.get('Retry-After') || 30) * 1000);
                return;
            }
            if (!response.ok) throw new Error();
            const data = await response.json();
            failures = 0;
            if (data.status && data.status !== 'pending') {
                stopped = true;
                notice.textContent = data.status === 'paid' ? 'Pembayaran berhasil. Memperbarui sertifikat dan pohon Anda…' : 'Status pembayaran berubah. Memperbarui halaman…';
                window.location.reload();
                return;
            }
            notice.textContent = 'Menunggu pembayaran. Status diperiksa otomatis setiap 10 detik.';
        } catch (error) {
            if (error.name === 'AbortError') return;
            failures++;
            delay = Math.min(30000, 10000 + failures * 5000);
            notice.textContent = 'Pemeriksaan otomatis akan dicoba kembali. Tombol Periksa Status Pembayaran tetap dapat digunakan.';
        } finally {
            busy = false;
            schedule(delay);
        }
    }
    document.addEventListener('visibilitychange', () => { if (!document.hidden) check(); });
    window.addEventListener('online', check);
    window.addEventListener('pagehide', () => { stopped = true; clearTimeout(timer); controller.abort(); });
    window.addEventListener('pageshow', event => {
        if (event.persisted) {
            stopped = false;
            busy = false;
            controller = new AbortController();
            schedule(1200);
        }
    });
    const watcher = {
        start() { enabled = true; schedule(1200); },
        check() { enabled = true; clearTimeout(timer); return check(); }
    };
    if (enabled) schedule(1200);
    return watcher;
}
