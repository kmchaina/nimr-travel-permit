<x-app-layout>
<div class="px-6 py-6 lg:px-8 lg:py-8 space-y-5">

    {{-- ── Page header ──────────────────────────────────────────────── --}}
    <div class="rounded-xl border border-blue-200 shadow-sm px-6 py-5 flex items-center justify-between gap-6" style="background:#dbeafe;">
        <div class="flex items-center gap-4">
            <div class="h-12 w-12 rounded-xl flex items-center justify-center shrink-0 border border-blue-200" style="background:rgba(255,255,255,0.6);">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">
                    {{ ($user->isHr() || $user->isDirectorGeneral()) ? __('travel.list_all') : __('travel.list_mine') }}
                </h1>
                <p class="text-sm font-medium text-blue-700 mt-0.5">
                    @if ($requests->total() > 0)
                        {{ __('travel.results_count', ['from' => $requests->firstItem(), 'to' => $requests->lastItem(), 'total' => $requests->total()]) }}
                    @else
                        {{ __('travel.no_results') }}
                    @endif
                </p>
            </div>
        </div>
        @if (!$user->isDirectorGeneral())
        <a href="{{ route('travel-requests.create') }}"
           class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold text-white shadow-sm transition hover:opacity-90 shrink-0"
           style="background-color:#05499c;">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
            {{ __('travel.new_request') }}
        </a>
        @endif
    </div>

    @if (session('status'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
        class="flex items-center gap-3 p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-700 text-sm">
        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        <span class="flex-1">{{ session('status') }}</span>
        <button @click="show = false"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
    </div>
    @endif

    {{-- ── Search + filter panel ─────────────────────────────────────── --}}
    @php
        $totalCount = array_sum($statusCounts);
        $tabs = [
            ''           => __('travel.all_statuses'),
            'pending'    => __('common.status_pending'),
            'approved'   => __('common.status_approved'),
            'returned'   => __('common.status_returned'),
            'rejected'   => __('common.status_rejected'),
            'draft'      => __('common.status_draft'),
            'cancelled'  => __('common.status_cancelled'),
        ];
        $activeTab = request('status', '');
    @endphp
    {{-- Search bar --}}
    <form method="GET" action="{{ route('travel-requests.index') }}"
          class="flex items-center gap-3">
        <div class="flex items-center gap-3 bg-white border border-slate-200 rounded-xl shadow-sm px-4 py-2.5 w-full max-w-3xl focus-within:ring-2 focus-within:ring-blue-100 focus-within:border-blue-400 transition">
            <input type="text" name="q" value="{{ request('q') }}"
                   placeholder="{{ __('travel.search_placeholder') }}"
                   class="flex-1 bg-transparent text-sm text-slate-800 placeholder-slate-400 outline-none min-w-0">
            @if (request('q'))
            <a href="{{ route('travel-requests.index', array_filter(['status' => request('status')])) }}"
               class="text-slate-300 hover:text-slate-500 transition shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </a>
            @endif
        </div>
        <button type="submit"
                class="shrink-0 flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold text-white shadow-sm transition hover:opacity-90"
                style="background-color:#05499c;">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            {{ __('common.search') }}
        </button>
    </form>

    {{-- Status tabs --}}
    <div class="flex items-center gap-0 border-b border-slate-200 overflow-x-auto">
        @foreach ($tabs as $val => $label)
        @php
            $count = $val === '' ? $totalCount : ($statusCounts[$val] ?? 0);
            $isActive = $activeTab === $val;
        @endphp
        @if ($count > 0 || $isActive || $val === '')
        <a href="{{ route('travel-requests.index', array_filter(['q' => request('q'), 'status' => $val ?: null])) }}"
           class="flex items-center gap-1.5 px-3.5 py-2.5 text-sm font-medium border-b-2 whitespace-nowrap transition -mb-px
                  {{ $isActive ? 'border-current' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' }}"
           style="{{ $isActive ? 'color:#05499c; border-color:#05499c;' : '' }}">
            {{ $label }}
            @if ($count > 0)
            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold leading-none
                         {{ $isActive ? '' : 'bg-slate-100 text-slate-500' }}"
                  style="{{ $isActive ? 'background-color:#05499c18; color:#05499c;' : '' }}">{{ $count }}</span>
            @endif
        </a>
        @endif
        @endforeach
    </div>

    {{-- ── Request list ──────────────────────────────────────────────── --}}
    @if ($requests->isEmpty())
    <div class="card p-14 text-center">
        <div class="h-14 w-14 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-4">
            <svg class="w-7 h-7 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        </div>
        <p class="text-base font-medium text-slate-600 mb-1">{{ __('travel.no_found') }}</p>
        @if (request()->hasAny(['q', 'status']))
        <p class="text-sm text-slate-400 mb-5">{{ __('travel.adjust_filters') }}</p>
        <a href="{{ route('travel-requests.index') }}" class="btn-secondary btn-sm">{{ __('travel.reset_search') }}</a>
        @elseif (!$user->isDirectorGeneral())
        <p class="text-sm text-slate-400 mb-5">{{ __('travel.no_submitted') }}</p>
        <a href="{{ route('travel-requests.create') }}" class="btn-primary btn-sm">{{ __('travel.first_submit') }}</a>
        @endif
    </div>
    @else
    <div class="card overflow-hidden divide-y divide-slate-100">
        @foreach ($requests as $tr)
        <a href="{{ route('travel-requests.show', $tr) }}"
           class="group flex items-start gap-5 px-6 py-5 hover:bg-slate-50/70 transition">

            {{-- Icon --}}
            @php
                [$iconBg, $iconColor] = match($tr->status) {
                    'approved'  => ['#f0fdf4', '#6bab84'],
                    'rejected'  => ['#fef6f6', '#c47878'],
                    'pending'   => ['#fefdf0', '#b89a4a'],
                    'returned'  => ['#fff9f5', '#c07a50'],
                    'cancelled' => ['#f8fafc', '#9ca3af'],
                    default     => ['#f0f4ff', '#5c7fd4'],
                };
            @endphp
            <div class="h-14 w-14 rounded-xl flex items-center justify-center shrink-0 mt-0.5 border transition"
                 style="background:{{ $iconBg }}; border-color:{{ $iconColor }}22;">
                @if ($user->isHr() || $user->isDirectorGeneral())
                <span class="text-sm font-bold" style="color:{{ $iconColor }};">{{ strtoupper(substr($tr->b_applicant_name ?? '?', 0, 2)) }}</span>
                @else
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:{{ $iconColor }};stroke-width:1.7">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                @endif
            </div>

            {{-- Content --}}
            <div class="flex-1 min-w-0">
                {{-- Primary row --}}
                <div class="flex items-center gap-2 flex-wrap">
                    @if ($user->isHr() || $user->isDirectorGeneral())
                    <span class="text-base font-semibold text-slate-800 group-hover:text-slate-900 transition">{{ $tr->b_applicant_name ?? '—' }}</span>
                    @else
                    <span class="text-base font-semibold text-slate-800 group-hover:text-slate-900 transition">{{ $tr->b_destination ?? '—' }}</span>
                    @endif
                    <span class="badge {{ $tr->statusColor() }}">{{ $tr->statusLabel() }}</span>
                </div>

                {{-- Secondary info --}}
                <div class="flex items-center flex-wrap gap-x-3 gap-y-0.5 mt-1">
                    @if ($user->isHr() || $user->isDirectorGeneral())
                    {{-- HR/DG: destination · unit --}}
                    @if ($tr->b_destination)
                    <span class="text-xs text-slate-400">{{ $tr->b_destination }}</span>
                    @endif
                    @if ($tr->unit)
                    <span class="text-xs text-slate-300">&middot;</span>
                    <span class="text-xs text-slate-400">{{ $tr->unit->name }}</span>
                    @endif
                    @else
                    {{-- Staff: waiting for / dates --}}
                    @if ($tr->status === \App\Models\TravelRequest::STATUS_PENDING && $tr->currentApprover)
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs bg-slate-100 border border-slate-200">
                        <svg class="w-3 h-3 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        <span class="text-slate-400 font-normal">With</span>
                        <span class="text-slate-600 font-semibold">{{ $tr->currentApprover->name }}</span>
                    </span>
                    @endif
                    @endif

                    {{-- Date badges --}}
                    @if ($tr->b_departure_date)
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs bg-blue-50 border border-blue-100">
                        <svg class="w-3 h-3 text-blue-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <span class="text-blue-400 font-normal">Departs</span>
                        <span class="text-blue-700 font-semibold">{{ $tr->b_departure_date->format('d M Y') }}</span>
                    </span>
                    @endif
                    @if ($tr->b_return_date)
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs bg-violet-50 border border-violet-100">
                        <svg class="w-3 h-3 text-violet-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                        <span class="text-violet-400 font-normal">Returns</span>
                        <span class="text-violet-700 font-semibold">{{ $tr->b_return_date->format('d M Y') }}</span>
                    </span>
                    @endif
                </div>
            </div>

            {{-- Right: submitted date + chevron --}}
            <div class="shrink-0 flex flex-col items-end gap-2 self-center">
                @if ($tr->submitted_at)
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs bg-emerald-50 border border-emerald-100">
                    <svg class="w-3 h-3 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span class="text-emerald-400 font-normal">Submitted</span>
                    <span class="text-emerald-700 font-semibold">{{ $tr->submitted_at->format('d M Y') }}</span>
                </span>
                @elseif ($tr->status === \App\Models\TravelRequest::STATUS_DRAFT)
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs bg-slate-100 border border-slate-200">
                    <svg class="w-3 h-3 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    <span class="text-slate-400 font-normal">Draft</span>
                    <span class="text-slate-600 font-semibold">{{ $tr->created_at->format('d M Y') }}</span>
                </span>
                @endif
                <svg class="w-4 h-4 text-slate-300 group-hover:text-slate-400 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </div>

        </a>
        @endforeach
    </div>
    @endif

    {{-- Pagination --}}
    @if ($requests->hasPages())
    <div class="flex justify-center">
        {{ $requests->links() }}
    </div>
    @endif

</div>
</x-app-layout>
