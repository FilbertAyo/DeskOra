<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Weekly document form reference (Deskora AI)
    |--------------------------------------------------------------------------
    |
    | Structured description of weekly plan and weekly report create/edit/review
    | inputs. Used by WeeklyDocumentFormKnowledgeService for AI prompts.
    |
    */

    'location_options' => ['Office', 'Field', 'Virtual', 'On site'],

    'weekly_plan' => [
        'label' => 'Weekly Plan',
        'about' => 'Sets intentions for the upcoming work period. Staff create or edit a plan, then save as draft or submit.',
        'actions' => ['Save Draft', 'Submit'],
        'sections' => [
            [
                'title' => 'Metadata (create only)',
                'fields' => [
                    ['label' => 'Document Type', 'required_on_submit' => true, 'notes' => 'Fixed to weekly plan when creating from the weekly plans menu.'],
                    ['label' => 'Work Period', 'required_on_submit' => true, 'notes' => 'Usually pre-selected for the upcoming open period.'],
                ],
            ],
            [
                'title' => 'Collaboration (Optional)',
                'fields' => [
                    ['label' => 'Assigned Staff', 'required_on_submit' => false, 'notes' => 'Selected staff can view, edit, and submit the plan while it remains draft.'],
                ],
            ],
            [
                'title' => '1. Key Objective for the Week',
                'fields' => [
                    ['label' => 'Objective(s) / Main Target(s)', 'required_on_submit' => true, 'notes' => 'Required when submitting the plan.'],
                    ['label' => 'Reflection / Progress', 'required_on_submit' => false],
                ],
            ],
            [
                'title' => '2. Planned Activities',
                'repeatable' => true,
                'required_on_submit' => 'At least one complete activity row (Day and Activity) is required on submit.',
                'fields' => [
                    ['label' => 'Day', 'required_on_submit' => true, 'options' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']],
                    ['label' => 'Activity', 'required_on_submit' => true],
                    ['label' => 'Location', 'required_on_submit' => false, 'options_key' => 'location_options'],
                    ['label' => 'Staff/Team', 'required_on_submit' => false, 'notes' => 'Multi-select staff assigned to the activity.'],
                    ['label' => 'Notes / Inclusion', 'required_on_submit' => false],
                ],
            ],
            [
                'title' => '3. Key Meetings / Engagements',
                'repeatable' => true,
                'fields' => [
                    ['label' => 'Date', 'required_on_submit' => false],
                    ['label' => 'Meeting / Engagement', 'required_on_submit' => false],
                    ['label' => 'Participant(s)', 'required_on_submit' => false],
                    ['label' => 'Location', 'required_on_submit' => false, 'options_key' => 'location_options'],
                    ['label' => 'Reactions / Notes', 'required_on_submit' => false],
                ],
            ],
            [
                'title' => '4. Budget',
                'repeatable' => true,
                'fields' => [
                    ['label' => 'Budget Item / Activity', 'required_on_submit' => false],
                    ['label' => 'Description / Purpose', 'required_on_submit' => false],
                    ['label' => 'Amount (TZS)', 'required_on_submit' => false],
                ],
            ],
        ],
    ],

    'weekly_report' => [
        'label' => 'Weekly Report',
        'about' => 'Records what was done during a completed work period. Created in a multi-step wizard: report content, optional action tasks, then review and submit.',
        'actions' => ['Save Draft', 'Continue', 'Submit (on review step)'],
        'sections' => [
            [
                'title' => 'Main Target (from weekly plan)',
                'fields' => [
                    ['label' => 'Main Target', 'required_on_submit' => false, 'notes' => 'Read-only display copied from the related weekly plan. Not an editable input.'],
                ],
            ],
            [
                'title' => '1. Summary of the Week',
                'fields' => [
                    ['label' => 'Summary', 'required_on_submit' => false, 'notes' => 'Describe what the week focused on and overall progress.'],
                ],
            ],
            [
                'title' => '2. Weekly Activity Progress',
                'repeatable' => true,
                'fields' => [
                    ['label' => 'Activity (from Weekly Plan)', 'required_on_submit' => false, 'notes' => 'Choose a planned activity, or Other (Not on Plan) and describe an off-plan activity.'],
                    ['label' => 'Progress', 'required_on_submit' => false, 'options' => [
                        'Done' => 'Done',
                        'Done and carry' => 'Done (recurring)',
                        'Partially done' => 'Carry to next week',
                        'Not started' => 'Not done',
                    ]],
                    ['label' => 'Remarks/Challenges', 'required_on_submit' => false],
                    ['label' => 'Next Step', 'required_on_submit' => false],
                ],
            ],
            [
                'title' => '3. Meetings Progress',
                'repeatable' => true,
                'fields' => [
                    ['label' => 'Date', 'required_on_submit' => false],
                    ['label' => 'Meeting / Engagement', 'required_on_submit' => false],
                    ['label' => 'Participant(s)', 'required_on_submit' => false],
                    ['label' => 'Status', 'required_on_submit' => false, 'options' => ['Done', 'Not done']],
                ],
                'notes' => 'Some meetings assigned from other plans may be view-only.',
            ],
            [
                'title' => '4. Key Achievements',
                'repeatable' => true,
                'fields' => [
                    ['label' => 'Achievement', 'required_on_submit' => false],
                ],
            ],
            [
                'title' => '5. Challenges & Mitigation',
                'repeatable' => true,
                'fields' => [
                    ['label' => 'Challenge(s)', 'required_on_submit' => false],
                    ['label' => 'Action Taken/Planned', 'required_on_submit' => false],
                ],
            ],
            [
                'title' => '6. Budget (from weekly plan)',
                'fields' => [
                    ['label' => 'Budget items', 'required_on_submit' => false, 'notes' => 'Read-only list from the related weekly plan (item, description, amount, review status). Not editable on the report form.'],
                ],
            ],
            [
                'title' => 'Wizard step 2 — Action tasks (conditional)',
                'fields' => [
                    ['label' => 'Progress status', 'required_on_submit' => false, 'options' => ['Not Started', 'In Progress', 'Done', 'Cancelled', 'Reassign']],
                    ['label' => 'Reassign to', 'required_on_submit' => false, 'notes' => 'Shown when progress is Reassign.'],
                    ['label' => 'Progress notes / Reason for reassignment', 'required_on_submit' => false],
                ],
                'notes' => 'Appears when the user has active action log tasks. Updated via a quick-update modal, not the main report form.',
            ],
            [
                'title' => 'Wizard step 3 — Review & Submit',
                'fields' => [
                    [
                        'label' => 'Target accomplishment status',
                        'required_on_submit' => true,
                        'options' => [
                            'completed' => 'Completed',
                            'partially_completed' => 'Partially completed',
                            'not_completed' => 'Not completed',
                        ],
                    ],
                    [
                        'label' => 'Why was the target not fully accomplished?',
                        'required_on_submit' => false,
                        'notes' => 'Required when status is partially completed or not completed.',
                    ],
                ],
            ],
        ],
    ],

];
