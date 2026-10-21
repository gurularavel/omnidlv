<?php

namespace App\Console\Commands;

use App\Enums\Channel;
use App\Enums\StatusCategory;
use App\Models\Company;
use App\Models\Hub;
use App\Models\Task;
use App\Models\User;
use App\Services\AssignmentService;
use App\Services\StatusMachine;
use App\Services\TaskService;
use App\Support\CurrentCompany;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Orders for the demo shop at /demo-shop/: what an online store would send through the
 * integration API, moved to different stages so every tracking state can be seen. The shop's
 * own order list (its "database") is written to public/demo-shop/orders.json.
 */
class DemoShopOrders extends Command
{
    protected $signature = 'demo:shop-orders {--company= : Company id (default: the demo company)} {--fresh : Replace earlier demo shop orders}';

    protected $description = 'Create demo shop orders in several delivery stages and the shop\'s order list';

    private const ORDERS = [
        ['no' => 'NM-10421', 'stage' => StatusCategory::Created, 'customer' => 'Aysel Məmmədova', 'phone' => '+994502224411', 'address' => 'Nizami küç. 45, Bakı',
            'products' => [['Simsiz qulaqlıq Pro', 1, 129.90], ['Qoruyucu qab', 1, 15.00]]],
        ['no' => 'NM-10418', 'stage' => StatusCategory::Assigned, 'customer' => 'Rauf Hüseynov', 'phone' => '+994553331207', 'address' => 'Azadlıq pr. 88, Bakı',
            'products' => [['Ağıllı saat S3', 1, 249.00]]],
        ['no' => 'NM-10409', 'stage' => StatusCategory::OnDelivery, 'customer' => 'Leyla Əliyeva', 'phone' => '+994707785590', 'address' => 'Füzuli küç. 12, Bakı',
            'products' => [['Qəhvə maşını Barista', 1, 389.00], ['Arabica dənəli qəhvə 1 kq', 2, 34.50]]],
        ['no' => 'NM-10377', 'stage' => StatusCategory::Completed, 'customer' => 'Tural Qasımov', 'phone' => '+994514406612', 'address' => 'Bülbül pr. 23, Bakı',
            'products' => [['Kətan yataq dəsti', 1, 119.00]]],
    ];

    public function handle(TaskService $tasks, StatusMachine $statuses, AssignmentService $assignments, CurrentCompany $tenant): int
    {
        $company = $this->option('company')
            ? Company::query()->findOrFail($this->option('company'))
            : User::query()->where('email', 'demo@omnidlv.test')->firstOrFail()->activeCompany;

        $list = $tenant->run($company, function () use ($tasks, $statuses, $assignments) {
            $hub = Hub::query()->orderBy('id')->firstOrFail();
            $courier = User::query()->couriers()->orderBy('id')->first();

            if ($this->option('fresh')) {
                Task::query()->whereJsonContains('tags', 'demo-shop')->get()->each->delete();
            }

            $list = [];
            foreach (self::ORDERS as $i => $order) {
                $task = Task::query()->where('integration_id', $order['no'])->first()
                    ?? $this->create($tasks, $hub, $order, $i);

                $waiting = in_array($task->status_category_slug, [StatusCategory::Created, StatusCategory::Pool], true);
                if ($waiting && $order['stage'] !== StatusCategory::Created && $courier) {
                    $assignments->assign($task, $courier, Channel::Api, ignoreCapacity: true);
                    foreach ([StatusCategory::InProgress, StatusCategory::OnDelivery, StatusCategory::Completed] as $step) {
                        if (array_search($step, StatusCategory::cases(), true) > array_search($order['stage'], StatusCategory::cases(), true)) {
                            break;
                        }
                        $statuses->toCategory($task->refresh(), $step, Channel::Courier, $courier);
                    }
                }

                $task->refresh();
                $list[] = [
                    'order_number' => $order['no'],
                    'placed_at' => $task->created_at->toIso8601String(),
                    'customer' => $order['customer'],
                    'phone_last4' => substr($order['phone'], -4),
                    'address' => $order['address'],
                    'tracking_code' => $task->tracking_code,
                    'total' => round(array_sum(array_map(fn ($p) => $p[1] * $p[2], $order['products'])), 2),
                    'items' => array_map(fn ($p) => ['name' => $p[0], 'quantity' => $p[1], 'price' => $p[2]], $order['products']),
                ];
            }

            return $list;
        });

        File::ensureDirectoryExists(public_path('demo-shop'));
        File::put(public_path('demo-shop/orders.json'), json_encode(['orders' => $list], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $this->table(['Order', 'Tracking code', 'Phone (last 4)'], array_map(fn ($o) => [$o['order_number'], $o['tracking_code'], $o['phone_last4']], $list));
        $this->info('Open '.url('/demo-shop/'));

        return self::SUCCESS;
    }

    private function create(TaskService $tasks, Hub $hub, array $order, int $i): Task
    {
        $points = [[40.3793, 49.8467], [40.3946, 49.8443], [40.3773, 49.8389], [40.4005, 49.8670]];

        return $tasks->create([
            'hub_id' => $hub->id,
            'integration_id' => $order['no'],
            'action_slug' => 'drop_off',
            'starts_at' => now()->addMinutes(30)->toIso8601String(),
            'ends_at' => now()->addHours(3)->toIso8601String(),
            'tags' => ['demo-shop'],
            'payment_type' => 'prepaid',
            'destination' => [
                'address' => $order['address'],
                'name' => $order['customer'],
                'phone' => $order['phone'],
                'lat' => $points[$i][0],
                'lng' => $points[$i][1],
            ],
            // Orders wait in the pool until the stage below moves them on.
            'assignment' => ['type' => 'pool'],
            'items' => array_map(fn ($p, $k) => [
                'integration_id' => $order['no'].'-'.($k + 1), 'name' => $p[0], 'quantity' => $p[1], 'amount' => $p[2], 'currency' => 'AZN',
            ], $order['products'], array_keys($order['products'])),
        ], Channel::Api);
    }
}
