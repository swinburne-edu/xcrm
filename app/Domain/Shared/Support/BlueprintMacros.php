<?php

namespace App\Domain\Shared\Support;

use Illuminate\Database\Schema\Blueprint;

/**
 * Schema macros for the columns every business table must have
 * (see CLAUDE.md §5 rule 5 and docs/04-database.md §1).
 *
 * Usage in a migration:
 *   $table->organizationId();
 *   ...business columns...
 *   $table->auditColumns();
 *   $table->timestamps();
 *   $table->softDeletes();
 */
class BlueprintMacros
{
    public static function register(): void
    {
        Blueprint::macro('organizationId', function (): void {
            /** @var Blueprint $this */
            $this->foreignId('organization_id')->constrained('organizations');
        });

        Blueprint::macro('auditColumns', function (): void {
            /** @var Blueprint $this */
            $this->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $this->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }
}
