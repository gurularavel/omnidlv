<?php

namespace Database\Seeders;

use App\Enums\Channel;
use App\Enums\Role;
use App\Enums\StatusCategory;
use App\Models\Company;
use App\Models\CourierLocation;
use App\Models\Employment;
use App\Models\Status;
use App\Models\Task;
use App\Models\User;
use App\Services\AssignmentService;
use App\Services\CourierService;
use App\Services\HubService;
use App\Services\StatusMachine;
use App\Services\TaskService;
use App\Services\TeamService;
use App\Models\Hub;
use App\Models\Team;
use App\Support\CurrentCompany;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Synthetic demo company in Baku for local development and screenshots.
 * Sign in: demo@omnidlv.test / password. Not for production.
 */
class DemoSeeder extends Seeder
{
    private const ADDRESSES = [
        ['Nizami küç. 10', 40.3777, 49.8436], ['Ə. Rəcəbli küç. 44', 40.4046, 49.8789], ['Babək pr. 21', 40.3989, 49.8932],
        ['Neftçilər pr. 153', 40.3734, 49.8596], ['H. Əliyev pr. 88', 40.4012, 49.9425], ['Füzuli küç. 49', 40.3781, 49.8392],
        ['Səməd Vurğun küç. 34', 40.3802, 49.8481], ['Atatürk pr. 37', 40.4089, 49.8583], ['Tbilisi pr. 3', 40.3937, 49.8037],
        ['Z. Bünyadov pr. 11', 40.4142, 49.8406], ['Qara Qarayev pr. 68', 40.4205, 49.9416], ['Mətbuat pr. 529', 40.3769, 49.8079],
        ['Azadlıq pr. 191', 40.4113, 49.8370], ['Heydər Əliyev pr. 115', 40.4048, 49.9071], ['Yusif Səfərov küç. 27', 40.3887, 49.8784],
        ['İnşaatçılar pr. 22', 40.3863, 49.8159], ['Şərifzadə küç. 194', 40.3816, 49.8250], ['Ağa Nemətulla küç. 44', 40.3960, 49.8676],
        ['Bakıxanov küç. 32', 40.3807, 49.8588], ['Xətai pr. 19', 40.3836, 49.8784], ['Moskva pr. 74', 40.4000, 49.8521],
        ['Abbas Səhhət küç. 7', 40.3743, 49.8396], ['Ziya Bünyadov pr. 2', 40.4178, 49.8526], ['Sabunçu yolu 9', 40.4264, 49.9068],
    ];

    private const RECIPIENTS = ['Aysel Məmmədova', 'Vüsal Quliyev', 'Leyla Həsənova', 'Orxan İsmayılov', 'Nərmin Səfərova', 'Kamran Əliyev',
        'Günay Rzayeva', 'Tural Hüseynov', 'Səbinə Kərimova', 'Elşən Nəbiyev', 'Fidan Abbasova', 'Rauf Mustafayev'];

    public function run(): void
    {
        $this->call(StatusSeeder::class);

        if (User::query()->where('email', 'demo@omnidlv.test')->exists()) {
            return;
        }

        $company = Company::create([
            'name' => 'Demo Logistika MMC', 'short_name' => 'Demo Logistika', 'slug' => 'demo-logistika',
            'plan_slug' => 'scale', 'trial_ends_at' => now()->addDays(14),
        ]);

        $owner = User::create([
            'name' => 'Nigar Dispetçer', 'email' => 'demo@omnidlv.test', 'password' => Hash::make('password'),
            'active_company_id' => $company->id, 'locale' => 'az',
        ]);
        Employment::withoutGlobalScopes()->create(['company_id' => $company->id, 'user_id' => $owner->id, 'role_slug' => Role::Owner->value]);

        app(CurrentCompany::class)->run($company, fn () => $this->fill($owner));
    }

    private function fill(User $owner): void
    {
        $hubs = app(HubService::class);
        $central = $hubs->save(new Hub, ['name' => 'Mərkəzi anbar', 'integration_id' => 'WH-01', 'address' => 'Mərkəzi anbar, Nərimanov r.', 'lat' => 40.4029, 'lng' => 49.8733]);
        $west = $hubs->save(new Hub, ['name' => 'Yasamal filialı', 'integration_id' => 'WH-02', 'address' => 'Yasamal filialı, Ş. Mehdiyev küç.', 'lat' => 40.3895, 'lng' => 49.8205]);

        $teamService = app(TeamService::class);
        $teams = [
            $teamService->save(new Team, ['name' => 'Mərkəz', 'hub_id' => $central->id, 'color' => '#e21d2d',
                'polygon' => [[40.365, 49.83], [40.365, 49.885], [40.395, 49.885], [40.395, 49.83]]]),
            $teamService->save(new Team, ['name' => 'Şərq', 'hub_id' => $central->id, 'color' => '#2f6bff',
                'polygon' => [[40.38, 49.885], [40.38, 49.96], [40.44, 49.96], [40.44, 49.885]]]),
            $teamService->save(new Team, ['name' => 'Qərb', 'hub_id' => $west->id, 'color' => '#22b14c',
                'polygon' => [[40.37, 49.78], [40.37, 49.83], [40.43, 49.83], [40.43, 49.78]]]),
        ];

        $couriers = collect([
            ['Rəşad Əliyev', 'motorcycle', 0, [40.392, 49.861]], ['Elvin Quliyev', 'car', 0, [40.381, 49.848]],
            ['Nicat Məmmədov', 'motorcycle', 1, [40.405, 49.905]], ['Fərid Həsənli', 'minivan', 1, [40.414, 49.928]],
            ['Samir Babayev', 'bicycle', 2, [40.398, 49.812]], ['Ayxan Rüstəmov', 'car', 2, null],
        ])->map(function ($c, $i) use ($teams) {
            $courier = app(CourierService::class)->create([
                'name' => $c[0], 'phone' => '+99450'.str_pad((string) (2000000 + $i * 1111), 7, '0', STR_PAD_LEFT),
                'vehicle_type_slug' => $c[1], 'capacity' => 8, 'team_ids' => [$teams[$c[2]]->id],
            ]);
            CourierLocation::query()->updateOrCreate(['user_id' => $courier->id], [
                'is_online' => $c[3] !== null, 'is_working' => $c[3] !== null, 'on_duty' => $c[3] !== null,
                'lat' => $c[3][0] ?? null, 'lng' => $c[3][1] ?? null, 'last_seen_at' => $c[3] ? now()->subMinutes(rand(0, 4)) : now()->subDay(),
            ]);

            return $courier;
        });

        $tasks = app(TaskService::class);
        $machine = app(StatusMachine::class);
        $assign = app(AssignmentService::class);
        $status = fn (string $slug) => Status::query()->where('slug', $slug)->first();

        // [address index, minutes from now to deadline, plan]
        $plan = [
            // delivered earlier today
            [0, -150, 'delivered', 0], [1, -120, 'delivered', 2], [2, -95, 'delivered', 2], [5, -80, 'delivered', 1], [8, -60, 'delivered', 4],
            [3, -70, 'failed', 1], [11, -40, 'delivered', 4],
            // en route
            [6, -14, 'on_the_way', 0], [18, 12, 'picked_up', 0], [21, 35, 'assigned', 0], [12, 70, 'assigned', 0],
            [7, -6, 'on_the_way', 1], [19, 18, 'assigned', 1], [14, 55, 'assigned', 1],
            [4, 28, 'on_the_way', 2], [10, 47, 'assigned', 2], [23, 90, 'assigned', 3], [13, 64, 'picked_up', 3],
            [15, 25, 'on_the_way', 4], [16, 80, 'assigned', 4],
            // waiting for a courier
            [9, 15, 'pool', null], [17, 40, 'pool', null], [20, 95, 'created', null], [22, 130, 'created', null],
        ];

        foreach ($plan as $n => [$addr, $deadline, $target, $courierIndex]) {
            [$street, $lat, $lng] = self::ADDRESSES[$addr];
            $hub = $lng < 49.83 ? $west : $central;
            $task = $tasks->create([
                'hub_id' => $hub->id,
                'integration_id' => 'ORD-'.(24100 + $n),
                'starts_at' => now()->addMinutes($deadline - 120)->toIso8601String(),
                'ends_at' => now()->addMinutes($deadline)->toIso8601String(),
                'destination' => ['address' => $street.', Bakı', 'lat' => $lat, 'lng' => $lng,
                    'name' => self::RECIPIENTS[$n % count(self::RECIPIENTS)], 'phone' => '+99455'.str_pad((string) (3000000 + $n * 7919), 7, '0', STR_PAD_LEFT)],
                'requirements' => ['delivery_code' => $n % 3 === 0, 'pod_photo' => $n % 2 === 0],
                'assignment' => ['type' => $target === 'created' ? 'capacity_aware' : 'pool'],
                'tags' => $n % 4 === 0 ? ['express'] : [],
                'items' => [['integration_id' => 'SKU-'.(500 + $n), 'name' => ['Ayaqqabı', 'Telefon qabı', 'Kitab dəsti', 'Çay dəsti'][$n % 4], 'quantity' => 1, 'amount' => 20 + $n * 3]],
                'payment_type' => $n % 3 === 0 ? 'cash' : 'prepaid',
                'amount' => $n % 3 === 0 ? 40 + $n : null,
                'currency' => 'AZN',
            ], Channel::Api, $owner);

            if ($courierIndex === null) {
                continue;
            }

            $assign->assign($task, $couriers[$courierIndex], Channel::Panel, $owner, ignoreCapacity: true);

            $path = match ($target) {
                'assigned' => [],
                'picked_up' => ['picked_up'],
                'on_the_way' => ['picked_up', 'on_the_way'],
                'delivered' => ['picked_up', 'on_the_way', 'delivered'],
                'failed' => ['picked_up', 'on_the_way', 'recipient_not_available'],
            };

            foreach ($path as $slug) {
                $machine->transition($task, $status($slug), Channel::Courier, $couriers[$courierIndex], at: $task->destinationPoint());
            }

            // Finished tasks: place the finish time where the plan says it happened.
            if ($task->isFinal()) {
                Task::query()->whereKey($task->id)->update([
                    'ended_at' => now()->addMinutes($deadline - rand(5, 25)),
                    'was_delayed' => false,
                    'was_finalized_duly' => $target === 'delivered',
                ]);
            }
        }

        // Route order per courier follows the deadline, as a planner would.
        Task::query()->whereNotNull('courier_id')->orderBy('ends_at')->get()->groupBy('courier_id')
            ->each(fn ($group) => $group->values()->each(fn (Task $t, $i) => $t->forceFill(['route_order' => $i + 1])->save()));
    }
}
