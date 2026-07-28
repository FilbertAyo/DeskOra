<?php

namespace App\Providers;

use App\Models\AnonymousFeedback;
use App\Models\Document;
use App\Models\MeetingMinute;
use App\Models\OfficialLetter;
use App\Policies\ActivityPolicy;
use App\Policies\AnonymousFeedbackPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\MeetingMinutePolicy;
use App\Policies\OfficialLetterPolicy;
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
        MeetingMinute::class => MeetingMinutePolicy::class,
        OfficialLetter::class => OfficialLetterPolicy::class,
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
