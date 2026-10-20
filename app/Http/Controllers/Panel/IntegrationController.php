<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Support\CurrentCompany;
use Illuminate\Http\Request;

/** API keys are Sanctum tokens owned by the company; the plain key is shown exactly once. */
class IntegrationController extends Controller
{
    public function __construct(private readonly CurrentCompany $tenant) {}

    public function keys()
    {
        return view('panel.integration.keys', [
            'tokens' => $this->tenant->getOrFail()->tokens()->orderByDesc('id')->get(),
            'baseUrl' => url('/api/integration'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);
        $token = $this->tenant->getOrFail()->createToken($data['name'], ['integration']);

        return redirect()->route('integration.keys')->with('new_key', $token->plainTextToken);
    }

    public function destroy(int $token)
    {
        $this->tenant->getOrFail()->tokens()->whereKey($token)->delete();

        return redirect()->route('integration.keys')->with('status', __('Key revoked. Requests using it now fail with 401.'));
    }
}
