<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $travelRequest->request_number }} — Fomu ya Ruhusa ya Kusafiri</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 11pt;
            color: #000;
            background: #fff;
            padding: 0;
        }

        .page {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            padding: 18mm 20mm 18mm 20mm;
            background: #fff;
        }

        /* Header */
        .doc-header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }
        .form-number { text-align: right; font-style: italic; font-size: 10pt; }
        .org-name { font-size: 12pt; font-weight: bold; text-transform: uppercase; margin: 4px 0; }
        .logo-circle {
            display: inline-block;
            width: 60px; height: 60px;
            margin: 6px 0;
            object-fit: contain;
        }
        .form-title { font-size: 11pt; font-weight: bold; text-transform: uppercase; }

        /* Sections */
        .section { margin-bottom: 14px; }
        .section-heading {
            font-weight: bold;
            text-transform: uppercase;
            font-size: 10.5pt;
            border-bottom: 1.5px solid #000;
            padding-bottom: 2px;
            margin-bottom: 8px;
        }

        /* Fields */
        .field-row {
            display: flex;
            align-items: flex-end;
            margin-bottom: 6px;
            gap: 4px;
        }
        .field-num { width: 28px; flex-shrink: 0; }
        .field-label { width: 200px; flex-shrink: 0; }
        .field-value {
            flex: 1;
            border-bottom: 1px solid #000;
            min-height: 16px;
            padding: 0 2px;
        }

        /* Text blocks */
        .text-block {
            border-bottom: 1px solid #000;
            min-height: 20px;
            margin-bottom: 3px;
            padding: 1px 2px;
        }
        .text-area-block {
            border: none;
            min-height: 55px;
        }
        .dotted-lines { }
        .dotted-line {
            border-bottom: 1px dotted #555;
            margin-bottom: 5px;
            min-height: 16px;
            padding: 1px 2px;
        }

        /* Indentation */
        .ml-1 { margin-left: 14px; }
        .ml-2 { margin-left: 28px; }
        .ml-3 { margin-left: 42px; }

        /* Signature row */
        .sig-row {
            display: flex;
            gap: 40px;
            margin-top: 8px;
            flex-wrap: wrap;
        }
        .sig-item { display: flex; align-items: flex-end; gap: 6px; }
        .sig-line { border-bottom: 1px solid #000; width: 120px; min-height: 16px; }
        .sig-line-wide { width: 180px; }
        /* Decision stamp — replaces the wet signature and rubber stamp lines */
        .stamp { width: 90px; height: auto; }

        /* Approval section box */
        .approval-section {
            border: 1px solid #000;
            padding: 8px 10px;
            margin-bottom: 10px;
        }
        .approval-decision {
            font-weight: bold;
            font-size: 11pt;
        }

        /* Status stamp */
        .status-stamp {
            display: inline-block;
            border: 3px solid #000;
            padding: 4px 12px;
            font-size: 14pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin: 8px 0;
        }
        .stamp-approved { border-color: #155724; color: #155724; }
        .stamp-rejected  { border-color: #721c24; color: #721c24; }

        /* Print controls (hidden on print) */
        .print-controls {
            position: fixed;
            top: 16px;
            right: 16px;
            display: flex;
            gap: 8px;
            z-index: 999;
        }
        .btn-print {
            background: #1d4ed8;
            color: #fff;
            border: none;
            padding: 8px 20px;
            font-size: 13px;
            font-family: sans-serif;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }
        .btn-back {
            background: #fff;
            color: #374151;
            border: 1px solid #d1d5db;
            padding: 8px 16px;
            font-size: 13px;
            font-family: sans-serif;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }

        @media print {
            .print-controls { display: none !important; }
            body { padding: 0; }
            .page { margin: 0; padding: 15mm 18mm; width: 100%; }
            @page { size: A4; margin: 0; }
        }

        p, span, div { line-height: 1.4; }
        .instructions-list p { font-size: 10pt; margin-bottom: 4px; }
        .small { font-size: 9.5pt; }
    </style>
</head>
<body>

{{-- Print / Back controls --}}
<div class="print-controls">
    <a href="{{ route('travel-requests.show', $travelRequest) }}" class="btn-back">← {{ __('common.back') }}</a>
    <a href="{{ route('travel-requests.pdf', $travelRequest) }}" class="btn-print" style="background:#0f8a4b;text-decoration:none;">⬇ Download PDF</a>
    <button class="btn-print" onclick="window.print()">{{ __('common.print') }}</button>
</div>

<div class="page">

    {{-- ================================================================ --}}
    {{-- DOCUMENT HEADER                                                   --}}
    {{-- ================================================================ --}}
    <div class="doc-header">
        <div class="form-number">Fomu Na: NIMR – ADM – F002</div>
        <div class="org-name">Taasisi ya Taifa ya Utafiti wa Magonjwa ya Binadamu</div>
        <div><img src="{{ asset('NIMR.png') }}" alt="NIMR" class="logo-circle"></div>
        <div class="form-title">Fomu ya Maombi ya Ruhusa ya Kusafiri Ndani ya Nchi</div>
    </div>

    @php $tr = $travelRequest; @endphp

    {{-- ================================================================ --}}
    {{-- SECTION A: UTANGULIZI                                             --}}
    {{-- ================================================================ --}}
    <div class="section">
        <div class="section-heading">A: Utangulizi:</div>
        <div class="instructions-list">
            <p><strong>(i)</strong> Watumishi wote wanaotarajia kusafiri ndani ya nchi wanapaswa kujaza kikamilifu fomu hii siku 14 kabla ya safari.</p>
            <p><strong>(ii)</strong> Pamoja na fomu hii, viambatisho muhimu vinavyothibitisha safari lazima viwepo (mfano barua ya mwaliko, maelezo ya wazi ya gharama zote za safari na kwamba ni nani ana jukumu la kuzilipa, kama zinalipwa kwa sehemu na mfadhili, ni kiasi gani kinatakiwa kugharamiwa na Taasisi.</p>
            <p><strong>(iii)</strong> Fomu hii itajazwa na kila Mtumishi wa Taasisi atakayesafiri kwa shughuli za kiofisi ndani ya nchi.</p>
            <p><strong>(iv)</strong> Fomu ambayo haikujazwa kikamilifu kuonesha taarifa zote muhimu na kwa kiwango kinachoeleweka au imechelewa chini ya siku zilizotajwa hapo juu haitashughulikiwa na hivyo Mtumishi atakuwa amejinyima ruhusa mwenyewe ya kusafiri.</p>
            <p><strong>(v)</strong> Ndani ya kipindi cha wiki mbili baada ya kurudi kutoka safarini Mtumishi anatakiwa kuwasilisha ripoti ya safari kwa Mkurugenzi Mkuu kupitia kwa Mkurugenzi wa Idara/ Meneja wa Kituo au Mkuu wa Idara anapofanyia kazi. Nakala ya ripoti hii pia iwasilishwe Ofisi ya Rasilimali Watu na Utawala.</p>
            <p><strong>(vi)</strong> Safari za dharura zitatumia utaratibu wa dharura.</p>
        </div>
    </div>

    {{-- ================================================================ --}}
    {{-- SECTION B: TAARIFA ZA MTUMISHI ANAYESAFIRI                       --}}
    {{-- ================================================================ --}}
    <div class="section">
        <div class="section-heading">B: Taarifa za Mtumishi Anayesafiri</div>

        <div class="field-row">
            <span class="field-num">(i)</span>
            <span class="field-label">Jina la Mtumishi anayesafiri</span>
            <span class="field-value">{{ $tr->b_applicant_name }}</span>
        </div>
        <div class="field-row">
            <span class="field-num">(ii)</span>
            <span class="field-label">Simu</span>
            <span class="field-value">{{ $tr->b_phone }}</span>
        </div>
        <div class="field-row">
            <span class="field-num">(iii)</span>
            <span class="field-label">Barua Pepe</span>
            <span class="field-value">{{ $tr->b_email }}</span>
        </div>
        <div class="field-row">
            <span class="field-num">(iv)</span>
            <span class="field-label">Cheo</span>
            <span class="field-value">{{ $tr->b_position }}</span>
        </div>
        <div class="field-row">
            <span class="field-num">(v)</span>
            <span class="field-label">Mikoa/Mkoa/Wilaya anapokwenda</span>
            <span class="field-value">{{ $tr->b_destination }}</span>
        </div>
        <div class="field-row">
            <span class="field-num">(vi)</span>
            <span class="field-label">Tarehe ya Kuondoka</span>
            <span class="field-value">{{ $tr->b_departure_date?->format('d/m/Y') }}</span>
        </div>
        <div class="field-row">
            <span class="field-num">(vii)</span>
            <span class="field-label">Tarehe ya Kurudi</span>
            <span class="field-value">{{ $tr->b_return_date?->format('d/m/Y') }}</span>
        </div>
    </div>

    {{-- ================================================================ --}}
    {{-- SECTION C: CHANZO CHA SAFARI                                      --}}
    {{-- ================================================================ --}}
    <div class="section">
        <div class="section-heading">C: Chanzo cha Safari (Kielezwe kwa Kina):</div>
        <p class="small">Ni nani aliyeanzisha safari? Je ni mtumishi? Je ni Serikali au Mwaliko mwingine? (Eleza kwa ufafanuzi chanzo cha safari utakacho jaza na ambatisha barua ya mwaliko)</p>
        <div class="dotted-lines" style="margin-top:4px;">
            @foreach (str_split(wordwrap($tr->c_travel_source ?? '', 110, "\n", true), 1) as $dummy)@endforeach
            @php $lines = $tr->c_travel_source ? explode("\n", wordwrap($tr->c_travel_source, 110, "\n", true)) : ['', '', '']; while(count($lines) < 3) $lines[] = ''; @endphp
            @foreach ($lines as $line)
            <div class="dotted-line">{{ $line }}</div>
            @endforeach
        </div>
    </div>

    {{-- ================================================================ --}}
    {{-- SECTION D: FAIDA YA SAFARI NA ATHARI                             --}}
    {{-- ================================================================ --}}
    <div class="section">
        <div class="section-heading">D: Faida ya Safari na Athari Zitakazokuwepo kama Safari Haikupitishwa.</div>

        <p><strong>(i)</strong> Nini Faida / Tija na Umuhimu wa safari husika kwa Taasisi na Taifa kwa ujumla:</p>

        <div class="ml-1" style="margin-top:4px;">
            <p><strong>(a)</strong> Tija na Umuhimu kwa Taasisi:</p>
            @php $lines = $tr->d_benefit_to_institution ? explode("\n", wordwrap($tr->d_benefit_to_institution, 100, "\n", true)) : ['','','','']; while(count($lines) < 4) $lines[] = ''; @endphp
            @foreach ($lines as $line)<div class="dotted-line ml-1">{{ $line }}</div>@endforeach

            <p style="margin-top:6px;"><strong>(b)</strong> Tija na Umuhimu kwa Taifa:</p>
            @php $lines = $tr->d_benefit_to_nation ? explode("\n", wordwrap($tr->d_benefit_to_nation, 100, "\n", true)) : ['','','','']; while(count($lines) < 4) $lines[] = ''; @endphp
            @foreach ($lines as $line)<div class="dotted-line ml-1">{{ $line }}</div>@endforeach
        </div>

        <p style="margin-top:6px;"><strong>(i)</strong> Athari zitakazokuwepo kama safari haikupitishwa:</p>
        @php $lines = $tr->d_consequences_if_rejected ? explode("\n", wordwrap($tr->d_consequences_if_rejected, 110, "\n", true)) : ['','','','']; while(count($lines) < 4) $lines[] = ''; @endphp
        @foreach ($lines as $line)<div class="dotted-line ml-1">{{ $line }}</div>@endforeach
    </div>

    {{-- ================================================================ --}}
    {{-- SECTION E: GHARAMA ZA SAFARI                                      --}}
    {{-- ================================================================ --}}
    <div class="section">
        <div class="section-heading">E: Gharama za Safari Zikianisha Yafuatayo:</div>

        <p><strong>(i)</strong> Gharama za Usafiri <strong>(Taja kiwango)</strong></p>
        @php $lines = $tr->e_transport_costs ? explode("\n", wordwrap($tr->e_transport_costs, 110, "\n", true)) : ['','']; while(count($lines) < 2) $lines[] = ''; @endphp
        @foreach ($lines as $line)<div class="dotted-line ml-1">{{ $line }}</div>@endforeach

        <p style="margin-top:6px;"><strong>(ii)</strong> Posho zote za safari husika: <strong>(Taja kiwango)</strong></p>
        <div class="ml-1">
            <p>a. Posho zote za safari husika: <strong>(Taja kiwango)</strong></p>
            @foreach (['a' => $tr->e_allowance_a, 'b' => $tr->e_allowance_b, 'c' => $tr->e_allowance_c, 'd' => $tr->e_allowance_d] as $letter => $val)
            <div class="field-row ml-1">
                <span class="field-num">({{ $letter }})</span>
                <span class="field-value">{{ $val }}</span>
            </div>
            @endforeach

            <div class="field-row" style="margin-top:4px;">
                <span style="flex-shrink:0; margin-right:4px;">b. Taja kifungu cha Safari kinachohusika na Safari inayotarajiwa</span>
                <span class="field-value">{{ $tr->e_budget_line }}</span>
            </div>
        </div>

        <p style="margin-top:6px;"><strong>(ii)</strong> Eleza mlipaji wa gharama za safari husika: <span class="small">(Kama zipo zinazogharamiwa na Serikali, eleza na utaje ni kiasi gani)</span></p>
        <div class="ml-1">
            <p>(a) Gharama zinazolipwa na Wafadhili: <strong>(Taja kiwango)</strong></p>
            @foreach (['i' => $tr->e_donor_cost_i, 'ii' => $tr->e_donor_cost_ii, 'iii' => $tr->e_donor_cost_iii] as $num => $val)
            <div class="field-row ml-1">
                <span class="field-num">({{ $num }})</span>
                <span class="field-value">{{ $val }}</span>
            </div>
            @endforeach

            <p style="margin-top:4px;">(b) Gharama zinazolipwa na Serikali kama zipo. <span class="small">(Taja kiwango)</span></p>
            @foreach (['i' => $tr->e_govt_cost_i, 'ii' => $tr->e_govt_cost_ii, 'iii' => $tr->e_govt_cost_iii] as $num => $val)
            <div class="field-row ml-1">
                <span class="field-num">({{ $num }})</span>
                <span class="field-value">{{ $val }}</span>
            </div>
            @endforeach
        </div>

        <p style="margin-top:6px;"><strong>(iv)</strong> Eleza kama kuna gharama zingine nje ya zilizotajwa hapo juu: <strong>(Taja kiwango).</strong></p>
        @php $lines = $tr->e_other_costs ? explode("\n", wordwrap($tr->e_other_costs, 110, "\n", true)) : ['','']; while(count($lines) < 2) $lines[] = ''; @endphp
        @foreach ($lines as $line)<div class="dotted-line ml-1">{{ $line }}</div>@endforeach
    </div>

    {{-- ================================================================ --}}
    {{-- SECTION F: MANUFAA YA SAFARI ZA NYUMA                             --}}
    {{-- ================================================================ --}}
    <div class="section">
        <div class="section-heading">F: Manufaa Yaliyopatikana kwa Taasisi na Taifa kwa Safari kama Hiyo kwa Kipindi cha Nyuma: (Impact Assessment of the Previous Travels).</div>
        @php $lines = $tr->f_previous_travel_impact ? explode("\n", wordwrap($tr->f_previous_travel_impact, 110, "\n", true)) : ['','','']; while(count($lines) < 3) $lines[] = ''; @endphp
        @foreach ($lines as $line)<div class="dotted-line">{{ $line }}</div>@endforeach

        <div class="sig-row" style="margin-top:10px;">
            <div class="sig-item">
                <span>Imewasilishwa kielektroniki na: <strong>{{ $tr->b_applicant_name }}</strong></span>
            </div>
            <div class="sig-item">
                <span>Tarehe: <strong>{{ $tr->submitted_at?->format('d/m/Y') ?? '—' }}</strong></span>
            </div>
        </div>
    </div>

    {{-- ================================================================ --}}
    {{-- SECTION G: HANDOVER NOTE                                          --}}
    {{-- ================================================================ --}}
    <div class="section">
        <div class="section-heading">G: Taja Jina la Afisa na Cheo cha Utakayemkaimisha Majukumu Yako. (Ambatisha Makubaliano ya Kukabidhiana Majuku (Handover Note))</div>
        @if ($tr->g_no_handover_officer)
        <div class="field-row">
            <span class="field-label">Tamko:</span>
            <span class="field-value">{{ __('travel.g_no_handover_checkbox', [], 'sw') }}</span>
        </div>
        <div class="field-row">
            <span class="field-label">Sababu:</span>
            <span class="field-value">{{ $tr->g_no_handover_declaration }}</span>
        </div>
        @else
        <div class="field-row">
            <span class="field-label">Jina:</span>
            <span class="field-value">{{ $tr->g_handover_officer_name }}</span>
        </div>
        <div class="field-row">
            <span class="field-label">Cheo:</span>
            <span class="field-value">{{ $tr->g_handover_officer_title }}</span>
        </div>
        @endif
    </div>

    {{-- ================================================================ --}}
    {{-- APPROVAL SECTIONS H / I / J / K                                   --}}
    {{-- ================================================================ --}}
    @php
        $sectionDefs = [
            'supervisor' => 'H: Maoni ya Mkurugenzi / Meneja / Mkuu wa Idara Kuhusu Tija na Umuhimu wa Safari kwa Taasisi na Taifa.',
            'director'   => 'I: Maoni ya Mkuu wa Idara / Kituo au Mkurugenzi Kuhusu Tija na Umuhimu wa Safari kwa Taasisi na Taifa.',
            'final'      => 'J: Kibali cha Kusafiri Ndani ya Tanzania (Itajazwa na Meneja wa Kituo au Mkurugenzi Mkuu).',
            'hr'         => 'K: Kwa Taarifa — Meneja Rasilimali Watu na Utawala / Afisa Rasilimali Watu / Afisa Utawala.',
        ];
    @endphp

    @foreach ($tr->approvalActions as $action)
    <div class="approval-section">
        <div class="section-heading" style="border-bottom: 1px solid #000; margin-bottom: 6px;">
            {{ $sectionDefs[$action->stage] ?? $action->stage }}
        </div>

        @if ($action->stage !== 'hr')
        <p><strong>(i) Tija na Umuhimu:</strong></p>
        @php $lines = $action->comment ? explode("\n", wordwrap($action->comment, 110, "\n", true)) : ['','','']; while(count($lines) < 3) $lines[] = ''; @endphp
        @foreach ($lines as $line)<div class="dotted-line">{{ $line }}</div>@endforeach
        @endif

        <div style="margin-top: 8px;">
            @if ($action->stage === 'final')
                <p>Maombi ya kusafiri ndani ya Tanzania kwa mtumishi alietajwa hapo juu,</p>
                <div class="approval-decision" style="{{ $action->decision === 'approved' ? 'color:#155724' : 'color:#721c24' }}">
                    {{ $action->decision === 'approved' ? 'YAMEKUBALIWA' : 'YAMEKATALIWA' }}
                </div>
            @else
                <span class="approval-decision" style="{{ $action->decision === 'approved' ? 'color:#155724' : 'color:#721c24' }}">
                    {{ $action->decision === 'approved' ? 'ARUHUSIWE' : 'ASIRUHUSIWE' }}
                </span>
            @endif
        </div>

        <div class="sig-row" style="margin-top:8px;">
            <div class="sig-item">
                <span>Jina: <strong>{{ $action->actor?->name }}</strong></span>
            </div>
            <div class="sig-item">
                <span>Cheo: <strong>{{ $action->actor?->job_title }}</strong></span>
            </div>
        </div>
        <div class="sig-row" style="margin-top:6px; align-items:center;">
            <div class="sig-item">
                <span>Tarehe: <strong>{{ $action->acted_at->format('d/m/Y') }}</strong></span>
            </div>
            @if (in_array($action->decision, ['approved', 'rejected'], true))
            <div class="sig-item">
                <img src="{{ asset('images/stamp-'.$action->decision.'.png') }}"
                     alt="{{ $action->decision }}" class="stamp">
            </div>
            @endif
        </div>
    </div>
    @endforeach

    {{-- Pending approval sections (blank) — not for a request that is already
         finished, where the remaining steps will never be taken. --}}
    @if ($tr->approval_chain && ! in_array($tr->status, ['rejected', 'cancelled'], true))
    @php
        $completedStages = $tr->approvalActions->pluck('stage')->toArray();
        $pendingSteps = collect($tr->approval_chain)->filter(fn($s) => !in_array($s['stage'], $completedStages));
    @endphp
    @foreach ($pendingSteps as $step)
    <div class="approval-section">
        <div class="section-heading" style="border-bottom: 1px solid #000; margin-bottom: 6px;">
            {{ $sectionDefs[$step['stage']] ?? $step['stage'] }}
        </div>
        @if ($step['stage'] !== 'hr')
        <p><strong>(i) Tija na Umuhimu:</strong></p>
        <div class="dotted-line"></div>
        <div class="dotted-line"></div>
        <div class="dotted-line"></div>
        <div style="margin-top:6px;">
            @if ($step['stage'] === 'final')
                <p>Maombi ya kusafiri ndani ya Tanzania kwa mtumishi alietajwa hapo juu,</p>
                <p><strong>YAMEKUBALIWA / YAMEKATALIWA</strong> ........................................</p>
            @else
                <strong>ARUHUSIWE / ASIRUHUSIWE</strong> ........................................
            @endif
        </div>
        @endif
        <p style="margin-top:8px; font-size:9pt;"><em>Inasubiri uamuzi.</em></p>
    </div>
    @endforeach
    @endif

    <div style="margin-top: 12px; text-align: center; font-size: 9pt; color: #555; border-top: 1px solid #ccc; padding-top: 6px;">
        Nambari ya Ombi: <strong>{{ $tr->request_number }}</strong> &nbsp;|&nbsp;
        Imetolewa: {{ now()->format('d/m/Y H:i') }} &nbsp;|&nbsp;
        Mfumo wa NIMR Internal Travel Permit
    </div>

</div>
</body>
</html>
