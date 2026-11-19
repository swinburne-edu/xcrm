<?php

namespace App\Domain\Platform\Services;

use App\Domain\Platform\Enums\SavedViewObject;
use App\Domain\Platform\Models\SavedView;
use App\Models\User;

/**
 * The saved views a user sees on a list screen, as props for SavedViewMenu: shared views first, then the user's own.
 * `can_edit` tells the menu whether to offer update / rename / delete.
 */
class SavedViews
{
    /**
     * @return array{object: string, paths: array<string, string>, can_share: bool, items: list<array<string, mixed>>}
     */
    public function for(SavedViewObject $object, User $user): array
    {
        $views = SavedView::query()->where('object', $object->value)->visibleTo($user)
            ->orderByDesc('is_shared')->orderBy('sort_order')->orderBy('id')->get();

        return [
            'object' => $object->value,
            'paths' => $object->paths(),
            'can_share' => $user->can('saved_view.manage'),
            'items' => $views->map(fn (SavedView $v) => [
                'id' => $v->id,
                'name' => $v->name,
                'is_shared' => $v->is_shared,
                'view_type' => $v->view_type,
                'config' => $v->config ?? ['filters' => [], 'sort' => null, 'per_page' => null, 'columns' => null],
                'can_edit' => $user->can('update', $v),
            ])->all(),
        ];
    }
}
