<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Platform\Actions\SaveSavedView;
use App\Domain\Platform\Models\SavedView;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\SavedViewRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * /saved-views (task 2.6): save, update and delete the saved views of the list screens. Applying a view is done
 * in the browser by visiting the list with the view's filters. Authorization: SavedViewPolicy.
 */
class SavedViewController extends Controller
{
    public function store(SavedViewRequest $request, SaveSavedView $save): RedirectResponse
    {
        $view = $save->handle(null, $request->validated(), $request->user());

        return $this->toast("Đã lưu chế độ xem {$view->name}.");
    }

    public function update(SavedViewRequest $request, int $id, SaveSavedView $save): RedirectResponse
    {
        $view = $save->handle($request->view(), $request->validated(), $request->user());

        return $this->toast("Đã cập nhật chế độ xem {$view->name}.");
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $view = SavedView::query()->visibleTo($request->user())->findOrFail($id);
        $this->authorize('delete', $view);
        $view->delete();

        return $this->toast("Đã xoá chế độ xem {$view->name}.");
    }

    private function toast(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
