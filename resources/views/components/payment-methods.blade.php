@props(['order'])
<div class="midtrans-widget" data-payment-methods>
    <div class="midtrans-header"><div><span class="midtrans-brand">midtrans</span><small class="midtrans-sub">SNAP SANDBOX GATEWAY</small></div><span class="badge badge-amber">Batas: {{ $order->expires_at->format('d M Y, H:i') }}</span></div>
    <p class="midtrans-section-title">Pilih Metode Pembayaran</p>
    <div class="midtrans-method-list" aria-label="Metode pembayaran Midtrans">
        <div class="midtrans-method-item">
            <button type="button" class="midtrans-method-trigger" data-method="credit_card" aria-expanded="false" aria-controls="method-card"><span class="midtrans-method-icon"><span class="material-symbols-outlined">credit_card</span></span><div class="midtrans-method-info"><strong>Credit / Debit Card</strong><small>Visa, Mastercard, JCB, Amex</small></div><div class="midtrans-card-badges"><span class="pay-chip">VISA</span><span class="pay-chip">MC</span><span class="pay-chip">JCB</span></div><span class="material-symbols-outlined">chevron_right</span></button>
            <div class="midtrans-method-body" id="method-card" hidden><label>Nomor Kartu Sandbox<input value="Diisi pada halaman aman Midtrans" readonly></label><div class="form-grid"><label>Masa Berlaku<input value="MM / YY" readonly></label><label>CVV<input value="•••" readonly></label></div><small class="muted">Masukkan kartu uji pada popup Midtrans Sandbox setelah menekan Bayar Sekarang.</small></div>
        </div>
        <div class="midtrans-method-item">
            <button type="button" class="midtrans-method-trigger" data-method="qris" aria-expanded="false" aria-controls="method-qris"><span class="midtrans-method-icon"><span class="material-symbols-outlined">qr_code_2</span></span><div class="midtrans-method-info"><strong>QRIS</strong><small>Bayar dengan aplikasi e-wallet atau mobile banking</small></div><span class="material-symbols-outlined">chevron_right</span></button>
            <div class="midtrans-method-body gopay-body" id="method-qris" hidden><div class="midtrans-qr-box" aria-hidden="true"><span class="material-symbols-outlined">qr_code_scanner</span><span>QRIS · {{ $order->reference }}</span></div><small class="muted">Kode QR pembayaran akan diterbitkan oleh Midtrans Sandbox. Gunakan simulator Sandbox untuk pengujian.</small></div>
        </div>
        <div class="midtrans-method-item">
            <button type="button" class="midtrans-method-trigger" data-method="gopay" aria-expanded="false" aria-controls="method-gopay"><span class="midtrans-method-icon gopay"><span class="material-symbols-outlined">account_balance_wallet</span></span><div class="midtrans-method-info"><strong>Go-Pay</strong><small>Pembayaran melalui dompet GoPay</small></div><div class="midtrans-card-badges"><span class="pay-chip gopay-chip">gopay</span></div><span class="material-symbols-outlined">chevron_right</span></button>
            <div class="midtrans-method-body gopay-body" id="method-gopay" hidden><small class="muted">Pembayaran GoPay dibuka melalui Midtrans Sandbox.</small></div>
        </div>
        <div class="midtrans-method-item active">
            <button type="button" class="midtrans-method-trigger" data-method="bank_transfer" aria-expanded="true" aria-controls="method-bank"><span class="midtrans-method-icon bank"><span class="material-symbols-outlined">account_balance</span></span><div class="midtrans-method-info"><strong>Bank Transfer / Virtual Account</strong><small>BCA, Mandiri, BNI, BRI, Permata VA</small></div><div class="midtrans-card-badges"><span class="pay-chip">BCA</span><span class="pay-chip">BNI</span><span class="pay-chip">BRI</span></div><span class="material-symbols-outlined">chevron_right</span></button>
            <div class="midtrans-method-body" id="method-bank"><div class="midtrans-bank-pills">@foreach(['bca_va'=>'BCA','echannel'=>'Mandiri','bni_va'=>'BNI','bri_va'=>'BRI','permata_va'=>'Permata'] as $bank=>$name)<button type="button" class="bank-pill {{ $loop->first?'active':'' }}" data-bank="{{ $bank }}" aria-pressed="{{ $loop->first?'true':'false' }}">{{ $name }}</button>@endforeach</div><div class="va-number-box"><small data-bank-name>BCA Virtual Account</small><strong>Diterbitkan oleh Midtrans</strong></div></div>
        </div>
    </div>
    <button type="button" class="button" data-pay="{{ route('orders.pay',$order) }}" data-method="bank_transfer" data-bank="bca_va"><span class="material-symbols-outlined">lock</span><span data-pay-label>Bayar Sekarang (BCA VA)</span></button>
    <p class="muted">Metode yang tersedia mengikuti konfigurasi merchant Midtrans Sandbox. Nomor VA dan QR pembayaran diterbitkan di popup Midtrans.</p>
</div>
