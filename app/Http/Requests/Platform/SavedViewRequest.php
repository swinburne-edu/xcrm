<?php

namespace App\Http\Requests\Platform;

use App\Domain\Platform\Enums\SavedViewObject;
use App\Domain\Platform\Models\SavedView;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /saved-views and PUT /saved-views/{id}. Anyone who may open the list keeps personal views;
 * sharing needs saved_view.manage, and so does editing a shared view (SavedViewPolicy).
 */
class SavedViewRequest extends FormRequest
{
    private ?SavedView $view = null;

    /** The view being edited, visible to the user only (404 otherwise). */
    public function view(): ?SavedView
    {
        if ($this->route('id') === null) {
            return null;
        }

        return $this->view ??= SavedView::query()->visibleTo($this->user())->findOrFail($this->route('id'));
    }

    public function object(): SavedViewObject
    {
        return $this->view()?->object ?? SavedViewObject::tryFrom((string) $this->input('object')) ?? abort(422, 'Màn hình không hỗ trợ chế độ xem đã lưu.');
    }

    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user?->can($this->object()->permission())) {
            return false;
        }

        $shared = $this->boolean('is_shared');
        $view = $this->view();

        return $view === null
            ? $user->can('create', [SavedView::class, $shared])
            : $user->can('update', $view) && (! $shared || $user->can('saved_view.manage'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'object' => [$this->route('id') === null ? 'required' : 'nullable', Rule::enum(SavedViewObject::class)],
            'name' => ['required', 'string', 'max:255'],
            'is_shared' => ['boolean'],
            'view_type' => ['nullable', Rule::in($this->object()->viewTypes())],
            'config' => ['nullable', 'array'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['name' => 'tên chế độ xem', 'view_type' => 'kiểu xem'];
    }
}
