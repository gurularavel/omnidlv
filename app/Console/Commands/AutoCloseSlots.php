<?php

namespace App\Console\Commands;

use App\Enums\Channel;
use App\Enums\StatusCategory;
use App\Exceptions\DomainException;
use App\Models\Company;
use App\Models\Hub;
use App\Models\Task;
use App\Services\Planning\SlotService;
use App\Support\CurrentCompany;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Closes slots whose tasks asked for it: slot_auto_closing_time is the number of minutes
 * before the slot starts at which planning runs. Runs every minute.
 */
class AutoCloseSlots extends Command
{
    protected $signature = 'slots:auto-close';

    protected $description = 'Plan delivery slots that reached their auto-closing time';

    public function handle(SlotService $slots, CurrentCompany $tenant): int
    {
        $due = Task::query()
            ->whereNotNull('slot')
            ->whereNull('plan_id')
            ->whereNull('courier_id')
            ->whereNotNull('slot_auto_closing_time')
            ->whereIn('status_category_slug', [StatusCategory::Created->value, StatusCategory::Pool->value])
            ->where('slot_starts_at', '<=', DB::raw("now() + (slot_auto_closing_time * interval '1 minute')"))
            ->select('company_id', 'hub_id', 'slot')
            ->distinct()
            ->get();

        foreach ($due->groupBy('company_id') as $companyId => $rows) {
            $company = Company::query()->find($companyId);

            $tenant->run($company, function () use ($rows, $slots) {
                foreach ($rows as $row) {
                    $options = Task::query()->where('hub_id', $row->hub_id)->where('slot', $row->slot)->value('slot_options') ?? [];
                    $options = is_string($options) ? json_decode($options, true) : $options;

                    try {
                        $result = $slots->close($row->slot, Hub::query()->findOrFail($row->hub_id), [
                            'tour' => $options['tour'] ?? true,
                            'dispatch_early' => $options['dispatch_early'] ?? false,
                            'from_hub' => $options['from_hub'] ?? true,
                        ], Channel::System);

                        $this->info("Slot {$row->slot} (hub {$row->hub_id}): ".count($result['plans']).' plan(s), '.count($result['unassigned']).' unassigned.');
                    } catch (DomainException $e) {
                        // No courier on shift yet: the next run tries again.
                        $this->warn("Slot {$row->slot} (hub {$row->hub_id}): {$e->getMessage()}");
                    }
                }
            });
        }

        return self::SUCCESS;
    }
}
