export function initMemberDelete(scope, closeDialog) {
    const dialog = scope.querySelector('#member-delete-confirm');
    if (!dialog) return;
    const confirmButton = dialog.querySelector('[data-confirm-delete]');
    const memberName = dialog.querySelector('[data-member-name]');
    let pendingForm = null;
    const confirmedForms = new WeakSet();

    scope.addEventListener('submit', event => {
        const form = event.target;
        if (!form.matches('form[data-confirm-name]')) return;
        if (confirmedForms.has(form)) {
            confirmedForms.delete(form);
            return;
        }
        event.preventDefault();
        if (dialog.open || dialog.classList.contains('is-closing')) return;
        pendingForm = form;
        memberName.textContent = form.dataset.confirmName;
        confirmButton.disabled = false;
        dialog.showModal();
    });

    dialog.addEventListener('click', event => {
        if (event.target.closest('[data-dialog-close]')) {
            closeDialog(dialog);
            return;
        }
        if (!event.target.closest('[data-confirm-delete]') || confirmButton.disabled || !pendingForm?.isConnected) return;
        confirmButton.disabled = true;
        confirmedForms.add(pendingForm);
        pendingForm.requestSubmit();
    });

    dialog.addEventListener('close', () => {
        pendingForm = null;
        memberName.textContent = '';
        confirmButton.disabled = false;
    });
}
