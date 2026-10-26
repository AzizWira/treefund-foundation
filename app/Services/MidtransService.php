<?php

namespace App\Services;

use App\Models\Adoption;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class MidtransService
{
    private function key(): string
    {
        $key = (string) config('treefund.midtrans.server_key');
        if ($key === '') {
            throw ValidationException::withMessages(['payment' => 'Pembayaran belum dikonfigurasi. Hubungi pengelola untuk mengaktifkan pembayaran Sandbox.']);
        }
        if (! str_starts_with($key, 'SB-Mid-server-')) {
            throw ValidationException::withMessages(['payment' => 'Konfigurasi pembayaran belum sesuai lingkungan Sandbox. Hubungi pengelola.']);
        }

        return $key;
    }

    public function token(Adoption $order): string
    {
        $this->key();
        if (! str_starts_with((string) config('treefund.midtrans.client_key'), 'SB-Mid-client-')) {
            throw ValidationException::withMessages(['payment' => 'Konfigurasi tampilan pembayaran Sandbox belum siap. Hubungi pengelola.']);
        }

        return Cache::lock('snap-order-'.$order->id, 35)->block(5, function () use ($order) {
            $order->refresh();
            abort_unless($order->status === 'pending' && $order->expires_at->isFuture(), 409, 'Pesanan tidak dapat dibayar.');
            if ($order->snap_token) {
                return $order->snap_token;
            }
            $minutes = max(1, (int) now()->diffInMinutes($order->expires_at, false));
            $result = Http::withBasicAuth($this->key(), '')->acceptJson()->timeout(20)->post(config('treefund.midtrans.snap_url'), [
                'transaction_details' => ['order_id' => $order->reference, 'gross_amount' => $order->total],
                'customer_details' => ['first_name' => $order->user->name, 'email' => $order->user->email, 'phone' => $order->user->phone],
                'item_details' => $order->items->map(fn ($item) => ['id' => (string) $item->offer_id, 'price' => $item->unit_price, 'quantity' => $item->quantity, 'name' => mb_substr($item->offer->species, 0, 45)])->all(),
                'credit_card' => ['secure' => true],
                'expiry' => ['unit' => 'minute', 'duration' => $minutes],
                'callbacks' => ['finish' => route('orders.show', $order)],
            ])->throw()->json();
            if (empty($result['token'])) {
                throw ValidationException::withMessages(['payment' => 'Midtrans belum mengembalikan token. Gunakan pemeriksaan status sebelum mencoba kembali.']);
            }
            $order->update(['snap_token' => $result['token'], 'payment_url' => $result['redirect_url'] ?? null]);

            return $result['token'];
        });
    }

    public function status(Adoption $order): array
    {
        $data = Http::withBasicAuth($this->key(), '')->acceptJson()->timeout(15)->get(config('treefund.midtrans.api_url').'/'.rawurlencode($order->reference).'/status')->throw()->json();
        $this->validateIdentity($order, $data);

        return $data;
    }

    public function validateIdentity(Adoption $order, array $data): void
    {
        abort_unless(($data['order_id'] ?? null) === $order->reference, 422, 'Nomor pesanan tidak cocok.');
        $gross = (string) ($data['gross_amount'] ?? '');
        abort_unless(preg_match('/^0*'.preg_quote((string) $order->total, '/').'(\.0{1,2})?$/', $gross), 422, 'Nominal pembayaran tidak cocok.');
    }

    public function notification(array $payload): void
    {
        $signature = hash('sha512', ($payload['order_id'] ?? '').($payload['status_code'] ?? '').($payload['gross_amount'] ?? '').$this->key());
        abort_unless(hash_equals($signature, (string) ($payload['signature_key'] ?? '')), 403, 'Signature Midtrans tidak valid.');
        $order = Adoption::where('reference', $payload['order_id'] ?? '')->firstOrFail();
        $this->validateIdentity($order, $payload);
        // Status is obtained from the authenticated API: signature does not cover transaction_status.
        app(AdoptionService::class)->applyProviderStatus($order, $this->status($order));
    }
}
