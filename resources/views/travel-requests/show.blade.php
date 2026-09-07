<x-app-layout>
<div class="px-6 py-6 lg:px-8 lg:py-8" x-data="approvalModal()">

    {{-- Flash --}}
    @if (session('status'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
        x-transition:leave="transition ease-in duration-300" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
        class="flex items-center gap-3 p-4 mb-5 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-700 text-sm">
        <svg class="w-5 h-5 shrink-0 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        <span class="flex-1">{{ session('status') }}</span>
        <button @click="show = false"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
    </div>
    @endif

    {{-- Overlap warning --}}
    @if (session('overlap_warning'))
    <div class="flex items-start gap-3 p-4 mb-5 bg-amber-50 border border-amber-200 rounded-xl text-amber-800 text-sm">
        <svg class="w-5 h-5 shrink-0 mt-0.5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
        <span class="flex-1"><strong>Overlapping dates:</strong> Request <strong>{{ session('overlap_warning') }}</strong> covers the same travel period. Please review your requests to avoid conflicts.</span>
    </div>
    @endif

    {{-- ══ PREMIUM PAGE HEADER ════════════════════════════════════════════ --}}
    @php $tr = $travelRequest; @endphp
    <div class="rounded-xl overflow-hidden mb-6 shadow-sm" style="background-color:#05499c;">
        <div class="px-6 py-5">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="flex items-center gap-3 flex-wrap mb-2">
                        <h1 class="text-2xl font-bold text-white tracking-tight">
                            {{ $tr->b_destination ?? __('travel.untitled') }}
                        </h1>
                        <span class="badge {{ $tr->statusColor() }} text-sm px-3 py-1">{{ $tr->statusLabel() }}</span>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap text-sm mt-1" style="color:rgba(255,255,255,0.7);">
                        @if ($tr->requester->name)
                        <span class="font-semibold text-white">{{ $tr->requester->name }}</span>
                        @endif
                        @if ($tr->b_departure_date)
                        <span style="color:rgba(255,255,255,0.35);">&middot;</span>
                        <span class="flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:rgba(255,255,255,0.5);"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            {{ $tr->b_departure_date->format('d M Y') }}
                            @if ($tr->b_return_date) &mdash; {{ $tr->b_return_date->format('d M Y') }} @endif
                        </span>
                        @endif
                        @if ($tr->submitted_at)
                        <span style="color:rgba(255,255,255,0.35);">&middot;</span>
                        <span>{{ __('travel.submitted_at', ['date' => $tr->submitted_at->format('d M Y, H:i')]) }}</span>
                        @endif
                    </div>
                    @if ($tr->status === \App\Models\TravelRequest::STATUS_PENDING && $tr->currentApprover)
                    <p class="text-xs font-medium mt-2 flex items-center gap-1.5" style="color:#fcd34d;">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        {{ __('travel.waiting_for', ['name' => $tr->currentApprover->name]) }}
                    </p>
                    @endif
                </div>

                {{-- Action buttons --}}
                <div class="flex items-center gap-2 shrink-0 flex-wrap justify-end">
                    <a href="{{ route('travel-requests.pdf', $tr) }}"
                       class="btn btn-sm text-white font-semibold transition hover:opacity-90"
                       style="background:#0f8a4b;">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        PDF
                    </a>
                    <a href="{{ route('travel-requests.print', $tr) }}"
                       class="btn btn-sm bg-white font-semibold transition hover:bg-slate-100"
                       style="color:#374151;" target="_blank">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        Print
                    </a>
                    @if ($tr->isEditable() && $tr->requester_id === auth()->id())
                    <a href="{{ route('travel-requests.edit', $tr) }}"
                       class="btn btn-sm bg-white font-semibold transition hover:bg-slate-100"
                       style="color:#05499c;">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        {{ __('common.edit') }}
                    </a>
                    @endif
                    @if ($tr->isCancellable() && $tr->requester_id === auth()->id())
                    <button @click="confirmCancel()"
                            class="btn btn-sm bg-red-500 hover:bg-red-400 text-white font-semibold transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        {{ __('travel.cancel_request') }}
                    </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ══ TWO-COLUMN LAYOUT ══════════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

        {{-- ── LEFT (2/3): approval action + official form ──────────────── --}}
        <div class="xl:col-span-2 space-y-5">

            {{-- Accumulated days out of office — advisory, shown to the traveller
                 and to anyone reviewing the request --}}
            @isset($travelDays)
                <x-travel-days-warning
                    :travel-days="$travelDays"
                    :context="(int) $tr->requester_id === (int) auth()->id() ? 'requester' : 'approver'" />
            @endisset

            {{-- Approval action form --}}
            @if ($tr->status === \App\Models\TravelRequest::STATUS_PENDING && (int)$tr->current_approver_id === (int)auth()->id())
            {{-- ▶ It's this user's turn: show the decision card --}}
            <div class="card overflow-hidden" style="border-left: 4px solid #05499c;">
                <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-3" style="background-color:#05499c0a;">
                    <div class="h-8 w-8 rounded-full flex items-center justify-center shrink-0" style="background-color:#05499c20;">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:#05499c;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-800">{{ __('travel.approval_action') }}</h3>
                        <p class="text-xs text-slate-500 mt-0.5">{{ __('travel.read_before_decide') }}</p>
                    </div>
                </div>
                <form id="approval-form" method="POST" action="{{ route('travel-requests.approve', $tr) }}" class="p-5 space-y-4">
                    @csrf
                    <input type="hidden" name="decision" id="approval-decision-input" value="">

                    @if ($errors->any())
                    <div class="p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-xs">
                        @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                    </div>
                    @endif

                    <div class="field">
                        <label class="label">
                            {{ __('travel.comments') }}
                            <span class="font-normal text-slate-400">{{ __('travel.comments_hint') }}</span>
                        </label>
                        <textarea name="comment" rows="3" placeholder="{{ __('travel.comments') }}..." class="input resize-none">{{ old('comment') }}</textarea>
                    </div>

                    <div class="flex flex-wrap gap-2 pt-1">
                        <button type="button" @click="openModal('approved')" class="btn-success btn-sm">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            {{ __('travel.approve_btn') }}
                        </button>
                        <button type="button" @click="openModal('returned')" class="btn-warning btn-sm">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                            {{ __('travel.return_btn') }}
                        </button>
                        <button type="button" @click="openModal('rejected')" class="btn-danger btn-sm">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            {{ __('travel.reject_btn') }}
                        </button>
                    </div>
                </form>
            </div>
            @elseif ($tr->status === \App\Models\TravelRequest::STATUS_PENDING && $tr->currentApprover && (int)$tr->current_approver_id !== (int)auth()->id() && auth()->user()->isApprover())
            @php $myAction = $travelRequest->approvalActions->firstWhere('actor_id', auth()->id()); @endphp
            @if ($myAction)
            {{-- ▶ This approver already acted — show what they did --}}
            <div class="card overflow-hidden" style="border-left: 4px solid #16a34a;">
                <div class="px-5 py-4 flex items-center gap-3">
                    <div class="h-8 w-8 rounded-full flex items-center justify-center shrink-0" style="background-color:#dcfce7;">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-slate-800">
                            You {{ $myAction->decision === 'approved' ? 'approved' : ($myAction->decision === 'returned' ? 'returned' : 'rejected') }} this request on {{ $myAction->created_at->format('d M Y, H:i') }}.
                        </p>
                        <p class="text-xs text-slate-500 mt-0.5">Currently with: {{ $tr->currentApprover->name }}</p>
                    </div>
                </div>
            </div>
            @else
            {{-- ▶ Pending but not yet this approver's turn --}}
            <div class="card overflow-hidden" style="border-left: 4px solid #f59e0b;">
                <div class="px-5 py-4 flex items-center gap-3" style="background-color:#fef3c70a;">
                    <div class="h-8 w-8 rounded-full flex items-center justify-center shrink-0" style="background-color:#fef3c7;">
                        <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-slate-800">{{ __('travel.not_your_turn') }}</p>
                        <p class="text-xs text-slate-500 mt-0.5">{{ __('travel.awaiting_approver', ['name' => $tr->currentApprover->name]) }}</p>
                    </div>
                </div>
            </div>
            @endif
            @elseif ($tr->status === \App\Models\TravelRequest::STATUS_DRAFT && auth()->user()->isApprover() && $tr->requester_id !== auth()->id())
            {{-- ▶ Draft — not submitted yet --}}
            <div class="card overflow-hidden" style="border-left: 4px solid #94a3b8;">
                <div class="px-5 py-4 flex items-center gap-3">
                    <div class="h-8 w-8 rounded-full flex items-center justify-center shrink-0 bg-slate-100">
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-slate-700">{{ __('travel.still_draft') }}</p>
                        <p class="text-xs text-slate-400 mt-0.5">{{ __('travel.still_draft_hint') }}</p>
                    </div>
                </div>
            </div>
            @endif

            {{-- Official Form --}}
            <div class="card overflow-hidden">
                <div class="text-center px-8 py-6 border-b-2 border-slate-800 bg-white">
                    <p class="text-right text-xs italic font-semibold text-slate-600">{{ __('travel.form_number') }}</p>
                    <div class="flex justify-center my-3">
                        <img src="{{ asset('NIMR.png') }}" alt="NIMR" class="h-14 w-14 object-contain">
                    </div>
                    <h1 class="text-xs font-bold uppercase tracking-wide text-slate-800">{{ __('travel.institution_name') }}</h1>
                    <h2 class="text-xs font-bold uppercase tracking-wide mt-1 text-slate-800">{{ __('travel.form_full_name') }}</h2>
                </div>

                @php
                    $ro     = 'flex-1 text-sm text-slate-800 border-b border-slate-200 py-0.5 px-1 bg-transparent';
                    $numCls = 'text-sm w-10 shrink-0 text-slate-400';
                    $lblCls = 'text-sm w-72 shrink-0 text-slate-600';
                @endphp

                <div class="p-6 space-y-8">

                    {{-- A --}}
                    <div>
                        <h3 class="text-xs font-bold uppercase tracking-widest border-b pb-2 mb-4" style="color:#05499c;border-color:#bfdbfe;">A: {{ __('travel.section_a_title') }}</h3>
                        <div class="text-xs space-y-2 text-slate-600 leading-relaxed bg-slate-50 rounded-lg p-4 border border-slate-100">
                            <p><span class="font-semibold text-slate-700">(i)</span> {{ __('travel.section_a_i') }}</p>
                            <p><span class="font-semibold text-slate-700">(ii)</span> {{ __('travel.section_a_ii') }}</p>
                            <p><span class="font-semibold text-slate-700">(iii)</span> {{ __('travel.section_a_iii') }}</p>
                            <p><span class="font-semibold text-slate-700">(iv)</span> {{ __('travel.section_a_iv') }}</p>
                            <p><span class="font-semibold text-slate-700">(v)</span> {{ __('travel.section_a_v') }}</p>
                            <p><span class="font-semibold text-slate-700">(vi)</span> {{ __('travel.section_a_viii') }}</p>
                        </div>
                    </div>

                    {{-- B --}}
                    <div>
                        <h3 class="text-xs font-bold uppercase tracking-widest border-b pb-2 mb-4" style="color:#05499c;border-color:#bfdbfe;">B: {{ __('travel.section_b_title') }}</h3>
                        <div class="space-y-3">
                            @foreach ([
                                ['(i)',   __('travel.b_name'),           $tr->requester->name],
                                ['(ii)',  __('travel.b_phone'),          $tr->requester->phone],
                                ['(iii)', __('travel.b_email'),          $tr->requester->email],
                                ['(iv)',  __('travel.b_position'),       $tr->requester->job_title],
                                ['(v)',   __('travel.b_destination'),    $tr->b_destination],
                                ['(vi)',  __('travel.b_departure_date'), $tr->b_departure_date?->format('d M Y')],
                                ['(vii)', __('travel.b_return_date'),    $tr->b_return_date?->format('d M Y')],
                            ] as [$num, $label, $value])
                            <div class="flex items-baseline gap-2">
                                <span class="{{ $numCls }}">{{ $num }}</span>
                                <span class="{{ $lblCls }}">{{ $label }}</span>
                                <span class="{{ $ro }}">{{ $value ?? '—' }}</span>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- C --}}
                    <div>
                        <h3 class="text-xs font-bold uppercase tracking-widest border-b pb-2 mb-3" style="color:#05499c;border-color:#bfdbfe;">C: {{ __('travel.section_c_title') }}</h3>
                        <div class="text-sm text-slate-800 bg-slate-50 rounded-lg p-4 border border-slate-100 whitespace-pre-wrap min-h-[4rem]">{{ $tr->c_travel_source ?? '—' }}</div>
                    </div>

                    {{-- D --}}
                    <div>
                        <h3 class="text-xs font-bold uppercase tracking-widest border-b pb-2 mb-4" style="color:#05499c;border-color:#bfdbfe;">D: {{ __('travel.section_d_title') }}</h3>
                        <p class="text-sm font-medium text-slate-600 mb-3">{{ __('travel.d_benefits_sub') }}</p>
                        <div class="space-y-4">
                            @foreach ([
                                [__('travel.d_institution'), $tr->d_benefit_to_institution],
                                [__('travel.d_nation'),      $tr->d_benefit_to_nation],
                                [__('travel.d_consequences'),$tr->d_consequences_if_rejected],
                            ] as [$label, $value])
                            <div>
                                <p class="text-sm font-medium text-slate-600 mb-1.5">{{ $label }}</p>
                                <div class="text-sm text-slate-800 bg-slate-50 rounded-lg p-4 border border-slate-100 whitespace-pre-wrap min-h-[3rem]">{{ $value ?? '—' }}</div>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- E --}}
                    <div>
                        <h3 class="text-xs font-bold uppercase tracking-widest border-b pb-2 mb-4" style="color:#05499c;border-color:#bfdbfe;">E: {{ __('travel.section_e_title') }}</h3>
                        <div class="space-y-4">
                            <div>
                                <p class="text-sm font-medium text-slate-600 mb-1.5">{{ __('travel.e_transport') }}</p>
                                <div class="text-sm text-slate-800 bg-slate-50 rounded-lg p-4 border border-slate-100 whitespace-pre-wrap min-h-[2.5rem]">{{ $tr->e_transport_costs ?? '—' }}</div>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-slate-600 mb-2">{{ __('travel.e_allowances') }}</p>
                                <div class="ml-4 space-y-1.5">
                                    @foreach (['a' => $tr->e_allowance_a, 'b' => $tr->e_allowance_b, 'c' => $tr->e_allowance_c, 'd' => $tr->e_allowance_d] as $letter => $val)
                                    <div class="flex items-baseline gap-2">
                                        <span class="{{ $numCls }}">({{ $letter }})</span>
                                        <span class="{{ $ro }}">{{ $val ?? '—' }}</span>
                                    </div>
                                    @endforeach
                                    <div class="flex items-baseline gap-2">
                                        <span class="text-sm shrink-0 text-slate-600">{{ __('travel.e_budget_line') }}</span>
                                        <span class="{{ $ro }}">{{ $tr->e_budget_line ?? '—' }}</span>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-slate-600 mb-2">{{ __('travel.e_donor') }}</p>
                                <div class="ml-4 space-y-1">
                                    @foreach (['i' => $tr->e_donor_cost_i, 'ii' => $tr->e_donor_cost_ii, 'iii' => $tr->e_donor_cost_iii] as $num => $val)
                                    <div class="flex items-baseline gap-2"><span class="{{ $numCls }}">({{ $num }})</span><span class="{{ $ro }}">{{ $val ?? '—' }}</span></div>
                                    @endforeach
                                </div>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-slate-600 mb-2">{{ __('travel.e_govt') }}</p>
                                <div class="ml-4 space-y-1">
                                    @foreach (['i' => $tr->e_govt_cost_i, 'ii' => $tr->e_govt_cost_ii, 'iii' => $tr->e_govt_cost_iii] as $num => $val)
                                    <div class="flex items-baseline gap-2"><span class="{{ $numCls }}">({{ $num }})</span><span class="{{ $ro }}">{{ $val ?? '—' }}</span></div>
                                    @endforeach
                                </div>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-slate-600 mb-1.5">{{ __('travel.e_other') }}</p>
                                <div class="text-sm text-slate-800 bg-slate-50 rounded-lg p-4 border border-slate-100 whitespace-pre-wrap min-h-[2.5rem]">{{ $tr->e_other_costs ?? '—' }}</div>
                            </div>
                        </div>
                    </div>

                    {{-- F --}}
                    <div>
                        <h3 class="text-xs font-bold uppercase tracking-widest border-b pb-2 mb-3" style="color:#05499c;border-color:#bfdbfe;">F: {{ __('travel.section_f_title') }}</h3>
                        <div class="text-sm text-slate-800 bg-slate-50 rounded-lg p-4 border border-slate-100 whitespace-pre-wrap min-h-[4rem]">{{ $tr->f_previous_travel_impact ?? '—' }}</div>
                    </div>

                    {{-- G --}}
                    <div>
                        <h3 class="text-xs font-bold uppercase tracking-widest border-b pb-2 mb-3" style="color:#05499c;border-color:#bfdbfe;">G: {{ __('travel.section_g_title') }}</h3>
                        @if ($tr->g_no_handover_officer)
                        {{-- The traveller declared there is nobody to hand over to.
                             Their declaration stands in place of the handover note,
                             so the approver must actually see it. --}}
                        <div class="mb-3 rounded-lg border border-amber-200 bg-amber-50 p-4">
                            <p class="text-xs font-bold uppercase tracking-widest text-amber-800 mb-2">
                                {{ __('travel.g_no_handover_shown_label') }}
                            </p>
                            <p class="text-sm text-amber-900 whitespace-pre-wrap leading-relaxed">{{ $tr->g_no_handover_declaration }}</p>
                        </div>
                        @else
                        <div class="space-y-2 mb-3">
                            <div class="flex items-baseline gap-2">
                                <span class="text-sm w-16 shrink-0 text-slate-600">{{ __('travel.signed_name') }}</span>
                                <span class="{{ $ro }}">{{ $tr->g_handover_officer_name ?? '—' }}</span>
                            </div>
                            <div class="flex items-baseline gap-2">
                                <span class="text-sm w-16 shrink-0 text-slate-600">{{ __('travel.signed_title') }}</span>
                                <span class="{{ $ro }}">{{ $tr->g_handover_officer_title ?? '—' }}</span>
                            </div>
                        </div>
                        @endif
                        @if ($tr->g_handover_document)
                        <a href="{{ route('travel-requests.download', $tr) }}" class="btn-secondary btn-sm w-fit">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            {{ __('common.download') }} Handover Note
                        </a>
                        @endif
                        @if ($tr->c_invitation_document)
                        <a href="{{ route('travel-requests.invitation.download', $tr) }}" class="btn-secondary btn-sm w-fit">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            {{ __('travel.c_invitation_download') }}
                        </a>
                        @endif
                    </div>

                    {{-- H/I/J/K approval signatures --}}
                    @if ($travelRequest->approvalActions->count() > 0)
                    <div class="border-t-2 border-slate-800 pt-6 space-y-6">
                        @php
                            $sectionLabels = [
                                'supervisor' => __('travel.h_section'),
                                'director'   => __('travel.i_section'),
                                'final'      => __('travel.j_section'),
                                'hr'         => __('travel.k_section'),
                            ];
                        @endphp
                        @foreach ($travelRequest->approvalActions as $action)
                        <div>
                            <h3 class="text-xs font-bold uppercase tracking-widest border-b pb-2 mb-3" style="color:#05499c;border-color:#bfdbfe;">
                                {{ $sectionLabels[$action->stage] ?? $action->stage }}
                            </h3>
                            @if ($action->comment)
                        <div class="mb-4">
                            <p class="text-sm text-slate-600 mb-1.5">{{ __('travel.i_benefit_label') }}</p>
                            <div class="text-sm text-slate-800 bg-slate-50 rounded-lg p-4 border border-slate-100 whitespace-pre-wrap">{{ $action->comment }}</div>
                        </div>
                        @endif
                        <div class="flex items-baseline gap-8 text-sm text-slate-700 mt-1">
                            <div>
                                <span class="text-xs text-slate-400 block mb-0.5">{{ __('travel.signed_name') }}</span>
                                <span class="font-semibold text-slate-800">{{ $action->actor?->name ?? '—' }}</span>
                            </div>
                            <div>
                                <span class="text-xs text-slate-400 block mb-0.5">{{ __('travel.signed_title') }}</span>
                                <span class="font-semibold text-slate-800">{{ $action->actor?->job_title ?? '—' }}</span>
                            </div>
                        </div>
                        </div>
                        @endforeach
                    </div>
                    @endif

                </div>
            </div>

        </div>

        {{-- ── RIGHT (1/3): approval timeline + info card ─────────────────── --}}
        <div class="space-y-5">

            {{-- Approval flow timeline --}}
            @php
                $hasChain   = !empty($travelRequest->approval_chain);
                $hasActions = $travelRequest->approvalActions->count() > 0;
                $reqStatus  = $travelRequest->status;

                // Index of the first truly terminal action in the chain.
                // 'rejected' is always terminal.
                // 'returned' is only terminal while the request is still awaiting resubmission;
                //  once resubmitted (status back to 'pending') it is historical, not terminal.
                $terminalIndex = null;
                if ($hasChain) {
                    foreach ($travelRequest->approval_chain as $idx => $cs) {
                        $ca = $travelRequest->approvalActions->where('stage', $cs['stage'])->first();
                        if ($ca && $ca->decision === 'rejected') {
                            $terminalIndex = $idx;
                            break;
                        }
                        if ($ca && $ca->decision === 'returned'
                            && $reqStatus === \App\Models\TravelRequest::STATUS_RETURNED) {
                            $terminalIndex = $idx;
                            break;
                        }
                    }
                }
            @endphp
            @if ($hasChain || $hasActions)
            <div class="rounded-xl overflow-hidden shadow-sm p-5" style="background-color:#059669;">
                <h3 class="text-[10px] font-bold uppercase tracking-widest mb-5" style="color:rgba(255,255,255,0.65);">{{ __('travel.approval_flow') }}</h3>
                <ol class="relative ml-3 space-y-6" style="border-left: 2px solid rgba(255,255,255,0.25);">

                    {{-- ① Submission node --}}
                    <li class="ml-6">
                        <span class="absolute -left-[9px] w-4 h-4 rounded-full ring-4 ring-blue-100" style="background-color:#3b82f6;"></span>
                        <div class="pl-1">
                            <p class="text-xs font-bold text-white">{{ __('common.stage_submitted') }}</p>
                            <p class="text-xs mt-0.5" style="color:rgba(255,255,255,0.75);">{{ $tr->requester->name }}</p>
                            @if ($tr->requester->job_title)
                            <p class="text-[10px]" style="color:rgba(255,255,255,0.5);">{{ $tr->requester->job_title }}</p>
                            @endif
                            @if ($tr->submitted_at)
                            <span class="inline-flex items-center gap-1 mt-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold border text-blue-700 bg-blue-50 border-blue-200">
                                {{ $tr->submitted_at->format('d M Y, H:i') }}
                            </span>
                            @endif
                        </div>
                    </li>

                    @if ($hasChain)
                    {{-- ② Full chain — all steps always visible --}}
                    @foreach ($travelRequest->approval_chain as $index => $step)
                    @php
                        $approver     = $chainApprovers->get((int)$step['approver_id']);

                        $stageActions = $travelRequest->approvalActions
                                            ->where('stage', $step['stage'])
                                            ->sortBy('acted_at')
                                            ->values();
                        $action  = $stageActions->last();
                        $isDone  = $stageActions->isNotEmpty();

                        $isCurrent       = (int)$travelRequest->current_approver_id === (int)$step['approver_id']
                                           && $reqStatus === \App\Models\TravelRequest::STATUS_PENDING;
                        $isAfterTerminal = $terminalIndex !== null && $index > $terminalIndex;
                        $showDivider     = $isAfterTerminal && $index === $terminalIndex + 1;

                        // Split stage actions at the first 'returned' decision.
                        // This drives the Resubmitted node regardless of whether the approver
                        // is still waiting or has already decided in the new cycle.
                        $returnIdx        = $stageActions->search(fn($a) => $a->decision === 'returned');
                        $preReturnActions = $returnIdx !== false
                            ? $stageActions->take($returnIdx + 1)->values()
                            : $stageActions;
                        $postReturnActions = $returnIdx !== false
                            ? $stageActions->skip($returnIdx + 1)->values()
                            : collect();

                        // needsReturnSplit: stage went through at least one return cycle
                        // (either still pending after resubmission, or already resolved in second cycle)
                        $isCurrentWithHistory = $isCurrent  && $returnIdx !== false;
                        $hadReturnCycle       = !$isCurrent && $returnIdx !== false && $postReturnActions->isNotEmpty();
                        $needsReturnSplit     = $isCurrentWithHistory || $hadReturnCycle;

                        // Badge helper
                        $badgeCls = fn($d) => match($d) {
                            'approved' => 'text-emerald-700 bg-emerald-50 border-emerald-200',
                            'returned' => 'text-orange-700 bg-orange-50 border-orange-200',
                            'rejected' => 'text-red-700 bg-red-50 border-red-200',
                            default    => 'text-slate-600 bg-slate-100 border-slate-200',
                        };

                        // Dot for the FIRST (pre-return) node
                        if ($needsReturnSplit) {
                            // Pre-return node always orange (ended with returned)
                            $dotBg   = '#f97316';
                            $ringCls = 'ring-orange-100';
                            $liStyle = '';
                        } elseif ($isCurrent) {
                            $dotBg   = '#f59e0b';
                            $ringCls = 'ring-amber-100';
                            $liStyle = '';
                        } elseif ($isDone) {
                            $dotBg   = match($action->decision) {
                                'approved' => '#10b981',
                                'returned' => '#f97316',
                                'rejected' => '#ef4444',
                                default    => '#94a3b8',
                            };
                            $ringCls = match($action->decision) {
                                'approved' => 'ring-emerald-100',
                                'returned' => 'ring-orange-100',
                                'rejected' => 'ring-red-100',
                                default    => 'ring-slate-100',
                            };
                            $liStyle = '';
                        } elseif ($isAfterTerminal || $reqStatus === \App\Models\TravelRequest::STATUS_CANCELLED) {
                            $dotBg   = '#64748b';
                            $ringCls = 'ring-slate-300';
                            $liStyle = $reqStatus === \App\Models\TravelRequest::STATUS_RETURNED
                                ? 'opacity:0.55;'
                                : 'opacity:0.38;';
                        } else {
                            $dotBg   = '#94a3b8';
                            $ringCls = 'ring-slate-200';
                            $liStyle = '';
                        }

                        // Dot for the SECOND (post-return) node when cycle is resolved
                        $postAction     = $postReturnActions->last();
                        $postDotBg      = $postAction ? match($postAction->decision) {
                            'approved' => '#10b981',
                            'rejected' => '#ef4444',
                            'returned' => '#f97316',
                            default    => '#94a3b8',
                        } : '#94a3b8';
                        $postRingCls    = $postAction ? match($postAction->decision) {
                            'approved' => 'ring-emerald-100',
                            'rejected' => 'ring-red-100',
                            'returned' => 'ring-orange-100',
                            default    => 'ring-slate-100',
                        } : 'ring-slate-100';
                    @endphp

                    @if ($showDivider)
                    <li class="ml-6 !mt-3 !mb-0 list-none" style="pointer-events:none;">
                        <div class="pl-1 flex items-center gap-2">
                            <div class="flex-1 border-t border-dashed" style="border-color:rgba(255,255,255,0.18);"></div>
                            <span class="text-[9px] font-bold uppercase tracking-widest px-2 py-0.5 rounded-full"
                                  style="@if($reqStatus === \App\Models\TravelRequest::STATUS_RETURNED) color:#f97316; background-color:rgba(249,115,22,0.12); @else color:rgba(255,255,255,0.35); background-color:rgba(0,0,0,0.18); @endif">
                                @if ($reqStatus === \App\Models\TravelRequest::STATUS_RETURNED)
                                    {{ __('travel.flow_on_hold') }}
                                @else
                                    {{ __('travel.flow_not_reached') }}
                                @endif
                            </span>
                            <div class="flex-1 border-t border-dashed" style="border-color:rgba(255,255,255,0.18);"></div>
                        </div>
                    </li>
                    @endif

                    {{-- Main step node (pre-return actions when split, all actions otherwise) --}}
                    <li class="ml-6" style="{{ $liStyle }}">
                        <span class="absolute -left-[9px] w-4 h-4 rounded-full ring-4 {{ $ringCls }} {{ ($isCurrent && !$needsReturnSplit) ? 'animate-pulse' : '' }}"
                              style="background-color:{{ $dotBg }};"></span>
                        <div class="pl-1">
                            <p class="text-xs font-bold text-white">{{ __('common.stage_' . $step['stage']) }}</p>
                            <p class="text-xs mt-0.5" style="color:rgba(255,255,255,0.75);">{{ $approver?->name ?? '—' }}</p>
                            @if ($approver?->job_title)
                            <p class="text-[10px]" style="color:rgba(255,255,255,0.5);">{{ $approver->job_title }}</p>
                            @endif
                            <div class="mt-1.5 space-y-1">
                            @foreach ($needsReturnSplit ? $preReturnActions : $stageActions as $sa)
                            <div>
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $badgeCls($sa->decision) }}">
                                    {{ __('travel.decided_' . $sa->decision) }}&nbsp;{{ $sa->acted_at->format('d M Y') }}
                                </span>
                                @if ($sa->comment)
                                <p class="text-xs mt-1 italic rounded-lg p-2" style="color:rgba(255,255,255,0.7); background-color:rgba(0,0,0,0.15);">"{{ Str::limit($sa->comment, 100) }}"</p>
                                @endif
                            </div>
                            @endforeach
                            @if ($isCurrent && !$needsReturnSplit)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold border text-amber-700 bg-amber-50 border-amber-200">
                                {{ __('travel.waiting') }}
                            </span>
                            @endif
                            </div>
                        </div>
                    </li>

                    @if ($needsReturnSplit)
                    {{-- Resubmitted node — always shown when a return cycle exists --}}
                    <li class="ml-6">
                        <span class="absolute -left-[9px] w-4 h-4 rounded-full ring-4 ring-blue-100" style="background-color:#3b82f6;"></span>
                        <div class="pl-1">
                            <p class="text-xs font-bold text-white">{{ __('common.stage_resubmitted') }}</p>
                            <p class="text-xs mt-0.5" style="color:rgba(255,255,255,0.75);">{{ $tr->requester->name }}</p>
                            @if ($tr->requester->job_title)
                            <p class="text-[10px]" style="color:rgba(255,255,255,0.5);">{{ $tr->requester->job_title }}</p>
                            @endif
                            @if ($tr->submitted_at)
                            <span class="inline-flex items-center gap-1 mt-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold border text-blue-700 bg-blue-50 border-blue-200">
                                {{ $tr->submitted_at->format('d M Y, H:i') }}
                            </span>
                            @endif
                        </div>
                    </li>
                    {{-- Second cycle node: waiting (if still pending) or resolved (if acted upon) --}}
                    @if ($isCurrentWithHistory)
                    <li class="ml-6">
                        <span class="absolute -left-[9px] w-4 h-4 rounded-full ring-4 ring-amber-100 animate-pulse" style="background-color:#f59e0b;"></span>
                        <div class="pl-1">
                            <p class="text-xs font-bold text-white">{{ __('common.stage_' . $step['stage']) }}</p>
                            <p class="text-xs mt-0.5" style="color:rgba(255,255,255,0.75);">{{ $approver?->name ?? '—' }}</p>
                            @if ($approver?->job_title)
                            <p class="text-[10px]" style="color:rgba(255,255,255,0.5);">{{ $approver->job_title }}</p>
                            @endif
                            <span class="inline-flex items-center gap-1 mt-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold border text-amber-700 bg-amber-50 border-amber-200">
                                {{ __('travel.waiting') }}
                            </span>
                        </div>
                    </li>
                    @elseif ($hadReturnCycle)
                    <li class="ml-6">
                        <span class="absolute -left-[9px] w-4 h-4 rounded-full ring-4 {{ $postRingCls }}" style="background-color:{{ $postDotBg }};"></span>
                        <div class="pl-1">
                            <p class="text-xs font-bold text-white">{{ __('common.stage_' . $step['stage']) }}</p>
                            <p class="text-xs mt-0.5" style="color:rgba(255,255,255,0.75);">{{ $approver?->name ?? '—' }}</p>
                            @if ($approver?->job_title)
                            <p class="text-[10px]" style="color:rgba(255,255,255,0.5);">{{ $approver->job_title }}</p>
                            @endif
                            <div class="mt-1.5 space-y-1">
                            @foreach ($postReturnActions as $sa)
                            <div>
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $badgeCls($sa->decision) }}">
                                    {{ __('travel.decided_' . $sa->decision) }}&nbsp;{{ $sa->acted_at->format('d M Y') }}
                                </span>
                                @if ($sa->comment)
                                <p class="text-xs mt-1 italic rounded-lg p-2" style="color:rgba(255,255,255,0.7); background-color:rgba(0,0,0,0.15);">"{{ Str::limit($sa->comment, 100) }}"</p>
                                @endif
                            </div>
                            @endforeach
                            </div>
                        </div>
                    </li>
                    @endif
                    @endif

                    @endforeach

                    {{-- ③ Terminal node for cancelled requests --}}
                    @if ($reqStatus === \App\Models\TravelRequest::STATUS_CANCELLED)
                    <li class="ml-6">
                        {{-- Striped/hatched dot to distinguish from rejected (solid red) --}}
                        <span class="absolute -left-[9px] w-4 h-4 rounded-full ring-4 ring-slate-300 flex items-center justify-center"
                              style="background-color:#475569;">
                            <svg class="w-2 h-2 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </span>
                        <div class="pl-1">
                            <p class="text-xs font-bold" style="color:rgba(255,255,255,0.6);">{{ __('common.stage_cancelled') }}</p>
                            <p class="text-xs mt-0.5" style="color:rgba(255,255,255,0.45);">{{ $tr->requester->name }}</p>
                            <span class="inline-flex items-center gap-1 mt-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold border text-slate-600 bg-slate-100 border-slate-300">
                                {{ $tr->updated_at->format('d M Y, H:i') }}
                            </span>
                        </div>
                    </li>
                    @endif

                    @else
                    {{-- Legacy fallback: chain cleared — render from recorded actions only --}}
                    @foreach ($travelRequest->approvalActions as $action)
                    @php
                        $dotBg    = match($action->decision) { 'approved' => '#10b981', 'returned' => '#f97316', default => '#ef4444' };
                        $ringCls  = match($action->decision) { 'approved' => 'ring-emerald-100', 'returned' => 'ring-orange-100', default => 'ring-red-100' };
                        $labelCls = match($action->decision) { 'approved' => 'text-emerald-700 bg-emerald-50 border-emerald-200', 'returned' => 'text-orange-700 bg-orange-50 border-orange-200', default => 'text-red-700 bg-red-50 border-red-200' };
                    @endphp
                    <li class="ml-6">
                        <span class="absolute -left-[9px] w-4 h-4 rounded-full ring-4 {{ $ringCls }}" style="background-color:{{ $dotBg }};"></span>
                        <div class="pl-1">
                            <p class="text-xs font-bold text-white">{{ __('common.stage_' . $action->stage) }}</p>
                            <p class="text-xs mt-0.5" style="color:rgba(255,255,255,0.75);">{{ $action->actor?->name ?? '—' }}</p>
                            @if ($action->actor?->job_title)
                            <p class="text-[10px]" style="color:rgba(255,255,255,0.5);">{{ $action->actor->job_title }}</p>
                            @endif
                            <span class="inline-flex items-center gap-1 mt-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $labelCls }}">
                                {{ __('travel.decided_' . $action->decision) }}&nbsp;{{ $action->acted_at->format('d M Y') }}
                            </span>
                            @if ($action->comment)
                            <p class="text-xs mt-1.5 italic rounded-lg p-2" style="color:rgba(255,255,255,0.7); background-color:rgba(0,0,0,0.15);">"{{ Str::limit($action->comment, 100) }}"</p>
                            @endif
                        </div>
                    </li>
                    @endforeach
                    @endif

                </ol>
            </div>
            @endif

            {{-- Info card — blue --}}
            @if ($tr->status === \App\Models\TravelRequest::STATUS_APPROVED || $tr->travel_report_document)
            {{-- Travel report --}}
            <div class="card overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-3">
                    <div class="h-9 w-9 rounded-lg flex items-center justify-center shrink-0" style="background-color:#ecfdf5;color:#047857;">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0119 9.414V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-800">{{ __('travel.report_title') }}</h3>
                        <p class="text-xs text-slate-500 mt-0.5">{{ __('travel.report_hint') }}</p>
                    </div>
                </div>

                <div class="p-5 space-y-4">
                    @if ($tr->travel_report_document)
                    <div class="rounded-lg border border-emerald-100 bg-emerald-50 p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-emerald-800 truncate">
                                    {{ $tr->travel_report_original_name ?: __('travel.report_title') }}
                                </p>
                                @if ($tr->travel_report_submitted_at)
                                <p class="text-xs text-emerald-700 mt-0.5">
                                    {{ __('travel.report_submitted', ['date' => $tr->travel_report_submitted_at->format('d M Y, H:i')]) }}
                                </p>
                                @else
                                <p class="text-xs text-amber-700 mt-0.5">{{ __('travel.report_unlocked_notice') }}</p>
                                @endif
                            </div>
                            <a href="{{ route('travel-requests.report.download', $tr) }}" class="btn-secondary btn-sm shrink-0">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0119 9.414V19a2 2 0 01-2 2z"/>
                                </svg>
                                {{ __('common.download') }}
                            </a>
                        </div>
                    </div>
                    @else
                    <p class="text-sm text-slate-500">{{ __('travel.report_missing') }}</p>
                    @endif

                    @if ($tr->travel_report_notes)
                    <div>
                        <p class="text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">{{ __('travel.report_notes') }}</p>
                        <div class="text-sm text-slate-700 bg-slate-50 border border-slate-100 rounded-lg p-3 whitespace-pre-wrap">{{ $tr->travel_report_notes }}</div>
                    </div>
                    @endif

                    @can('uploadReport', $tr)
                    <form method="POST"
                          action="{{ route('travel-requests.report.upload', $tr) }}"
                          enctype="multipart/form-data"
                          class="space-y-3 pt-1">
                        @csrf

                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                            <p class="font-semibold">{{ __('travel.report_final_warning_title') }}</p>
                            <p class="mt-1 text-xs leading-relaxed">{{ __('travel.report_final_warning') }}</p>
                        </div>

                        <div class="field">
                            <label class="label">{{ $tr->travel_report_document ? __('travel.report_upload_correction') : __('travel.report_upload') }}</label>
                            <input type="file"
                                   name="travel_report_document"
                                   accept=".pdf"
                                   required
                                   class="block w-full text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-emerald-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-emerald-700 hover:file:bg-emerald-100">
                            <p class="mt-1.5 text-xs text-slate-500">{{ __('travel.report_file_hint') }}</p>
                            @error('travel_report_document')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="field">
                            <label class="label">{{ __('travel.report_notes') }}</label>
                            <textarea name="travel_report_notes"
                                      rows="4"
                                      class="input resize-none"
                                      placeholder="{{ __('travel.report_notes_ph') }}">{{ old('travel_report_notes', $tr->travel_report_notes) }}</textarea>
                            @error('travel_report_notes')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <label class="flex items-start gap-2.5 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
                            <input type="checkbox" name="report_submission_confirmed" value="1" required
                                   class="mt-0.5 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                   @checked(old('report_submission_confirmed'))>
                            <span>{{ __('travel.report_confirmation') }}</span>
                        </label>
                        @error('report_submission_confirmed')
                        <p class="text-xs text-red-600">{{ $message }}</p>
                        @enderror

                        <button type="submit" class="btn-primary btn-sm">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            {{ $tr->travel_report_document ? __('travel.report_submit_correction') : __('travel.report_submit_final') }}
                        </button>
                    </form>
                    @elseif ($tr->isTravelReportLocked())
                    <p class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600">
                        {{ __('travel.report_locked_notice') }}
                    </p>
                    @elseif ($tr->status !== \App\Models\TravelRequest::STATUS_APPROVED)
                    <p class="text-xs text-slate-400">{{ __('travel.report_available_after_approval') }}</p>
                    @elseif (! $tr->hasEnded())
                    {{-- Approved, but the trip is still ahead: a report covers travel
                         that has happened, so there is nothing to file yet. --}}
                    <p class="text-xs text-slate-400">{{ __('travel.report_required_after_return_only') }}</p>
                    @endif

                    @can('unlockReport', $tr)
                    <div x-data="{ confirming: false }" class="border-t border-slate-100 pt-4">
                        <button x-show="!confirming" type="button" @click="confirming = true" class="text-xs font-semibold text-amber-700 hover:text-amber-900">
                            {{ __('travel.report_unlock_action') }}
                        </button>
                        <div x-show="confirming" x-cloak class="rounded-lg border border-amber-200 bg-amber-50 p-3">
                            <p class="text-xs text-amber-800">{{ __('travel.report_unlock_warning') }}</p>
                            <div class="mt-3 flex gap-2">
                                <form method="POST" action="{{ route('travel-requests.report.unlock', $tr) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-700">
                                        {{ __('travel.report_unlock_confirm') }}
                                    </button>
                                </form>
                                <button type="button" @click="confirming = false" class="rounded-lg bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 border border-slate-200">
                                    {{ __('common.cancel') }}
                                </button>
                            </div>
                        </div>
                    </div>
                    @endcan
                </div>
            </div>
            @endif

            <div class="rounded-xl overflow-hidden shadow-sm p-5" style="background-color:#05499c;">
                <h3 class="text-[10px] font-bold uppercase tracking-widest mb-4" style="color:rgba(255,255,255,0.55);">{{ __('travel.info_card') }}</h3>
                <div class="space-y-4">
                    @foreach ([
                        [__('common.applicant'),   $tr->requester->name ?? '—'],
                        [__('common.unit'),        $tr->unit?->name ?? '—'],
                        [__('common.destination'), $tr->b_destination ?? '—'],
                    ] as [$k, $v])
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-wider mb-0.5" style="color:rgba(255,255,255,0.5);">{{ $k }}</p>
                        <p class="text-sm font-semibold text-white leading-snug">{{ $v }}</p>
                    </div>
                    @endforeach
                    @if ($tr->b_departure_date)
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-wider mb-0.5" style="color:rgba(255,255,255,0.5);">{{ __('travel.trip_dates') }}</p>
                        <p class="text-sm font-semibold text-white">
                            {{ $tr->b_departure_date->format('d M Y') }}
                            @if ($tr->b_return_date) &mdash; {{ $tr->b_return_date->format('d M Y') }} @endif
                        </p>
                    </div>
                    @endif
                    @if ($tr->submitted_at)
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-wider mb-0.5" style="color:rgba(255,255,255,0.5);">{{ __('travel.submitted') }}</p>
                        <p class="text-sm font-semibold text-white">{{ $tr->submitted_at->format('d M Y') }}</p>
                    </div>
                    @endif
                </div>
            </div>

        </div>
    </div>

    {{-- Confirmation modal --}}
    <div x-show="modalOpen" x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
    x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
         @click.stop>
        <div class="flex items-start gap-4 mb-5">
            <div :class="modalIconClass" class="flex items-center justify-center w-10 h-10 rounded-full shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="modalIconPath"/></svg>
            </div>
            <div>
                <h3 class="text-base font-bold text-slate-900" x-text="modalTitle"></h3>
                <p class="text-sm text-slate-500 mt-1" x-text="modalDescription"></p>
            </div>
        </div>
        <div class="flex gap-3 justify-end">
            <button @click="closeModal()" class="btn-secondary btn-sm">{{ __('common.cancel') }}</button>
            <button @click="confirmAction()" :class="modalBtnClass" class="btn btn-sm text-white px-4 py-2 rounded-lg font-semibold text-sm"><span x-text="modalConfirmLabel"></span></button>
        </div>
    </div>
</div>

{{-- Cancel form --}}
<form id="cancel-form" method="POST" action="{{ route('travel-requests.cancel', $travelRequest) }}" class="hidden">
    @csrf @method('DELETE')
</form>

</div>{{-- end x-data="approvalModal()" --}}

<script>
    function approvalModal() {
        return {
            modalOpen: false, selectedDecision: '',
            modalTitle: '', modalDescription: '', modalConfirmLabel: '',
            modalBtnClass: '', modalIconClass: '', modalIconPath: '',
            pendingAction: null,
            openModal(decision) {
                this.selectedDecision = decision;
                const c = {
                    approved: { title: @json(__('travel.modal_approve_title')), desc: @json(__('travel.modal_approve_desc')), label: @json(__('travel.approve_btn')), btn: 'bg-emerald-600 hover:bg-emerald-700', icon: 'bg-emerald-100 text-emerald-700', path: 'M5 13l4 4L19 7', act: () => { document.getElementById('approval-decision-input').value = 'approved'; document.getElementById('approval-form').submit(); } },
                    returned:  { title: @json(__('travel.modal_return_title')), desc: @json(($returnGoesToApplicant ?? true) ? __('travel.modal_return_desc') : __('travel.modal_return_desc_previous')), label: @json(__('travel.return_btn')), btn: 'bg-amber-500 hover:bg-amber-600', icon: 'bg-amber-100 text-amber-700', path: 'M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6', act: () => { document.getElementById('approval-decision-input').value = 'returned'; document.getElementById('approval-form').submit(); } },
                    rejected:  { title: @json(__('travel.modal_reject_title')), desc: @json(__('travel.modal_reject_desc')), label: @json(__('travel.reject_btn')), btn: 'bg-red-600 hover:bg-red-700', icon: 'bg-red-100 text-red-700', path: 'M6 18L18 6M6 6l12 12', act: () => { document.getElementById('approval-decision-input').value = 'rejected'; document.getElementById('approval-form').submit(); } },
                    cancel:    { title: @json(__('travel.modal_cancel_title')), desc: @json(__('travel.modal_cancel_desc')), label: @json(__('travel.cancel_request')), btn: 'bg-red-600 hover:bg-red-700', icon: 'bg-red-100 text-red-700', path: 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16', act: () => document.getElementById('cancel-form').submit() },
                };
                const cfg = c[decision] || c.rejected;
                this.modalTitle = cfg.title; this.modalDescription = cfg.desc;
                this.modalConfirmLabel = cfg.label; this.modalBtnClass = cfg.btn;
                this.modalIconClass = cfg.icon; this.modalIconPath = cfg.path;
                this.pendingAction = cfg.act; this.modalOpen = true;
            },
            confirmCancel() { this.openModal('cancel'); },
            confirmAction() { this.modalOpen = false; this.pendingAction && this.pendingAction(); },
            closeModal() { this.modalOpen = false; this.selectedDecision = ''; },
        };
    }
</script>

</x-app-layout>
