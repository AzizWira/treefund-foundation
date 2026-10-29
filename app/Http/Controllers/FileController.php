<?php

namespace App\Http\Controllers;

use App\Models\Monitoring;
use App\Models\Partner;
use App\Models\Planting;
use App\Services\PrivateEvidenceService;
use Illuminate\Http\Request;

class FileController extends Controller
{
    public function __invoke(Request $request, string $type, int $id, PrivateEvidenceService $evidence): mixed
    {
        if (in_array($type, ['planting', 'monitoring'], true)) {
            $row = ($type === 'planting' ? Planting::class : Monitoring::class)::with('tree.item.workOrder', 'tree.item.adoption')->findOrFail($id);
            $user = $request->user();
            $item = $row->tree->item;
            $allowed = $user->role === 'admin'
                || ($user->role === 'partner' && $user->partner && $item->workOrder?->partner_id === $user->partner->id)
                || ($user->role === 'donor' && $item->adoption->user_id === $user->id && $item->adoption->status === 'paid' && $row->status === 'approved');
            abort_unless($allowed, 403);

            return $evidence->respond($request, $row->photo, $type === 'planting' ? 'Bukti Penanaman' : 'Bukti Monitoring', $type, $id);
        }

        abort_unless($type === 'legal', 404);
        $row = Partner::findOrFail($id);
        $user = $request->user();
        abort_unless($user->role === 'admin' || $row->user_id === $user->id, 403);

        return $evidence->respond($request, $row->document, 'Dokumen Legalitas Kelompok Tani', $type, $id);
    }
}
