<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\SmsMessage;
use App\Services\Sms\SmsService;
use App\Support\CurrentCompany;

/** Every SMS the company sent (or, in demo mode, would have sent). */
class SmsLogController extends Controller
{
    public function __invoke(CurrentCompany $tenant, SmsService $sms)
    {
        return view('panel.sms.index', [
            'messages' => SmsMessage::query()
                ->with('task:id,tracking_code')
                ->where('company_id', $tenant->id())
                ->latest('id')
                ->paginate(50),
            'demo' => $sms->isDemo(),
        ]);
    }
}
