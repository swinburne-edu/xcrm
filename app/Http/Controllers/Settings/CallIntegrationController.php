<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Shared\Support\DisplayTime;
use App\Domain\Telephony\Models\CallIntegration;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * /settings/call-integrations (task 3.7): webhook endpoints for the telephone system. The secret is shown only once,
 * right after it is created or renewed (flash), and is never sent to the browser again.
 */
class CallIntegrationController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', CallIntegration::class);

        return Inertia::render('CallIntegrations/Index', [
            'integrations' => CallIntegration::query()->orderBy('name')->get()->map(fn (CallIntegration $i) => [
                'id' => $i->id,
                'name' => $i->name,
                'is_active' => $i->is_active,
                'webhook_url' => $i->webhookUrl(),
                'last_received_at' => $i->last_received_at === null ? null : str_replace('T', ' ', (string) DisplayTime::toInput($i->last_received_at)),
            ])->all(),
            'new_secret' => $request->session()->get('new_secret'),
            'driver' => config('telephony.driver'),
            'can' => ['manage' => $request->user()->can('create', CallIntegration::class)],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', CallIntegration::class);
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $secret = Str::random(40);
        CallIntegration::query()->create(['name' => $data['name'], 'secret' => $secret, 'organization_id' => $request->user()->organization_id]);

        return $this->done('Đã tạo tích hợp tổng đài.', $secret);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $integration = CallIntegration::query()->findOrFail($id);
        $this->authorize('update', $integration);
        $data = $request->validate(['name' => ['sometimes', 'required', 'string', 'max:255'], 'is_active' => ['sometimes', 'boolean'], 'renew_secret' => ['sometimes', 'boolean']]);

        $secret = null;

        if ($request->boolean('renew_secret')) {
            $secret = Str::random(40);
            $integration->secret = $secret;
        }

        $integration->fill(array_intersect_key($data, array_flip(['name', 'is_active'])))->save();

        return $this->done('Đã cập nhật tích hợp tổng đài.', $secret);
    }

    public function destroy(int $id): RedirectResponse
    {
        $integration = CallIntegration::query()->findOrFail($id);
        $this->authorize('delete', $integration);
        $integration->delete();

        return $this->done('Đã xoá tích hợp tổng đài.');
    }

    private function done(string $message, ?string $secret = null): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        $response = back();

        return $secret === null ? $response : $response->with('new_secret', $secret);
    }
}
