<?php

use Illuminate\Support\Facades\Schedule;

// Runs every morning, but a given request is only chased every 3 days: the
// command decides what is due, counting from when the request landed with its
// current approver. The requester is copied on each one.
Schedule::command('approvals:remind --days=3')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->runInBackground();

// Daily report reminder at 08:30: first reminder 14 days after return, then every 3 days
Schedule::command('travel-reports:remind')
    ->dailyAt('08:30')
    ->withoutOverlapping()
    ->runInBackground();
