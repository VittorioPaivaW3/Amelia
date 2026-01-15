<?php

namespace App\Http\Controllers;

use App\Models\ChatTokenUsage;
use App\Models\GutRequest;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GutRequestController extends Controller
{
    private const SECTORS = ['mkt', 'juridico', 'rh'];
    private const STATUSES = ['novo', 'em_andamento', 'concluido', 'recusado', 'cancelado'];

    public function dashboard(Request $request): View
    {
        $user = $request->user();
        $role = $user?->role;
        $isAdmin = $role === 'admin';
        $isStaff = in_array($role, self::SECTORS, true);

        if (! $isAdmin && ! $isStaff) {
            return view('dashboard', [
                'role' => $role,
            ]);
        }

        $sectorFilterValue = 'all';
        $sectorFilter = null;
        if ($isAdmin) {
            $sectorInput = strtolower((string) $request->input('sector', 'all'));
            if (in_array($sectorInput, self::SECTORS, true)) {
                $sectorFilterValue = $sectorInput;
                $sectorFilter = $sectorInput;
            }
        }
        $sectorFilterMeta = [
            'all' => ['label' => 'Todos', 'sub' => 'Geral'],
            'mkt' => ['label' => 'MKT', 'sub' => 'Marketing'],
            'juridico' => ['label' => 'Juridico', 'sub' => 'Legal'],
            'rh' => ['label' => 'RH', 'sub' => 'Recursos Humanos'],
        ];

        [$from, $to, $rangeDays] = $this->resolveDateRange($request);
        $periodLabel = $from->format('d/m/Y').' - '.$to->format('d/m/Y');
        $summaryTitle = $rangeDays === 7 ? 'Resumo semanal' : 'Resumo do periodo';
        $summaryFooterTitle = $rangeDays === 7 ? 'Resumo da semana' : 'Resumo do periodo';

        $baseQuery = $this->baseQueryForRole($role, $user->id);
        if ($sectorFilter) {
            $baseQuery->where('sector', $sectorFilter);
        }
        $filteredQuery = $this->applyDateRange(clone $baseQuery, $from, $to);

        $total = (clone $filteredQuery)->count();
        $pending = (clone $filteredQuery)
            ->where(function ($query) {
                $query->where('status', 'novo')
                    ->orWhereNull('status');
            })
            ->count();
        $rejected = (clone $filteredQuery)
            ->where('status', 'recusado')
            ->count();
        $canceled = (clone $filteredQuery)
            ->where('status', 'cancelado')
            ->count();
        $accepted = (clone $filteredQuery)
            ->whereIn('status', ['em_andamento', 'concluido'])
            ->count();

        $avgAcceptMinutes = $this->averageAcceptMinutes(clone $filteredQuery);
        $avgAccept = $this->formatMinutes($avgAcceptMinutes);
        $acceptRate = $total > 0 ? (int) round(($accepted / $total) * 100) : 0;
        $rejectRate = $total > 0 ? (int) round(($rejected / $total) * 100) : 0;
        $cancelRate = $total > 0 ? (int) round(($canceled / $total) * 100) : 0;
        $ringValue = $avgAcceptMinutes ? min(100, (int) round(($avgAcceptMinutes / 180) * 100)) : 0;

        $previousTo = (clone $from)->subDay()->endOfDay();
        $previousFrom = (clone $previousTo)->subDays($rangeDays - 1)->startOfDay();
        $previousQuery = $this->applyDateRange(clone $baseQuery, $previousFrom, $previousTo);
        $previousTotal = (clone $previousQuery)->count();
        $previousAccepted = (clone $previousQuery)
            ->whereIn('status', ['em_andamento', 'concluido'])
            ->count();
        $previousRejected = (clone $previousQuery)
            ->where('status', 'recusado')
            ->count();
        $previousCanceled = (clone $previousQuery)
            ->where('status', 'cancelado')
            ->count();
        $previousAvgAcceptMinutes = $this->averageAcceptMinutes(clone $previousQuery);

        $stats = [
            'total' => $total,
            'pending' => $pending,
            'rejected' => $rejected,
            'canceled' => $canceled,
            'reject_rate' => $rejectRate.'%',
            'cancel_rate' => $cancelRate.'%',
            'accept_rate' => $acceptRate.'%',
            'avg_accept' => $avgAccept,
            'growth' => $this->formatTrend($total, $previousTotal),
        ];

        $sectorPalette = [
            'mkt' => ['label' => 'MKT', 'name' => 'Marketing', 'color' => '#3b82f6'],
            'juridico' => ['label' => 'JURIDICO', 'name' => 'Juridico', 'color' => '#ef4444'],
            'rh' => ['label' => 'RH', 'name' => 'RH', 'color' => '#f59e0b'],
        ];

        $sectorInfo = $sectorPalette[$role] ?? ['label' => strtoupper((string) $role), 'name' => 'Setor', 'color' => 'var(--accent)'];

        $sectorCounts = [];
        if ($isAdmin) {
            $sectorTotals = (clone $filteredQuery)
                ->select('sector', DB::raw('count(*) as total'))
                ->groupBy('sector')
                ->pluck('total', 'sector')
                ->all();
            $sectorSum = array_sum($sectorTotals);
            foreach ($sectorPalette as $key => $info) {
                $count = (int) ($sectorTotals[$key] ?? 0);
                $share = $sectorSum > 0 ? (int) round(($count / $sectorSum) * 100) : 0;
                $sectorCounts[] = [
                    'label' => $info['label'],
                    'count' => $count,
                    'share' => $share,
                    'color' => $info['color'],
                ];
            }
        } else {
            $sectorCounts[] = [
                'label' => $sectorInfo['label'],
                'count' => $total,
                'share' => $total > 0 ? 100 : 0,
                'color' => $sectorInfo['color'],
            ];
        }

        $recentClosures = $this->applyDateRange(clone $baseQuery, $from, $to, 'updated_at')
            ->whereIn('status', ['recusado', 'cancelado'])
            ->orderByDesc('updated_at')
            ->take(3)
            ->get()
            ->map(function (GutRequest $item) use ($sectorPalette) {
                $sectorLabel = $sectorPalette[$item->sector]['label'] ?? strtoupper((string) $item->sector);
                $statusLabel = $item->status === 'cancelado' ? 'Cancelado' : 'Recusado';
                return [
                    'title' => '#'.$item->id.' | '.$sectorLabel,
                    'time' => $item->updated_at?->format('d/m H:i'),
                    'status' => $statusLabel,
                ];
            })
            ->all();

        $summaryCards = [
            [
                'label' => 'Novas demandas',
                'value' => $total,
                'trend' => $this->formatTrend($total, $previousTotal),
            ],
            [
                'label' => 'Aceitas no periodo',
                'value' => $accepted,
                'trend' => $this->formatTrend($accepted, $previousAccepted),
            ],
            [
                'label' => 'Recusadas',
                'value' => $rejected,
                'trend' => $this->formatTrend($rejected, $previousRejected),
            ],
            [
                'label' => 'Canceladas',
                'value' => $canceled,
                'trend' => $this->formatTrend($canceled, $previousCanceled),
            ],
            [
                'label' => 'Tempo medio',
                'value' => $avgAccept,
                'trend' => $this->formatTrend($avgAcceptMinutes, $previousAvgAcceptMinutes),
            ],
        ];

        $reportSummary = [
            ['label' => 'Total', 'value' => $total],
            ['label' => 'Aceitas', 'value' => $accepted],
            ['label' => 'Recusadas', 'value' => $rejected],
            ['label' => 'Canceladas', 'value' => $canceled],
            ['label' => 'Pendentes', 'value' => $pending],
        ];

        $costLabel = (string) config('services.openai.cost_label', 'media');
        $tokenStats = [
            'input' => '0',
            'output' => '0',
            'total' => '0',
            'avg' => '0',
            'requests' => '0',
            'cost_input' => '0',
            'cost_output' => '0',
            'cost_total' => '0',
            'cost_avg' => '0',
        ];
        $topTokenUsers = [];
        if ($isAdmin && Schema::hasTable('chat_token_usages')) {
            $currency = (string) config('services.openai.cost_currency', 'USD');
            $tokenQuery = ChatTokenUsage::query();
            if ($sectorFilter) {
                $tokenQuery->where('sector', $sectorFilter);
            }
            $tokenQuery = $this->applyDateRange($tokenQuery, $from, $to, 'chat_token_usages.created_at');

            $tokenTotals = (clone $tokenQuery)
                ->selectRaw('COALESCE(SUM(input_tokens), 0) as input_tokens')
                ->selectRaw('COALESCE(SUM(output_tokens), 0) as output_tokens')
                ->selectRaw('COALESCE(SUM(total_tokens), 0) as total_tokens')
                ->selectRaw('COALESCE(SUM(input_cost), 0) as input_cost')
                ->selectRaw('COALESCE(SUM(output_cost), 0) as output_cost')
                ->selectRaw('COALESCE(SUM(total_cost), 0) as total_cost')
                ->selectRaw('COUNT(*) as total_rows')
                ->first();

            $tokenInput = (int) ($tokenTotals->input_tokens ?? 0);
            $tokenOutput = (int) ($tokenTotals->output_tokens ?? 0);
            $tokenTotal = (int) ($tokenTotals->total_tokens ?? 0);
            $tokenRows = (int) ($tokenTotals->total_rows ?? 0);
            $tokenInputCost = (float) ($tokenTotals->input_cost ?? 0);
            $tokenOutputCost = (float) ($tokenTotals->output_cost ?? 0);
            $tokenTotalCost = (float) ($tokenTotals->total_cost ?? 0);

            $tokenStats = [
                'input' => $this->formatNumber($tokenInput),
                'output' => $this->formatNumber($tokenOutput),
                'total' => $this->formatNumber($tokenTotal),
                'avg' => $this->formatNumber($tokenRows > 0 ? (int) round($tokenTotal / $tokenRows) : 0),
                'requests' => $this->formatNumber($tokenRows),
                'cost_input' => $this->formatCurrency($tokenInputCost, $currency),
                'cost_output' => $this->formatCurrency($tokenOutputCost, $currency),
                'cost_total' => $this->formatCurrency($tokenTotalCost, $currency),
                'cost_avg' => $this->formatCurrency($tokenRows > 0 ? $tokenTotalCost / $tokenRows : 0, $currency),
            ];

            $topTokenUsers = (clone $tokenQuery)
                ->leftJoin('users', 'chat_token_usages.user_id', '=', 'users.id')
                ->select('users.name', DB::raw('SUM(chat_token_usages.total_tokens) as total_tokens'))
                ->groupBy('users.id', 'users.name')
                ->orderByDesc('total_tokens')
                ->limit(5)
                ->get()
                ->map(function ($row) {
                    return [
                        'name' => $row->name ?: 'Usuario removido',
                        'tokens' => $this->formatNumber((int) $row->total_tokens),
                    ];
                })
                ->all();
        }

        $heroTitle = $isAdmin ? 'Bem-vindo, '.$user->name : 'Painel do setor '.$sectorInfo['name'];
        $heroSub = $isAdmin
            ? 'Visao geral do periodo '.$periodLabel.'.'
            : 'Indicadores do setor no periodo '.$periodLabel.'.';
        $sectorPanelTitle = $isAdmin ? 'Demandas por setor' : 'Demandas do setor';
        $ringLabel = $isAdmin ? 'media geral' : 'media do setor';
        $ringColor = $isAdmin ? '#63BE15' : $sectorInfo['color'];

        return view('dashboard', [
            'role' => $role,
            'stats' => $stats,
            'sectorCounts' => $sectorCounts,
            'recentClosures' => $recentClosures,
            'summaryCards' => $summaryCards,
            'reportSummary' => $reportSummary,
            'tokenStats' => $tokenStats,
            'topTokenUsers' => $topTokenUsers,
            'costLabel' => $costLabel,
            'summaryTitle' => $summaryTitle,
            'summaryFooterTitle' => $summaryFooterTitle,
            'heroTitle' => $heroTitle,
            'heroSub' => $heroSub,
            'sectorPanelTitle' => $sectorPanelTitle,
            'ringLabel' => $ringLabel,
            'ringColor' => $ringColor,
            'ringValue' => $ringValue,
            'periodLabel' => $periodLabel,
            'filterFrom' => $from->format('Y-m-d'),
            'filterTo' => $to->format('Y-m-d'),
            'filterSector' => $sectorFilterValue,
            'filterSectorLabel' => $sectorFilterMeta[$sectorFilterValue]['label'] ?? 'Todos',
            'filterSectorSub' => $sectorFilterMeta[$sectorFilterValue]['sub'] ?? 'Geral',
        ]);
    }

    public function calls(Request $request): View
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        $role = $user->role;
        $query = GutRequest::query()->with(['user', 'acceptedBy', 'attachments']);
        if ($role !== 'admin') {
            $query->where('user_id', $user->id);
        }

        $fromInput = $request->input('from');
        $toInput = $request->input('to');
        $filterFrom = $fromInput ?: ($toInput ?? '');
        $filterTo = $toInput ?: ($fromInput ?? '');
        if ($fromInput || $toInput) {
            $from = $fromInput ? Carbon::parse($fromInput)->startOfDay() : null;
            $to = $toInput ? Carbon::parse($toInput)->endOfDay() : null;
            if ($from && ! $to) {
                $to = (clone $from)->endOfDay();
            } elseif (! $from && $to) {
                $from = (clone $to)->startOfDay();
            }
            $query->whereBetween('created_at', [$from, $to]);
        }

        $sector = $request->input('sector');
        if ($sector && $sector !== 'all' && in_array($sector, self::SECTORS, true)) {
            $query->where('sector', $sector);
        }

        $requestIdRaw = trim((string) $request->input('request_id', ''));
        if ($requestIdRaw !== '') {
            $requestId = preg_replace('/\D+/', '', $requestIdRaw);
            if ($requestId !== '') {
                $query->whereKey((int) $requestId);
            }
        }

        $requests = $query->latest()->get();

        return view('calls.index', [
            'requests' => $requests,
            'role' => $role,
            'filterFrom' => $filterFrom,
            'filterTo' => $filterTo,
            'filterSector' => $sector,
            'filterRequestId' => $requestIdRaw,
            'sectorOptions' => self::SECTORS,
        ]);
    }

    public function sector(Request $request, string $sector): View
    {
        $sector = strtolower($sector);
        if (! in_array($sector, self::SECTORS, true)) {
            abort(404);
        }

        $query = GutRequest::query()
            ->with(['user', 'acceptedBy', 'attachments'])
            ->where('sector', $sector);

        $fromInput = $request->input('from');
        $toInput = $request->input('to');
        $filterFrom = $fromInput ?: ($toInput ?? '');
        $filterTo = $toInput ?: ($fromInput ?? '');
        if ($fromInput || $toInput) {
            $from = $fromInput ? Carbon::parse($fromInput)->startOfDay() : null;
            $to = $toInput ? Carbon::parse($toInput)->endOfDay() : null;
            if ($from && ! $to) {
                $to = (clone $from)->endOfDay();
            } elseif (! $from && $to) {
                $from = (clone $to)->startOfDay();
            }
            $query->whereBetween('created_at', [$from, $to]);
        }

        $requestIdRaw = trim((string) $request->input('request_id', ''));
        if ($requestIdRaw !== '') {
            $requestId = preg_replace('/\D+/', '', $requestIdRaw);
            if ($requestId !== '') {
                $query->whereKey((int) $requestId);
            }
        }

        $requests = $query
            ->orderByDesc('score')
            ->orderByDesc('created_at')
            ->get();

        return view('portals.index', [
            'sector' => $sector,
            'requests' => $requests,
            'statuses' => self::STATUSES,
            'sectors' => self::SECTORS,
            'filterFrom' => $filterFrom,
            'filterTo' => $filterTo,
            'filterRequestId' => $requestIdRaw,
        ]);
    }

    public function update(Request $request, GutRequest $gutRequest): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        if ($user->role !== 'admin') {
            if (! in_array($user->role, self::SECTORS, true)) {
                abort(403);
            }
            if (($gutRequest->sector ?? '') !== $user->role) {
                abort(403);
            }
            if (! in_array($gutRequest->status ?? '', ['novo', ''], true)) {
                abort(403);
            }

            $data = $request->validate([
                'status' => ['required', 'in:em_andamento,recusado'],
                'rejection_reason' => ['nullable', 'string', 'max:1000', 'required_if:status,recusado'],
            ]);
            if (($data['status'] ?? '') === 'em_andamento') {
                if (($gutRequest->status ?? '') !== 'em_andamento' || ! $gutRequest->accepted_by) {
                    $data['accepted_by'] = $user->id;
                }
                if (! $gutRequest->accepted_at) {
                    $data['accepted_at'] = now();
                }
                $data['rejection_reason'] = null;
            } else {
                $data['accepted_by'] = null;
                $data['accepted_at'] = null;
                $data['rejection_reason'] = null;
                $data['rejection_reason'] = trim((string) ($data['rejection_reason'] ?? ''));
            }

            $gutRequest->update($data);

            return back()->with('status', 'Solicitacao atualizada.');
        }

        $data = $request->validate([
            'sector' => ['required', 'in:'.implode(',', self::SECTORS)],
            'gravity' => ['required', 'integer', 'between:1,5'],
            'urgency' => ['required', 'integer', 'between:1,5'],
            'trend' => ['required', 'integer', 'between:1,5'],
            'status' => ['required', 'in:'.implode(',', self::STATUSES)],
            'rejection_reason' => ['nullable', 'string', 'max:1000', 'required_if:status,recusado'],
        ]);

        $data['score'] = $data['gravity'] * $data['urgency'] * $data['trend'];
        if (($data['status'] ?? '') === 'em_andamento') {
            if (($gutRequest->status ?? '') !== 'em_andamento' || ! $gutRequest->accepted_by) {
                $data['accepted_by'] = $user->id;
            }
            if (! $gutRequest->accepted_at) {
                $data['accepted_at'] = now();
            }
            $data['rejection_reason'] = null;
        } elseif (($data['status'] ?? '') === 'recusado') {
            $data['accepted_by'] = null;
            $data['accepted_at'] = null;
            $data['rejection_reason'] = trim((string) ($data['rejection_reason'] ?? ''));
        } elseif (($data['status'] ?? '') === 'cancelado') {
            $data['accepted_by'] = null;
            $data['accepted_at'] = null;
            $data['rejection_reason'] = trim((string) ($data['rejection_reason'] ?? ''));
            if ($data['rejection_reason'] === '') {
                $data['rejection_reason'] = null;
            }
        } elseif (($data['status'] ?? '') === 'novo') {
            $data['accepted_by'] = null;
            $data['accepted_at'] = null;
            $data['rejection_reason'] = null;
        } else {
            $data['rejection_reason'] = null;
            unset($data['accepted_by']);
            unset($data['accepted_at']);
        }

        $gutRequest->update($data);

        return back()->with('status', 'Solicitacao atualizada.');
    }

    public function cancel(Request $request, GutRequest $gutRequest): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        if ($gutRequest->user_id !== $user->id) {
            abort(403);
        }

        $status = $gutRequest->status ?? '';
        if (! in_array($status, ['novo', ''], true) || $gutRequest->accepted_by || $gutRequest->accepted_at) {
            return back()->with('status', 'Solicitacao nao pode ser cancelada.');
        }

        $gutRequest->update([
            'status' => 'cancelado',
            'rejection_reason' => 'Cancelado pelo solicitante.',
            'accepted_by' => null,
            'accepted_at' => null,
        ]);

        return back()->with('status', 'Solicitacao cancelada.');
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        [$from, $to] = $this->resolveDateRange($request);
        $query = $this->applyDateRange($this->baseQueryForRole($user->role, $user->id)->with(['user', 'acceptedBy']), $from, $to)
            ->orderByDesc('created_at');
        if ($user->role === 'admin') {
            $sectorInput = strtolower((string) $request->input('sector', 'all'));
            if (in_array($sectorInput, self::SECTORS, true)) {
                $query->where('sector', $sectorInput);
            }
        }

        $filename = 'relatorio-chamados-'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'ID',
                'Setor',
                'Status',
                'Solicitante',
                'Email solicitante',
                'Aceito por',
                'Criado em',
                'Aceito em',
                'Atualizado em',
                'GUT',
            ], ';');

            $query->chunk(200, function ($rows) use ($handle) {
                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row->id,
                        $row->sector,
                        $row->status,
                        $row->user?->name,
                        $row->user?->email,
                        $row->acceptedBy?->name,
                        $row->created_at?->format('d/m/Y H:i'),
                        $row->accepted_at?->format('d/m/Y H:i'),
                        $row->updated_at?->format('d/m/Y H:i'),
                        $row->score,
                    ], ';');
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function baseQueryForRole(?string $role, int $userId)
    {
        $query = GutRequest::query();
        if ($role === 'admin') {
            return $query;
        }
        if (in_array($role, self::SECTORS, true)) {
            return $query->where('sector', $role);
        }

        return $query->where('user_id', $userId);
    }

    private function resolveDateRange(Request $request): array
    {
        $fromInput = $request->input('from');
        $toInput = $request->input('to');

        $from = $fromInput ? Carbon::parse($fromInput)->startOfDay() : null;
        $to = $toInput ? Carbon::parse($toInput)->endOfDay() : null;

        if (! $from && ! $to) {
            $to = now()->endOfDay();
            $from = (clone $to)->subDays(6)->startOfDay();
        } elseif ($from && ! $to) {
            $to = (clone $from)->endOfDay();
        } elseif (! $from && $to) {
            $from = (clone $to)->startOfDay();
        }

        $rangeDays = $from->diffInDays($to) + 1;

        return [$from, $to, $rangeDays];
    }

    private function applyDateRange($query, Carbon $from, Carbon $to, string $column = 'created_at')
    {
        return $query->whereBetween($column, [$from, $to]);
    }

    private function averageAcceptMinutes($query): ?float
    {
        $value = $query->whereNotNull('accepted_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, accepted_at)) as avg_minutes')
            ->value('avg_minutes');

        return $value !== null ? (float) $value : null;
    }

    private function formatMinutes(?float $minutes): string
    {
        if (! $minutes || $minutes < 1) {
            return '0m';
        }

        $minutes = (int) round($minutes);
        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        if ($hours > 0) {
            return sprintf('%dh %02dm', $hours, $remaining);
        }

        return sprintf('%dm', $remaining);
    }

    private function formatTrend(?float $current, ?float $previous): string
    {
        $current = $current ?? 0.0;
        $previous = $previous ?? 0.0;

        if (abs($previous) < 0.00001) {
            return $current > 0 ? '+100%' : '0%';
        }

        $change = (($current - $previous) / $previous) * 100;
        $sign = $change >= 0 ? '+' : '';

        return $sign.(string) round($change).'%';
    }

    private function formatNumber(int|float $value): string
    {
        return number_format((float) $value, 0, ',', '.');
    }

    private function formatCurrency(float $value, string $currency = 'USD'): string
    {
        $currency = strtoupper(trim($currency));
        $symbol = match ($currency) {
            'BRL' => 'R$',
            'USD' => 'US$',
            'EUR' => 'EUR',
            default => $currency !== '' ? $currency : 'USD',
        };

        return $symbol.' '.number_format($value, 4, ',', '.');
    }
}
