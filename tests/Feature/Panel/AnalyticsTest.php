<?php

namespace Tests\Feature\Panel;

use App\Enums\Channel;
use App\Enums\Role;
use App\Models\Company;
use App\Models\Hub;
use App\Models\Status;
use App\Models\Task;
use App\Models\User;
use App\Services\Analytics\AnalyticsService;
use App\Services\Analytics\ReportFilters;
use App\Services\TaskService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    private Company $company;

    private Hub $hub;

    private User $ali;

    private User $vera;

    protected function setUp(): void
    {
        parent::setUp();
        // 21 Oct 2026, 16:00 in Baku (UTC+4).
        $this->travelTo(Carbon::parse('2026-10-21 12:00:00', 'UTC'));

        $this->company = $this->makeCompany(['timezone' => 'Asia/Baku', 'plan_slug' => 'start']);
        $this->hub = $this->makeHub($this->company);
        $this->ali = $this->makeCourier($this->company, ['name' => 'Əli Kuryer']);
        $this->vera = $this->makeCourier($this->company, ['name' => 'Vera Kuryer']);

        $this->finish('completed', 'delivered', '2026-10-21 10:00', '2026-10-21 10:30', [
            'courier_id' => $this->ali->id, 'payment_type' => 'cash', 'amount' => 20, 'rating' => 5, 'distance' => 3000,
        ]);
        $this->finish('completed', 'delivered', '2026-10-21 09:00', '2026-10-21 10:00', ['courier_id' => $this->ali->id, 'was_delayed' => true, 'rating' => 3]);
        // 01:30 on the 21st in Baku, still the 20th in UTC: counts on the company's day.
        $this->finish('completed', 'left_at_door', '2026-10-20 21:00', '2026-10-20 21:30', ['courier_id' => $this->vera->id]);
        $this->finish('failed', 'recipient_not_available', null, '2026-10-21 11:00', ['courier_id' => $this->vera->id]);
        $this->finish('cancelled', 'cancelled', null, '2026-10-21 07:00');
        // Yesterday, for the comparison.
        $this->finish('completed', 'delivered', '2026-10-20 07:00', '2026-10-20 08:00', ['courier_id' => $this->ali->id]);
    }

    public function test_figures_follow_the_company_day_and_leave_out_cancellations_from_success(): void
    {
        $other = $this->makeCompany();
        $this->finish('completed', 'delivered', '2026-10-21 09:00', '2026-10-21 09:30', [], $other, $this->makeHub($other));

        $report = $this->asTenant($this->company, fn () => app(AnalyticsService::class)->report(ReportFilters::make($this->company, 'today')));
        $s = $report['summary'];

        $this->assertSame(5, $s['total']);
        $this->assertSame([3, 1, 1], [$s['delivered'], $s['failed'], $s['cancelled']]);
        $this->assertSame(75.0, $s['success_rate']);
        $this->assertSame(66.7, $s['on_time_rate']);
        $this->assertSame(30, $s['median_minutes']);
        $this->assertSame(4.0, $s['rating']);
        $this->assertSame(2, $s['rated']);
        $this->assertSame(20.0, $s['cash']);
        $this->assertSame(3.0, $s['distance_km']);

        $this->assertSame(1, $report['previous']['delivered']);
        $this->assertSame('2026-10-21', $report['series']['points'][0]['date']);
        $this->assertSame(3, $report['series']['points'][0]['delivered']);
        $this->assertSame(2, $report['hours'][14]['delivered']);
        $this->assertSame(1, $report['hours'][1]['delivered']);

        $this->assertSame(['Əli Kuryer', 'Vera Kuryer'], array_column($report['couriers'], 'name'));
        $this->assertSame(100.0, $report['couriers'][0]['success_rate']);
        $this->assertSame(50.0, $report['couriers'][1]['success_rate']);
        $this->assertSame([1, 1], array_column($report['reasons'], 'count'));
        $this->assertContains('failed', array_column($report['reasons'], 'category'));
    }

    public function test_the_plan_history_limit_clamps_the_start_and_long_spans_go_weekly(): void
    {
        $f = ReportFilters::make($this->company, 'custom', '2026-01-01', '2026-10-21');

        $this->assertTrue($f->clamped);
        $this->assertSame('2026-07-24', $f->from->toDateString());
        $this->assertSame(90, $f->days());
        $this->assertSame('week', $f->bucket());
        $this->assertNull($f->previous(), 'the span before is outside the plan history');

        $this->company->update(['plan_slug' => 'enterprise']);
        $this->assertFalse(ReportFilters::make($this->company, 'custom', '2026-01-01', '2026-10-21')->clamped);
        $this->assertSame('2026-10-15', ReportFilters::make($this->company, '7d')->from->toDateString());
        $this->assertSame('2026-09-01', ReportFilters::make($this->company, 'last_month')->from->toDateString());
        $this->assertSame('2026-09-30', ReportFilters::make($this->company, 'last_month')->to->toDateString());
    }

    public function test_staff_see_the_page_and_couriers_do_not(): void
    {
        $owner = $this->makeStaff($this->company);
        $this->actingAs($owner)->get('/app/analytics?period=today')
            ->assertOk()
            ->assertSee('Əli Kuryer')
            ->assertSee('Recipient not available');

        $this->actingAs($owner)->get('/app/analytics?period=custom&from=2026-01-01&to=2026-10-21')
            ->assertOk()
            ->assertSee(__('analytics.history_limit', ['days' => 90]));

        $this->actingAs($this->makeStaff($this->company, Role::Dispatcher))->get('/app/analytics')->assertOk();
        $this->actingAs($this->ali)->get('/app/analytics')->assertForbidden();
    }

    public function test_exports_stream_this_company_only_and_defuse_formulas(): void
    {
        $this->finish('completed', 'delivered', '2026-10-21 09:00', '2026-10-21 09:40', ['destination' => ['name' => '=HYPERLINK("x")']]);
        $other = $this->makeCompany();
        $foreign = $this->finish('completed', 'delivered', '2026-10-21 09:00', '2026-10-21 09:30', [], $other, $this->makeHub($other));

        $owner = $this->makeStaff($this->company);
        $csv = $this->actingAs($owner)->get('/app/analytics/export?report=tasks&format=csv&period=today');
        $csv->assertOk();
        $body = $csv->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $body, 'BOM so Excel reads UTF-8');
        $this->assertStringContainsString('Tracking code', $body);
        $this->assertStringContainsString("'=HYPERLINK", $body);
        $this->assertStringNotContainsString($foreign->tracking_code, $body);
        $this->assertSame(7, substr_count(trim($body), "\n") + 1, 'header + 6 finished tasks today');

        $xlsx = $this->actingAs($owner)->get('/app/analytics/export?report=couriers&format=xlsx&period=7d');
        $xlsx->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringStartsWith('PK', $xlsx->streamedContent());

        $this->actingAs($owner)->get('/app/analytics/export?report=secrets&format=csv')->assertSessionHasErrors('report');
    }

    private function finish(string $category, string $statusSlug, ?string $startedAt, string $endedAt, array $attributes = [], ?Company $company = null, ?Hub $hub = null): Task
    {
        $company ??= $this->company;
        $hub ??= $this->hub;
        $destination = $attributes['destination'] ?? [];
        unset($attributes['destination']);

        return $this->asTenant($company, function () use ($hub, $category, $statusSlug, $startedAt, $endedAt, $attributes, $destination) {
            $task = app(TaskService::class)->create([
                'hub_id' => $hub->id,
                'integration_id' => 'ORD-'.Str::random(8),
                'starts_at' => '2026-10-21T06:00:00Z',
                'ends_at' => '2026-10-21T10:15:00Z',
                'destination' => ['address' => 'Nizami küç. 10', 'lat' => 40.38, 'lng' => 49.85, 'phone' => '+994551234567', ...$destination],
            ], Channel::Api);

            $status = Status::withoutGlobalScopes()->where('slug', $statusSlug)->whereNull('company_id')->firstOrFail();
            $task->forceFill([
                'status_id' => $status->id,
                'status_category_slug' => $category,
                'started_at' => $startedAt ? Carbon::parse($startedAt, 'UTC') : null,
                'ended_at' => Carbon::parse($endedAt, 'UTC'),
                'was_delayed' => false,
                ...$attributes,
            ])->save();

            return $task;
        });
    }
}
