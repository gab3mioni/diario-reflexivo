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

/**
 * Provedor principal de serviços da aplicação.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Regista os bindings e singletons no container de serviços.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->bind(BranchClassifierContract::class, BranchClassifier::class);
    }

    /**
     * Inicializa os serviços da aplicação após o registo.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerPolicies();
    }

    /**
     * Regista as policies de autorização para os modelos.
     *
     * @return void
     */
    protected function registerPolicies(): void
    {
        Gate::policy(Lesson::class, LessonPolicy::class);
        Gate::policy(LessonResponse::class, LessonResponsePolicy::class);
        Gate::policy(ResponseAlert::class, ResponseAlertPolicy::class);
        Gate::policy(QuestionScript::class, QuestionScriptPolicy::class);
    }

    /**
     * Configura comportamentos padrão para ambientes de produção.
     *
     * @return void
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
