<?php

namespace App\Domain\Intake\Models;

use App\Domain\Shared\Concerns\BelongsToOrganization;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One uploaded spreadsheet being (or done being) imported in the background.
 * status: pending → processing → done | failed. errors = [{row, message}] (first 200).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $object
 * @property string $file_path
 * @property string|null $original_name
 * @property array<string, string>|null $mapping field => heading
 * @property int|null $campaign_id
 * @property string $status
 * @property int $total_rows
 * @property int $success_rows
 * @property int $duplicate_rows
 * @property int $error_rows
 * @property list<array{row: int, message: string}>|null $errors
 * @property int|null $user_id
 */
#[Fillable(['organization_id', 'object', 'file_path', 'original_name', 'mapping', 'campaign_id', 'status', 'total_rows', 'success_rows', 'duplicate_rows', 'error_rows', 'errors', 'user_id'])]
class ImportJob extends Model
{
    use BelongsToOrganization;

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return ['mapping' => 'array', 'errors' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
