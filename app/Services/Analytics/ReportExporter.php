<?php

namespace App\Services\Analytics;

use App\Models\Task;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\WriterInterface;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams reports as CSV (UTF-8 with BOM, so Excel shows ə and Cyrillic) or XLSX.
 * Rows are written as they are read, so a large month never sits in memory.
 */
class ReportExporter
{
    public const FORMATS = ['csv', 'xlsx'];

    public const REPORTS = ['tasks', 'couriers'];

    public function __construct(private readonly AnalyticsService $analytics) {}

    public function download(string $report, string $format, ReportFilters $f): StreamedResponse
    {
        $name = sprintf('omnidlv-%s-%s_%s.%s', $report, $f->from->toDateString(), $f->to->toDateString(), $format);

        return response()->streamDownload(function () use ($report, $format, $f) {
            $writer = $format === 'xlsx' ? new XlsxWriter : new CsvWriter;
            $writer->openToFile('php://output');
            $report === 'couriers' ? $this->couriers($writer, $f) : $this->tasks($writer, $f);
            $writer->close();
        }, $name, [
            'Content-Type' => $format === 'xlsx'
                ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                : 'text/csv; charset=UTF-8',
        ]);
    }

    private function tasks(WriterInterface $writer, ReportFilters $f): void
    {
        $this->header($writer, [
            'tracking_code', 'order', 'type', 'hub', 'team', 'courier', 'outcome', 'status', 'recipient', 'address',
            'window_start', 'window_end', 'finished_at', 'on_time', 'minutes_on_task', 'distance_km',
            'payment', 'amount', 'currency', 'rating', 'comment', 'channel',
        ]);

        $time = fn ($t) => $t ? AnalyticsService::localDate($t, $f->timezone) : null;

        $this->analytics->finished($f)
            ->with(['hub:id,name', 'team:id,name', 'courier:id,name', 'status'])
            ->lazyById(500)
            ->each(function (Task $t) use ($writer, $time) {
                $done = $t->status_category_slug->value === 'completed';
                $writer->addRow($this->row([
                    $t->tracking_code,
                    $t->integration_id,
                    __('analytics.type.'.$t->type_slug->value),
                    $t->hub?->name,
                    $t->team?->name,
                    $t->courier?->name,
                    __('analytics.outcome.'.$t->status_category_slug->value),
                    $t->status?->label(),
                    $t->destination_name,
                    $t->destination_address,
                    $time($t->starts_at),
                    $time($t->ends_at),
                    $time($t->ended_at),
                    $done ? __($t->was_delayed ? 'analytics.no' : 'analytics.yes') : null,
                    $done && $t->started_at && $t->ended_at ? (int) round($t->started_at->diffInMinutes($t->ended_at)) : null,
                    $t->distance ? round($t->distance / 1000, 2) : null,
                    $t->payment_type,
                    $t->amount !== null ? (float) $t->amount : null,
                    $t->currency,
                    $t->rating ?: null,
                    $t->comment,
                    $t->channel?->value,
                ]));
            });
    }

    private function couriers(WriterInterface $writer, ReportFilters $f): void
    {
        $this->header($writer, [
            'courier', 'finished', 'delivered', 'failed', 'cancelled', 'success_rate', 'on_time_rate',
            'median_minutes', 'rating', 'rated', 'cash', 'distance_km',
        ]);

        foreach ($this->analytics->couriers($f) as $c) {
            $writer->addRow($this->row([
                $c['name'], $c['total'], $c['delivered'], $c['failed'], $c['cancelled'], $c['success_rate'],
                $c['on_time_rate'], $c['median_minutes'], $c['rating'], $c['rated'], $c['cash'], $c['distance_km'],
            ]));
        }
    }

    private function header(WriterInterface $writer, array $columns): void
    {
        $writer->addRow(Row::fromValuesWithStyle(
            array_map(fn ($c) => __('analytics.columns.'.$c), $columns),
            (new Style)->withFontBold(true),
        ));
    }

    /** Text that a spreadsheet would run as a formula (=, +, -, @) is defused with a leading quote. */
    private function row(array $values): Row
    {
        return Row::fromValues(array_map(
            fn ($v) => is_string($v) && $v !== '' && str_contains("=+-@\t\r", $v[0]) ? "'".$v : $v,
            $values,
        ));
    }
}
