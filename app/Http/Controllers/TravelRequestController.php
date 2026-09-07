<?php

namespace App\Http\Controllers;

use App\Exceptions\StaleRequestException;
use App\Models\TravelRequest;
use App\Models\User;
use App\Notifications\TravelRequestHrCopyNotification;
use App\Notifications\TravelRequestHandoverNotification;
use App\Notifications\TravelRequestSubmittedNotification;
use App\Services\ApprovalChainService;
use App\Services\SupervisorService;
use App\Services\TravelDaysService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TravelRequestController extends Controller
{
    public function __construct(
        private ApprovalChainService $chainService,
        private SupervisorService $supervisors,
    ) {}

    public function index(Request $request): View
    {
        $user = auth()->user();
        $query = TravelRequest::with(['requester', 'unit', 'currentApprover']);

        if ($user->isHr()) {
            if ($user->unit?->type === 'research_centre') {
                $query->where('unit_id', $user->unit_id);
            }
        } elseif ($user->isDirectorGeneral()) {
            // DG sees: pending requests at their stage + all resolved/returned requests.
            // They do NOT see drafts or requests still pending at a lower stage.
            $query->where(function ($q) use ($user) {
                $q->where(function ($inner) use ($user) {
                    $inner->where('status', TravelRequest::STATUS_PENDING)
                        ->where('current_approver_id', $user->id);
                })->orWhereIn('status', [
                    TravelRequest::STATUS_APPROVED,
                    TravelRequest::STATUS_REJECTED,
                    TravelRequest::STATUS_RETURNED,
                    TravelRequest::STATUS_CANCELLED,
                ]);
            });
        } else {
            $query->where('requester_id', $user->id);
        }

        // Clone base query for status counts (before search/status filters)
        $baseQuery = clone $query;

        // Search
        if ($search = $request->get('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('b_applicant_name', 'like', "%{$search}%")
                    ->orWhere('b_destination', 'like', "%{$search}%")
                    ->orWhere('request_number', 'like', "%{$search}%");
            });
            $baseQuery->where(function ($q) use ($search) {
                $q->where('b_applicant_name', 'like', "%{$search}%")
                    ->orWhere('b_destination', 'like', "%{$search}%")
                    ->orWhere('request_number', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($status = $request->get('status')) {
            if (in_array($status, TravelRequest::STATUSES)) {
                $query->where('status', $status);
            }
        }

        $requests = $query->latest()->paginate(15)->withQueryString();
        $statusCounts = $baseQuery->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        return view('travel-requests.index', compact('requests', 'user', 'statusCounts'));
    }

    public function create(): View|RedirectResponse
    {
        $user = auth()->user();

        if ($blocker = $this->blockingOpenRequest($user)) {
            return redirect()->route('travel-requests.show', $blocker)
                ->with('error', $this->blockedMessageFor($blocker));
        }

        if ($this->missingSupervisor($user)) {
            return redirect()->route('dashboard')
                ->with('status', __('dashboard.supervisor_required_to_submit'));
        }

        $handoverUsers = $this->handoverUserList($user);
        $travelDays = $this->travelDaysSummary($user);

        return view('travel-requests.create', compact('user', 'handoverUsers', 'travelDays'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $isDraft = $request->input('action') === 'draft';

        // Blocked from *submitting*, not from saving. A form already open when
        // the block arose can still be parked as a draft rather than losing the
        // typing; it just cannot enter the approval chain.
        if (! $isDraft && ($blocker = $this->blockingOpenRequest($user))) {
            return redirect()->route('travel-requests.show', $blocker)
                ->with('error', $this->blockedMessageFor($blocker));
        }

        $validated = $this->validateForm($request, withFile: true, isDraft: $isDraft);

        if (! $isDraft) {
            $this->checkAtLeastOneCost($validated);
        }

        if (! $isDraft && $this->missingSupervisor($user)) {
            return redirect()->route('dashboard')
                ->with('status', __('dashboard.supervisor_required_to_submit'));
        }

        $chain = null;
        $currentApproverId = null;
        $status = TravelRequest::STATUS_DRAFT;
        $submittedAt = null;

        if (! $isDraft) {
            try {
                $chain = $this->chainService->buildChain($user);
                $currentApproverId = $chain[0]['approver_id'];
                $status = TravelRequest::STATUS_PENDING;
                $submittedAt = now();
            } catch (\RuntimeException $e) {
                return back()->withInput()->withErrors(['submit' => $e->getMessage()]);
            }
        }

        $documentPath = null;
        if ($request->hasFile('g_handover_document')) {
            $documentPath = $request->file('g_handover_document')->store('handover-documents', 'private');
        }

        $invitation = $this->storeInvitationLetter($request);

        // Deliberately not wrapped in a transaction: this is a single insert,
        // and createWithRequestNumber retries after a losing race for a permit
        // number — which a surrounding aborted transaction would prevent.
        $travelRequest = $this->createWithRequestNumber([
            ...$validated,
            ...$this->travellerIdentity($user),
            ...$this->handoverIdentity($request, $user),
            ...$invitation,
            'g_handover_document' => $documentPath,
            'requester_id' => $user->id,
            'unit_id' => $user->unit_id,
            'status' => $status,
            'approval_chain' => $chain,
            'current_approver_id' => $currentApproverId,
            'submitted_at' => $submittedAt,
        ]);

        if (! $isDraft && $chain) {
            $this->notifyFirstApprover($travelRequest);
            $this->notifyHandoverOfficer($travelRequest, TravelRequestHandoverNotification::STAGE_SUBMITTED);
        }

        // Persist phone to user profile if provided and differs from saved value
        if (! empty($validated['b_phone']) && $user->phone !== $validated['b_phone']) {
            $user->forceFill(['phone' => $validated['b_phone']])->save();
        }

        $message = $isDraft ? 'Ombi limehifadhiwa kama rasimu.' : 'Ombi limewasilishwa kwa mafanikio.';
        $redirect = redirect()->route('travel-requests.show', $travelRequest)->with('status', $message);

        if (! $isDraft) {
            $overlap = $this->findOverlappingRequest($user->id, $validated['b_departure_date'], $validated['b_return_date'], $travelRequest->id);
            if ($overlap) {
                $redirect->with('overlap_warning', $overlap->request_number);
            }
        }

        return $redirect;
    }

    public function show(TravelRequest $travelRequest): View
    {
        $this->authorize('view', $travelRequest);
        $travelRequest->load(['requester', 'unit', 'currentApprover', 'approvalActions.actor']);

        // Preload all approvers from the chain to avoid N+1 queries in the view
        $chainApprovers = collect();
        if ($travelRequest->approval_chain) {
            $ids = collect($travelRequest->approval_chain)->pluck('approver_id')->filter()->unique();
            $chainApprovers = User::whereIn('id', $ids)->get()->keyBy('id');
        }

        // Days this traveller has accumulated in the financial year the trip
        // falls in — shown to the traveller and to whoever is reviewing it.
        $travelDays = $this->travelDaysSummary($travelRequest->requester, $travelRequest);

        // Drives the wording of the "Return for Revision" modal, which differs
        // depending on whether the request steps back to the applicant or to
        // the approver below the current one.
        $returnGoesToApplicant = $this->chainService->returnGoesToRequester($travelRequest);

        return view('travel-requests.show', compact(
            'travelRequest',
            'chainApprovers',
            'travelDays',
            'returnGoesToApplicant',
        ));
    }

    /**
     * Accumulated days out of office for a traveller, optionally projected to
     * include a specific trip. Returns null when it cannot be computed.
     *
     * The 60-day ceiling is advisory: this only ever produces a warning.
     */
    private function travelDaysSummary(?User $user, ?TravelRequest $travelRequest = null): ?array
    {
        if (! $user) {
            return null;
        }

        $service = app(TravelDaysService::class);

        $financialYear = $travelRequest?->b_departure_date
            ? $service->financialYearFor($travelRequest->b_departure_date)
            : $service->financialYearFor();

        $accumulated = $service->accumulatedDaysFor($user, $financialYear);
        $projected = $travelRequest
            ? $service->projectedDaysWith($travelRequest, $financialYear)
            : $accumulated;

        return [
            'financial_year' => $financialYear,
            'financial_year_label' => $service->label($financialYear),
            'limit' => TravelDaysService::ANNUAL_LIMIT,
            'accumulated' => $accumulated,
            'projected' => $projected,
            'remaining' => $service->remaining($accumulated),
            'over_limit' => $service->isOverLimit($accumulated),
            'projected_over_limit' => $service->isOverLimit($projected),
        ];
    }

    public function edit(TravelRequest $travelRequest): View
    {
        $this->authorize('update', $travelRequest);
        $user = auth()->user();
        $handoverUsers = $this->handoverUserList($user);

        return view('travel-requests.edit', compact('travelRequest', 'user', 'handoverUsers'));
    }

    public function update(Request $request, TravelRequest $travelRequest): RedirectResponse
    {
        $this->authorize('update', $travelRequest);

        $isDraft = $request->input('action') === 'draft';

        if (! $isDraft && ($blocker = $this->blockingOpenRequest($request->user(), $travelRequest->id))) {
            return redirect()->route('travel-requests.show', $blocker)
                ->with('error', $this->blockedMessageFor($blocker));
        }

        // A document already on the record satisfies the upload requirement.
        $validated = $this->validateForm(
            $request,
            withFile: true,
            isDraft: $isDraft,
            fileRequired: ! $travelRequest->g_handover_document,
        );

        if (! $isDraft) {
            $this->checkAtLeastOneCost($validated);
        }

        if (! $isDraft && $this->missingSupervisor($request->user())) {
            return redirect()->route('dashboard')
                ->with('status', __('dashboard.supervisor_required_to_submit'));
        }

        // Store replacements before touching the database, and remember the old
        // paths so they are only deleted once the write has actually committed.
        $replacedDocuments = [];

        if ($request->hasFile('g_handover_document')) {
            $replacedDocuments[] = $travelRequest->g_handover_document;
            $validated['g_handover_document'] = $request->file('g_handover_document')
                ->store('handover-documents', 'private');
        } else {
            unset($validated['g_handover_document']);
        }

        if ($request->hasFile('c_invitation_document')) {
            $replacedDocuments[] = $travelRequest->c_invitation_document;
            $validated = [...$validated, ...$this->storeInvitationLetter($request)];
        } else {
            unset($validated['c_invitation_document']);
        }

        $user = $request->user();

        $handoverIdentity = $this->handoverIdentity($request, $user);

        try {
            DB::transaction(function () use ($travelRequest, $validated, $isDraft, $user, $handoverIdentity) {
                // Re-read under a row lock: a stale tab must not overwrite a
                // request that has since been submitted, cancelled or acted on.
                $fresh = TravelRequest::whereKey($travelRequest->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $fresh->isEditable()) {
                    throw new StaleRequestException(__('travel.no_longer_editable'));
                }

                $payload = [
                    ...$validated,
                    ...$this->travellerIdentity($user),
                    ...$handoverIdentity,
                ];

                if ($isDraft) {
                    $fresh->update([...$payload, 'status' => TravelRequest::STATUS_DRAFT]);

                    return;
                }

                // If resubmitting a returned request, resume from the approver who
                // returned it rather than restarting the whole chain from step 1.
                if ($fresh->status === TravelRequest::STATUS_RETURNED && $fresh->approval_chain) {
                    $chain = $fresh->approval_chain;
                    $returnAction = $fresh->approvalActions()
                        ->where('decision', 'returned')
                        ->latest('acted_at')
                        ->first();
                    $resumeIndex = $returnAction
                        ? collect($chain)->search(fn ($step) => (int) $step['approver_id'] === (int) $returnAction->actor_id)
                        : false;
                    $startApprover = $resumeIndex !== false ? $chain[$resumeIndex]['approver_id'] : $chain[0]['approver_id'];
                } else {
                    $chain = $this->chainService->buildChain($user);
                    $startApprover = $chain[0]['approver_id'];
                    // The chain was built from the traveller's current unit, so
                    // the request must follow them there after a transfer.
                    $payload['unit_id'] = $user->unit_id;
                }

                $fresh->update([
                    ...$payload,
                    'status' => TravelRequest::STATUS_PENDING,
                    'approval_chain' => $chain,
                    'current_approver_id' => $startApprover,
                    'submitted_at' => now(),
                ]);
            });

            $travelRequest->refresh();
        } catch (StaleRequestException $e) {
            $this->discardUploads($validated['g_handover_document'] ?? null, $validated['c_invitation_document'] ?? null);

            return redirect()->route('travel-requests.show', $travelRequest)
                ->with('error', $e->getMessage());
        } catch (\RuntimeException $e) {
            $this->discardUploads($validated['g_handover_document'] ?? null, $validated['c_invitation_document'] ?? null);

            return back()->withInput()->withErrors(['submit' => $e->getMessage()]);
        }

        // Committed — the superseded documents can now safely go.
        $this->discardUploads(...$replacedDocuments);

        if (! $isDraft) {
            $this->notifyFirstApprover($travelRequest);
            $this->notifyHandoverOfficer($travelRequest, TravelRequestHandoverNotification::STAGE_SUBMITTED);
        }

        // Persist phone to user profile if provided and differs from saved value
        $updateUser = $request->user();
        if (! empty($validated['b_phone']) && $updateUser->phone !== $validated['b_phone']) {
            $updateUser->forceFill(['phone' => $validated['b_phone']])->save();
        }

        $message = $isDraft ? 'Rasimu imesasishwa.' : 'Ombi limewasilishwa kwa mafanikio.';
        $redirect = redirect()->route('travel-requests.show', $travelRequest)->with('status', $message);

        if (! $isDraft) {
            $overlap = $this->findOverlappingRequest($request->user()->id, $validated['b_departure_date'], $validated['b_return_date'], $travelRequest->id);
            if ($overlap) {
                $redirect->with('overlap_warning', $overlap->request_number);
            }
        }

        return $redirect;
    }

    public function cancel(Request $request, TravelRequest $travelRequest): RedirectResponse
    {
        $this->authorize('cancel', $travelRequest);

        try {
            DB::transaction(function () use ($travelRequest) {
                // Re-check under a row lock so a cancellation cannot race an
                // approval that is being recorded at the same moment.
                $fresh = TravelRequest::whereKey($travelRequest->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $fresh->isCancellable()) {
                    throw new StaleRequestException(__('travel.no_longer_cancellable'));
                }

                $fresh->update([
                    'status' => TravelRequest::STATUS_CANCELLED,
                    'current_approver_id' => null,
                ]);
            });
        } catch (StaleRequestException $e) {
            return redirect()->route('travel-requests.show', $travelRequest)
                ->with('error', $e->getMessage());
        }

        $travelRequest->refresh();

        return redirect()->route('travel-requests.show', $travelRequest)
            ->with('status', 'Ombi limefutwa.');
    }

    /**
     * Remove stored documents, tolerating already-missing or null paths.
     */
    protected function discardUploads(?string ...$paths): void
    {
        foreach (array_filter($paths) as $path) {
            Storage::disk('private')->delete($path);
        }
    }

    /**
     * Persist the optional Section C invitation letter, keeping the original
     * filename so it downloads as something the applicant recognises.
     *
     * @return array<string, string>
     */
    protected function storeInvitationLetter(Request $request): array
    {
        if (! $request->hasFile('c_invitation_document')) {
            return [];
        }

        $file = $request->file('c_invitation_document');

        return [
            'c_invitation_document' => $file->store('invitation-letters', 'private'),
            'c_invitation_original_name' => $file->getClientOriginalName(),
        ];
    }

    public function downloadInvitation(TravelRequest $travelRequest)
    {
        $this->authorize('download', $travelRequest);

        abort_unless($travelRequest->c_invitation_document, 404);
        abort_unless(Storage::disk('private')->exists($travelRequest->c_invitation_document), 404);

        return Storage::disk('private')->download(
            $travelRequest->c_invitation_document,
            $travelRequest->c_invitation_original_name ?: $travelRequest->request_number.'-invitation.pdf',
        );
    }

    public function download(TravelRequest $travelRequest)
    {
        $this->authorize('download', $travelRequest);

        abort_unless($travelRequest->g_handover_document, 404);
        abort_unless(Storage::disk('private')->exists($travelRequest->g_handover_document), 404);

        return Storage::disk('private')->download($travelRequest->g_handover_document);
    }

    public function uploadReport(Request $request, TravelRequest $travelRequest): RedirectResponse
    {
        $this->authorize('uploadReport', $travelRequest);

        $validated = $request->validate([
            'travel_report_document' => ['required', 'file', 'mimes:pdf', 'max:5120'],
            'travel_report_notes' => ['nullable', 'string', 'max:5000'],
            'report_submission_confirmed' => ['accepted'],
        ]);

        unset($validated['report_submission_confirmed']);

        $file = $request->file('travel_report_document');
        $newPath = $file->store('travel-reports', 'private');

        if (! is_string($newPath) || $newPath === '') {
            throw ValidationException::withMessages([
                'travel_report_document' => __('travel.report_upload_failed'),
            ]);
        }

        $oldPath = null;

        try {
            DB::transaction(function () use ($travelRequest, $validated, $file, $newPath, &$oldPath): void {
                $lockedRequest = TravelRequest::query()->lockForUpdate()->findOrFail($travelRequest->id);
                $this->authorize('uploadReport', $lockedRequest);

                $oldPath = $lockedRequest->travel_report_document;
                $lockedRequest->update([
                    ...$validated,
                    'travel_report_document' => $newPath,
                    'travel_report_original_name' => $file->getClientOriginalName(),
                    'travel_report_submitted_at' => now(),
                ]);
            });
        } catch (\Throwable $e) {
            Storage::disk('private')->delete($newPath);
            throw $e;
        }

        if ($oldPath && $oldPath !== $newPath) {
            Storage::disk('private')->delete($oldPath);
        }

        return redirect()->route('travel-requests.show', $travelRequest)
            ->with('status', __('travel.report_saved'));
    }

    public function unlockReport(TravelRequest $travelRequest): RedirectResponse
    {
        $this->authorize('unlockReport', $travelRequest);

        $travelRequest->update(['travel_report_submitted_at' => null]);

        return redirect()->route('travel-requests.show', $travelRequest)
            ->with('status', __('travel.report_unlocked'));
    }

    public function downloadReport(TravelRequest $travelRequest)
    {
        $this->authorize('downloadReport', $travelRequest);

        abort_unless($travelRequest->travel_report_document, 404);
        abort_unless(Storage::disk('private')->exists($travelRequest->travel_report_document), 404);

        $filename = $travelRequest->travel_report_original_name
            ?: $travelRequest->request_number.'-travel-report.pdf';

        return Storage::disk('private')->download($travelRequest->travel_report_document, $filename);
    }

    public function print(TravelRequest $travelRequest): View
    {
        $this->authorize('view', $travelRequest);
        $travelRequest->load(['requester', 'unit', 'currentApprover', 'approvalActions.actor']);

        return view('travel-requests.print', compact('travelRequest'));
    }

    public function pdf(TravelRequest $travelRequest)
    {
        $this->authorize('view', $travelRequest);
        $travelRequest->load(['requester', 'unit', 'currentApprover', 'approvalActions.actor']);

        $pdf = Pdf::loadView('travel-requests.pdf', compact('travelRequest'))
            ->setPaper('a4', 'portrait');

        $filename = $travelRequest->request_number.'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Identity of the traveller as printed on the permit. Never taken from the
     * request body — the form shows these fields read-only, and a forged post
     * would otherwise put someone else's name on an approved permit.
     *
     * @return array<string, string|null>
     */
    protected function travellerIdentity(User $traveller): array
    {
        return [
            'b_applicant_name' => $traveller->name,
            'b_email' => $traveller->email,
            'b_position' => $traveller->job_title,
        ];
    }

    protected function validateForm(
        Request $request,
        bool $withFile = true,
        bool $isDraft = false,
        bool $fileRequired = true,
    ): array {
        $req = $isDraft ? 'nullable' : 'required';
        $noHandover = $request->boolean('g_no_handover_officer');

        // b_applicant_name / b_email / b_position are deliberately absent: the
        // traveller's identity is printed on an official permit and is filled
        // server-side from the authenticated account by travellerIdentity().
        $rules = [
            'b_phone' => ['required', 'string', 'max:50'],
            'b_destination' => ['required', 'string', 'max:500'],
            'b_departure_date' => ['required', 'date', 'after:today'],
            'b_return_date' => ['required', 'date', 'after:b_departure_date'],
            'c_travel_source' => ['nullable', 'string'],
            'd_benefit_to_institution' => [$req, 'string'],
            'd_benefit_to_nation' => [$req, 'string'],
            'd_consequences_if_rejected' => [$req, 'string'],
            'e_transport_costs' => ['nullable', 'string'],
            'e_allowance_a' => ['nullable', 'string', 'max:500'],
            'e_allowance_b' => ['nullable', 'string', 'max:500'],
            'e_allowance_c' => ['nullable', 'string', 'max:500'],
            'e_allowance_d' => ['nullable', 'string', 'max:500'],
            'e_budget_line' => ['nullable', 'string', 'max:500'],
            'e_donor_cost_i' => ['nullable', 'string', 'max:500'],
            'e_donor_cost_ii' => ['nullable', 'string', 'max:500'],
            'e_donor_cost_iii' => ['nullable', 'string', 'max:500'],
            'e_govt_cost_i' => ['nullable', 'string', 'max:500'],
            'e_govt_cost_ii' => ['nullable', 'string', 'max:500'],
            'e_govt_cost_iii' => ['nullable', 'string', 'max:500'],
            'e_other_costs' => ['nullable', 'string'],
            'f_previous_travel_impact' => [$req, 'string'],
            'f_traveller_signed_date' => ['nullable', 'date'],
            // Section G accepts one of two accounts of what happens to the
            // traveller's duties: a named officer with a signed note, or a
            // declaration that there is nobody to hand over to. The wizard
            // makes the second deliberate — a checkbox, a written declaration
            // and a confirmation dialog — but all of that is JavaScript, so
            // the requirement is re-decided here from the submitted values.
            'g_no_handover_officer' => ['nullable', 'boolean'],
            'g_no_handover_declaration' => $noHandover && ! $isDraft
                ? ['required', 'string', 'min:20', 'max:2000']
                : ['nullable', 'string', 'max:2000'],
            'g_handover_officer_name' => [$noHandover ? 'nullable' : $req, 'string', 'max:255'],
            'g_handover_officer_title' => ['nullable', 'string', 'max:255'],
            // Nullable, not required: permits saved before this column existed
            // carry a name and no id. handoverIdentity() does the real check —
            // that the id is an active colleague in the traveller's own unit.
            'g_handover_officer_id' => ['nullable', 'integer', 'exists:users,id'],
        ];

        if ($withFile) {
            $rules['g_handover_document'] = $isDraft || ! $fileRequired || $noHandover
                ? ['nullable', 'file', 'mimes:pdf', 'max:5120']
                : ['required', 'file', 'mimes:pdf', 'max:5120'];

            // Supporting evidence for Section C. Always optional — not every
            // trip stems from an invitation. Scans are common, so images too.
            $rules['c_invitation_document'] = ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'];
        }

        return $request->validate($rules);
    }

    private function checkAtLeastOneCost(array $validated): void
    {
        $costFields = [
            'e_transport_costs', 'e_allowance_a', 'e_allowance_b', 'e_allowance_c', 'e_allowance_d',
            'e_budget_line', 'e_donor_cost_i', 'e_donor_cost_ii', 'e_donor_cost_iii',
            'e_govt_cost_i', 'e_govt_cost_ii', 'e_govt_cost_iii', 'e_other_costs',
        ];

        $hasCost = collect($costFields)->some(fn ($f) => ! empty($validated[$f]));

        if (! $hasCost) {
            throw ValidationException::withMessages([
                'e_costs' => 'Please fill in at least one cost or allowance field.',
            ]);
        }
    }

    /**
     * Handover only makes sense to someone who can actually absorb the duties,
     * which means a colleague in the same unit — the list used to be every
     * active account in the institute, so a Mwanza technician could hand over
     * to a Dodoma finance officer. The traveller is excluded: nobody hands over
     * to themselves.
     */
    private function handoverUserList(User $traveller): Collection
    {
        if (! $traveller->unit_id) {
            return collect();
        }

        return User::where('is_active', true)
            ->where('unit_id', $traveller->unit_id)
            ->whereKeyNot($traveller->getKey())
            ->orderBy('name')
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'title' => $u->job_title ?? '',
            ]);
    }

    /**
     * The picker submits an id; the name and title are derived from it rather
     * than trusted from the request body, the same reason travellerIdentity()
     * never takes the applicant's own name from the form. Requests submitted
     * before the id column existed keep whatever name they were saved with.
     */
    private function handoverIdentity(Request $request, User $traveller): array
    {
        // Declaring there is nobody to hand over to and naming somebody are
        // mutually exclusive accounts. Clear the officer rather than leaving a
        // name from an earlier edit sitting beside the declaration.
        if ($request->boolean('g_no_handover_officer')) {
            return [
                'g_handover_officer_id' => null,
                'g_handover_officer_name' => null,
                'g_handover_officer_title' => null,
            ];
        }

        $officerId = $request->input('g_handover_officer_id');

        // Always return the key. Returning an empty array would let the raw id
        // from the validated request body survive the spread — 'exists:users,id'
        // only proves the account exists, not that it is a colleague the
        // traveller may hand over to.
        if (! $officerId) {
            return ['g_handover_officer_id' => null];
        }

        $officer = User::where('is_active', true)
            ->where('unit_id', $traveller->unit_id)
            ->whereKeyNot($traveller->getKey())
            ->find($officerId);

        if (! $officer) {
            return ['g_handover_officer_id' => null];
        }

        return [
            'g_handover_officer_id' => $officer->id,
            'g_handover_officer_name' => $officer->name,
            'g_handover_officer_title' => $officer->job_title ?? '',
        ];
    }

    private function missingSupervisor(User $user): bool
    {
        if ($this->supervisors->applyFixedSupervisor($user)) {
            return false;
        }

        return ! $user->supervisor_id && $this->supervisors->isRequiredFor($user);
    }

    private function findOverlappingRequest(int $userId, string $departure, string $return, int $excludeId): ?TravelRequest
    {
        return TravelRequest::where('requester_id', $userId)
            ->where('id', '!=', $excludeId)
            ->whereIn('status', [TravelRequest::STATUS_DRAFT, TravelRequest::STATUS_PENDING, TravelRequest::STATUS_APPROVED, TravelRequest::STATUS_RETURNED])
            ->where('b_departure_date', '<=', $return)
            ->where('b_return_date', '>=', $departure)
            ->first();
    }

    /**
     * One live request at a time.
     *
     * This used to block only on an *overdue* report — an approved trip whose
     * return date had already passed — which left the rule trivial to evade:
     * nothing stopped a traveller submitting ten future-dated requests at once,
     * because none of them were due yet, and by the time any report was owed
     * every trip had already been approved.
     *
     * A request therefore blocks the next one while it is still live:
     *   - pending, i.e. still somewhere in the approval chain; or
     *   - approved but not yet reported on.
     *
     * Drafts, rejected, returned and cancelled requests block nothing — none of
     * them is a granted or in-flight application.
     */
    private function blockingOpenRequest(User $user, ?int $excludeRequestId = null): ?TravelRequest
    {
        return TravelRequest::query()
            ->where('requester_id', $user->id)
            ->where(function ($query) {
                $query->where('status', TravelRequest::STATUS_PENDING)
                    ->orWhere(function ($approved) {
                        $approved->where('status', TravelRequest::STATUS_APPROVED)
                            ->whereNull('travel_report_submitted_at');
                    });
            })
            ->when($excludeRequestId, fn ($query) => $query->where('id', '!=', $excludeRequestId))
            ->oldest('b_departure_date')
            ->first();
    }

    /**
     * Why the traveller is blocked, so the flash message names the actual
     * obstacle rather than always claiming a report is outstanding.
     */
    private function blockedMessageFor(TravelRequest $blocker): string
    {
        return $blocker->status === TravelRequest::STATUS_PENDING
            ? __('travel.pending_request_before_new_request')
            : __('travel.report_required_before_new_request');
    }

    /**
     * Create the request, allocating the next permit number.
     *
     * The number is derived from the highest number already issued this year
     * rather than a row count, so a cancelled or rejected request no longer
     * causes the next submission to reuse a live number. A unique index on
     * request_number is the real guarantee: if two submissions race for the
     * same number, one insert loses and is retried with the next one.
     */
    protected function createWithRequestNumber(array $attributes): TravelRequest
    {
        $attempts = 0;

        while (true) {
            try {
                return TravelRequest::create([
                    ...$attributes,
                    'request_number' => $this->nextRequestNumber(),
                ]);
            } catch (UniqueConstraintViolationException $e) {
                if (++$attempts >= 5) {
                    throw $e;
                }
            }
        }
    }

    protected function nextRequestNumber(): string
    {
        $year = now()->year;
        $prefix = 'NIMR-ITP-'.$year.'-';

        $highest = TravelRequest::where('request_number', 'like', $prefix.'%')
            ->pluck('request_number')
            ->map(function (string $number) use ($prefix) {
                preg_match('/^\d+/', substr($number, strlen($prefix)), $matches);

                return (int) ($matches[0] ?? 0);
            })
            ->max();

        return $prefix.str_pad((string) (($highest ?? 0) + 1), 3, '0', STR_PAD_LEFT);
    }

    /**
     * Tell the handover officer they have been named. Failures are logged and
     * swallowed for the same reason the approver notification swallows them —
     * a mail outage must not roll back a submitted permit.
     */
    public function notifyHandoverOfficer(TravelRequest $travelRequest, string $stage): void
    {
        if (! $travelRequest->g_handover_officer_id) {
            return;
        }

        try {
            $officer = User::find($travelRequest->g_handover_officer_id);

            if ($officer && $officer->is_active) {
                $officer->notify(new TravelRequestHandoverNotification($travelRequest, $stage));
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to notify handover officer for request '.$travelRequest->request_number, [
                'officer_id' => $travelRequest->g_handover_officer_id,
                'stage' => $stage,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function notifyFirstApprover(TravelRequest $travelRequest): void
    {
        try {
            $firstApprover = User::find($travelRequest->current_approver_id);
            if ($firstApprover) {
                $firstApprover->notify(new TravelRequestSubmittedNotification($travelRequest));
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to notify first approver for request '.$travelRequest->request_number, [
                'approver_id' => $travelRequest->current_approver_id,
                'error' => $e->getMessage(),
            ]);
        }

        // Send HR an awareness copy; HR does not approve the request.
        try {
            $this->chainService
                ->hrCopyRecipients($travelRequest)
                ->each(fn ($hr) => $hr->notify(new TravelRequestHrCopyNotification($travelRequest, 'submitted')));
        } catch (\Throwable $e) {
            Log::warning('Failed to send HR copy on submission for request '.$travelRequest->request_number, [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
