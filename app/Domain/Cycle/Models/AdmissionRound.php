<?php

namespace App\Domain\Cycle\Models;

use App\Domain\Cycle\Enums\RoundType;
use Database\Factories\AdmissionRoundFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An admission round of a cycle: early school admission, the Ministry system round, supplementary rounds.
 * Times are stored in UTC and shown in Asia/Ho_Chi_Minh.
 *
 * @property int $id
 * @property int $cycle_id
 * @property string $name
 * @property RoundType|null $type
 * @property Carbon|null $apply_start
 * @property Carbon|null $apply_end
 * @property Carbon|null $result_date
 * @property Carbon|null $confirm_deadline
 * @property int $sort_order
 */
#[Fillable(['cycle_id', 'name', 'type', 'apply_start', 'apply_end', 'result_date', 'confirm_deadline', 'sort_order'])]
#[UseFactory(AdmissionRoundFactory::class)]
class AdmissionRound extends Model
{
    /** @use HasFactory<AdmissionRoundFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $attributes = ['sort_order' => 0];

    protected function casts(): array
    {
        return [
            'type' => RoundType::class,
            'apply_start' => 'datetime',
            'apply_end' => 'datetime',
            'result_date' => 'date',
            'confirm_deadline' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<AdmissionCycle, $this>
     */
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(AdmissionCycle::class, 'cycle_id');
    }
}
