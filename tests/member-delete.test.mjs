import test from 'node:test';
import assert from 'node:assert/strict';
import { initMemberDelete } from '../resources/js/member-delete.js';

function fixture() {
    const handlers = {};
    const dialogHandlers = {};
    const button = { disabled: false };
    const name = { textContent: '' };
    const dialog = {
        open: false,
        classList: { contains: () => false },
        querySelector: selector => selector === '[data-confirm-delete]' ? button : name,
        addEventListener: (type, fn) => { dialogHandlers[type] = fn; },
        showModal() { this.open = true; },
    };
    const scope = { querySelector: () => dialog, addEventListener: (type, fn) => { handlers[type] = fn; } };
    let submissions = 0;
    const form = {
        dataset: { confirmName: '<script>Nama Anggota</script>' }, isConnected: true,
        matches: () => true,
        requestSubmit() {
            let prevented = false;
            handlers.submit({ target: this, preventDefault: () => { prevented = true; } });
            if (!prevented) submissions++;
        },
    };
    const close = () => { dialog.open = false; dialogHandlers.close(); };
    initMemberDelete(scope, close);
    return { dialog, button, name, form, close, handlers, dialogHandlers, count: () => submissions,
        click: selector => dialogHandlers.click({ target: { closest: value => value === selector } }) };
}

test('opening confirmation keeps the member and renders its name as text', () => {
    const f = fixture();
    f.form.requestSubmit();
    assert.equal(f.count(), 0);
    assert.equal(f.dialog.open, true);
    assert.equal(f.name.textContent, '<script>Nama Anggota</script>');
});

test('cancel closes confirmation without submitting and clears the pending member', () => {
    const f = fixture();
    f.form.requestSubmit();
    f.click('[data-dialog-close]');
    assert.equal(f.count(), 0);
    assert.equal(f.dialog.open, false);
    assert.equal(f.name.textContent, '');
    f.click('[data-confirm-delete]');
    assert.equal(f.count(), 0);
});

test('confirmation submits exactly once even on repeated clicks', () => {
    const f = fixture();
    f.form.requestSubmit();
    f.click('[data-confirm-delete]');
    f.click('[data-confirm-delete]');
    assert.equal(f.count(), 1);
    assert.equal(f.button.disabled, true);
});

test('a detached form cannot be submitted', () => {
    const f = fixture();
    f.form.requestSubmit();
    f.form.isConnected = false;
    f.click('[data-confirm-delete]');
    assert.equal(f.count(), 0);
});
