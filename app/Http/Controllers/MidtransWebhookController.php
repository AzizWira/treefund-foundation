<?php

namespace App\Http\Controllers;

use App\Services\MidtransService;
use Illuminate\Http\Request;

class MidtransWebhookController extends Controller
{
    public function __invoke(Request $request, MidtransService $service): mixed
    {
        $payload = $request->validate(['order_id' => 'required|string|max:50', 'status_code' => 'required|string|max:5', 'gross_amount' => 'required|numeric', 'signature_key' => 'required|string|size:128']);
        $service->notification($payload);

        return response()->json(['received' => true]);
    }
}
