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
    public function catalog(Request $request): mixed
    {
        $published = Offer::where('active', true)->whereHas('plot', fn ($q) => $q->where('status', 'approved')->whereHas('partner', fn ($p) => $p->where('status', 'approved')));
        $speciesOptions = (clone $published)->select('species')->distinct()->orderBy('species')->pluck('species');
        $offers = $published->with('plot.partner')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($s) => $s->where('species', 'like', '%'.$request->string('q').'%')->orWhereHas('plot', fn ($p) => $p->where('name', 'like', '%'.$request->string('q').'%')->orWhere('location', 'like', '%'.$request->string('q').'%'))))
            ->when($request->filled('species'), fn ($q) => $q->where('species', $request->string('species')))->paginate(9)->withQueryString();

        $portalCatalog = $request->routeIs('donor.catalog');

        return view('catalog', compact('offers', 'portalCatalog', 'speciesOptions'));
    }

    public function checkout(Offer $offer): mixed
    {
        abort_unless($offer->active && $offer->plot->status === 'approved', 404);

        return view('checkout', compact('offer'));
    }

    public function store(Request $request, AdoptionService $service): mixed
    {
        $data = $request->validate(['offer_id' => 'required|exists:offers,id', 'quantity' => 'required|integer|min:1|max:1000', 'certificate_name' => 'required|string|max:150', 'agree' => 'accepted']);
        $order = $service->checkout($request->user(), Offer::findOrFail($data['offer_id']), (int) $data['quantity'], $data['certificate_name']);

        return redirect()->route('orders.show', $order)->with('success', 'Kuota dicadangkan. Lanjutkan pembayaran Midtrans Sandbox.');
    }

    private function owner(Request $request, Adoption $order): void
    {
        abort_unless($order->user_id === $request->user()->id, 403);
    }

    public function orders(Request $request): mixed
    {
        if ($request->input('view') === 'certificates') {
            return redirect()->route('certificates');
        }

        return view('orders', ['orders' => $request->user()->adoptions()->with('items.offer.plot', 'certificate')->when($request->filled('q'), fn ($q) => $q->where(fn ($q) => $q->where('reference', 'like', '%'.$request->input('q').'%')->orWhere('certificate_name', 'like', '%'.$request->input('q').'%')->orWhereHas('items.offer', fn ($q) => $q->where('species', 'like', '%'.$request->input('q').'%')->orWhereHas('plot', fn ($q) => $q->where('name', 'like', '%'.$request->input('q').'%')))))->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))->latest()->paginate(12)->withQueryString()]);
    }

    public function certificates(Request $request): mixed
    {
        return view('certificates', ['orders' => $request->user()->adoptions()->where('status', 'paid')->whereHas('certificate')->with('items.offer.plot', 'certificate')->when($request->filled('q'), fn ($q) => $q->where(fn ($q) => $q->where('certificate_name', 'like', '%'.$request->input('q').'%')->orWhere('reference', 'like', '%'.$request->input('q').'%')->orWhereHas('certificate', fn ($q) => $q->where('number', 'like', '%'.$request->input('q').'%'))))->latest('paid_at')->paginate(9)->withQueryString()]);
    }

    public function show(Request $request, Adoption $order): mixed
    {
        $this->owner($request, $order);
        $order->load('items.offer.plot', 'items.trees.planting', 'certificate');

        return view('order', compact('order'));
    }

    public function pay(Request $request, Adoption $order, MidtransService $midtrans): mixed
    {
        $this->owner($request, $order);
        try {
            return response()->json(['token' => $midtrans->token($order), 'client_key' => config('treefund.midtrans.client_key')]);
        } catch (ConnectionException|RequestException $e) {
            report($e);

            $message = $e instanceof RequestException && $e->response->status() === 401
                ? 'Kredensial pembayaran ditolak oleh Midtrans Sandbox. Hubungi pengelola; pesanan Anda tetap tersimpan.'
                : 'Layanan Midtrans belum dapat dihubungi. Pesanan tersimpan; periksa status sebelum mencoba kembali.';

            return response()->json(['message' => $message], 503);
        }
    }

    public function sync(Request $request, Adoption $order, MidtransService $midtrans, AdoptionService $service): mixed
    {
        $this->owner($request, $order);
        try {
            $service->applyProviderStatus($order, $midtrans->status($order));

            if ($request->expectsJson()) {
                return response()->json(['status' => $order->fresh()->status]);
            }

            return back()->with('success', 'Status pembayaran diperiksa dari server Midtrans.');
        } catch (ConnectionException|RequestException $e) {
            report($e);

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Status pembayaran belum dapat dikonfirmasi. Pemeriksaan akan dicoba kembali.'], 503);
            }

            return back()->withErrors(['payment' => 'Status belum dapat diperiksa. Pastikan kunci Sandbox benar dan transaksi sudah dibuka melalui Snap.']);
        }
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

    public function trees(Request $request): mixed
    {
        $trees = Tree::with('item.offer.plot.partner', 'planting', 'monitorings')->whereHas('item.adoption', fn ($q) => $q->where('user_id', $request->user()->id)->where('status', 'paid'))->get();

        return view('trees', compact('trees'));
    }

    public function mapData(Request $request): mixed
    {
        $trees = Tree::with('item.offer.plot', 'planting', 'monitorings')->whereHas('item.adoption', fn ($q) => $q->where('user_id', $request->user()->id)->where('status', 'paid'))->whereHas('planting', fn ($q) => $q->where('status', 'approved'))->get();

        return response()->json($trees->map(fn ($t) => ['id' => $t->id, 'code' => $t->code, 'species' => $t->item->offer->species, 'plot' => $t->item->offer->plot->name, 'latitude' => (float) $t->planting->latitude, 'longitude' => (float) $t->planting->longitude, 'updated_at' => $t->monitorings->where('status', 'approved')->first()?->observed_at?->format('d M Y') ?? $t->planting->planted_at->format('d M Y')]));
    }
}
