<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PolicyState;
use App\Models\PolicyRequest;
use App\Services\Reconciliation\ReconciliationService;
use Illuminate\Console\Command;

class ReconcilePoliciesCommand extends Command
{
    protected $signature = 'policyflow:reconcile';

    protected $description = 'Reconcile policy requests stuck in PENDING_CONFIRMATION or NEEDS_RECONCILIATION';

    public function handle(ReconciliationService $reconciliation): int
    {
        $requests = PolicyRequest::query()
            ->whereIn('state', [
                PolicyState::PendingConfirmation->value,
                PolicyState::NeedsReconciliation->value,
            ])
            ->orderBy('id')
            ->get();

        $checked = 0;
        $resolved = 0;
        $pending = 0;
        $failed = 0;

        foreach ($requests as $request) {
            $checked++;
            $updated = $reconciliation->resolve($request, 'Artisan reconciliation');

            match ($updated->state) {
                PolicyState::Issued => $resolved++,
                PolicyState::Failed => $failed++,
                default => $pending++,
            };
        }

        $this->info("Checked: {$checked}");
        $this->info("Resolved (ISSUED): {$resolved}");
        $this->info("Still pending: {$pending}");
        $this->info("Failed: {$failed}");

        return self::SUCCESS;
    }
}
