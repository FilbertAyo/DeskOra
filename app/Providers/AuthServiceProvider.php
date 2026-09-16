<?php

namespace App\Providers;

use App\Models\AnonymousFeedback;
use App\Models\Document;
use App\Models\FeatureUsageEvent;
use App\Models\InventoryAsset;
use App\Models\InventoryBorrowRequest;
use App\Models\InventoryDispatch;
use App\Models\InventoryDisposalRequest;
use App\Models\InventoryMaintenanceRecord;
use App\Models\InventoryReceipt;
use App\Models\InventoryStockItem;
use App\Models\InventoryVerificationSession;
use App\Models\MeetingMinute;
use App\Models\NominationPeriod;
use App\Models\OfficialLetter;
use App\Models\WeeklyWorkLog;
use App\Policies\ActivityPolicy;
use App\Policies\AnonymousFeedbackPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\FeatureUsageEventPolicy;
use App\Policies\InventoryAssetPolicy;
use App\Policies\InventoryBorrowRequestPolicy;
use App\Policies\InventoryDispatchPolicy;
use App\Policies\InventoryDisposalRequestPolicy;
use App\Policies\InventoryMaintenanceRecordPolicy;
use App\Policies\InventoryReceiptPolicy;
use App\Policies\InventoryStockItemPolicy;
use App\Policies\InventoryVerificationSessionPolicy;
use App\Policies\MeetingMinutePolicy;
use App\Policies\NominationPeriodPolicy;
use App\Policies\OfficialLetterPolicy;
use App\Policies\WeeklyWorkLogPolicy;
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
        FeatureUsageEvent::class => FeatureUsageEventPolicy::class,
        MeetingMinute::class => MeetingMinutePolicy::class,
        NominationPeriod::class => NominationPeriodPolicy::class,
        OfficialLetter::class => OfficialLetterPolicy::class,
        WeeklyWorkLog::class => WeeklyWorkLogPolicy::class,
        InventoryAsset::class => InventoryAssetPolicy::class,
        InventoryStockItem::class => InventoryStockItemPolicy::class,
        InventoryReceipt::class => InventoryReceiptPolicy::class,
        InventoryBorrowRequest::class => InventoryBorrowRequestPolicy::class,
        InventoryDispatch::class => InventoryDispatchPolicy::class,
        InventoryMaintenanceRecord::class => InventoryMaintenanceRecordPolicy::class,
        InventoryDisposalRequest::class => InventoryDisposalRequestPolicy::class,
        InventoryVerificationSession::class => InventoryVerificationSessionPolicy::class,
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
