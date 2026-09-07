<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TravelRequest extends Model
{
    use HasFactory;

    const STATUS_DRAFT = 'draft';

    const STATUS_PENDING = 'pending';

    const STATUS_APPROVED = 'approved';

    const STATUS_REJECTED = 'rejected';

    const STATUS_RETURNED = 'returned';

    const STATUS_CANCELLED = 'cancelled';

    const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_RETURNED,
        self::STATUS_CANCELLED,
    ];

    const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Rasimu',
        self::STATUS_PENDING => 'Inasubiri Idhini',
        self::STATUS_APPROVED => 'Imeidhinishwa',
        self::STATUS_REJECTED => 'Imekataliwa',
        self::STATUS_RETURNED => 'Imerudishwa kwa Marekebisho',
        self::STATUS_CANCELLED => 'Imefutwa',
    ];

    const STATUS_COLORS = [
        self::STATUS_DRAFT => 'bg-gray-100 text-gray-600',
        self::STATUS_PENDING => 'bg-amber-100 text-amber-700',
        self::STATUS_APPROVED => 'bg-green-100 text-green-700',
        self::STATUS_REJECTED => 'bg-red-100 text-red-700',
        self::STATUS_RETURNED => 'bg-orange-100 text-orange-700',
        self::STATUS_CANCELLED => 'bg-gray-100 text-gray-400',
    ];

    protected $fillable = [
        'request_number',
        'requester_id',
        'unit_id',
        'status',
        'current_approver_id',

        // Section B
        'b_applicant_name',
        'b_phone',
        'b_email',
        'b_position',
        'b_destination',
        'b_departure_date',
        'b_return_date',

        // Section C
        'c_travel_source',
        'c_invitation_document',
        'c_invitation_original_name',

        // Section D
        'd_benefit_to_institution',
        'd_benefit_to_nation',
        'd_consequences_if_rejected',

        // Section E
        'e_transport_costs',
        'e_allowance_a',
        'e_allowance_b',
        'e_allowance_c',
        'e_allowance_d',
        'e_budget_line',
        'e_donor_cost_i',
        'e_donor_cost_ii',
        'e_donor_cost_iii',
        'e_govt_cost_i',
        'e_govt_cost_ii',
        'e_govt_cost_iii',
        'e_other_costs',

        // Section F
        'f_previous_travel_impact',
        'f_traveller_signed_date',

        // Section G
        'g_handover_officer_name',
        'g_handover_officer_title',
        'g_handover_officer_id',
        'g_handover_document',
        'g_no_handover_officer',
        'g_no_handover_declaration',
        'travel_report_document',
        'travel_report_original_name',
        'travel_report_notes',
        'travel_report_submitted_at',
        'travel_report_last_reminded_at',
        'travel_report_reminder_count',

        'approval_chain',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'g_no_handover_officer' => 'boolean',
            'b_departure_date' => 'date',
            'b_return_date' => 'date',
            'f_traveller_signed_date' => 'date',
            'approval_chain' => 'array',
            'submitted_at' => 'datetime',
            'travel_report_submitted_at' => 'datetime',
            'travel_report_last_reminded_at' => 'datetime',
            'travel_report_reminder_count' => 'integer',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function currentApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_approver_id');
    }

    public function handoverOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'g_handover_officer_id');
    }

    public function approvalActions(): HasMany
    {
        return $this->hasMany(ApprovalAction::class)->orderBy('acted_at');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_RETURNED]);
    }

    public function isCancellable(): bool
    {
        if (! in_array($this->status, [self::STATUS_DRAFT, self::STATUS_PENDING, self::STATUS_RETURNED])) {
            return false;
        }

        return ! $this->approvalActions()
            ->where('decision', 'approved')
            ->exists();
    }

    /**
     * The trip is over — the return date has arrived or passed. A trip with no
     * return date on record cannot be shown to have ended, so it has not.
     */
    public function hasEnded(): bool
    {
        return $this->b_return_date !== null
            && $this->b_return_date->startOfDay()->lessThanOrEqualTo(now()->startOfDay());
    }

    public function isTravelReportLocked(): bool
    {
        return filled($this->travel_report_document)
            && $this->travel_report_submitted_at !== null;
    }

    public function statusLabel(): string
    {
        return __('common.status_'.$this->status, [], app()->getLocale())
            ?: (self::STATUS_LABELS[$this->status] ?? ucfirst($this->status));
    }

    public static function getStatusLabels(): array
    {
        return array_combine(self::STATUSES, array_map(
            fn ($s) => __('common.status_'.$s),
            self::STATUSES
        ));
    }

    public function statusColor(): string
    {
        return self::STATUS_COLORS[$this->status] ?? 'bg-gray-100 text-gray-500';
    }
}
