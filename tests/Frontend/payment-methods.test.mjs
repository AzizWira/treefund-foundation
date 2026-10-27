import test from 'node:test';
import assert from 'node:assert/strict';
import { paymentOptions } from '../../resources/js/payment-methods.js';

test('QRIS uses Midtrans Other QRIS instead of a locally generated payment result', () => {
    assert.deepEqual(paymentOptions('qris'), { enabledPayments: ['other_qris'], uiMode: 'qr' });
});

test('bank selections map to the provider payment channels', () => {
    assert.deepEqual(paymentOptions('bank_transfer', 'echannel'), { enabledPayments: ['echannel'], selectedPaymentType: 'echannel' });
    assert.deepEqual(paymentOptions('bank_transfer', 'bni_va'), { enabledPayments: ['bni_va'], selectedPaymentType: 'bni_va' });
});

test('BRI does not request a selectedPaymentType unsupported by Snap JS', () => {
    assert.deepEqual(paymentOptions('bank_transfer', 'bri_va'), { enabledPayments: ['bri_va'] });
});

test('unknown methods cannot pass arbitrary provider options through the selector', () => {
    assert.deepEqual(paymentOptions('bank_transfer', 'unknown_channel'), {});
    assert.deepEqual(paymentOptions('unsupported'), {});
});
