<?php

namespace App\Providers;

use App\Contracts\Chat\BranchClassifierContract;
use App\Models\Lesson;
use App\Models\LessonResponse;
use App\Models\QuestionScript;
use App\Models\ResponseAlert;
use App\Policies\LessonPolicy;
use App\Policies\LessonResponsePolicy;
use App\Policies\QuestionScriptPolicy;
use App\Policies\ResponseAlertPolicy;
use App\Services\Chat\BranchClassifier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(BranchClassifierContract::class, BranchClassifier::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerPolicies();
    }

    protected function registerPolicies(): void
    {
        Gate::policy(Lesson::class, LessonPolicy::class);
        Gate::policy(LessonResponse::class, LessonResponsePolicy::class);
        Gate::policy(ResponseAlert::class, ResponseAlertPolicy::class);
        Gate::policy(QuestionScript::class, QuestionScriptPolicy::class);
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
            : null
        );
    }
}
