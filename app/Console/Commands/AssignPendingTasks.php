<?php

namespace App\Console\Commands;

use App\Enums\AssignmentType;
use App\Enums\StatusCategory;
use App\Models\Company;
use App\Models\Task;
use App\Services\Planning\AutoAssigner;
use App\Support\CurrentCompany;
use Illuminate\Console\Command;

/** Retries capacity-based tasks that found no courier when they arrived. Runs every minute. */
class AssignPendingTasks extends Command
{
    protected $signature = 'tasks:assign-pending {--hours=12 : Only tasks starting within this many hours}';

    protected $description = 'Assign waiting capacity_aware / capacity_unaware tasks to couriers who became free';

    public function handle(AutoAssigner $assigner, CurrentCompany $tenant): int
    {
        $assigned = 0;
        $pending = fn () => Task::query()
            ->whereIn('assignment_type', [AssignmentType::CapacityAware->value, AssignmentType::CapacityUnaware->value])
            ->where('status_category_slug', StatusCategory::Created->value)
            ->whereNull('courier_id')
            ->where('starts_at', '<=', now()->addHours((int) $this->option('hours')));

        $companyIds = $pending()->distinct()->pluck('company_id');

        foreach (Company::query()->whereKey($companyIds)->get() as $company) {
            $assigned += $tenant->run($company, function () use ($pending, $assigner) {
                $count = 0;
                foreach ($pending()->orderByDesc('priority')->orderBy('ends_at')->limit(500)->get() as $task) {
                    $count += $assigner->assign($task) ? 1 : 0;
                }

                return $count;
            });
        }

        $this->info("Assigned {$assigned} task(s).");

        return self::SUCCESS;
    }
}
