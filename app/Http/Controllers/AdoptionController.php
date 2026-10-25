<?php

namespace App\Http\Controllers;

use App\Models\Adoption;
use App\Models\Offer;
use App\Services\AdoptionService;
use Illuminate\Http\Request;

class AdoptionController extends Controller
{
    public function checkout(int $offer): mixed
    {
        $offer = Offer::published()->with('plot.partner')->findOrFail($offer);
        abort_if($offer->plot->available < 1, 404);

        return view('checkout', compact('offer'));
    }

    public function store(Request $request, AdoptionService $service): mixed
    {
        $data = $request->validate([
            'offer_id' => 'required|integer|exists:offers,id',
            'quantity' => 'required|integer|min:1|max:1000',
            'certificate_name' => 'required|string|max:150',
            'agree' => 'accepted',
        ]);
        $order = $service->checkout($request->user(), Offer::findOrFail($data['offer_id']), (int) $data['quantity'], $data['certificate_name']);

        return redirect()->route('orders.show', $order)->with('success', 'Pesanan dibuat. Kuota dicadangkan hingga batas waktu pembayaran.');
    }

    public function orders(Request $request): mixed
    {
        $filters = $request->validate(['q' => 'nullable|string|max:190', 'status' => 'nullable|in:pending,paid,failed,expired,review']);
        $search = trim($filters['q'] ?? '');
        $orders = Adoption::where('user_id', $request->user()->id)->with('items.offer.plot')
            ->when($search !== '', fn ($query) => $query->where(fn ($matching) => $matching
                ->where('reference', 'like', '%'.$search.'%')
                ->orWhere('certificate_name', 'like', '%'.$search.'%')
                ->orWhereHas('items.offer', fn ($offer) => $offer->where('species', 'like', '%'.$search.'%')
                    ->orWhereHas('plot', fn ($plot) => $plot->where('name', 'like', '%'.$search.'%')))))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest('id')->paginate(12)->withQueryString();

        return view('orders', compact('orders'));
    }

    public function show(Request $request, Adoption $order): mixed
    {
        $this->owner($request, $order);
        $order->load('items.offer.plot');

        return view('order', compact('order'));
    }

    private function owner(Request $request, Adoption $order): void
    {
        abort_unless($order->user_id === $request->user()->id, 403);
    }
}
