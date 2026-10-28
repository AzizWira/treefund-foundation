<?php

namespace App\Http\Controllers;

use App\Models\Partner;
use App\Services\PrivateEvidenceService;
use Illuminate\Http\Request;

class FileController extends Controller
{
    public function __invoke(Request $request, string $type, int $id, PrivateEvidenceService $evidence): mixed
    {
        abort_unless($type === 'legal', 404);
        $row = Partner::findOrFail($id);
        $user = $request->user();
        abort_unless($user->role === 'admin' || $row->user_id === $user->id, 403);

        return $evidence->respond($request, $row->document, 'Dokumen Legalitas Kelompok Tani', $type, $id);
    }
}
