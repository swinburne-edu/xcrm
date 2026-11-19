<?php

namespace App\Domain\Platform\Models;

use App\Domain\Platform\Enums\SavedViewObject;
use App\Domain\Shared\Concerns\BelongsToOrganization;
use App\Domain\Shared\Concerns\HasAuditColumns;
use App\Models\User;
use Database\Factories\SavedViewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A saved list view. config: {filters: {key: value}, sort: string|null, per_page: int|null, columns: {column: bool}|null}.
 * Personal views belong to user_id; shared views (is_shared, user_id null) are visible to the whole organization.
 *
 * @property int $id
 * @property int $organization_id
 * @property SavedViewObject $object
 * @property string $name
 * @property int|null $user_id
 * @property bool $is_shared
 * @property string $view_type
 * @property array<string, mixed>|null $config
 * @property int $sort_order
 */
#[Fillable(['organization_id', 'object', 'name', 'user_id', 'is_shared', 'view_type', 'config', 'sort_order'])]
#[UseFactory(SavedViewFactory::class)]
class SavedView extends Model
{
    /** @use HasFactory<SavedViewFactory> */
    use BelongsToOrganization, HasAuditColumns, HasFactory, SoftDeletes;

    protected $attributes = ['is_shared' => false, 'view_type' => 'table', 'sort_order' => 0];

    protected function casts(): array
    {
        return [
            'object' => SavedViewObject::class,
            'is_shared' => 'boolean',
            'config' => 'array',
            'sort_order' => 'integer',
        ];
    }

    /** Shared views and the user's own. @param Builder<SavedView> $query */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->where(fn (Builder $q) => $q->where('is_shared', true)->orWhere('user_id', $user->id));
    }
}
