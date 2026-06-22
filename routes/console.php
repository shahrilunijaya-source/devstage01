<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Withdraw expired delegations + their bindings hourly (PRD §6.3 ACL auto-expiry).
Schedule::command('acl:expire')->hourly();
