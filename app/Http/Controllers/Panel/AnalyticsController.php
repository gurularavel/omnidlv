<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Hub;
use App\Models\Team;
use App\Services\Analytics\AnalyticsService;
use App\Services\Analytics\ReportExporter;
use App\Services\Analytics\ReportFilters;
use App\Support\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analytics,
        private readonly CurrentCompany $tenant,
    ) {}

    public function index(Request $request)
    {
        $filters = ReportFilters::fromRequest($request, $this->tenant->getOrFail());

        return view('panel.analytics.index', [
            'filters' => $filters,
            'report' => $this->analytics->report($filters),
            'hubs' => Hub::query()->orderBy('name')->pluck('name', 'id'),
            'teams' => Team::query()->orderBy('name')->pluck('name', 'id'),
            'currency' => $this->tenant->get()->currency,
        ]);
    }

    public function export(Request $request, ReportExporter $exporter)
    {
        $data = $request->validate([
            'report' => ['required', Rule::in(ReportExporter::REPORTS)],
            'format' => ['required', Rule::in(ReportExporter::FORMATS)],
        ]);

        return $exporter->download($data['report'], $data['format'], ReportFilters::fromRequest($request, $this->tenant->getOrFail()));
    }
}
