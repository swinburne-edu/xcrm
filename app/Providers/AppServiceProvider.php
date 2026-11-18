<?php

namespace App\Providers;

use App\Domain\Admission\Models\AdmissionCase;
use App\Domain\Admission\Models\Pipeline;
use App\Domain\Admission\Policies\CasePolicy;
use App\Domain\Admission\Policies\PipelinePolicy;
use App\Domain\Applicant\Models\Applicant;
use App\Domain\Applicant\Models\Guardian;
use App\Domain\Applicant\Policies\ApplicantPolicy;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Campaign\Policies\CampaignPolicy;
use App\Domain\Catalog\Catalogs\CatalogRegistry;
use App\Domain\Catalog\Models\Province;
use App\Domain\Catalog\Policies\CatalogPolicy;
use App\Domain\Cycle\Models\AdmissionCycle;
use App\Domain\Cycle\Policies\CyclePolicy;
use App\Domain\Cycle\Services\CurrentCycle;
use App\Domain\HighSchool\Models\HighSchool;
use App\Domain\HighSchool\Models\SchoolContact;
use App\Domain\HighSchool\Policies\HighSchoolPolicy;
use App\Domain\HighSchool\Policies\SchoolContactPolicy;
use App\Domain\Major\Models\Major;
use App\Domain\Major\Policies\MajorPolicy;
use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\Team;
use App\Domain\Organization\Models\Territory;
use App\Domain\Organization\Policies\TeamPolicy;
use App\Domain\Organization\Policies\TerritoryPolicy;
use App\Domain\Organization\Policies\UserPolicy;
use App\Domain\Shared\Services\CurrentOrganization;
use App\Domain\Shared\Services\PhoneNormalizer;
use App\Domain\Shared\Support\BlueprintMacros;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use libphonenumber\PhoneNumberUtil;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrentOrganization::class);
        $this->app->scoped(CurrentCycle::class);
        $this->app->singleton(PhoneNumberUtil::class, fn (): PhoneNumberUtil => PhoneNumberUtil::getInstance());
        $this->app->singleton(PhoneNormalizer::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        BlueprintMacros::register();

        $this->configureMorphMap();

        $this->configurePolicies();
    }

    /**
     * Policies for models outside app/Models (no auto-discovery for app/Domain).
     */
    protected function configurePolicies(): void
    {
        foreach (app(CatalogRegistry::class)->models() as $model) {
            Gate::policy($model, CatalogPolicy::class);
        }

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Team::class, TeamPolicy::class);
        Gate::policy(Territory::class, TerritoryPolicy::class);
        Gate::policy(AdmissionCycle::class, CyclePolicy::class);
        Gate::policy(Major::class, MajorPolicy::class);
        Gate::policy(Campaign::class, CampaignPolicy::class);
        Gate::policy(Applicant::class, ApplicantPolicy::class);
        Gate::policy(AdmissionCase::class, CasePolicy::class);
        Gate::policy(Pipeline::class, PipelinePolicy::class);
        Gate::policy(HighSchool::class, HighSchoolPolicy::class);
        Gate::policy(SchoolContact::class, SchoolContactPolicy::class);
    }

    /**
     * Short polymorphic type names (docs/04-database.md §1).
     * Add each new model that is stored in a *_type column here:
     * applicant, admission_case, high_school, school_contact, campaign, major, province...
     */
    protected function configureMorphMap(): void
    {
        Relation::enforceMorphMap([
            'user' => User::class,
            'organization' => Organization::class,
            'team' => Team::class,
            'role' => Role::class,
            'permission' => Permission::class,
            'province' => Province::class,
            'territory' => Territory::class,
            'admission_cycle' => AdmissionCycle::class,
            'major' => Major::class,
            'campaign' => Campaign::class,
            'applicant' => Applicant::class,
            'admission_case' => AdmissionCase::class,
            'pipeline' => Pipeline::class,
            'guardian' => Guardian::class,
            'high_school' => HighSchool::class,
            'school_contact' => SchoolContact::class,
        ]);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
