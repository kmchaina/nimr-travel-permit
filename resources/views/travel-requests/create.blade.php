<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="page-title">{{ __('travel.new_request') }}</h1>
            <p class="page-sub">{{ __('travel.form_subtitle') }} — {{ auth()->user()->unit?->name ?? '—' }}</p>
        </div>
    </x-slot>

    <div class="p-6" x-data="formWizard({{ $errors->any() ? 1 : 0 }})">
        <div class="max-w-3xl mx-auto">

            {{-- ── Step indicator ──────────────────────────────────────── --}}
            @php
                $steps = [
                    ['label' => 'A', 'title' => __('travel.step_a')],
                    ['label' => 'B', 'title' => __('travel.step_b')],
                    ['label' => 'C', 'title' => __('travel.step_c')],
                    ['label' => 'D', 'title' => __('travel.step_d')],
                    ['label' => 'E', 'title' => __('travel.step_e')],
                    ['label' => 'F', 'title' => __('travel.step_f')],
                    ['label' => 'G', 'title' => __('travel.step_g')],
                    ['label' => 'H', 'title' => __('travel.step_review')],
                ];
            @endphp

            <div class="mb-8">
                {{-- Desktop stepper --}}
                <div class="hidden sm:flex items-center">
                    @foreach ($steps as $i => $step)
                    <div class="flex items-center {{ $i < count($steps) - 1 ? 'flex-1' : '' }}">
                        <div class="flex flex-col items-center">
                            <button type="button" @click="goTo({{ $i }})"
                                :class="currentStep === {{ $i }}
                                    ? 'bg-[#05499c] text-white ring-4 ring-blue-100 shadow-md shadow-blue-200'
                                    : (currentStep > {{ $i }} ? 'bg-emerald-500 text-white' : 'bg-slate-100 text-slate-400 cursor-default')"
                                class="h-8 w-8 rounded-full flex items-center justify-center text-xs font-bold transition-all duration-150">
                                <template x-if="currentStep > {{ $i }}">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </template>
                                <template x-if="currentStep <= {{ $i }}">
                                    <span>{{ $step['label'] }}</span>
                                </template>
                            </button>
                            <span :class="currentStep === {{ $i }} ? 'text-[#05499c] font-semibold' : (currentStep > {{ $i }} ? 'text-emerald-600' : 'text-slate-300')"
                                class="text-[10px] mt-1.5 whitespace-nowrap transition-colors">{{ $step['title'] }}</span>
                        </div>
                        @if ($i < count($steps) - 1)
                        <div :class="currentStep > {{ $i }} ? 'bg-emerald-400' : 'bg-slate-200'"
                            class="flex-1 h-0.5 mx-1.5 transition-colors duration-300"></div>
                        @endif
                    </div>
                    @endforeach
                </div>

                {{-- Mobile step label --}}
                <div class="flex items-center justify-between sm:hidden">
                    <span class="text-xs text-slate-500" x-text="'{{ __('travel.step_x_of_y', ['x' => '', 'y' => count($steps)]) }}'.replace('', currentStep + 1)">
                    </span>
                    <span class="text-xs font-semibold text-[#05499c]" x-text="stepTitles[currentStep]"></span>
                    <div class="flex gap-1">
                        @for ($i = 0; $i < count($steps); $i++)
                        <div :class="currentStep === {{ $i }} ? 'bg-[#05499c] w-4' : (currentStep > {{ $i }} ? 'bg-emerald-400' : 'bg-slate-200')"
                            class="h-1.5 rounded-full transition-all duration-200 w-1.5"></div>
                        @endfor
                    </div>
                </div>
            </div>

            {{-- Validation errors --}}
            @if ($errors->any())
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm">
                <div class="flex items-center gap-2 mb-2 font-semibold">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ __('users.errors_title') }}
                </div>
                <ul class="list-disc list-inside space-y-0.5">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
            @endif

            @isset($travelDays)
            <div class="mb-4">
                <x-travel-days-warning :travel-days="$travelDays" context="requester" />
            </div>
            @endisset

            <form method="POST" action="{{ route('travel-requests.store') }}" enctype="multipart/form-data" id="travel-form" x-ref="form" novalidate @submit="if ($event.submitter?.value === 'submit' && !validateAll()) $event.preventDefault()">
                @csrf

                {{-- ── Step 0: Terms ────────────────────────────────────── --}}
                <div x-show="currentStep === 0" x-cloak data-step="0">
                    <div class="card overflow-hidden mb-4">
                        {{-- Header --}}
                        <div class="px-6 py-5 border-b" style="background:linear-gradient(135deg,#eff6ff,#dbeafe);border-color:#bfdbfe;">
                            <div class="flex items-center gap-4">
                                <div class="h-12 w-12 rounded-2xl flex items-center justify-center text-white font-bold text-base shrink-0 shadow-sm"
                                     style="background:linear-gradient(135deg,#05499c,#1d6fd8);">A</div>
                                <div>
                                    <h3 class="text-lg font-bold text-slate-900">{{ __('travel.section_a_title') }}</h3>
                                    <p class="text-sm font-medium mt-0.5" style="color:#1e40af;">{{ __('travel.section_a_read') }}</p>
                                </div>
                            </div>
                        </div>

                        {{-- Rules --}}
                        <div class="divide-y divide-slate-100">
                            @foreach ([
                                __('travel.section_a_i'),
                                __('travel.section_a_ii'),
                                __('travel.section_a_iii'),
                                __('travel.section_a_iv'),
                                __('travel.section_a_v'),
                                __('travel.section_a_vi'),
                            ] as $i => $rule)
                            <div class="flex items-start gap-4 px-6 py-4">
                                <div class="h-7 w-7 rounded-full flex items-center justify-center shrink-0 mt-0.5"
                                     style="background:#dbeafe;color:#05499c;font-size:11px;font-weight:700;">
                                    {{ ['i','ii','iii','iv','v','vi','vii','viii'][$i] }}
                                </div>
                                <p class="text-sm text-slate-700 leading-relaxed">{{ $rule }}</p>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Unit note --}}
                    <div class="rounded-xl border px-4 py-3.5 flex items-start gap-3"
                         style="background:#eff6ff;border-color:#bfdbfe;">
                        <svg class="w-4 h-4 shrink-0 mt-0.5" style="color:#3b82f6;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="text-sm" style="color:#1e40af;">
                            {!! __('travel.section_a_unit_note', ['unit' => '<strong>' . (auth()->user()->unit?->name ?? '—') . '</strong>']) !!}
                        </p>
                    </div>
                </div>

                {{-- ── Step 1: Employee Info ─────────────────────────────── --}}
                <div x-show="currentStep === 1" x-cloak data-step="1">
                    <div class="card overflow-hidden">
                        <div class="px-6 py-5 border-b" style="background:linear-gradient(135deg,#f8fafc,#eff6ff);border-color:#e2e8f0;">
                            <div class="flex items-center gap-4">
                                <div class="h-12 w-12 rounded-2xl flex items-center justify-center text-white font-bold text-base shrink-0 shadow-sm"
                                     style="background:linear-gradient(135deg,#05499c,#1d6fd8);">B</div>
                                <h3 class="text-lg font-bold text-slate-900">{{ __('travel.section_b_title') }}</h3>
                            </div>
                        </div>
                        <div class="p-6 space-y-5">
                            @php
                                $flds = [
                                    ['(i)',   __('travel.b_name'),        'b_applicant_name', 'text',  old('b_applicant_name', $user->name), true,  '', !empty($user->name), ''],
                                    ['(ii)',  __('travel.b_phone'),       'b_phone',          'tel',   old('b_phone', $user->phone),          true,  '+255 7XX XXX XXX', !empty($user->phone) && !old('b_phone'), 'validatePhone($el)'],
                                    ['(iii)', __('travel.b_email'),       'b_email',          'email', old('b_email', $user->email),           false, '', !empty($user->email), ''],
                                    ['(iv)',  __('travel.b_position'),    'b_position',       'text',  old('b_position', $user->job_title),    false, '', !empty($user->job_title), ''],
                                    ['(v)',   __('travel.b_destination'), 'b_destination',    'text',  old('b_destination'),                   true,  __('travel.b_destination_ph'), false, 'validateRequired($el)'],
                                ];
                            @endphp
                            @foreach ($flds as [$num, $label, $name, $type, $val, $req, $ph, $locked, $blur])
                            <div class="field">
                                <label class="label flex items-center gap-1.5">
                                    <span class="text-slate-400">{{ $num }}</span>
                                    <span>{{ $label }}@if($req) <span class="text-red-500 ml-0.5">*</span> @endif</span>
                                    @if($locked)
                                    <span class="inline-flex items-center gap-1 ml-1 text-xs font-medium text-slate-400 bg-slate-100 border border-slate-200 rounded-full px-2 py-0.5">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                        from profile
                                    </span>
                                    @endif
                                </label>
                                <input type="{{ $type }}" name="{{ $name }}" value="{{ $val }}"
                                    class="input @error($name) input-error @enderror {{ $locked ? 'bg-slate-50 text-slate-500 cursor-not-allowed border-slate-200' : '' }}"
                                    :class="hasError('{{ $name }}') ? 'border-red-400 ring-1 ring-red-300' : ''"
                                    {{ $locked ? 'readonly' : '' }}
                                    @if($blur) @blur="{{ $blur }}" @endif
                                    @if($ph) placeholder="{{ $ph }}" @endif>
                                <p x-show="hasError('{{ $name }}')" x-cloak x-text="errorFor('{{ $name }}')"
                                   class="mt-1.5 text-xs text-red-600 flex items-center gap-1">
                                </p>
                            </div>
                            @endforeach
                            <div class="grid grid-cols-2 gap-4">
                                <div class="field">
                                    <label class="label"><span class="text-slate-400 mr-1.5">(vi)</span> {{ __('travel.b_departure_date') }} <span class="text-red-500">*</span></label>
                                    <input type="date" name="b_departure_date" value="{{ old('b_departure_date') }}"
                                        min="{{ now()->addDays(1)->format('Y-m-d') }}"
                                        class="input @error('b_departure_date') input-error @enderror"
                                        :class="hasError('b_departure_date') ? 'border-red-400 ring-1 ring-red-300' : ''"
                                        @change="validateDeparture($el)">
                                    <p x-show="hasError('b_departure_date')" x-cloak x-text="errorFor('b_departure_date')"
                                       class="mt-1.5 text-xs text-red-600"></p>
                                </div>
                                <div class="field">
                                    <label class="label"><span class="text-slate-400 mr-1.5">(vii)</span> {{ __('travel.b_return_date') }} <span class="text-red-500">*</span></label>
                                    <input type="date" name="b_return_date" value="{{ old('b_return_date') }}"
                                        min="{{ now()->addDays(2)->format('Y-m-d') }}"
                                        class="input @error('b_return_date') input-error @enderror"
                                        :class="hasError('b_return_date') ? 'border-red-400 ring-1 ring-red-300' : ''"
                                        @change="validateReturn($el)">
                                    <p x-show="hasError('b_return_date')" x-cloak x-text="errorFor('b_return_date')"
                                       class="mt-1.5 text-xs text-red-600"></p>
                                </div>
                            </div>
                            <p class="text-xs text-slate-400">{{ __('travel.b_note') }}</p>
                        </div>
                    </div>
                </div>

                {{-- ── Step 2: Travel Source ────────────────────────────── --}}
                <div x-show="currentStep === 2" x-cloak data-step="2">
                    <div class="card overflow-hidden">
                        <div class="px-6 py-5 border-b" style="background:linear-gradient(135deg,#f8fafc,#eff6ff);border-color:#e2e8f0;">
                            <div class="flex items-center gap-4">
                                <div class="h-12 w-12 rounded-2xl flex items-center justify-center text-white font-bold text-base shrink-0 shadow-sm"
                                     style="background:linear-gradient(135deg,#05499c,#1d6fd8);">C</div>
                                <h3 class="text-lg font-bold text-slate-900">{{ __('travel.section_c_title') }}</h3>
                            </div>
                        </div>
                        <div class="p-6">
                            <p class="text-base text-slate-500 mb-4 leading-relaxed">{{ __('travel.c_desc') }}</p>
                            <div class="field">
                                <label class="label">{{ __('travel.c_label') }} <span class="text-red-500">*</span></label>
                                <textarea name="c_travel_source" rows="8" class="input resize-none leading-relaxed"
                                    placeholder="{{ __('travel.c_label') }}...">{{ old('c_travel_source') }}</textarea>
                            </div>

                            <x-invitation-upload class="mt-5" />
                        </div>
                    </div>
                </div>

                {{-- ── Step 3: Benefits ─────────────────────────────────── --}}
                <div x-show="currentStep === 3" x-cloak data-step="3">
                    <div class="card overflow-hidden">
                        <div class="px-6 py-5 border-b" style="background:linear-gradient(135deg,#f8fafc,#eff6ff);border-color:#e2e8f0;">
                            <div class="flex items-center gap-4">
                                <div class="h-12 w-12 rounded-2xl flex items-center justify-center text-white font-bold text-base shrink-0 shadow-sm"
                                     style="background:linear-gradient(135deg,#05499c,#1d6fd8);">D</div>
                                <h3 class="text-lg font-bold text-slate-900">{{ __('travel.section_d_title') }}</h3>
                            </div>
                        </div>
                        <div class="p-6 space-y-6">
                            <h4 class="text-sm font-bold uppercase tracking-widest" style="color:#05499c;">{{ __('travel.d_benefits_sub') }}</h4>
                            <div class="field">
                                <label class="label">{{ __('travel.d_institution') }} <span class="text-red-500">*</span></label>
                                <textarea name="d_benefit_to_institution" rows="5" required
                                    class="input resize-none leading-relaxed"
                                    :class="hasError('d_benefit_to_institution') ? 'border-red-400 ring-1 ring-red-300' : ''"
                                    @blur="validateRequired($el)"
                                    placeholder="...">{{ old('d_benefit_to_institution') }}</textarea>
                                <p x-show="hasError('d_benefit_to_institution')" x-cloak x-text="errorFor('d_benefit_to_institution')" class="mt-1.5 text-xs text-red-600"></p>
                            </div>
                            <div class="field">
                                <label class="label">{{ __('travel.d_nation') }} <span class="text-red-500">*</span></label>
                                <textarea name="d_benefit_to_nation" rows="5" required
                                    class="input resize-none leading-relaxed"
                                    :class="hasError('d_benefit_to_nation') ? 'border-red-400 ring-1 ring-red-300' : ''"
                                    @blur="validateRequired($el)"
                                    placeholder="...">{{ old('d_benefit_to_nation') }}</textarea>
                                <p x-show="hasError('d_benefit_to_nation')" x-cloak x-text="errorFor('d_benefit_to_nation')" class="mt-1.5 text-xs text-red-600"></p>
                            </div>
                            <div class="field">
                                <label class="label">{{ __('travel.d_consequences') }} <span class="text-red-500">*</span></label>
                                <textarea name="d_consequences_if_rejected" rows="4" required
                                    class="input resize-none leading-relaxed"
                                    :class="hasError('d_consequences_if_rejected') ? 'border-red-400 ring-1 ring-red-300' : ''"
                                    @blur="validateRequired($el)"
                                    placeholder="...">{{ old('d_consequences_if_rejected') }}</textarea>
                                <p x-show="hasError('d_consequences_if_rejected')" x-cloak x-text="errorFor('d_consequences_if_rejected')" class="mt-1.5 text-xs text-red-600"></p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ── Step 4: Costs ────────────────────────────────────── --}}
                <div x-show="currentStep === 4" x-cloak data-step="4">
                    <div class="card overflow-hidden">
                        <div class="px-6 py-5 border-b" style="background:linear-gradient(135deg,#f8fafc,#eff6ff);border-color:#e2e8f0;">
                            <div class="flex items-center gap-4">
                                <div class="h-12 w-12 rounded-2xl flex items-center justify-center text-white font-bold text-base shrink-0 shadow-sm"
                                     style="background:linear-gradient(135deg,#05499c,#1d6fd8);">E</div>
                                <h3 class="text-lg font-bold text-slate-900">{{ __('travel.section_e_title') }} <span class="text-red-500">*</span></h3>
                            </div>
                        </div>
                        <div class="p-6 space-y-6">
                            <p x-show="hasError('e_costs')" x-cloak x-text="errorFor('e_costs')"
                               class="text-sm text-red-600 font-medium flex items-center gap-1.5 -mb-2">
                            </p>
                            <div class="field">
                                <label class="label">{{ __('travel.e_transport') }} <span class="font-normal text-slate-400">{{ __('travel.e_transport_hint') }}</span></label>
                                <textarea name="e_transport_costs" rows="3" class="input resize-none"
                                    placeholder="...">{{ old('e_transport_costs') }}</textarea>
                            </div>

                            <div>
                                <label class="label font-bold" style="color:#05499c;text-transform:uppercase;font-size:11px;letter-spacing:.08em;">{{ __('travel.e_allowances') }}</label>
                                <div class="space-y-3">
                                    @foreach (['a', 'b', 'c', 'd'] as $letter)
                                    <div class="flex items-center gap-3">
                                        <span class="text-base text-slate-400 w-8 shrink-0">({{ $letter }})</span>
                                        <input type="text" name="e_allowance_{{ $letter }}" value="{{ old('e_allowance_' . $letter) }}"
                                            class="input" placeholder="...">
                                    </div>
                                    @endforeach
                                </div>
                                <div class="flex items-center gap-3 mt-3">
                                    <span class="text-base text-slate-500 shrink-0">{{ __('travel.e_budget_line') }}</span>
                                    <input type="text" name="e_budget_line" value="{{ old('e_budget_line') }}"
                                        class="input" placeholder="...">
                                </div>
                            </div>

                            <div>
                                <label class="label font-bold" style="color:#05499c;text-transform:uppercase;font-size:11px;letter-spacing:.08em;">{{ __('travel.e_donor') }}</label>
                                <div class="space-y-3">
                                    @foreach (['i', 'ii', 'iii'] as $num)
                                    <div class="flex items-center gap-3">
                                        <span class="text-base text-slate-400 w-8 shrink-0">({{ $num }})</span>
                                        <input type="text" name="e_donor_cost_{{ $num }}" value="{{ old('e_donor_cost_' . $num) }}"
                                            class="input" placeholder="...">
                                    </div>
                                    @endforeach
                                </div>
                            </div>

                            <div>
                                <label class="label font-bold" style="color:#05499c;text-transform:uppercase;font-size:11px;letter-spacing:.08em;">{{ __('travel.e_govt') }}</label>
                                <div class="space-y-3">
                                    @foreach (['i', 'ii', 'iii'] as $num)
                                    <div class="flex items-center gap-3">
                                        <span class="text-base text-slate-400 w-8 shrink-0">({{ $num }})</span>
                                        <input type="text" name="e_govt_cost_{{ $num }}" value="{{ old('e_govt_cost_' . $num) }}"
                                            class="input" placeholder="...">
                                    </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="field">
                                <label class="label">{{ __('travel.e_other') }}</label>
                                <textarea name="e_other_costs" rows="3" class="input resize-none"
                                    placeholder="...">{{ old('e_other_costs') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ── Step 5: Impact ───────────────────────────────────── --}}
                <div x-show="currentStep === 5" x-cloak data-step="5">
                    <div class="card overflow-hidden">
                        <div class="px-6 py-5 border-b" style="background:linear-gradient(135deg,#f8fafc,#eff6ff);border-color:#e2e8f0;">
                            <div class="flex items-center gap-4">
                                <div class="h-12 w-12 rounded-2xl flex items-center justify-center text-white font-bold text-base shrink-0 shadow-sm"
                                     style="background:linear-gradient(135deg,#05499c,#1d6fd8);">F</div>
                                <h3 class="text-lg font-bold text-slate-900">{{ __('travel.section_f_title') }}</h3>
                            </div>
                        </div>
                        <div class="p-6">
                            <p class="text-sm text-slate-500 mb-4 leading-relaxed">{{ __('travel.f_desc') }}</p>
                            <div class="field">
                                <label class="label">{{ __('travel.f_label') }} <span class="text-red-500">*</span></label>
                                <textarea name="f_previous_travel_impact" rows="10" required
                                    class="input resize-none leading-relaxed"
                                    :class="hasError('f_previous_travel_impact') ? 'border-red-400 ring-1 ring-red-300' : ''"
                                    @blur="validateRequired($el)"
                                    placeholder="...">{{ old('f_previous_travel_impact') }}</textarea>
                                <p x-show="hasError('f_previous_travel_impact')" x-cloak x-text="errorFor('f_previous_travel_impact')" class="mt-1.5 text-xs text-red-600"></p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ── Step 6: Handover ─────────────────────────────────── --}}
                <div x-show="currentStep === 6" x-cloak data-step="6">
                    <div class="card overflow-hidden mb-5">
                        <div class="px-6 py-5 border-b" style="background:linear-gradient(135deg,#f8fafc,#eff6ff);border-color:#e2e8f0;">
                            <div class="flex items-center gap-4">
                                <div class="h-12 w-12 rounded-2xl flex items-center justify-center text-white font-bold text-base shrink-0 shadow-sm"
                                     style="background:linear-gradient(135deg,#05499c,#1d6fd8);">G</div>
                                <h3 class="text-lg font-bold text-slate-900">{{ __('travel.section_g_title') }}</h3>
                            </div>
                        </div>
                        <div class="p-6 space-y-5">
                            {{-- Gate 1: the traveller must tick this deliberately. --}}
                            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                                <label class="flex items-start gap-2.5 cursor-pointer select-none">
                                    <input type="checkbox" name="g_no_handover_officer" value="1"
                                           x-model="noHandover" @change="onNoHandoverToggle()"
                                           class="mt-0.5 h-4 w-4 rounded border-amber-300 text-amber-600 shadow-sm focus:ring-amber-500">
                                    <span>
                                        <span class="block text-sm font-semibold text-amber-900">{{ __('travel.g_no_handover_checkbox') }}</span>
                                        <span class="block text-xs text-amber-700 mt-0.5">{{ __('travel.g_no_handover_hint') }}</span>
                                    </span>
                                </label>
                            </div>

                            {{-- Gate 2: they must say why, in their own words. --}}
                            <div class="field" x-show="noHandover" x-cloak>
                                <label class="label">{{ __('travel.g_no_handover_declaration_label') }} <span class="text-red-500">*</span></label>
                                <textarea name="g_no_handover_declaration" rows="4"
                                          class="input"
                                          :class="hasError('g_no_handover_declaration') ? 'input-error' : ''"
                                          placeholder="{{ __('travel.g_no_handover_declaration_ph') }}">{{ old('g_no_handover_declaration') }}</textarea>
                                <p class="mt-1 text-xs text-slate-400">{{ __('travel.g_no_handover_declaration_help') }}</p>
                                <p x-show="hasError('g_no_handover_declaration')" x-cloak
                                   x-text="errorFor('g_no_handover_declaration')" class="mt-1.5 text-xs text-red-600"></p>
                                @error('g_no_handover_declaration')
                                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Naming an officer and declaring there is nobody are
                                 mutually exclusive, so hide these once it is ticked. --}}
                            <template x-if="!noHandover">
                            <div class="space-y-5">
                            {{-- Handover officer picker --}}
                            <div class="field relative"
                                 x-data="{
                                     open: false,
                                     search: {{ Js::from(old('g_handover_officer_name', '')) }},
                                     selectedId: {{ Js::from((string) old('g_handover_officer_id', '')) }},
                                     selectedName: {{ Js::from(old('g_handover_officer_name', '')) }},
                                     selectedTitle: {{ Js::from(old('g_handover_officer_title', '')) }},
                                     users: {{ Js::from($handoverUsers) }},
                                     get filtered() {
                                         if (!this.search) return this.users;
                                         const q = this.search.toLowerCase();
                                         return this.users.filter(u => u.name.toLowerCase().includes(q) || u.title.toLowerCase().includes(q));
                                     },
                                     clear() { this.selectedId = ''; this.selectedName = ''; this.selectedTitle = ''; },
                                     select(u) { this.selectedId = String(u.id); this.selectedName = u.name; this.selectedTitle = u.title; this.search = u.name; this.open = false; }
                                 }"
                                 @click.outside="open = false">
                                <label class="label">{{ __('travel.g_officer_name') }}</label>
                                <input type="hidden" name="g_handover_officer_id" :value="selectedId">
                                <input type="hidden" name="g_handover_officer_name" :value="selectedName">
                                <input type="hidden" name="g_handover_officer_title" :value="selectedTitle">
                                <div class="relative">
                                    <input type="text" x-model="search"
                                           @focus="open = true" @input="open = true; clear()"
                                           class="input pr-9" placeholder="Search by name or title…" autocomplete="off">
                                    <button type="button" @click="open = !open"
                                            class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                </div>
                                <div x-show="open" x-cloak
                                     class="absolute z-50 mt-1 w-full bg-white border border-slate-200 rounded-xl shadow-lg overflow-y-auto"
                                     style="max-height:200px;">
                                    <template x-for="u in filtered" :key="u.id">
                                        <button type="button" @click="select(u)"
                                                class="w-full text-left px-3 py-2.5 hover:bg-blue-50 flex flex-col border-b border-slate-50 last:border-0 transition">
                                            <span class="text-sm font-medium text-slate-800" x-text="u.name"></span>
                                            <span class="text-xs text-slate-400" x-text="u.title"></span>
                                        </button>
                                    </template>
                                    <div x-show="filtered.length === 0" class="px-3 py-3 text-sm text-slate-400 text-center">No results</div>
                                </div>
                                <p x-show="selectedTitle" x-cloak class="text-xs text-slate-400 mt-1" x-text="'Title: ' + selectedTitle"></p>
                                <p x-show="hasError('g_handover_officer_name')" x-cloak x-text="errorFor('g_handover_officer_name')" class="mt-1.5 text-xs text-red-600"></p>
                            </div>

                            {{-- File upload — PDF only --}}
                            <div x-data="{ fileName: '', dragOver: false, fileError: '' }" class="field">
                                <label class="label">{{ __('travel.g_upload') }} <span class="text-red-500">*</span> <span class="font-normal text-slate-400">PDF only, max 5 MB</span></label>
                                <label
                                    :class="dragOver ? 'border-indigo-400 bg-indigo-50' : (fileName ? 'border-emerald-400 bg-emerald-50' : (fileError ? 'border-red-400 bg-red-50' : 'border-slate-300 hover:border-indigo-400 hover:bg-indigo-50/40'))"
                                    class="flex flex-col items-center justify-center w-full h-28 border-2 border-dashed rounded-xl cursor-pointer transition-all"
                                    @dragover.prevent="dragOver = true"
                                    @dragleave="dragOver = false"
                                    @drop.prevent="
                                        dragOver = false;
                                        const f = $event.dataTransfer.files[0];
                                        if (!f) { return; }
                                        if (f.type !== 'application/pdf') {
                                            fileError = 'Only PDF files are accepted.'; fileName = ''; $refs.fileInput.value = '';
                                            return;
                                        }
                                        // The dropped file must be moved into the input itself,
                                        // otherwise nothing is submitted and the green
                                        // 'file selected' state is a lie.
                                        const dt = new DataTransfer();
                                        dt.items.add(f);
                                        $refs.fileInput.files = dt.files;
                                        fileError = ''; fileName = f.name;
                                    ">
                                    <div x-show="!fileName && !fileError" class="flex flex-col items-center gap-2 text-slate-400">
                                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                                        <span class="text-sm">{{ __('travel.g_click_drag') }}</span>
                                    </div>
                                    <div x-show="fileError" class="flex items-center gap-2 text-red-600">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        <span class="text-sm font-medium" x-text="fileError"></span>
                                    </div>
                                    <div x-show="fileName" class="flex items-center gap-3 text-emerald-700">
                                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        <div>
                                            <span class="text-sm font-medium" x-text="fileName"></span>
                                            <p class="text-xs text-emerald-600">{{ __('travel.g_file_selected') }}</p>
                                        </div>
                                    </div>
                                    <input type="file" name="g_handover_document" accept=".pdf" class="hidden"
                                        x-ref="fileInput"
                                        @change="
                                            const f = $event.target.files[0];
                                            if (f && f.type !== 'application/pdf') { fileError = 'Only PDF files are accepted.'; fileName = ''; $event.target.value = ''; }
                                            else { fileError = ''; fileName = f?.name ?? ''; }
                                        ">
                                </label>
                                <p x-show="hasError('g_handover_document')" x-cloak x-text="errorFor('g_handover_document')" class="mt-1.5 text-xs text-red-600"></p>
                            </div>
                            </div>
                            </template>
                        </div>
                    </div>

                    {{-- Pre-submit checklist --}}
                    <div class="card p-5 bg-emerald-50 border-emerald-200">
                        <h4 class="text-sm font-bold text-emerald-800 mb-3 flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            {{ __('travel.checklist_title') }}
                        </h4>
                        <ul class="space-y-1.5 text-base text-emerald-700">
                            @foreach ([__('travel.checklist_1'), __('travel.checklist_2'), __('travel.checklist_3')] as $item)
                            <li class="flex items-center gap-2">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                {{ $item }}
                            </li>
                            @endforeach
                        </ul>
                    </div>
                </div>

                {{-- Review --}}
                <div x-show="currentStep === 7" x-cloak data-step="7">
                    {{-- Header --}}
                    <div class="rounded-2xl overflow-hidden mb-5 shadow-sm" style="background:#05499c;">
                        <div class="px-6 py-5 flex items-center gap-4">
                            <div class="h-12 w-12 rounded-2xl flex items-center justify-center text-white font-bold text-base shrink-0"
                                 style="background:rgba(255,255,255,0.15);">H</div>
                            <div>
                                <h3 class="text-lg font-bold text-white">{{ __('travel.review_title') }}</h3>
                                <p class="text-sm mt-0.5" style="color:rgba(255,255,255,0.7);">{{ __('travel.review_hint') }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="card overflow-hidden">
                        {{-- Form header --}}
                        <div class="text-center px-8 py-5 border-b-2 border-slate-800 bg-white">
                            <p class="text-right text-xs italic font-semibold text-slate-500">{{ __('travel.form_number') }}</p>
                            <div class="flex justify-center my-2">
                                <img src="{{ asset('NIMR.png') }}" alt="NIMR" class="h-12 w-12 object-contain">
                            </div>
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-800">{{ __('travel.institution_name') }}</p>
                            <p class="text-xs font-bold uppercase tracking-wide mt-0.5 text-slate-800">{{ __('travel.form_full_name') }}</p>
                        </div>

                        @php
                            $ro     = 'flex-1 text-sm text-slate-800 border-b border-slate-200 py-0.5 px-1';
                            $numCls = 'text-sm w-10 shrink-0 text-slate-400';
                            $lblCls = 'text-sm w-64 shrink-0 text-slate-600';
                        @endphp

                        <div class="p-6 space-y-8">

                            {{-- A --}}
                            <div>
                                <h3 class="text-xs font-bold uppercase tracking-widest text-slate-400 border-b border-slate-200 pb-2 mb-3">A: {{ __('travel.section_a_title') }}</h3>
                                <div class="text-xs space-y-1.5 text-slate-600 leading-relaxed bg-slate-50 rounded-lg p-4 border border-slate-100">
                                    @foreach (['section_a_i','section_a_ii','section_a_iii','section_a_iv','section_a_v','section_a_vi'] as $i => $key)
                                    <p><span class="font-semibold text-slate-700">({{ ['i','ii','iii','iv','v','vi'][$i] }})</span> {{ __('travel.' . $key) }}</p>
                                    @endforeach
                                </div>
                            </div>

                            {{-- B --}}
                            <div>
                                <h3 class="text-xs font-bold uppercase tracking-widest text-slate-400 border-b border-slate-200 pb-2 mb-4">B: {{ __('travel.section_b_title') }}</h3>
                                <div class="space-y-3">
                                    @foreach ([
                                        ['(i)',   __('travel.b_name'),         'b_applicant_name'],
                                        ['(ii)',  __('travel.b_phone'),        'b_phone'],
                                        ['(iii)', __('travel.b_email'),        'b_email'],
                                        ['(iv)',  __('travel.b_position'),     'b_position'],
                                        ['(v)',   __('travel.b_destination'),  'b_destination'],
                                        ['(vi)',  __('travel.b_departure_date'),'b_departure_date'],
                                        ['(vii)', __('travel.b_return_date'),  'b_return_date'],
                                    ] as [$num, $label, $fname])
                                    <div class="flex items-baseline gap-2">
                                        <span class="{{ $numCls }}">{{ $num }}</span>
                                        <span class="{{ $lblCls }}">{{ $label }}</span>
                                        <span class="{{ $ro }}" x-text="field('{{ $fname }}')"></span>
                                    </div>
                                    @endforeach
                                </div>
                            </div>

                            {{-- C --}}
                            <div>
                                <h3 class="text-xs font-bold uppercase tracking-widest text-slate-400 border-b border-slate-200 pb-2 mb-3">C: {{ __('travel.section_c_title') }}</h3>
                                <div class="text-sm text-slate-800 bg-slate-50 rounded-lg p-4 border border-slate-100 whitespace-pre-wrap min-h-[3rem]" x-text="field('c_travel_source')"></div>
                            </div>

                            {{-- D --}}
                            <div>
                                <h3 class="text-xs font-bold uppercase tracking-widest text-slate-400 border-b border-slate-200 pb-2 mb-4">D: {{ __('travel.section_d_title') }}</h3>
                                <p class="text-sm font-medium text-slate-600 mb-3">{{ __('travel.d_benefits_sub') }}</p>
                                <div class="space-y-4">
                                    @foreach ([
                                        [__('travel.d_institution'), 'd_benefit_to_institution'],
                                        [__('travel.d_nation'),      'd_benefit_to_nation'],
                                        [__('travel.d_consequences'),'d_consequences_if_rejected'],
                                    ] as [$label, $fname])
                                    <div>
                                        <p class="text-sm font-medium text-slate-600 mb-1.5">{{ $label }}</p>
                                        <div class="text-sm text-slate-800 bg-slate-50 rounded-lg p-4 border border-slate-100 whitespace-pre-wrap min-h-[3rem]" x-text="field('{{ $fname }}')"></div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>

                            {{-- E --}}
                            <div>
                                <h3 class="text-xs font-bold uppercase tracking-widest text-slate-400 border-b border-slate-200 pb-2 mb-4">E: {{ __('travel.section_e_title') }}</h3>
                                <div class="space-y-4">
                                    <div>
                                        <p class="text-sm font-medium text-slate-600 mb-1.5">{{ __('travel.e_transport') }}</p>
                                        <div class="text-sm text-slate-800 bg-slate-50 rounded-lg p-4 border border-slate-100 whitespace-pre-wrap min-h-[2.5rem]" x-text="field('e_transport_costs')"></div>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-slate-600 mb-2">{{ __('travel.e_allowances') }}</p>
                                        <div class="ml-4 space-y-1.5">
                                            @foreach (['a','b','c','d'] as $l)
                                            <div class="flex items-baseline gap-2">
                                                <span class="{{ $numCls }}">({{ $l }})</span>
                                                <span class="{{ $ro }}" x-text="field('e_allowance_{{ $l }}')"></span>
                                            </div>
                                            @endforeach
                                            <div class="flex items-baseline gap-2 mt-1">
                                                <span class="text-sm shrink-0 text-slate-600">{{ __('travel.e_budget_line') }}</span>
                                                <span class="{{ $ro }}" x-text="field('e_budget_line')"></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-slate-600 mb-2">{{ __('travel.e_donor') }}</p>
                                        <div class="ml-4 space-y-1">
                                            @foreach (['i','ii','iii'] as $n)
                                            <div class="flex items-baseline gap-2">
                                                <span class="{{ $numCls }}">({{ $n }})</span>
                                                <span class="{{ $ro }}" x-text="field('e_donor_cost_{{ $n }}')"></span>
                                            </div>
                                            @endforeach
                                        </div>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-slate-600 mb-2">{{ __('travel.e_govt') }}</p>
                                        <div class="ml-4 space-y-1">
                                            @foreach (['i','ii','iii'] as $n)
                                            <div class="flex items-baseline gap-2">
                                                <span class="{{ $numCls }}">({{ $n }})</span>
                                                <span class="{{ $ro }}" x-text="field('e_govt_cost_{{ $n }}')"></span>
                                            </div>
                                            @endforeach
                                        </div>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-slate-600 mb-1.5">{{ __('travel.e_other') }}</p>
                                        <div class="text-sm text-slate-800 bg-slate-50 rounded-lg p-4 border border-slate-100 whitespace-pre-wrap min-h-[2.5rem]" x-text="field('e_other_costs')"></div>
                                    </div>
                                </div>
                            </div>

                            {{-- F --}}
                            <div>
                                <h3 class="text-xs font-bold uppercase tracking-widest text-slate-400 border-b border-slate-200 pb-2 mb-3">F: {{ __('travel.section_f_title') }}</h3>
                                <div class="text-sm text-slate-800 bg-slate-50 rounded-lg p-4 border border-slate-100 whitespace-pre-wrap min-h-[4rem]" x-text="field('f_previous_travel_impact')"></div>
                            </div>

                            {{-- G --}}
                            <div>
                                <h3 class="text-xs font-bold uppercase tracking-widest text-slate-400 border-b border-slate-200 pb-2 mb-3">G: {{ __('travel.section_g_title') }}</h3>
                                <div class="space-y-2">
                                    <div class="flex items-baseline gap-2">
                                        <span class="text-sm w-20 shrink-0 text-slate-600">{{ __('travel.signed_name') }}</span>
                                        <span class="{{ $ro }}" x-text="field('g_handover_officer_name')"></span>
                                    </div>
                                    <div class="flex items-baseline gap-2">
                                        <span class="text-sm w-20 shrink-0 text-slate-600">{{ __('travel.signed_title') }}</span>
                                        <span class="{{ $ro }}" x-text="field('g_handover_officer_title')"></span>
                                    </div>
                                    <div class="flex items-center gap-2 mt-2">
                                        <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        <span class="text-sm text-slate-600" x-text="fileField('g_handover_document')"></span>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                {{-- ── Navigation ───────────────────────────────────────── --}}
                <div class="flex items-center justify-between mt-6 pt-5 border-t border-slate-200">
                    <button type="button" @click="prev()" x-show="currentStep > 0" class="btn-ghost">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        {{ __('users.back') }}
                    </button>
                    <div x-show="currentStep === 0"></div>

                    <div class="flex items-center gap-3">
                        <button type="submit" name="action" value="draft" x-show="currentStep > 0 && currentStep < 7" class="btn-secondary btn-sm" formnovalidate>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                            {{ __('travel.save_draft') }}
                        </button>

                        <button type="button" @click="next()" x-show="currentStep < 7" class="btn-primary">
                            <span x-text='currentStep === 0 ? @json(__('travel.understood_next')) : @json(__('travel.next'))'></span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </button>

                        <button type="submit" name="action" value="submit" x-show="currentStep === 7" class="btn-success">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            {{ __('travel.submit_request') }}
                        </button>
                    </div>
                </div>

            </form>

            {{-- Gate 3: leaving Section G with the declaration ticked stops here.
                 Confirming is what actually advances the wizard; cancelling
                 unticks the box, so the officer and note are required again. --}}
            <div x-show="showNoHandoverModal" x-cloak
                 class="fixed inset-0 z-50 flex items-center justify-center p-4"
                 role="dialog" aria-modal="true" aria-labelledby="no-handover-title">
                <div class="absolute inset-0 bg-slate-900/50" @click="cancelNoHandover()"></div>
                <div class="relative w-full max-w-lg rounded-2xl bg-white shadow-xl p-6"
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100">
                    <div class="flex items-start gap-3 mb-4">
                        <div class="h-10 w-10 rounded-full bg-amber-100 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                        </div>
                        <h3 id="no-handover-title" class="text-base font-bold text-slate-900 pt-1.5">
                            {{ __('travel.g_no_handover_modal_title') }}
                        </h3>
                    </div>
                    <p class="text-sm text-slate-600 leading-relaxed mb-6">
                        {{ __('travel.g_no_handover_modal_body') }}
                    </p>
                    <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
                        <button type="button" @click="cancelNoHandover()" class="btn-secondary">
                            {{ __('travel.g_no_handover_modal_cancel') }}
                        </button>
                        <button type="button" @click="confirmNoHandover()"
                                class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-white shadow-sm transition hover:opacity-90"
                                style="background-color:#b45309;">
                            {{ __('travel.g_no_handover_modal_confirm') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function formWizard(initialStep) {
            return {
                currentStep: initialStep,
                stepTitles: @json(array_column($steps, 'title')),
                fieldErrors: {},
                touched: {},

                // Declaring there is nobody to hand over to takes three
                // deliberate acts: this checkbox, a written declaration, and
                // the confirmation dialog below. None of it is a default.
                noHandover: {{ Js::from((bool) old('g_no_handover_officer', false)) }},
                noHandoverConfirmed: {{ Js::from((bool) old('g_no_handover_officer', false)) }},
                showNoHandoverModal: false,

                onNoHandoverToggle() {
                    // Re-tick means re-confirm; the dialog is not a one-off.
                    this.noHandoverConfirmed = false;
                    delete this.fieldErrors['g_handover_officer_name'];
                    delete this.fieldErrors['g_handover_document'];
                    delete this.fieldErrors['g_no_handover_declaration'];
                },

                confirmNoHandover() {
                    this.noHandoverConfirmed = true;
                    this.showNoHandoverModal = false;
                    this.advance();
                },

                cancelNoHandover() {
                    // They chose to name someone after all — untick, so the
                    // officer and note become required again.
                    this.showNoHandoverModal = false;
                    this.noHandover = false;
                    this.noHandoverConfirmed = false;
                },

                advance() { if (this.currentStep < 7) { this.currentStep++; window.scrollTo({ top: 0, behavior: 'smooth' }); } },

                next() {
                    if (!this.validateStep(this.currentStep)) return;

                    // Third gate: leaving Section G having declared no handover
                    // stops for an explicit confirmation.
                    if (this.currentStep === 6 && this.noHandover && !this.noHandoverConfirmed) {
                        this.showNoHandoverModal = true;
                        return;
                    }

                    this.advance();
                },
                prev() { if (this.currentStep > 0) { this.currentStep--; window.scrollTo({ top: 0, behavior: 'smooth' }); } },
                goTo(step) { if (step <= this.currentStep) { this.currentStep = step; window.scrollTo({ top: 0, behavior: 'smooth' }); } },

                hasError(name) { return !!(this.touched[name] && this.fieldErrors[name]); },
                errorFor(name) { return this.fieldErrors[name] || ''; },

                validateRequired(el) {
                    this.touched[el.name] = true;
                    if (!el.value.trim()) {
                        this.fieldErrors[el.name] = 'This field is required.';
                    } else {
                        delete this.fieldErrors[el.name];
                    }
                },

                validatePhone(el) {
                    this.touched[el.name] = true;
                    const val = (el.value || '').trim();
                    if (!val) {
                        this.fieldErrors[el.name] = 'Phone number is required.';
                    } else if (!/^\+?[\d\s\-()–]{7,20}$/.test(val)) {
                        this.fieldErrors[el.name] = 'Enter a valid phone number (e.g. +255 712 345 678).';
                    } else {
                        delete this.fieldErrors[el.name];
                    }
                },

                validateDeparture(el) {
                    this.touched[el.name] = true;
                    const val = el.value;
                    if (!val) {
                        this.fieldErrors[el.name] = 'Departure date is required.';
                        return;
                    }
                    const today = new Date(); today.setHours(0, 0, 0, 0);
                    const tomorrow = new Date(today); tomorrow.setDate(today.getDate() + 1);
                    const selected = new Date(val + 'T00:00:00');
                    if (selected < tomorrow) {
                        this.fieldErrors[el.name] = 'Departure date must be at least one day from today.';
                    } else {
                        delete this.fieldErrors[el.name];
                    }
                    const retEl = this.$refs.form?.elements['b_return_date'];
                    if (retEl?.value && this.touched['b_return_date']) this.validateReturn(retEl);
                },

                validateReturn(el) {
                    this.touched[el.name] = true;
                    const val = el.value;
                    const depVal = this.$refs.form?.elements['b_departure_date']?.value;
                    if (!val) {
                        this.fieldErrors[el.name] = 'Return date is required.';
                    } else if (depVal && val <= depVal) {
                        this.fieldErrors[el.name] = 'Return must be at least one day after departure. Same-day return is not allowed.';
                    } else {
                        delete this.fieldErrors[el.name];
                    }
                },

                field(name) {
                    void this.currentStep; // reactive dependency — re-reads when step changes
                    const control = this.$refs.form?.elements[name];
                    return control?.value?.trim() || '—';
                },
                fileField(name) {
                    void this.currentStep;
                    const control = this.$refs.form?.elements[name];
                    return control?.files?.[0]?.name || '—';
                },

                validateAll() {
                    for (let step = 0; step <= 6; step++) {
                        if (!this.validateStep(step)) {
                            this.currentStep = step;
                            this.$nextTick(() => window.scrollTo({ top: 0, behavior: 'smooth' }));
                            return false;
                        }
                    }
                    return true;
                },

                validateStep(step) {
                    const form = this.$refs.form;
                    if (!form) return true;

                    if (step === 1) {
                        const step1Fields = ['b_applicant_name', 'b_destination'];
                        step1Fields.forEach(name => {
                            const el = form.elements[name];
                            if (el) this.validateRequired(el);
                        });
                        const phoneEl = form.elements['b_phone'];
                        if (phoneEl) this.validatePhone(phoneEl);
                        const depEl = form.elements['b_departure_date'];
                        if (depEl) this.validateDeparture(depEl);
                        const retEl = form.elements['b_return_date'];
                        if (retEl) this.validateReturn(retEl);

                        const step1Keys = ['b_applicant_name', 'b_destination', 'b_phone', 'b_departure_date', 'b_return_date'];
                        return step1Keys.every(k => !this.fieldErrors[k]);
                    }

                    if (step === 3) {
                        ['d_benefit_to_institution', 'd_benefit_to_nation', 'd_consequences_if_rejected'].forEach(name => {
                            const el = form.elements[name];
                            if (el) this.validateRequired(el);
                        });
                        return ['d_benefit_to_institution', 'd_benefit_to_nation', 'd_consequences_if_rejected'].every(k => !this.fieldErrors[k]);
                    }

                    if (step === 4) {
                        const costFields = [
                            'e_transport_costs', 'e_allowance_a', 'e_allowance_b', 'e_allowance_c', 'e_allowance_d',
                            'e_budget_line', 'e_donor_cost_i', 'e_donor_cost_ii', 'e_donor_cost_iii',
                            'e_govt_cost_i', 'e_govt_cost_ii', 'e_govt_cost_iii', 'e_other_costs',
                        ];
                        const hasCost = costFields.some(name => (form.elements[name]?.value || '').trim());
                        if (!hasCost) {
                            this.touched['e_costs'] = true;
                            this.fieldErrors['e_costs'] = 'Please fill in at least one cost or allowance field.';
                            return false;
                        }
                        delete this.fieldErrors['e_costs'];
                        return true;
                    }

                    if (step === 5) {
                        const el = form.elements['f_previous_travel_impact'];
                        if (el) this.validateRequired(el);
                        return !this.fieldErrors['f_previous_travel_impact'];
                    }

                    if (step === 6) {
                        // Either a named officer with a signed note, or a
                        // written declaration that there is nobody. Mirrors
                        // validateForm() on the server, which decides again.
                        if (this.noHandover) {
                            const declarationEl = form.elements['g_no_handover_declaration'];
                            this.touched['g_no_handover_declaration'] = true;
                            const declaration = (declarationEl?.value || '').trim();
                            if (declaration.length < 20) {
                                this.fieldErrors['g_no_handover_declaration'] =
                                    'Please explain in your own words why no one can take over your duties.';
                                return false;
                            }
                            delete this.fieldErrors['g_no_handover_declaration'];
                            return true;
                        }

                        const officerEl = form.elements['g_handover_officer_name'];
                        const fileEl    = form.elements['g_handover_document'];
                        this.touched['g_handover_officer_name'] = true;
                        this.touched['g_handover_document']     = true;
                        if (!officerEl?.value?.trim()) {
                            this.fieldErrors['g_handover_officer_name'] = 'Please select the acting officer.';
                        } else {
                            delete this.fieldErrors['g_handover_officer_name'];
                        }
                        if (!fileEl?.files?.length) {
                            this.fieldErrors['g_handover_document'] = 'Please upload the handover note document.';
                        } else {
                            delete this.fieldErrors['g_handover_document'];
                        }
                        return !this.fieldErrors['g_handover_officer_name'] && !this.fieldErrors['g_handover_document'];
                    }

                    const controls = [...form.querySelectorAll(`[data-step="${step}"] input, [data-step="${step}"] textarea, [data-step="${step}"] select`)]
                        .filter(c => c.type !== 'hidden' && !c.disabled && c.required);
                    const invalid = controls.find(c => !c.value.trim());
                    if (invalid) { invalid.focus(); return false; }
                    return true;
                },
            };
        }
    </script>
</x-app-layout>
