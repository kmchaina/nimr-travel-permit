<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Unit;
use App\Models\User;
use App\Services\SessionRevocationService;
use App\Services\SupervisorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(
        private SupervisorService $supervisors,
        private SessionRevocationService $sessions,
    ) {}

    public function index(Request $request): View
    {
        $admin = auth()->user();
        $q = $request->input('q', '');

        $query = $this->scopeUsersForAdmin(User::with(['unit', 'supervisor']), $admin);
        if ($q) {
            $query->where(fn ($s) => $s
                ->where('name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")
            );
        }
        $users = $query->orderBy('name')->paginate(20)->withQueryString();

        $allUsers = $this->scopeUsersForAdmin(User::select('is_active'), $admin)->get();
        $stats = [
            'total' => $allUsers->count(),
            'active' => $allUsers->where('is_active', true)->count(),
            'inactive' => $allUsers->where('is_active', false)->count(),
        ];

        return view('users.index', compact('users', 'stats'));
    }

    public function create(): View
    {
        $admin = auth()->user();
        $units = $this->unitsForAdmin($admin);
        $roles = $this->rolesForAdmin($admin);
        $supervisorOptions = $this->supervisorOptionsForAdmin($admin);

        return view('users.create', compact('units', 'roles', 'supervisorOptions'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'unit_id' => ['required', 'exists:units,id'],
            'phone' => ['nullable', 'string', 'max:50'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'role' => ['required', 'in:' . implode(',', $this->rolesForAdmin($request->user()))],
            'supervisor_id' => ['nullable', 'integer'],
            'is_active' => ['boolean'],
        ]);

        $this->authorizeUnitForAdmin($request->user(), (int) $validated['unit_id']);
        $unit = $this->validateRoleForUnit($validated['role'], (int) $validated['unit_id']);
        $this->guardAgainstSecondLead($validated['role'], $unit);

        $validated['supervisor_id'] = $this->validatedSupervisorId(
            $request->user(),
            $unit,
            $validated['role'],
            $validated['supervisor_id'] ?? null,
        );
        $validated['password'] = Hash::make(Str::random(32));
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['email_verified_at'] = now();

        $user = User::create($validated);

        ActivityLog::record('created', $user);

        Password::sendResetLink(['email' => $user->email]);

        return redirect()->route('users.index')->with('status', __('users.invited_success', ['name' => $user->name]));
    }

    public function edit(User $user): View
    {
        $this->authorizeManagedUser(auth()->user(), $user);

        $admin = auth()->user();
        $user->load(['unit', 'supervisor']);
        $units = $this->unitsForAdmin($admin);
        $roles = $this->rolesForAdmin($admin);
        $supervisorOptions = $this->supervisorOptionsForAdmin($admin, $user);

        return view('users.edit', compact('user', 'units', 'roles', 'supervisorOptions'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeManagedUser($request->user(), $user);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,' . $user->id],
            'password' => ['nullable', 'string', 'min:8'],
            'unit_id' => ['required', 'exists:units,id'],
            'phone' => ['nullable', 'string', 'max:50'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'role' => ['required', 'in:' . implode(',', $this->rolesForAdmin($request->user()))],
            'supervisor_id' => ['nullable', 'integer'],
            'is_active' => ['boolean'],
            'confirm_unit_type_change' => ['nullable', 'boolean'],
        ]);

        $this->authorizeUnitForAdmin($request->user(), (int) $validated['unit_id']);
        $unit = $this->validateRoleForUnit($validated['role'], (int) $validated['unit_id']);
        $this->guardAgainstSecondLead($validated['role'], $unit, $user);
        $this->guardAgainstCasualUnitTypeChange($user, $unit, $request->boolean('confirm_unit_type_change'));

        $validated['supervisor_id'] = $this->validatedSupervisorId(
            $request->user(),
            $unit,
            $validated['role'],
            $validated['supervisor_id'] ?? null,
            $user,
        );

        unset($validated['confirm_unit_type_change']);

        if (empty($validated['password'])) {
            unset($validated['password']);
        } else {
            $validated['password'] = Hash::make($validated['password']);
        }

        $newIsActive = $request->boolean('is_active');
        $validated['is_active'] = $newIsActive;

        if ($user->is_active && !$newIsActive) {
            $pendingCount = \App\Models\TravelRequest::where('current_approver_id', $user->id)
                ->where('status', 'pending')
                ->count();
            if ($pendingCount > 0) {
                return back()->withInput()->withErrors([
                    'is_active' => __('users.deactivate_pending_warning', ['count' => $pendingCount]),
                ]);
            }
        }

        $wasDeactivated = $user->is_active && ! $newIsActive;

        $before = $user->only(['name', 'email', 'role', 'unit_id', 'supervisor_id', 'is_active', 'job_title', 'phone']);
        $user->update($validated);
        $after = $user->fresh()->only(['name', 'email', 'role', 'unit_id', 'supervisor_id', 'is_active', 'job_title', 'phone']);

        // Their role, unit or active status may have just changed, which can
        // leave people reporting to somebody who cannot approve for them.
        $this->releaseInvalidSubordinates($user->fresh());

        // Deactivation must lock the account out now, not at next login.
        if ($wasDeactivated) {
            $this->sessions->revokeAllFor($user);
        }

        ActivityLog::record('updated', $user, ['before' => $before, 'after' => $after]);

        return redirect()->route('users.index')->with('status', 'Mtumiaji amesasishwa.');
    }

    private function rolesForAdmin(User $admin): array
    {
        if ($admin->isCentreSystemAdmin()) {
            return ['staff', 'supervisor', 'centre_manager', 'hr'];
        }

        return ['staff', 'head', 'manager', 'supervisor', 'director', 'centre_manager', 'director_general', 'hr', 'system_admin'];
    }

    /**
     * Which roles actually make sense for a unit of this type, per the NIMR
     * organogram — kept separate from rolesForAdmin() (who's allowed to assign
     * what) so a Research Centre never offers HQ-only roles like Head/Director,
     * and vice versa. A role must pass both checks to be accepted.
     */
    private function rolesForUnitType(string $unitType): array
    {
        return match ($unitType) {
            'research_centre' => ['staff', 'supervisor', 'centre_manager', 'hr', 'system_admin'],
            'hq_standalone'   => ['staff', 'manager', 'hr', 'system_admin', 'director_general'],
            'hq_directorate'  => ['director'],
            'hq_section'      => ['staff', 'head', 'manager', 'hr', 'system_admin'],
            default => [],
        };
    }

    /**
     * Roles that are a single post rather than a rank: a section has one lead,
     * a directorate one Director, a standalone unit one Manager, a centre one
     * Centre Manager. Nothing enforced this, so a section could be given three
     * Heads — and its staff would then be shown three people to choose between,
     * with no way to tell which one actually leads them.
     *
     * hq_section lists both head and manager because which one leads depends on
     * the directorate: scientific sections are led by a head, Corporate
     * Services sections by a manager. Either way there is one of them.
     */
    private function singularLeadRolesFor(string $unitType): array
    {
        return match ($unitType) {
            'hq_section'      => ['head', 'manager'],
            'hq_standalone'   => ['manager'],
            'hq_directorate'  => ['director'],
            'research_centre' => ['centre_manager'],
            default => [],
        };
    }

    /**
     * Refuse to appoint a second holder of a post that only one person holds.
     * Refusing rather than quietly demoting the incumbent: who replaces whom is
     * an administrative decision, not something to infer from a dropdown.
     */
    private function guardAgainstSecondLead(string $role, Unit $unit, ?User $user = null): void
    {
        $leadRoles = $this->singularLeadRolesFor($unit->type);

        if (! in_array($role, $leadRoles, true)) {
            return;
        }

        $incumbent = User::query()
            ->where('unit_id', $unit->id)
            ->whereIn('role', $leadRoles)
            ->where('is_active', true)
            ->when($user, fn ($query) => $query->whereKeyNot($user->getKey()))
            ->orderBy('name')
            ->first();

        if ($incumbent) {
            throw ValidationException::withMessages([
                'role' => __('users.lead_post_already_held', [
                    'name' => $incumbent->name,
                    'role' => __('common.role_'.$incumbent->role),
                    'unit' => $unit->name,
                ]),
            ]);
        }
    }

    /**
     * Cut loose anyone still reporting to a user who can no longer supervise
     * them — because their role changed, they moved unit, or they were
     * deactivated. Left alone, the stored supervisor_id keeps routing requests
     * to somebody with no authority to approve them: demoting a Head of Section
     * used to leave their staff with an ordinary colleague as step one of their
     * approval chain, and nothing anywhere said so.
     *
     * The link is cleared rather than guessed at, so each person picks again.
     */
    private function releaseInvalidSubordinates(User $supervisor): void
    {
        $subordinates = User::with('unit')->where('supervisor_id', $supervisor->getKey())->get();

        foreach ($subordinates as $subordinate) {
            if ($this->supervisors->isValidCandidate(
                $supervisor->getKey(),
                $subordinate->unit,
                $subordinate->role,
                $subordinate->getKey(),
            )) {
                continue;
            }

            $subordinate->forceFill(['supervisor_id' => null])->save();
        }
    }

    private function validateRoleForUnit(string $role, int $unitId): Unit
    {
        $unit = Unit::find($unitId);

        if (!$unit) {
            throw ValidationException::withMessages([
                'unit_id' => __('users.errors_title'),
            ]);
        }

        if (!in_array($role, $this->rolesForUnitType($unit->type), true)) {
            throw ValidationException::withMessages([
                'role' => __('users.role_not_valid_for_unit'),
            ]);
        }

        return $unit;
    }

    /**
     * Moving a user between a Research Centre and headquarters (or the other
     * way around) rebuilds their whole place in the approval chain — it should
     * never happen by accident. Require an explicit confirmation checkbox
     * whenever the unit's category actually changes.
     */
    private function guardAgainstCasualUnitTypeChange(User $user, Unit $newUnit, bool $confirmed): void
    {
        $previousUnit = $user->unit;

        if (!$previousUnit) {
            return;
        }

        $wasCentre = $previousUnit->isResearchCentre();
        $willBeCentre = $newUnit->isResearchCentre();

        if ($wasCentre !== $willBeCentre && !$confirmed) {
            throw ValidationException::withMessages([
                'unit_id' => __('users.unit_type_change_requires_confirmation'),
            ]);
        }
    }

    private function unitsForAdmin(User $admin)
    {
        if ($admin->isCentreSystemAdmin()) {
            return Unit::whereKey($admin->unit_id)->get();
        }

        return Unit::orderBy('type')->orderBy('name')->get();
    }

    private function supervisorOptionsForAdmin(User $admin, ?User $target = null)
    {
        $query = User::with('unit')
            ->where('is_active', true)
            ->whereIn('role', ['head', 'manager', 'director', 'supervisor', 'centre_manager']);

        $this->scopeUsersForAdmin($query, $admin);

        if ($target) {
            $query->where('id', '!=', $target->id);
        }

        $directorGeneral = User::with('unit')
            ->where('role', 'director_general')
            ->where('is_active', true)
            ->when($target, fn ($dgQuery) => $dgQuery->where('id', '!=', $target->id))
            ->get();

        return $query->orderBy('name')->get()
            ->concat($directorGeneral)
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

    private function validatedSupervisorId(
        User $admin,
        Unit $unit,
        string $role,
        mixed $supervisorId,
        ?User $target = null,
    ): ?int {
        $fixedSupervisor = $this->supervisors->fixedSupervisorForAssignment($unit, $role, $target?->id);

        if ($fixedSupervisor) {
            return $fixedSupervisor->id;
        }

        if ($this->supervisors->reportsDirectlyToDirectorGeneral($unit, $role)) {
            throw ValidationException::withMessages([
                'supervisor_id' => __('users.dg_supervisor_missing'),
            ]);
        }

        if ($this->supervisors->reportsDirectlyToDirectorate($unit, $role)) {
            throw ValidationException::withMessages([
                'supervisor_id' => __('users.director_supervisor_missing'),
            ]);
        }

        if ($this->supervisors->reportsDirectlyToCentreManager($unit, $role)) {
            throw ValidationException::withMessages([
                'supervisor_id' => __('users.centre_manager_supervisor_missing'),
            ]);
        }

        if (!$supervisorId) {
            return null;
        }

        $supervisorId = (int) $supervisorId;

        if (!$this->supervisors->isValidCandidate($supervisorId, $unit, $role, $target?->id)) {
            throw ValidationException::withMessages([
                'supervisor_id' => __('dashboard.supervisor_invalid'),
            ]);
        }

        $supervisor = User::find($supervisorId);
        if (!$supervisor) {
            throw ValidationException::withMessages([
                'supervisor_id' => __('dashboard.supervisor_invalid'),
            ]);
        }

        $this->authorizeManagedUser($admin, $supervisor);

        return $supervisorId;
    }

    private function scopeUsersForAdmin($query, User $admin)
    {
        if ($admin->isCentreSystemAdmin()) {
            $query->where('unit_id', $admin->unit_id)
                ->where('role', '!=', 'system_admin');
        }

        return $query;
    }

    private function authorizeManagedUser(User $admin, User $target): void
    {
        if ($admin->isCentreSystemAdmin() && (int) $target->unit_id !== (int) $admin->unit_id) {
            abort(403);
        }

        if ($admin->isCentreSystemAdmin() && $target->isSystemAdmin()) {
            abort(403);
        }
    }

    private function authorizeUnitForAdmin(User $admin, int $unitId): void
    {
        if ($admin->isCentreSystemAdmin() && (int) $unitId !== (int) $admin->unit_id) {
            abort(403);
        }
    }
}
