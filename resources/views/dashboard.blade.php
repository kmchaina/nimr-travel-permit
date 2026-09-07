<x-app-layout>

@php
    $hour     = now()->hour;
    $greeting = match(true) {
        $hour >= 5 && $hour < 12  => __('dashboard.good_morning'),
        $hour >= 12 && $hour < 18 => __('dashboard.good_afternoon'),
        default                    => __('dashboard.good_evening'),
    };
    $firstName = explode(' ', trim($user->name))[0];

    /* ── Stats base (mirrors DashboardController logic) ───────────── */
    $viewStatsBase = ($user->isHr() || $user->isDirectorGeneral())
        ? $allRequests
        : $myRequests->merge($approvalRequests ?? collect());
    $returnedCount = $viewStatsBase->where('status', 'returned')->count();

    /* ── Focal request for hero card ──────────────────────────────── */
    $focalRequest = null;
    // HR travels too, so their own request belongs on their dashboard. The DG
    // is excluded because they have no chain to submit through at all.
    if (!$user->isDirectorGeneral()) {
        $focalRequest = $myRequests->firstWhere('status', 'returned')
            ?? $myRequests->where('status', 'pending')->sortBy('b_departure_date')->first()
            ?? $myRequests->firstWhere('status', 'draft');
    }

    /* ── Days to departure ────────────────────────────────────────── */
    $daysTo     = null;
    $daysOnRoad = null;
    if ($focalRequest?->b_departure_date) {
        $daysTo = (int) now()->startOfDay()->diffInDays(
            $focalRequest->b_departure_date->copy()->startOfDay(), false
        );
        if ($focalRequest->b_return_date) {
            $daysOnRoad = (int) $focalRequest->b_departure_date->copy()->startOfDay()
                ->diffInDays($focalRequest->b_return_date->copy()->startOfDay());
        }
    }

    /* ── Progress bar for hero card ───────────────────────────────── */
    $progressStages   = ['Submit'];
    $chainSteps       = [];
    $currentStepIndex = -1;

    if ($focalRequest) {
        $rawChain   = $focalRequest->approval_chain;
        $chainSteps = is_array($rawChain) ? $rawChain
            : (is_string($rawChain) ? (json_decode($rawChain, true) ?? []) : []);

        foreach ($chainSteps as $step) {
            $progressStages[] = match ($step['stage'] ?? '') {
                'supervisor' => 'Supervisor',
                'director'   => 'Director',
                'final'      => 'Final Approver',
                default      => ucfirst($step['stage'] ?? '—'),
            };
        }
        $progressStages[] = 'Permit';

        if ($focalRequest->status === 'pending' && $focalRequest->current_approver_id) {
            foreach ($chainSteps as $i => $step) {
                if ((int)($step['approver_id'] ?? 0) === (int)$focalRequest->current_approver_id) {
                    $currentStepIndex = $i;
                    break;
                }
            }
        }
    }

    $activeStageIdx = match ($focalRequest?->status) {
        'draft'    => 0,
        'returned' => 0,
        'pending'  => max(0, $currentStepIndex + 1),
        'approved' => count($progressStages) - 1,
        default    => 0,
    };

    /* ── Hero status subtitle ─────────────────────────────────────── */
    $heroStatusLine = match ($focalRequest?->status) {
        'draft'    => 'Not yet submitted — complete and submit to begin approval.',
        'returned' => 'Returned for revision — update and resubmit.',
        'approved' => 'Permit issued — approved for travel.',
        'rejected' => 'Request was rejected.',
        'cancelled'=> 'Request was cancelled.',
        'pending'  => match ($focalRequest->currentApprover?->role) {
            'head', 'manager', 'supervisor' => "Awaiting your supervisor\u{2019}s review.",
            'centre_manager'   => "Awaiting centre manager\u{2019}s approval.",
            'director'         => "Awaiting director\u{2019}s approval.",
            'director_general' => "Awaiting director general\u{2019}s approval.",
            default            => 'In review.',
        },
        default => null,
    };

    /* ── Requests list + modal data ───────────────────────────────── */
    $listRequests = ($user->isHr() || $user->isDirectorGeneral()) ? $allRequests : $myRequests;

    $dashboardRequestsData = collect()
        ->merge($needsMyAction ?? collect())
        ->merge($approvalRequests ?? collect())
        ->merge($myRequests ?? collect())
        ->merge($allRequests ?? collect())
        ->unique('id')
        ->mapWithKeys(fn($tr) => [(string)$tr->id => [
            'id'             => $tr->id,
            'request_number' => $tr->request_number,
            'status'         => $tr->status,
            'status_label'   => $tr->statusLabel(),
            'status_color'   => $tr->statusColor(),
            'applicant'      => $tr->b_applicant_name ?? '—',
            'destination'    => $tr->b_destination ?? '—',
            'purpose'        => $tr->d_benefit_to_institution ?? null,
            'departure'      => $tr->b_departure_date?->format('d M Y'),
            'return'         => $tr->b_return_date?->format('d M Y'),
            'submitted_at'   => $tr->submitted_at?->format('d M Y'),
            'approver'       => $tr->currentApprover?->name,
            'unit'           => $tr->unit?->name,
            'url'            => route('travel-requests.show', $tr->id),
        ]]);

    $avatarGrads = [
        'linear-gradient(135deg,#7c3aed,#a78bfa)',
        'linear-gradient(135deg,#b45309,#f59e0b)',
        'linear-gradient(135deg,#16a34a,#22c55e)',
        'linear-gradient(135deg,#be185d,#f472b6)',
        'linear-gradient(135deg,#1d4ed8,#3b82f6)',
    ];
@endphp

{{-- ================================================================== --}}
{{-- ROOT WRAPPER                                                        --}}
{{-- ================================================================== --}}
<div class="min-h-full p-5 lg:p-7 space-y-5"
     style="background:#f4f6fa;"
     x-data="{
         open: false,
         request: null,
         requests: {{ Js::from($dashboardRequestsData) }},
         openModal(id) { this.request = this.requests[String(id)] ?? null; if (this.request) this.open = true; },
         closeModal() { this.open = false; this.request = null; }
     }">

    {{-- Flash -------------------------------------------------------- --}}
    @if (session('status'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
         x-transition:leave="transition ease-in duration-300"
         x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="flex items-center gap-3 p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-700 text-sm">
        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        <span class="flex-1">{{ session('status') }}</span>
        <button @click="show = false"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
    </div>
    @endif

    {{-- Supervisor required banner ---------------------------------- --}}
    @if ($supervisorRequired && !$supervisor)
    <div class="flex items-start gap-4 p-4 rounded-xl border border-amber-300 bg-amber-50">
        <div class="h-9 w-9 rounded-full bg-amber-100 flex items-center justify-center shrink-0 mt-0.5">
            <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
            </svg>
        </div>
        <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-amber-800">{{ __('dashboard.supervisor_required_banner_title') }}</p>
            <p class="text-xs text-amber-700 mt-0.5">{{ __('dashboard.supervisor_required_banner_body') }}</p>
        </div>
        <a href="#supervisor-card"
           class="shrink-0 inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold text-amber-800 bg-amber-100 hover:bg-amber-200 transition border border-amber-300">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            Set now
        </a>
    </div>
    @endif

    {{-- ── Greeting row ──────────────────────────────────────────────── --}}
    <div class="flex items-end justify-between gap-4">
        <div>
            <h2 class="text-[22px] font-semibold leading-snug" style="color:#0e1a2b;">
                {{ $greeting }}, {{ $firstName }}.
            </h2>
            <p class="text-sm mt-1" style="color:#64748b;">
                @if ($user->isHr() || $user->isDirectorGeneral())
                    @php $orgPending = $allRequests->where('status','pending')->count(); @endphp
                    <strong style="color:#0e1a2b;">{{ $orgPending }}</strong>
                    {{ Str::plural('request', $orgPending) }} awaiting action across the organisation.
                @else
                    @php $activeCount = $myRequests->whereIn('status',['pending','returned','draft'])->count(); @endphp
                    @if ($activeCount > 0)
                        You have <strong style="color:#0e1a2b;">{{ $activeCount }} active {{ Str::plural('request', $activeCount) }}</strong> in progress.
                        @if ($needsMyAction->count() > 0)
                            &nbsp;·&nbsp;<strong style="color:#d97706;">{{ $needsMyAction->count() }} {{ Str::plural('approval', $needsMyAction->count()) }}</strong> waiting on you.
                        @endif
                    @endif
                @endif
            </p>
        </div>
        <div class="text-xs shrink-0 hidden sm:block" style="color:#94a3b8;">
            {{ now()->format('l, d F Y') }}
        </div>
    </div>

    {{-- ================================================================== --}}
    {{-- HERO CARD                                                           --}}
    {{-- ================================================================== --}}
    @if ($focalRequest)
    <section class="rounded-2xl overflow-hidden relative"
             style="background:linear-gradient(135deg,#03316e 0%,#05499c 58%,#1a6abf 110%);">

        {{-- Decorative glow --}}
        <div class="absolute pointer-events-none"
             style="width:500px;height:500px;border-radius:50%;
                    background:radial-gradient(closest-side,rgba(218,165,32,0.22),rgba(0,0,0,0));
                    top:-150px;right:-110px;"></div>

        <div class="relative grid grid-cols-1 lg:grid-cols-[1.45fr,1fr] gap-6 p-7 lg:p-8">

            {{-- Left ------------------------------------------------- --}}
            <div>
                <p class="text-[10.5px] font-bold tracking-[0.22em] uppercase mb-3"
                   style="color:#a5cdff;">
                    @if ($focalRequest->status === 'returned')
                        ⚠ Needs revision
                    @elseif ($focalRequest->status === 'draft')
                        Draft
                    @else
                        Your active request
                    @endif
                </p>

                <h2 class="font-bold text-white leading-snug mb-2"
                    style="font-size:clamp(20px,2.2vw,28px);letter-spacing:-0.01em;">
                    {{ $focalRequest->b_destination ?? 'No destination set' }}
                </h2>
                @if ($heroStatusLine)
                <p class="text-sm mb-4" style="color:#cfe1ff;">{{ $heroStatusLine }}</p>
                @endif

                @if ($focalRequest->d_benefit_to_institution)
                <p class="text-[13px] leading-relaxed mb-5 max-w-lg" style="color:#c8dbf5;">
                    {{ Str::limit($focalRequest->d_benefit_to_institution, 130) }}
                </p>
                @endif

                {{-- Meta pills --}}
                <div class="flex flex-wrap gap-2 mb-6">
                    @if ($focalRequest->b_departure_date)
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[12.5px] text-white"
                          style="background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.2);">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                        {{ $focalRequest->b_departure_date->format('d M Y') }}
                        @if ($focalRequest->b_return_date)
                        &nbsp;→&nbsp;{{ $focalRequest->b_return_date->format('d M Y') }}
                        @endif
                    </span>
                    @endif

                    @if ($focalRequest->status === 'pending' && $focalRequest->currentApprover)
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[12.5px] text-white"
                          style="background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.2);">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        With {{ $focalRequest->currentApprover->name }}
                    </span>
                    @endif

                    @if ($focalRequest->submitted_at)
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[12.5px] text-white"
                          style="background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.2);">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Submitted {{ $focalRequest->submitted_at->format('d M Y') }}
                    </span>
                    @endif
                </div>

                {{-- Action buttons --}}
                <div class="flex flex-wrap gap-2.5">
                    <a href="{{ route('travel-requests.show', $focalRequest->id) }}"
                       class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold transition hover:bg-slate-100"
                       style="background:white;color:#03316e;">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        View request →
                    </a>
                    @if (in_array($focalRequest->status, ['returned', 'draft']))
                    <a href="{{ route('travel-requests.edit', $focalRequest->id) }}"
                       class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-white transition"
                       style="background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.25);">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        Edit &amp; resubmit
                    </a>
                    @endif
                </div>
            </div>

            {{-- Right: countdown + progress ----------------------------- --}}
            <div>
                <div class="rounded-xl h-full flex flex-col gap-5 p-5"
                     style="background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.12);">

                    @if ($daysTo !== null)
                    <div>
                        <p class="text-[10.5px] font-bold tracking-[0.22em] uppercase mb-2" style="color:#a5cdff;">
                            Days to departure
                        </p>
                        @if ($daysTo > 0)
                        <div class="flex items-baseline gap-2">
                            <span class="font-bold text-white leading-none"
                                  style="font-size:56px;letter-spacing:-0.04em;">{{ str_pad($daysTo, 2, '0', STR_PAD_LEFT) }}</span>
                            <span class="text-sm" style="color:#cfe1ff;">
                                days{{ $daysOnRoad ? ' · ' . $daysOnRoad . ' days on the road' : '' }}
                            </span>
                        </div>
                        @elseif ($daysTo === 0)
                        <div class="flex items-baseline gap-2">
                            <span class="font-bold text-white leading-none"
                                  style="font-size:56px;letter-spacing:-0.04em;">00</span>
                            <span class="text-sm" style="color:#cfe1ff;">{{ __('dashboard.departing_today') }}</span>
                        </div>
                        @else
                        <p class="text-sm font-semibold" style="color:#cfe1ff;">{{ __('dashboard.trip_in_progress') }}</p>
                        @endif
                    </div>
                    @endif

                    @if (count($progressStages) > 2 && !in_array($focalRequest->status, ['draft', 'cancelled', 'rejected']))
                    <div>
                        <p class="text-[10.5px] font-bold tracking-[0.22em] uppercase mb-3" style="color:#a5cdff;">
                            Approval progress
                        </p>

                        {{-- Segment bar --}}
                        <div class="flex gap-1 mb-2">
                            @foreach ($progressStages as $sIdx => $sLabel)
                            @php
                                $isDone   = $sIdx < $activeStageIdx || $focalRequest->status === 'approved';
                                $isActive = $sIdx === $activeStageIdx && $focalRequest->status !== 'approved';
                            @endphp
                            <div class="flex-1 h-1.5 rounded-full"
                                 style="background:{{ $isDone ? '#daa520' : ($isActive ? 'rgba(255,255,255,0.7)' : 'rgba(255,255,255,0.15)') }};"></div>
                            @endforeach
                        </div>

                        {{-- Labels --}}
                        <div class="flex justify-between">
                            @foreach ($progressStages as $sLabel)
                            <span class="text-[9px] font-semibold uppercase tracking-wider" style="color:#a5cdff;">
                                {{ $sLabel }}
                            </span>
                            @endforeach
                        </div>
                    </div>
                    @elseif ($focalRequest->status === 'approved')
                    <div class="flex items-center gap-2">
                        <div class="h-8 w-8 rounded-full bg-emerald-500/20 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="#4ade80" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <p class="text-sm font-semibold" style="color:#86efac;">{{ __('dashboard.permit_issued_full') }}</p>
                    </div>
                    @endif

                </div>
            </div>

        </div>
    </section>
    @endif

    {{-- ================================================================== --}}
    {{-- KPI TILES                                                           --}}
    {{-- ================================================================== --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        @php
            $kpis = [
                [
                    'label'  => 'Total Submitted',
                    'value'  => $totalRequests,
                    'hint'   => ($user->isHr() || $user->isDirectorGeneral()) ? 'Organisation-wide' : 'All time',
                    'accent' => '#05499c',
                    'icon'   => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
                ],
                [
                    'label'  => 'In Review',
                    'value'  => $pendingCount,
                    'hint'   => 'Awaiting approval',
                    'accent' => '#f59e0b',
                    'icon'   => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
                ],
                [
                    'label'  => 'Approved',
                    'value'  => $approvedCount,
                    'hint'   => 'Permits issued',
                    'accent' => '#16a34a',
                    'icon'   => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
                ],
                [
                    'label'  => 'Returned',
                    'value'  => $returnedCount,
                    'hint'   => 'Need revision',
                    'accent' => '#c2410c',
                    'icon'   => 'M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6',
                ],
            ];
        @endphp
        @foreach ($kpis as $kpi)
        <div class="bg-white rounded-2xl border border-slate-200 p-5 relative overflow-hidden">
            {{-- Top accent line --}}
            <div class="absolute top-0 inset-x-0 h-[3px] rounded-t-2xl"
                 style="background:{{ $kpi['accent'] }};"></div>

            <div class="flex items-start justify-between mb-3">
                <p class="text-[10.5px] font-bold uppercase tracking-widest mt-0.5" style="color:#64748b;">
                    {{ $kpi['label'] }}
                </p>
                <div class="h-8 w-8 rounded-lg flex items-center justify-center shrink-0"
                     style="background:{{ $kpi['accent'] }}18;">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24"
                         stroke="{{ $kpi['accent'] }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="{{ $kpi['icon'] }}"/>
                    </svg>
                </div>
            </div>
            <p class="font-bold leading-none mb-1.5"
               style="font-size:38px;letter-spacing:-0.02em;color:#0e1a2b;">
                {{ $kpi['value'] }}
            </p>
            <p class="text-[11px]" style="color:#94a3b8;">{{ $kpi['hint'] }}</p>
        </div>
        @endforeach
    </div>

    {{-- ================================================================== --}}
    {{-- ACTION REQUIRED ZONE (dark navy)                                    --}}
    {{-- ================================================================== --}}
    @if ($needsMyAction->count() > 0)
    <section class="rounded-2xl p-6" style="background:#0a1628;">

        {{-- Header --}}
        <div class="flex items-center gap-3 mb-5 flex-wrap">
            <h3 class="text-sm font-semibold text-white">{{ __('dashboard.action_required') }}</h3>
            <span class="px-2.5 py-0.5 rounded-full text-[10.5px] font-bold"
                  style="background:#f59e0b;color:#1a0f00;letter-spacing:0.08em;">
                {{ $needsMyAction->count() }} waiting on you
            </span>
            @php $oldest = $needsMyAction->sortBy('submitted_at')->first(); @endphp
            @if ($oldest?->submitted_at)
            <span class="ml-auto text-[11.5px]" style="color:#94a3b8;">
                Oldest: <strong class="text-white">{{ $oldest->submitted_at->diffForHumans(['parts' => 1]) }}</strong>
            </span>
            @endif
        </div>

        {{-- Cards grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
            @foreach ($needsMyAction->take(2) as $tr)
            @php $waitDays = $tr->submitted_at ? (int)$tr->submitted_at->diffInDays(now()) : 0; @endphp
            <div class="rounded-xl p-4 flex flex-col gap-3"
                 style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);">

                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="inline-block w-2 h-2 rounded-full shrink-0"
                                  style="background:{{ $waitDays >= 3 ? '#ef4444' : '#94a3b8' }};"></span>
                            <p class="text-[15px] font-semibold text-white truncate">
                                {{ $tr->requester->name ?? $tr->b_applicant_name ?? '—' }}
                            </p>
                        </div>
                        <p class="text-[11.5px] mt-0.5 pl-4" style="color:#94a3b8;">
                            {{ $tr->requester->job_title ?? __('common.role_' . ($tr->requester->role ?? 'staff')) }}
                        </p>
                    </div>
                    <div class="text-[11px] shrink-0" style="color:#94a3b8;">{{ $waitDays }}d</div>
                </div>

                <p class="text-[12.5px]" style="color:#cbd5e1;">
                    → {{ $tr->b_destination ?? '—' }}
                    @if ($tr->b_departure_date)
                        &nbsp;· departs {{ $tr->b_departure_date->format('d M Y') }}
                    @endif
                </p>

                @if ($tr->submitted_at)
                <p class="text-[11px]" style="color:#64748b;">
                    Submitted {{ $tr->submitted_at->format('d M Y') }}
                </p>
                @endif

                <div class="flex gap-2 mt-auto pt-1">
                    <a href="{{ route('travel-requests.show', $tr->id) }}"
                       class="flex-1 text-center py-2 rounded-lg text-[12px] font-semibold text-white transition hover:opacity-90"
                       style="background:#16a34a;">
                        Review &amp; Approve
                    </a>
                    <button @click="openModal({{ $tr->id }})"
                            class="flex-1 text-center py-2 rounded-lg text-[12px] font-medium transition cursor-pointer"
                            style="color:#cbd5e1;background:transparent;border:1px solid rgba(255,255,255,0.18);">
                        Quick view
                    </button>
                </div>
            </div>
            @endforeach

            {{-- Overflow / caught-up card --}}
            @if ($needsMyAction->count() <= 2)
            <div class="rounded-xl p-4 flex flex-col items-center justify-center gap-2 text-center min-h-[120px]"
                 style="background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06);">
                <p class="text-2xl" style="color:rgba(255,255,255,0.15);">✓</p>
                <p class="text-sm" style="color:#64748b;">You're caught up after these.</p>
                <a href="{{ route('approvals.index') }}"
                   class="text-[12px] font-semibold mt-1 hover:underline" style="color:#a5cdff;">
                    Browse all approvals →
                </a>
            </div>
            @else
            <div class="rounded-xl p-4 flex flex-col items-center justify-center gap-2 text-center min-h-[120px]"
                 style="background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06);">
                <p class="font-bold text-white" style="font-size:32px;">+{{ $needsMyAction->count() - 2 }}</p>
                <p class="text-sm" style="color:#64748b;">more requests need your attention.</p>
                <a href="{{ route('approvals.index') }}"
                   class="inline-block mt-2 px-4 py-2 rounded-lg text-[12px] font-semibold text-white transition hover:opacity-90"
                   style="background:#05499c;">
                    Open approvals queue
                </a>
            </div>
            @endif
        </div>
    </section>
    @endif

    {{-- ================================================================== --}}
    {{-- BOTTOM TWO-COL                                                      --}}
    {{-- ================================================================== --}}
    <div class="grid grid-cols-1 lg:grid-cols-[1.5fr,1fr] gap-5">

        {{-- ── LEFT: Requests list ─────────────────────────────────────── --}}
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">

            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <h3 class="text-[15px] font-semibold" style="color:#0f172a;">
                    {{ ($user->isHr() || $user->isDirectorGeneral()) ? __('dashboard.recent_requests') : __('dashboard.your_requests') }}
                </h3>
                <a href="{{ route('travel-requests.index') }}"
                   class="text-[12.5px] font-semibold hover:underline transition" style="color:#05499c;">
                    View all
                    @if ($listRequests->count() > 5)
                        ({{ $listRequests->count() }})
                    @endif
                    →
                </a>
            </div>

            @if ($listRequests->isEmpty())
            <div class="flex flex-col items-center justify-center py-16 gap-3 px-5 text-center">
                <div class="h-12 w-12 rounded-2xl bg-slate-100 flex items-center justify-center">
                    <svg class="w-6 h-6 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <p class="text-sm" style="color:#94a3b8;">{{ __('dashboard.none_yet') }}</p>
                @if (!$user->isDirectorGeneral())
                    @if ($supervisorRequired && !$supervisor)
                    <a href="#supervisor-card"
                       class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-amber-800 border border-amber-300 bg-amber-50">
                        {{ __('dashboard.set_supervisor_first') }}
                    </a>
                    @else
                    <a href="{{ route('travel-requests.create') }}"
                       class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white"
                       style="background:#05499c;">
                        {{ __('dashboard.new_request_short') }}
                    </a>
                    @endif
                @endif
            </div>
            @else
            @foreach ($listRequests->take(6) as $idx => $tr)
            @php
                $grad     = $avatarGrads[$idx % count($avatarGrads)];
                $initials = strtoupper(substr($tr->b_destination ?? $tr->requester->name ?? '?', 0, 2));
                $badgeStyle = match($tr->status) {
                    'approved'  => 'background:#dcfce7;color:#15803d;',
                    'pending'   => 'background:#fef3c7;color:#a16207;',
                    'returned'  => 'background:#ffedd5;color:#c2410c;',
                    'rejected'  => 'background:#fee2e2;color:#b91c1c;',
                    'draft'     => 'background:#f1f5f9;color:#64748b;',
                    'cancelled' => 'background:#f1f5f9;color:#64748b;',
                    default     => 'background:#f1f5f9;color:#64748b;',
                };
            @endphp
            <button @click="openModal({{ $tr->id }})"
                    class="group w-full text-left flex items-center gap-4 px-5 py-3.5 border-b border-slate-50 hover:bg-slate-50 transition last:border-0">

                {{-- Avatar --}}
                <div class="h-10 w-10 rounded-xl text-white text-xs font-bold flex items-center justify-center shrink-0"
                     style="background:{{ $grad }};">
                    {{ $initials }}
                </div>

                {{-- Destination + request number --}}
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold truncate" style="color:#0f172a;">
                        {{ $tr->b_destination ?? '—' }}
                    </p>
                </div>

                {{-- Date --}}
                <div class="text-[12.5px] shrink-0 hidden sm:block" style="color:#475569;">
                    {{ $tr->b_departure_date ? $tr->b_departure_date->format('d M Y') : '—' }}
                </div>

                {{-- Stage --}}
                <div class="text-[11.5px] shrink-0 hidden md:block w-28 text-right" style="color:#64748b;">
                    @if ($tr->status === 'approved')
                        <span style="color:#15803d;">{{ __('dashboard.permit_issued') }}</span>
                    @elseif ($tr->status === 'returned')
                        <span style="color:#c2410c;font-weight:600;">{{ __('dashboard.action_needed') }}</span>
                    @elseif ($tr->status === 'pending' && $tr->currentApprover)
                        With <span style="color:#0f172a;font-weight:600;">{{ explode(' ', $tr->currentApprover->name)[0] }}</span>
                    @elseif ($tr->status === 'draft')
                        <span style="color:#94a3b8;">{{ __('dashboard.not_submitted') }}</span>
                    @else
                        —
                    @endif
                </div>

                {{-- Status badge --}}
                <span class="text-[10.5px] font-bold uppercase tracking-[0.06em] px-2.5 py-1 rounded-full shrink-0"
                      style="{{ $badgeStyle }}">
                    {{ $tr->statusLabel() }}
                </span>

                <svg class="w-3.5 h-3.5 shrink-0 transition text-slate-300 group-hover:text-slate-400"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </button>
            @endforeach
            @endif

        </div>

        {{-- ── RIGHT COLUMN ────────────────────────────────────────────── --}}
        <div class="flex flex-col gap-4">

            {{-- Supervisor card --}}
            @if (!$user->isDirectorGeneral())
            <div id="supervisor-card" class="bg-white rounded-2xl border border-slate-200{{ ($supervisorRequired && !$supervisor) ? ' ring-2 ring-amber-400' : '' }}">
                <div class="px-5 py-4 border-b border-slate-100 rounded-t-2xl">
                    <h3 class="text-[11px] font-bold uppercase tracking-[0.16em]" style="color:#64748b;">
                        {{ __('dashboard.my_supervisor') }}
                    </h3>
                </div>

                <div class="p-5 space-y-4">
                    @php
                        $currentSupervisorId   = (int) old('supervisor_id', $user->supervisor_id);
                        $currentSupervisorName = $supervisorCandidates->firstWhere('id', $currentSupervisorId)?->name ?? '';
                        $hasValidationError    = $errors->has('supervisor_id');
                    @endphp

                    <div x-data="{ changing: {{ $hasValidationError || !$supervisor ? 'true' : 'false' }} }">

                        {{-- Current supervisor display --}}
                        @if ($supervisor)
                        <div x-show="!changing" class="space-y-3">
                            <div class="flex items-center gap-3">
                                <div class="h-11 w-11 rounded-full text-white text-sm font-bold flex items-center justify-center shrink-0"
                                     style="background:linear-gradient(135deg,#16a34a,#22c55e);">
                                    {{ strtoupper(substr($supervisor->name, 0, 2)) }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold" style="color:#0f172a;">{{ $supervisor->name }}</p>
                                    <p class="text-[11.5px] mt-0.5" style="color:#64748b;">
                                        {{ $supervisor->job_title ?? __('common.role_' . $supervisor->role) }}
                                    </p>
                                </div>
                                @if ($supervisorCandidates->count() > 0)
                                <button type="button" @click="changing = true"
                                        class="shrink-0 text-xs font-semibold px-2.5 py-1 rounded-lg border border-slate-200 text-slate-500 hover:border-blue-300 hover:text-blue-600 hover:bg-blue-50 transition">
                                    Change
                                </button>
                                @endif
                            </div>
                            <div class="rounded-lg px-3 py-2.5 text-[11.5px] leading-relaxed"
                                 style="background:#f0fdf4;border:1px solid #bbf7d0;color:#15803d;">
                                {{ __('dashboard.supervisor_review_hint') }}
                            </div>
                        </div>
                        @elseif (!$supervisorRequired)
                        {{-- Role has no supervisor step (e.g. section head) — the chain is
                             derived from the unit hierarchy, so nothing is missing here. --}}
                        <div class="flex items-start gap-3 p-3 rounded-xl" style="background:#f0f9ff;border:1px solid #bae6fd;">
                            <div class="h-9 w-9 rounded-full flex items-center justify-center shrink-0" style="background:#e0f2fe;">
                                <svg class="w-4 h-4" style="color:#0284c7;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold" style="color:#0c4a6e;">{{ __('dashboard.supervisor_not_needed') }}</p>
                                <p class="text-xs mt-0.5 leading-relaxed" style="color:#0369a1;">
                                    @if ($firstApprover)
                                        @php
                                            $faName  = $firstApprover->name;
                                            $faTitle = $firstApprover->job_title ?: __('common.role_' . $firstApprover->role);
                                            // Some accounts are named after the post they hold
                                            // ("Director Corporate Services" / "Director of Corporate
                                            // Services") — don't print the same thing twice.
                                            $faKey       = fn ($s) => preg_replace('/[^a-z0-9]+/', '', strtolower((string) $s));
                                            $faRedundant = $faKey($faTitle) === '' || str_contains($faKey($faName), $faKey($faTitle)) || str_contains($faKey($faTitle), $faKey($faName));
                                        @endphp
                                        {{ $faRedundant
                                            ? __('dashboard.supervisor_not_needed_hint_named_plain', ['name' => $faName])
                                            : __('dashboard.supervisor_not_needed_hint_named', ['name' => $faName, 'title' => $faTitle]) }}
                                    @else
                                        {{ __('dashboard.supervisor_not_needed_hint') }}
                                    @endif
                                </p>
                            </div>
                        </div>
                        @else
                        <div class="flex items-start gap-3 p-3 rounded-xl border border-slate-100 bg-slate-50">
                            <div class="h-9 w-9 rounded-full bg-slate-200 flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-slate-700">{{ __('dashboard.supervisor_not_assigned') }}</p>
                                <p class="text-xs text-slate-400 mt-0.5">{{ __('dashboard.supervisor_not_assigned_hint') }}</p>
                            </div>
                        </div>
                        @endif

                        {{-- No candidates and none set — only a problem when one is actually required --}}
                        @if ($supervisorRequired && $supervisorCandidates->isEmpty() && !$supervisor)
                        <div class="mt-3 flex items-start gap-2.5 rounded-xl border border-amber-200 bg-amber-50 px-3.5 py-3">
                            <svg class="w-4 h-4 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                            <p class="text-xs text-amber-800 leading-relaxed">{{ __('dashboard.supervisor_candidates_empty') }}</p>
                        </div>
                        @endif

                        {{-- Supervisor combobox --}}
                        @if ($supervisorCandidates->count() > 0)
                        <div x-show="changing" x-cloak class="mt-3">
                        <form method="POST" action="{{ route('dashboard.supervisor.update') }}" class="space-y-2.5">
                            @csrf @method('PATCH')
                            <div class="flex items-center justify-between">
                                <label class="block text-[10.5px] font-bold uppercase tracking-widest" style="color:#64748b;">
                                    {{ $supervisor ? __('dashboard.supervisor_change') : __('dashboard.supervisor_change') }}
                                </label>
                                @if ($supervisor)
                                <button type="button" @click="changing = false"
                                        class="text-xs text-slate-400 hover:text-slate-600 transition">
                                    Cancel
                                </button>
                                @endif
                            </div>

                            <div x-data="{
                                    open: false,
                                    search: '{{ addslashes($currentSupervisorName) }}',
                                    selectedId: {{ $currentSupervisorId ?: 'null' }},
                                    candidates: {{ Js::from($supervisorCandidates->map(fn($c) => ['id' => $c->id, 'name' => $c->name, 'title' => $c->job_title ?? __('common.role_' . $c->role)])->values()) }},
                                    get filtered() {
                                        if (!this.search) return this.candidates;
                                        const q = this.search.toLowerCase();
                                        return this.candidates.filter(c => c.name.toLowerCase().includes(q) || c.title.toLowerCase().includes(q));
                                    },
                                    select(c) { this.selectedId = c.id; this.search = c.name; this.open = false; }
                                 }"
                                 class="relative" @click.outside="open = false">

                                <input type="hidden" name="supervisor_id" :value="selectedId ?? ''">

                                <div class="relative">
                                    <input type="text"
                                           x-model="search"
                                           @focus="open = true"
                                           @input="open = true; selectedId = null"
                                           placeholder="Search by name…"
                                           autocomplete="off"
                                           class="w-full rounded-xl border border-slate-200 px-3 py-2.5 pr-9 text-sm placeholder-slate-400 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 focus:outline-none transition"
                                           style="color:#0e1a2b;">
                                    <button type="button" @click="open = !open"
                                            class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                </div>

                                <div x-show="open" x-cloak
                                     class="absolute z-50 w-full mt-1 bg-white border border-slate-200 rounded-xl shadow-lg overflow-y-auto"
                                     style="max-height:200px;">
                                    <button type="button"
                                            @click="select({ id: null, name: '', title: '' }); selectedId = null; search = ''"
                                            class="w-full text-left px-3 py-2 text-xs text-slate-400 hover:bg-slate-50 border-b border-slate-100 italic">
                                        {{ __('dashboard.supervisor_clear') }}
                                    </button>
                                    <template x-for="c in filtered" :key="c.id">
                                        <button type="button"
                                                @click="select(c)"
                                                class="w-full text-left px-3 py-2.5 hover:bg-blue-50 flex items-center gap-2.5 border-b border-slate-50 last:border-0 transition"
                                                :class="{ 'bg-blue-50': selectedId === c.id }">
                                            <div class="h-7 w-7 rounded-full flex items-center justify-center text-white text-xs font-bold shrink-0"
                                                 style="background:linear-gradient(135deg,#16a34a,#22c55e);"
                                                 x-text="c.name.substring(0,2).toUpperCase()"></div>
                                            <div class="min-w-0">
                                                <p class="text-sm font-medium text-slate-800 truncate" x-text="c.name"></p>
                                                <p class="text-xs text-slate-400 truncate" x-text="c.title"></p>
                                            </div>
                                        </button>
                                    </template>
                                    <div x-show="filtered.length === 0"
                                         class="px-3 py-3 text-sm text-slate-400 text-center">{{ __('dashboard.no_results') }}</div>
                                </div>
                            </div>

                            @error('supervisor_id')
                            <p class="text-xs text-red-600">{{ $message }}</p>
                            @enderror

                            <button type="submit"
                                    class="w-full py-2.5 rounded-xl text-sm font-semibold text-white transition hover:opacity-90"
                                    style="background:#16a34a;">
                                {{ __('dashboard.supervisor_save') }}
                            </button>
                        </form>
                        </div>
                        @endif

                    </div>
                </div>
            </div>
            @endif

            {{-- Profile card --}}
            <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
                {{-- Banner --}}
                <div class="h-16 relative" style="background:linear-gradient(135deg,#dbeafe,#e0e7ff);">
                    <div class="absolute" style="bottom:-28px;left:20px;">
                        <div class="h-14 w-14 rounded-full text-white text-lg font-bold flex items-center justify-center border-4 border-white shadow-md"
                             style="background:linear-gradient(135deg,#03316e,#1a6abf);">
                            {{ strtoupper(substr($user->name, 0, 2)) }}
                        </div>
                    </div>
                    <a href="{{ route('profile.edit') }}"
                       class="absolute top-3 right-4 text-[11px] font-semibold hover:opacity-80 transition"
                       style="color:#1e3a8a;">
                        Edit profile →
                    </a>
                </div>

                <div class="pt-10 pb-5 px-5">
                    <p class="text-[15px] font-bold" style="color:#0f172a;">{{ $user->name }}</p>
                    <p class="text-[12.5px] mt-0.5" style="color:#64748b;">
                        {{ $user->job_title ?? __('common.role_' . $user->role) }}
                        @if ($user->unit) · {{ $user->unit->name }} @endif
                    </p>

                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <div class="rounded-xl p-3 col-span-2" style="background:#f8fafc;">
                            <p class="text-[9.5px] font-bold uppercase tracking-wider mb-1" style="color:#94a3b8;">Role</p>
                            <p class="text-[12.5px] font-medium truncate" style="color:#0f172a;">
                                {{ __('common.role_' . $user->role) }}
                            </p>
                        </div>
                        <div class="rounded-xl p-3 col-span-2" style="background:#f8fafc;">
                            <p class="text-[9.5px] font-bold uppercase tracking-wider mb-1" style="color:#94a3b8;">Email</p>
                            <p class="text-[12.5px] font-medium break-all" style="color:#0f172a;">
                                {{ $user->email }}
                            </p>
                        </div>
                        @if ($user->phone)
                        <div class="rounded-xl p-3 col-span-2" style="background:#f8fafc;">
                            <p class="text-[9.5px] font-bold uppercase tracking-wider mb-1" style="color:#94a3b8;">Phone</p>
                            <p class="text-[12.5px] font-medium" style="color:#0f172a;">{{ $user->phone }}</p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

        </div>{{-- end right col --}}

    </div>{{-- end two-col --}}

    {{-- ================================================================== --}}
    {{-- REQUEST DETAIL MODAL                                                --}}
    {{-- ================================================================== --}}
    <div x-show="open" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">

        <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="closeModal()"></div>

        <div class="relative w-full max-w-lg rounded-2xl shadow-2xl overflow-hidden"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95 translate-y-2"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
             x-transition:leave-end="opacity-0 scale-95 translate-y-2">

            {{-- Modal header --}}
            <div class="px-6 py-6 flex items-start justify-between gap-4"
                 style="background:linear-gradient(135deg,#03316e 0%,#05499c 60%,#1a6abf 100%);">
                <div class="min-w-0 flex-1">
                    <h3 class="text-xl font-bold text-white leading-snug" x-text="request?.destination ?? '—'"></h3>
                    <div class="mt-3">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold border"
                              style="background:rgba(255,255,255,0.15);color:white;border-color:rgba(255,255,255,0.25);"
                              x-text="request?.status_label ?? ''"></span>
                    </div>
                </div>
                <button @click="closeModal()"
                        class="h-8 w-8 rounded-lg flex items-center justify-center transition shrink-0 mt-0.5"
                        style="color:rgba(255,255,255,0.6);"
                        onmouseover="this.style.background='rgba(255,255,255,0.1)'"
                        onmouseout="this.style.background='transparent'">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Modal body --}}
            <div class="px-5 py-5 space-y-3" style="background:#f1f5f9;">
                <dl class="grid grid-cols-2 gap-2.5">
                    <div class="bg-white rounded-xl border border-slate-200 px-4 py-3 shadow-sm">
                        <dt class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-1">Applicant</dt>
                        <dd class="text-sm font-semibold text-slate-800 truncate" x-text="request?.applicant ?? '—'"></dd>
                    </div>
                    <div class="bg-white rounded-xl border border-slate-200 px-4 py-3 shadow-sm">
                        <dt class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-1">Unit</dt>
                        <dd class="text-sm font-semibold text-slate-800 truncate" x-text="request?.unit ?? '—'"></dd>
                    </div>
                    <div class="bg-white rounded-xl border border-blue-100 px-4 py-3 shadow-sm">
                        <dt class="text-[10px] font-bold uppercase tracking-widest mb-1" style="color:#93c5fd;">Departs</dt>
                        <dd class="text-sm font-bold text-blue-700" x-text="request?.departure ?? '—'"></dd>
                    </div>
                    <div class="bg-white rounded-xl border border-violet-100 px-4 py-3 shadow-sm">
                        <dt class="text-[10px] font-bold uppercase tracking-widest mb-1" style="color:#c4b5fd;">Returns</dt>
                        <dd class="text-sm font-bold text-violet-700" x-text="request?.return ?? '—'"></dd>
                    </div>
                    <div class="bg-white rounded-xl border border-slate-200 px-4 py-3 shadow-sm" x-show="request?.submitted_at">
                        <dt class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-1">Submitted</dt>
                        <dd class="text-sm font-semibold text-slate-800" x-text="request?.submitted_at"></dd>
                    </div>
                    <div class="bg-white rounded-xl border border-amber-100 px-4 py-3 shadow-sm" x-show="request?.approver">
                        <dt class="text-[10px] font-bold uppercase tracking-widest mb-1" style="color:#fcd34d;">With</dt>
                        <dd class="text-sm font-semibold text-slate-800 truncate" x-text="request?.approver"></dd>
                    </div>
                </dl>
                <div x-show="request?.purpose" class="bg-white rounded-xl border border-slate-200 px-4 py-3 shadow-sm">
                    <dt class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-1.5">Purpose</dt>
                    <dd class="text-sm text-slate-700 leading-relaxed line-clamp-4" x-text="request?.purpose"></dd>
                </div>
            </div>

            {{-- Modal footer --}}
            <div class="px-5 py-4 bg-white border-t border-slate-200 flex items-center justify-between gap-3">
                <button @click="closeModal()"
                        class="text-sm font-medium text-slate-500 hover:text-slate-700 transition px-3 py-2 rounded-lg hover:bg-slate-100">
                    Close
                </button>
                <a :href="request?.url ?? '#'"
                   class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold text-white shadow-md transition hover:opacity-90"
                   style="background:linear-gradient(135deg,#03316e,#05499c);">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    View Full Request
                </a>
            </div>

        </div>
    </div>

</div>
</x-app-layout>
