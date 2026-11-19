<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Personal API tokens (task 3.5) of the signed-in user: created here, shown once, revoked here. A token acts as its
 * owner with the abilities read or read and write. Needs api_token.manage.
 */
class ApiTokenController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('api_token.manage'), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'write' => ['boolean'],
            'expires_in_days' => ['nullable', 'integer', 'between:1,730'],
        ]);

        $token = $request->user()->createToken($data['name'], $request->boolean('write') ? ['read', 'write'] : ['read'], isset($data['expires_in_days']) ? now()->addDays((int) $data['expires_in_days']) : null);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Đã tạo token.']);

        return back()->with('new_token', $token->plainTextToken);
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->user()->can('api_token.manage'), 403);
        $request->user()->tokens()->whereKey($id)->firstOrFail()->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Đã thu hồi token.']);

        return back();
    }
}
