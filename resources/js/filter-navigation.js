export function initFilterNavigation({ beforeReplace, afterReplace }) {
    let request;
    let timer;
    let sequence = 0;
    const prepare = scope => {
        scope.querySelectorAll('.filter-bar [data-filter-submit]').forEach(button => { button.hidden = true; });
    };
    const formKey = form => JSON.stringify([
        new URL(form.action || location.href).pathname,
        form.method,
        Array.from(form.querySelectorAll('input[type=hidden]')).filter(input => input.name !== '_token').map(input => [input.name, input.value]),
    ]);
    function preserveDrafts(main) {
        const drafts = new Map();
        main.querySelectorAll('form:not(.filter-bar)').forEach(form => {
            const fields = [];
            Array.from(form.elements).forEach(input => {
                if (!input.name || input.disabled || input.readOnly || ['hidden', 'submit', 'button'].includes(input.type)) return;
                if (input.type === 'file') {
                    if (input.files?.length) fields.push({ name: input.name, file: input });
                } else if (['checkbox', 'radio'].includes(input.type)) {
                    if (input.checked !== input.defaultChecked) fields.push({ name: input.name, checked: input.checked, value: input.value });
                } else if (input.tagName === 'SELECT') {
                    const initial = Array.from(input.options).find(option => option.defaultSelected)?.value ?? input.options[0]?.value;
                    if (input.value !== initial) fields.push({ name: input.name, value: input.value });
                } else if (input.value !== input.defaultValue) fields.push({ name: input.name, value: input.value });
            });
            if (fields.length || form.closest('details')?.open) drafts.set(formKey(form), { fields, open: form.closest('details')?.open });
        });
        return drafts;
    }
    function restoreDrafts(main, drafts) {
        main.querySelectorAll('form:not(.filter-bar)').forEach(form => {
            const draft = drafts.get(formKey(form));
            if (!draft) return;
            if (draft.open && form.closest('details')) form.closest('details').open = true;
            for (const field of draft.fields) {
                const input = Array.from(form.elements).find(input => input.name === field.name && (!['checkbox', 'radio'].includes(input.type) || input.value === field.value));
                if (!input || input.disabled || input.closest('fieldset[disabled]')) continue;
                if (field.file) { input.replaceWith(field.file); field.file.dispatchEvent(new Event('change', { bubbles: true })); }
                else if ('checked' in field) input.checked = field.checked;
                else input.value = field.value;
            }
        });
    }
    prepare(document);
    async function navigate(url, focusName = null, selection = null, updateHistory = true) {
        request?.abort();
        request = new AbortController();
        const current = ++sequence;
        const previousScroll = window.scrollY;
        const main = document.querySelector('main');
        main?.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' }, signal: request.signal });
            if (!response.ok || new URL(response.url).pathname !== new URL(url, location.href).pathname) throw new Error('Page changed');
            const documentResult = new DOMParser().parseFromString(await response.text(), 'text/html');
            const nextMain = documentResult.querySelector('main');
            if (!main || !nextMain) throw new Error('Page unavailable');
            if (current !== sequence) return;
            const drafts = preserveDrafts(main);
            beforeReplace(main);
            main.replaceWith(nextMain);
            document.title = documentResult.title;
            if (updateHistory) history.replaceState(null, '', url);
            prepare(nextMain);
            afterReplace(nextMain);
            restoreDrafts(nextMain, drafts);
            if (focusName) {
                const input = Array.from(nextMain.querySelectorAll('input,select')).find(element => element.name === focusName);
                input?.focus({ preventScroll: true });
                if (selection && input?.setSelectionRange) input.setSelectionRange(...selection);
            }
            window.scrollTo({ top: previousScroll, behavior: 'instant' });
        } catch (error) {
            if (error.name !== 'AbortError' && current === sequence) window.location.assign(url);
        } finally {
            if (current === sequence) document.querySelector('main')?.removeAttribute('aria-busy');
        }
    }
    function submit(form) {
        const active = document.activeElement;
        const focusName = form.contains(active) ? active.name : null;
        const selection = active?.tagName === 'INPUT' && ['text', 'search'].includes(active.type) ? [active.selectionStart, active.selectionEnd] : null;
        const url = new URL(form.action || location.href);
        const params = new URLSearchParams(new FormData(form));
        for (const [key, value] of [...params]) if (!value) params.delete(key);
        url.search = params.toString();
        navigate(url.href, focusName, selection);
    }
    document.addEventListener('input', event => {
        const form = event.target.closest('form.filter-bar');
        if (!form || !event.target.matches('input:not([type=hidden])')) return;
        clearTimeout(timer);
        request?.abort();
        timer = setTimeout(() => submit(form), 300);
    });
    document.addEventListener('change', event => {
        const form = event.target.closest('form.filter-bar');
        if (form && event.target.matches('select')) { clearTimeout(timer); submit(form); }
    });
    document.addEventListener('submit', event => {
        if (!event.target.matches('form.filter-bar')) return;
        event.preventDefault(); clearTimeout(timer); submit(event.target);
    });
    document.addEventListener('click', event => {
        const link = event.target.closest('a.filter-reset-btn,nav.pagination a');
        if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button) return;
        const url = new URL(link.href);
        if (url.origin !== location.origin) return;
        event.preventDefault(); clearTimeout(timer); navigate(url.href);
    });
    window.addEventListener('pagehide', () => { clearTimeout(timer); request?.abort(); });
}
