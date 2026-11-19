<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Platform\Actions\SaveCustomData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\CustomDataRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * PUT /custom-data/{object}/{id} (task 2.5): inline edit of the custom fields shown on a detail page.
 * One endpoint for every object; the record's own policy decides who may save.
 */
class CustomDataController extends Controller
{
    public function update(CustomDataRequest $request, SaveCustomData $save): RedirectResponse
    {
        $save->handle($request->record(), $request->object(), (array) $request->validated('custom', []));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Đã lưu các trường tuỳ chỉnh.']);

        return back();
    }
}
