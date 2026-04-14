<?php

namespace App\Providers;

use App\Models\AnonymousFeedback;
use App\Models\Document;
use App\Policies\ActivityPolicy;
use App\Policies\AnonymousFeedbackPolicy;
use App\Policies\DocumentPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Spatie\Activitylog\Models\Activity;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Activity::class => ActivityPolicy::class,
        Document::class => DocumentPolicy::class,
        AnonymousFeedback::class => AnonymousFeedbackPolicy::class,
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}
