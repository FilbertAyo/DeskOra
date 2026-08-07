<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Request email notification routing
    |--------------------------------------------------------------------------
    |
    | Maps request type + lifecycle event to permissions, templates, and SMS
    | copy. Staff Requisition is implemented first; Petty Cash can be added later.
    |
    */

    'log_missing_recipients' => env('REQUEST_NOTIFICATIONS_LOG_MISSING', true),

    'request_type_keys' => [
        'Staff Requisition' => 'staff_requisition',
    ],

    'staff_requisition' => [
        'submitted' => [
            'permission' => 'review staff requisition',
            'subject' => 'Staff Requisition awaiting review',
            'headline' => 'New staff requisition submitted',
            'intro' => 'A new staff requisition has been submitted and is awaiting your review.',
            'action' => [
                'route' => 'petty.details',
                'stage' => 'review',
                'label' => 'Review Requisition',
            ],
            'view' => 'mail.requisitions.submitted',
            'sms' => 'New staff requisition :ref from :requester for :summary.',
        ],
        'reviewed' => [
            'permission' => 'approve staff requisition',
            'subject' => 'Staff Requisition awaiting approval',
            'headline' => 'Review complete — approval needed',
            'intro' => 'A staff requisition has been reviewed and is awaiting your approval.',
            'action' => [
                'route' => 'petty.details',
                'stage' => 'approve',
                'label' => 'Approve Requisition',
            ],
            'view' => 'mail.requisitions.stage-advanced',
            'sms' => 'Staff requisition :ref has been reviewed and is awaiting your approval.',
        ],
        'approved' => [
            'permission' => 'authorize staff requisition',
            'subject' => 'Staff Requisition awaiting authorization',
            'headline' => 'Approval complete — authorization needed',
            'intro' => 'A staff requisition has been approved and is awaiting your authorization.',
            'action' => [
                'route' => 'petty.details',
                'stage' => 'authorize',
                'label' => 'Authorize Requisition',
            ],
            'view' => 'mail.requisitions.stage-advanced',
            'sms' => 'Staff requisition :ref has been approved and is awaiting your authorization.',
        ],
        'authorized' => [
            'subject' => 'Staff Requisition fully authorized',
            'headline' => 'Your requisition has been authorized',
            'intro' => 'Your staff requisition has completed all approval stages and is now fully authorized.',
            'action' => [
                'route' => 'requisition.show',
                'label' => 'View Requisition',
            ],
            'view' => 'mail.requisitions.authorized',
            'sms' => 'Your staff requisition :ref has been fully authorized.',
        ],
        'rejected' => [
            'subject' => 'Staff Requisition rejected',
            'headline' => 'Your requisition was rejected',
            'intro' => 'We regret to inform you that your staff requisition has been rejected.',
            'action' => [
                'route' => 'requisition.show',
                'label' => 'View Requisition',
            ],
            'view' => 'mail.requisitions.rejected',
            'sms' => 'Your staff requisition :ref was rejected. Check DeskOra for feedback.',
        ],
        'resubmission_requested' => [
            'subject' => 'Staff Requisition requires updates',
            'headline' => 'Updates required on your requisition',
            'intro' => 'Your staff requisition requires updates before it can proceed. Please review the feedback and resubmit within 24 hours.',
            'action' => [
                'route' => 'requisition.edit',
                'label' => 'Update Requisition',
            ],
            'view' => 'mail.requisitions.resubmission-requested',
            'sms' => 'Staff requisition :ref requires updates within 24 hours.',
        ],
    ],

];
