<?php

namespace App\Notifications;

use App\Models\TravelRequest;
use App\Models\User;
use App\Notifications\Concerns\BuildsTravelRequestMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The requester's copy of an approver reminder.
 *
 * Between submitting and a decision the traveller heard nothing at all, so a
 * request sitting untouched for weeks looked identical to one nobody had
 * chased. This says plainly that it is still waiting, who it is waiting on, and
 * that the approver has just been reminded — so the silence is visibly the
 * system working rather than the request having been forgotten.
 */
class TravelRequestStillPendingNotification extends Notification implements ShouldQueue
{
    use Queueable, BuildsTravelRequestMail;

    public int $tries = 5;

    public function backoff(): array
    {
        return [5, 15, 30, 60, 120];
    }

    public function __construct(
        public TravelRequest $travelRequest,
        public int $daysWaiting,
        public ?User $approver = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tr = $this->travelRequest;
        $with = $this->approver
            ? " It is with {$this->approver->name}, who has just been reminded."
            : ' The approver has just been reminded.';

        return $this->travelRequestMail(
            notifiable: $notifiable,
            travelRequest: $tr,
            subject: "Still awaiting approval - {$tr->request_number}",
            headline: 'Your travel request is still waiting',
            intro: "Your request {$tr->request_number} has been waiting {$this->daysWaiting} day(s) for approval.{$with} "
                 . 'You do not need to do anything — this is to let you know it has not been forgotten.',
            tone: 'amber',
            actionUrl: route('travel-requests.show', $tr->id),
            actionText: 'View request',
        );
    }
}
