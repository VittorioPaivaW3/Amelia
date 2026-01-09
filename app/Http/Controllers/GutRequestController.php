<?php

namespace App\Http\Controllers;

use App\Models\GutRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GutRequestController extends Controller
{
    private const SECTORS = ['mkt', 'juridico', 'rh'];
    private const STATUSES = ['novo', 'em_andamento', 'concluido'];

    public function dashboard(Request $request): View
    {
        $user = $request->user();
        if ($user?->role === 'admin') {
            $requests = GutRequest::query()
                ->with('user')
                ->latest()
                ->get();
        } else {
            $requests = GutRequest::query()
                ->where('user_id', $user->id)
                ->latest()
                ->get();
        }

        return view('dashboard', [
            'requests' => $requests,
            'role' => $user->role,
        ]);
    }

    public function sector(Request $request, string $sector): View
    {
        $sector = strtolower($sector);
        if (! in_array($sector, self::SECTORS, true)) {
            abort(404);
        }

        $requests = GutRequest::query()
            ->with('user')
            ->where('sector', $sector)
            ->orderByDesc('score')
            ->orderByDesc('created_at')
            ->get();

        return view('portals.index', [
            'sector' => $sector,
            'requests' => $requests,
            'statuses' => self::STATUSES,
            'sectors' => self::SECTORS,
        ]);
    }

    public function update(Request $request, GutRequest $gutRequest): RedirectResponse
    {
        $user = $request->user();
        if (! $user || ! in_array($user->role, self::SECTORS, true)) {
            abort(403);
        }

        $data = $request->validate([
            'sector' => ['required', 'in:'.implode(',', self::SECTORS)],
            'gravity' => ['required', 'integer', 'between:1,5'],
            'urgency' => ['required', 'integer', 'between:1,5'],
            'trend' => ['required', 'integer', 'between:1,5'],
            'status' => ['required', 'in:'.implode(',', self::STATUSES)],
        ]);

        $data['score'] = $data['gravity'] * $data['urgency'] * $data['trend'];

        $gutRequest->update($data);

        return back()->with('status', 'Solicitacao atualizada.');
    }
}
