const banks = { bca_va: 'BCA Virtual Account', echannel: 'Mandiri Bill Payment', bni_va: 'BNI Virtual Account', bri_va: 'BRI Virtual Account', permata_va: 'Permata Virtual Account' };

export function paymentOptions(method, bank = 'bca_va') {
    if (method === 'credit_card') return { enabledPayments: ['credit_card'], selectedPaymentType: 'credit_card' };
    if (method === 'qris') return { enabledPayments: ['other_qris'], uiMode: 'qr' };
    if (method === 'gopay') return { enabledPayments: ['gopay'] };
    if (method === 'bank_transfer' && Object.hasOwn(banks, bank)) {
        const options = { enabledPayments: [bank] };
        if (['bca_va', 'echannel', 'bni_va', 'permata_va'].includes(bank)) options.selectedPaymentType = bank;
        return options;
    }
    return {};
}

export function initPaymentMethods(scope = document) {
    scope.querySelectorAll('[data-payment-methods]').forEach(widget => {
        const pay = widget.querySelector('[data-pay]');
        let method = 'bank_transfer';
        let bank = 'bca_va';
        const update = () => {
            widget.querySelectorAll('.midtrans-method-trigger[data-method]').forEach(trigger => {
                const active = trigger.dataset.method === method;
                trigger.setAttribute('aria-expanded', String(active));
                trigger.lastElementChild.textContent = active ? 'expand_less' : 'chevron_right';
                trigger.closest('.midtrans-method-item').classList.toggle('active', active);
                const body = trigger.closest('.midtrans-method-item').querySelector('.midtrans-method-body');
                if (body) body.hidden = !active;
            });
            widget.querySelectorAll('.bank-pill[data-bank]').forEach(button => {
                const active = button.dataset.bank === bank;
                button.classList.toggle('active', active);
                button.setAttribute('aria-pressed', String(active));
            });
            widget.querySelector('[data-bank-name]').textContent = banks[bank];
            pay.dataset.method = method;
            pay.dataset.bank = bank;
            widget.querySelector('[data-pay-label]').textContent = 'Bayar Sekarang (' + ({ credit_card: 'Credit Card', qris: 'QRIS', gopay: 'Go-Pay' }[method] || banks[bank].split(' ')[0] + ' VA') + ')';
        };
        widget.querySelectorAll('.midtrans-method-trigger[data-method]').forEach(trigger => trigger.addEventListener('click', () => { method = trigger.dataset.method; update(); }));
        widget.querySelectorAll('.bank-pill[data-bank]').forEach(button => button.addEventListener('click', () => { bank = button.dataset.bank; update(); }));
        update();
    });
}
