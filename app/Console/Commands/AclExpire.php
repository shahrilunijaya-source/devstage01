<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AccessControl\DelegationService;
use Illuminate\Console\Command;

/**
 * Sweeps expired delegations so their bindings reach an explicit revoked state
 * (PRD §6.3). Time-bound bindings already fall inactive via their window; this
 * makes the withdrawal durable and auditable. Schedule hourly.
 */
class AclExpire extends Command
{
    protected $signature = 'acl:expire';

    protected $description = 'Revoke delegations (and their bindings) past their end date';

    public function handle(DelegationService $delegations): int
    {
        $count = $delegations->expireDue();

        $this->info("Expired {$count} delegation(s).");

        return self::SUCCESS;
    }
}
