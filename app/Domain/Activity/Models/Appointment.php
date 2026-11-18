<?php

namespace App\Domain\Activity\Models;

use App\Domain\Activity\Enums\AppointmentType;
use App\Domain\Activity\Enums\Attendance;
use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Shared\Concerns\BelongsToOrganization;
use App\Domain\Shared\Concerns\HasAuditColumns;
use App\Models\User;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A counseling / campus tour / interview / talent exam booked for an admission case.
 * Visibility follows the case (CASE data scope), enforced by the controller.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $case_id
 * @property AppointmentType $type
 * @property Carbon $start_at
 * @property Carbon|null $end_at
 * @property string|null $location
 * @property int|null $host_id
 * @property Attendance $attendance
 * @property string|null $result
 */
#[Fillable(['organization_id', 'case_id', 'type', 'start_at', 'end_at', 'location', 'host_id', 'attendance', 'result'])]
#[UseFactory(AppointmentFactory::class)]
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use BelongsToOrganization, HasAuditColumns, HasFactory;

    protected $attributes = ['attendance' => 'scheduled'];

    protected function casts(): array
    {
        return ['type' => AppointmentType::class, 'attendance' => Attendance::class, 'start_at' => 'datetime', 'end_at' => 'datetime'];
    }

    /** @return BelongsTo<AdmissionCase, $this> */
    public function case(): BelongsTo
    {
        return $this->belongsTo(AdmissionCase::class, 'case_id')->withoutGlobalScopes();
    }

    /** @return BelongsTo<User, $this> */
    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }
}
