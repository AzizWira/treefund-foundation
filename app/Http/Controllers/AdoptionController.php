<?php

namespace App\Http\Controllers;

use App\Models\Adoption;
use App\Models\Offer;
use App\Models\Tree;
use App\Services\AdoptionService;
use App\Services\MidtransService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
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

    public function pay(Request $request, Adoption $order, MidtransService $midtrans): mixed
    {
        $this->owner($request, $order);
        abort_unless(config('treefund.midtrans.enabled') && ! $order->is_demo, 404);

        try {
            return response()->json(['token' => $midtrans->token($order)]);
        } catch (ConnectionException|RequestException $exception) {
            report($exception);

            return response()->json(['message' => 'Midtrans Sandbox belum dapat dihubungi. Coba lagi nanti.'], 502);
        }
    }

    public function sync(Request $request, Adoption $order, MidtransService $midtrans, AdoptionService $adoptions): mixed
    {
        $this->owner($request, $order);
        abort_unless(config('treefund.midtrans.enabled') && ! $order->is_demo, 404);

        try {
            if ($order->snap_token) {
                $adoptions->applyProviderStatus($order, $midtrans->status($order));
            } else {
                $adoptions->expireUnissued($order);
            }
        } catch (ConnectionException|RequestException $exception) {
            report($exception);
            $message = 'Status Midtrans belum dapat diperiksa. Coba lagi nanti.';

            return $request->expectsJson() ? response()->json(['message' => $message], 502) : back()->withErrors(['payment' => $message]);
        }

        return $request->expectsJson()
            ? response()->json(['status' => $order->refresh()->status])
            : back()->with('success', 'Status pembayaran diperbarui.');
    }

    public function trees(Request $request): mixed
    {
        $trees = Tree::with(['item.offer.plot.partner', 'planting', 'monitorings' => fn ($query) => $query->where('status', 'approved')])->whereHas('item.adoption', fn ($q) => $q->where('user_id', $request->user()->id)->where('status', 'paid'))->get();

        return view('trees', compact('trees'));
    }

    public function mapData(Request $request): mixed
    {
        $trees = Tree::with(['item.offer.plot', 'planting', 'monitorings' => fn ($query) => $query->where('status', 'approved')])->whereHas('item.adoption', fn ($q) => $q->where('user_id', $request->user()->id)->where('status', 'paid'))->whereHas('planting', fn ($q) => $q->where('status', 'approved'))->get();

        return response()->json($trees->map(fn ($t) => ['id' => $t->id, 'code' => $t->code, 'species' => $t->item->offer->species, 'plot' => $t->item->offer->plot->name, 'latitude' => (float) $t->planting->latitude, 'longitude' => (float) $t->planting->longitude, 'updated_at' => $t->monitorings->where('status', 'approved')->first()?->observed_at?->format('d M Y') ?? $t->planting->planted_at->format('d M Y')]));
    }

    public function certificates(Request $request): mixed
    {
        return view('certificates', ['orders' => Adoption::where('user_id', $request->user()->id)->where('status', 'paid')->whereHas('certificate')->with('items.offer.plot', 'certificate')->when($request->filled('q'), fn ($q) => $q->where(fn ($q) => $q->where('certificate_name', 'like', '%'.$request->input('q').'%')->orWhere('reference', 'like', '%'.$request->input('q').'%')->orWhereHas('certificate', fn ($q) => $q->where('number', 'like', '%'.$request->input('q').'%'))))->latest('paid_at')->paginate(9)->withQueryString()]);
    }

    public function certificate(Request $request, Adoption $order): mixed
    {
        $this->owner($request, $order);
        abort_unless($order->status === 'paid' && $order->certificate, 404);
        $order->load('user', 'items.offer.plot', 'items.trees', 'certificate');
        if ($request->boolean('preview')) {
            return view('certificate-preview', compact('order'));
        }
        $pdf = app('dompdf.wrapper')->loadView('certificate', compact('order'))->setPaper('a4', 'landscape');

        return $pdf->download($order->certificate->number.'.pdf');
    }

}
