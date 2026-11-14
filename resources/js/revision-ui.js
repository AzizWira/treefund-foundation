export function initRevisionUI(scope = document) {
    const money = value => 'Rp ' + new Intl.NumberFormat('id-ID').format(value);
    scope.querySelectorAll('[data-dialog-close]').forEach(button => {
        button.addEventListener('click', () => button.closest('dialog')?.close());
    });
    const checkout = scope.querySelector('[data-checkout]');
    const confirmation = scope.querySelector('#checkout-confirm');
    if (checkout && confirmation) {
        const agreement = checkout.elements.agree;
        if (agreement) {
            agreement.required = false;
            agreement.closest('label').hidden = true;
        }
        checkout.addEventListener('submit', event => {
            if (event.submitter?.hasAttribute('data-checkout-confirm')) {
                if (agreement) agreement.checked = true;
                confirmation.close();
                event.submitter.disabled = true;
                return;
            }
            event.preventDefault();
            const quantity = Number(checkout.elements.quantity.value);
            confirmation.querySelector('[data-confirm-quantity]').textContent = `${quantity} pohon`;
            confirmation.querySelector('[data-confirm-name]').textContent = checkout.elements.certificate_name.value.trim();
            confirmation.querySelector('[data-confirm-total]').textContent = money(quantity * Number(checkout.dataset.price));
            confirmation.showModal();
        });
    }
    let deleteForm;
    const deleteDialog = scope.querySelector('#member-delete-confirm');
    scope.querySelectorAll('[data-confirm]').forEach(form => {
        form.addEventListener('submit', event => {
            if (deleteDialog) {
                event.preventDefault();
                deleteForm = form;
                deleteDialog.querySelector('[data-member-name]').textContent = form.dataset.confirmName || '';
                deleteDialog.showModal();
            } else if (!window.confirm(form.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });
    deleteDialog?.querySelector('[data-confirm-delete]')?.addEventListener('click', event => {
        if (!deleteForm) return;
        event.currentTarget.disabled = true;
        deleteForm.submit();
    });
    scope.querySelectorAll('[data-print]').forEach(button => button.addEventListener('click', () => window.print()));
    scope.querySelectorAll('dialog').forEach(dialog => {
        dialog.addEventListener('close', () => {
            if (dialog.id.startsWith('review-') && location.hash === '#' + dialog.id) {
                history.replaceState(null, '', location.pathname + location.search);
            }
        });
    });
}
