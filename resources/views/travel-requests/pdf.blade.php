<!DOCTYPE html>
<html lang="sw">
<head>
<meta charset="utf-8">
<style>
@page { size: A4 portrait; margin: 15mm 16mm 16mm 16mm; }
* { box-sizing: border-box; margin: 0; padding: 0; }
html, body { margin: 0; padding: 0; }
body { font-family: DejaVu Serif, serif; font-size: 10pt; color: #000; background: #fff; }

/* Header */
.doc-header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 12px; }
.form-number { text-align: right; font-style: italic; font-size: 9pt; }
.org-name { font-size: 11pt; font-weight: bold; text-transform: uppercase; margin: 3px 0; }
.form-title { font-size: 10pt; font-weight: bold; text-transform: uppercase; }

/* Sections */
.section { margin-bottom: 12px; page-break-inside: avoid; }
.section-heading { font-weight: bold; text-transform: uppercase; font-size: 9.5pt; border-bottom: 1.5px solid #000; padding-bottom: 2px; margin-bottom: 7px; }

/* Field rows using table layout */
.field-row { width: 100%; margin-bottom: 5px; }
.field-row-num { display: inline; font-size: 10pt; }
.field-row-label { display: inline; font-size: 10pt; }
.field-table { width: 100%; border-collapse: collapse; margin-bottom: 5px; }
.field-table td { padding: 1px 2px; font-size: 10pt; vertical-align: bottom; }
.field-num-cell { width: 26px; white-space: nowrap; }
.field-label-cell { width: 200px; white-space: nowrap; }
.field-value-cell { border-bottom: 1px solid #000; }

/* Dotted lines */
.dotted-line { border-bottom: 1px dotted #555; margin-bottom: 4px; min-height: 15px; padding: 1px 2px; font-size: 10pt; }
.indent { margin-left: 14px; }
.indent2 { margin-left: 28px; }

/* Signature rows */
.sig-table { width: 100%; border-collapse: collapse; margin-top: 8px; }
.sig-table td { padding: 1px 4px; vertical-align: bottom; font-size: 10pt; }
.sig-line { border-bottom: 1px solid #000; min-width: 100px; display: inline-block; width: 120px; }
.sig-line-wide { width: 180px; }

/* Decision stamp — replaces the wet signature and rubber stamp lines */
.stamp { width: 92px; height: auto; }

/* Approval box */
.approval-box { border: 1px solid #000; padding: 7px 9px; margin-bottom: 9px; page-break-inside: avoid; }
.approval-decision { font-weight: bold; font-size: 11pt; }

/* Status colours */
.approved-text { color: #155724; }
.rejected-text { color: #721c24; }

/* Footer */
.footer { margin-top: 10px; text-align: center; font-size: 8.5pt; color: #555; border-top: 1px solid #ccc; padding-top: 5px; }

/* Page break helper */
.page-break { page-break-before: always; }
</style>
</head>
<body>

@php $tr = $travelRequest; @endphp

{{-- ── HEADER ── --}}
<div class="doc-header">
    <div class="form-number">Fomu Na: NIMR – ADM – F002</div>
    <div class="org-name">Taasisi ya Taifa ya Utafiti wa Magonjwa ya Binadamu</div>
    <div class="form-title">Fomu ya Maombi ya Ruhusa ya Kusafiri Ndani ya Nchi</div>
</div>

{{-- ── SECTION A ── --}}
<div class="section">
    <div class="section-heading">A: Utangulizi:</div>
    <p style="font-size:9pt;">(i) Watumishi wote wanaotarajia kusafiri ndani ya nchi wanapaswa kujaza kikamilifu fomu hii siku 14 kabla ya safari.</p>
    <p style="font-size:9pt;">(ii) Pamoja na fomu hii, viambatisho muhimu vinavyothibitisha safari lazima viwepo.</p>
    <p style="font-size:9pt;">(iii) Fomu hii itajazwa na kila Mtumishi wa Taasisi atakayesafiri kwa shughuli za kiofisi ndani ya nchi.</p>
    <p style="font-size:9pt;">(iv) Fomu ambayo haikujazwa kikamilifu haitashughulikiwa na hivyo Mtumishi atakuwa amejinyima ruhusa.</p>
    <p style="font-size:9pt;">(v) Ndani ya wiki mbili baada ya kurudi, Mtumishi atatoa ripoti ya safari kwa Mkurugenzi Mkuu.</p>
    <p style="font-size:9pt;">(vi) Safari za dharura zitatumia utaratibu wa dharura.</p>
</div>

{{-- ── SECTION B ── --}}
<div class="section">
    <div class="section-heading">B: Taarifa za Mtumishi Anayesafiri</div>
    <table class="field-table">
        <tr><td class="field-num-cell">(i)</td><td class="field-label-cell">Jina la Mtumishi anayesafiri</td><td class="field-value-cell">{{ $tr->b_applicant_name }}</td></tr>
        <tr><td class="field-num-cell">(ii)</td><td class="field-label-cell">Simu</td><td class="field-value-cell">{{ $tr->b_phone }}</td></tr>
        <tr><td class="field-num-cell">(iii)</td><td class="field-label-cell">Barua Pepe</td><td class="field-value-cell">{{ $tr->b_email }}</td></tr>
        <tr><td class="field-num-cell">(iv)</td><td class="field-label-cell">Cheo</td><td class="field-value-cell">{{ $tr->b_position }}</td></tr>
        <tr><td class="field-num-cell">(v)</td><td class="field-label-cell">Mkoa/Wilaya anapokwenda</td><td class="field-value-cell">{{ $tr->b_destination }}</td></tr>
        <tr><td class="field-num-cell">(vi)</td><td class="field-label-cell">Tarehe ya Kuondoka</td><td class="field-value-cell">{{ $tr->b_departure_date?->format('d/m/Y') }}</td></tr>
        <tr><td class="field-num-cell">(vii)</td><td class="field-label-cell">Tarehe ya Kurudi</td><td class="field-value-cell">{{ $tr->b_return_date?->format('d/m/Y') }}</td></tr>
    </table>
</div>

{{-- ── SECTION C ── --}}
<div class="section">
    <div class="section-heading">C: Chanzo cha Safari (Kielezwe kwa Kina):</div>
    @php $lines = $tr->c_travel_source ? explode("\n", wordwrap($tr->c_travel_source, 115, "\n", true)) : ['','','']; while(count($lines) < 3) $lines[] = ''; @endphp
    @foreach ($lines as $line)<div class="dotted-line">{{ $line }}</div>@endforeach
    <p style="font-size:9pt;margin-top:3px;">
        Barua ya mwaliko:
        <strong>{{ $tr->c_invitation_document ? ($tr->c_invitation_original_name ?: 'Imeambatishwa') : 'Haikuambatishwa' }}</strong>
    </p>
</div>

{{-- ── SECTION D ── --}}
<div class="section">
    <div class="section-heading">D: Faida ya Safari na Athari Zitakazokuwepo kama Safari Haikupitishwa.</div>
    <p><strong>(i)</strong> Nini Faida / Tija na Umuhimu wa safari husika kwa Taasisi na Taifa:</p>
    <p class="indent"><strong>(a)</strong> Tija na Umuhimu kwa Taasisi:</p>
    @php $lines = $tr->d_benefit_to_institution ? explode("\n", wordwrap($tr->d_benefit_to_institution, 105, "\n", true)) : ['','','']; while(count($lines) < 3) $lines[] = ''; @endphp
    @foreach ($lines as $line)<div class="dotted-line indent">{{ $line }}</div>@endforeach

    <p class="indent" style="margin-top:5px;"><strong>(b)</strong> Tija na Umuhimu kwa Taifa:</p>
    @php $lines = $tr->d_benefit_to_nation ? explode("\n", wordwrap($tr->d_benefit_to_nation, 105, "\n", true)) : ['','','']; while(count($lines) < 3) $lines[] = ''; @endphp
    @foreach ($lines as $line)<div class="dotted-line indent">{{ $line }}</div>@endforeach

    <p style="margin-top:5px;"><strong>(ii)</strong> Athari zitakazokuwepo kama safari haikupitishwa:</p>
    @php $lines = $tr->d_consequences_if_rejected ? explode("\n", wordwrap($tr->d_consequences_if_rejected, 115, "\n", true)) : ['','','']; while(count($lines) < 3) $lines[] = ''; @endphp
    @foreach ($lines as $line)<div class="dotted-line indent">{{ $line }}</div>@endforeach
</div>

{{-- ── SECTION E ── --}}
<div class="section">
    <div class="section-heading">E: Gharama za Safari Zikianisha Yafuatayo:</div>
    <p><strong>(i)</strong> Gharama za Usafiri <strong>(Taja kiwango)</strong></p>
    @php $lines = $tr->e_transport_costs ? explode("\n", wordwrap($tr->e_transport_costs, 115, "\n", true)) : ['','']; while(count($lines) < 2) $lines[] = ''; @endphp
    @foreach ($lines as $line)<div class="dotted-line indent">{{ $line }}</div>@endforeach

    <p style="margin-top:5px;"><strong>(ii)</strong> Posho za safari husika:</p>
    <table class="field-table indent" style="margin-top:3px;">
        @foreach (['a' => $tr->e_allowance_a, 'b' => $tr->e_allowance_b, 'c' => $tr->e_allowance_c, 'd' => $tr->e_allowance_d] as $ltr => $val)
        <tr><td class="field-num-cell">({{ $ltr }})</td><td class="field-value-cell">{{ $val }}</td></tr>
        @endforeach
    </table>

    <table class="field-table" style="margin-top:4px;">
        <tr><td style="white-space:nowrap;padding-right:6px;">Kifungu cha Safari:</td><td class="field-value-cell">{{ $tr->e_budget_line }}</td></tr>
    </table>

    <p style="margin-top:5px;"><strong>(iii)</strong> Mlipaji wa gharama za safari:</p>
    <p class="indent">(a) Gharama zinazolipwa na Wafadhili:</p>
    <table class="field-table indent2">
        @foreach (['i' => $tr->e_donor_cost_i, 'ii' => $tr->e_donor_cost_ii, 'iii' => $tr->e_donor_cost_iii] as $num => $val)
        <tr><td class="field-num-cell">({{ $num }})</td><td class="field-value-cell">{{ $val }}</td></tr>
        @endforeach
    </table>
    <p class="indent" style="margin-top:4px;">(b) Gharama zinazolipwa na Serikali:</p>
    <table class="field-table indent2">
        @foreach (['i' => $tr->e_govt_cost_i, 'ii' => $tr->e_govt_cost_ii, 'iii' => $tr->e_govt_cost_iii] as $num => $val)
        <tr><td class="field-num-cell">({{ $num }})</td><td class="field-value-cell">{{ $val }}</td></tr>
        @endforeach
    </table>

    <p style="margin-top:5px;"><strong>(iv)</strong> Gharama zingine nje ya zilizotajwa hapo juu:</p>
    @php $lines = $tr->e_other_costs ? explode("\n", wordwrap($tr->e_other_costs, 115, "\n", true)) : ['','']; while(count($lines) < 2) $lines[] = ''; @endphp
    @foreach ($lines as $line)<div class="dotted-line indent">{{ $line }}</div>@endforeach
</div>

{{-- ── SECTION F ── --}}
<div class="section">
    <div class="section-heading">F: Manufaa Yaliyopatikana kwa Safari kama Hiyo kwa Kipindi cha Nyuma.</div>
    @php $lines = $tr->f_previous_travel_impact ? explode("\n", wordwrap($tr->f_previous_travel_impact, 115, "\n", true)) : ['','','']; while(count($lines) < 3) $lines[] = ''; @endphp
    @foreach ($lines as $line)<div class="dotted-line">{{ $line }}</div>@endforeach

    <table class="sig-table" style="margin-top:8px;">
        <tr>
            <td>Imewasilishwa kielektroniki na: <strong>{{ $tr->b_applicant_name }}</strong></td>
            <td>Tarehe: <strong>{{ $tr->submitted_at?->format('d/m/Y') ?? '—' }}</strong></td>
        </tr>
    </table>
</div>

{{-- ── SECTION G ── --}}
<div class="section">
    <div class="section-heading">G: Jina la Afisa na Cheo cha Utakayemkaimisha Majukumu Yako.</div>
    @if ($tr->g_no_handover_officer)
    <table class="field-table">
        <tr><td class="field-label-cell">Tamko:</td><td class="field-value-cell">{{ __('travel.g_no_handover_checkbox', [], 'sw') }}</td></tr>
        <tr><td class="field-label-cell">Sababu:</td><td class="field-value-cell">{{ $tr->g_no_handover_declaration }}</td></tr>
    </table>
    @else
    <table class="field-table">
        <tr><td class="field-label-cell">Jina:</td><td class="field-value-cell">{{ $tr->g_handover_officer_name }}</td></tr>
        <tr><td class="field-label-cell">Cheo:</td><td class="field-value-cell">{{ $tr->g_handover_officer_title }}</td></tr>
    </table>
    @endif
</div>

{{-- ── APPROVAL SECTIONS ── --}}
@php
    $sectionDefs = [
        'supervisor' => 'H: Maoni ya Mkurugenzi / Meneja / Mkuu wa Idara.',
        'director'   => 'I: Maoni ya Mkurugenzi Kuhusu Tija na Umuhimu wa Safari.',
        'final'      => 'J: Kibali cha Kusafiri (Itajazwa na Meneja wa Kituo au Mkurugenzi Mkuu).',
    ];
@endphp

@foreach ($tr->approvalActions as $action)
@if ($action->stage === 'hr') @continue @endif
<div class="approval-box">
    <div class="section-heading" style="border-bottom:1px solid #000;margin-bottom:5px;">
        {{ $sectionDefs[$action->stage] ?? $action->stage }}
    </div>

    <p><strong>(i) Tija na Umuhimu:</strong></p>
    @php $lines = $action->comment ? explode("\n", wordwrap($action->comment, 115, "\n", true)) : ['','','']; while(count($lines) < 3) $lines[] = ''; @endphp
    @foreach ($lines as $line)<div class="dotted-line">{{ $line }}</div>@endforeach

    <div style="margin-top:7px;">
        @if ($action->stage === 'final')
            <p>Maombi ya kusafiri ndani ya Tanzania kwa mtumishi alietajwa hapo juu,</p>
            <p class="approval-decision {{ $action->decision === 'approved' ? 'approved-text' : 'rejected-text' }}">
                {{ $action->decision === 'approved' ? 'YAMEKUBALIWA' : 'YAMEKATALIWA' }}
            </p>
        @else
            <p class="approval-decision {{ $action->decision === 'approved' ? 'approved-text' : 'rejected-text' }}">
                {{ $action->decision === 'approved' ? 'ARUHUSIWE' : 'ASIRUHUSIWE' }}
            </p>
        @endif
    </div>

    <table class="sig-table" style="margin-top:7px;">
        <tr>
            <td style="width:62%;">
                Jina: <strong>{{ $action->actor?->name }}</strong><br>
                Cheo: <strong>{{ $action->actor?->job_title }}</strong><br>
                Tarehe: <strong>{{ $action->acted_at->format('d/m/Y') }}</strong>
            </td>
            <td style="width:38%;text-align:center;vertical-align:middle;">
                @if (in_array($action->decision, ['approved', 'rejected'], true))
                    <img src="{{ public_path('images/stamp-'.$action->decision.'.png') }}"
                         alt="{{ $action->decision }}" class="stamp">
                @endif
            </td>
        </tr>
    </table>
</div>
@endforeach

{{-- Pending blank approval sections — not for a request that is already
     finished, where the remaining steps will never be taken. --}}
@if ($tr->approval_chain && ! in_array($tr->status, ['rejected', 'cancelled'], true))
@php
    $completedStages = $tr->approvalActions->pluck('stage')->toArray();
    $pendingSteps    = collect($tr->approval_chain)->filter(fn($s) => !in_array($s['stage'], $completedStages) && $s['stage'] !== 'hr');
@endphp
@foreach ($pendingSteps as $step)
<div class="approval-box">
    <div class="section-heading" style="border-bottom:1px solid #000;margin-bottom:5px;">
        {{ $sectionDefs[$step['stage']] ?? $step['stage'] }}
    </div>
    <p><strong>(i) Tija na Umuhimu:</strong></p>
    <div class="dotted-line">&nbsp;</div>
    <div class="dotted-line">&nbsp;</div>
    <div class="dotted-line">&nbsp;</div>
    <div style="margin-top:6px;">
        @if ($step['stage'] === 'final')
        <p>Maombi ya kusafiri ndani ya Tanzania kwa mtumishi alietajwa hapo juu,</p>
        <p><strong>YAMEKUBALIWA / YAMEKATALIWA</strong> .......................................</p>
        @else
        <p><strong>ARUHUSIWE / ASIRUHUSIWE</strong> .......................................</p>
        @endif
    </div>
    <p style="font-size:9pt;margin-top:6px;"><em>Inasubiri uamuzi.</em></p>
</div>
@endforeach
@endif

<div class="footer">
    Nambari ya Ombi: <strong>{{ $tr->request_number }}</strong> &nbsp;|&nbsp;
    Imetolewa: {{ now()->format('d/m/Y H:i') }} &nbsp;|&nbsp;
    Mfumo wa NIMR Internal Travel Permit
</div>

</body>
</html>
