                                @php
                                    $content = is_array($document->content) ? $document->content : [];
                                    $plannedActivities = $content['planned_activities'] ?? [];
                                    $keyMeetings = $content['key_meetings'] ?? [];
                                    $budget = $content['budget'] ?? [];
                                    $objective = $content['objective'] ?? [];
                                @endphp

                            <div class="col-12 mb-3">
                                <div class="card border-primary-subtle">
                                    <div class="card-header bg-white border-bottom">
                                        <h6 class="mb-0"><i class="ti ti-users-group me-2"></i>Collaboration</h6>
                                    </div>
                                    <div class="card-body">
                                        @php
                                            $selectedSharedUsers = old('shared_user_ids', $document->sharedUsers->pluck('id')->all());
                                            if (!is_array($selectedSharedUsers)) $selectedSharedUsers = [];
                                        @endphp
                                        <label for="shared_user_ids" class="form-label">Shared staff (same department)</label>
                                        <select class="form-select @error('shared_user_ids') is-invalid @enderror" id="shared_user_ids" name="shared_user_ids[]" multiple>
                                            @foreach(($shareableUsers ?? collect()) as $shareUser)
                                                <option value="{{ $shareUser->id }}" {{ in_array((string)$shareUser->id, array_map('strval', $selectedSharedUsers), true) ? 'selected' : '' }}>
                                                    {{ $shareUser->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('shared_user_ids')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                        <small class="form-text text-muted">Remove staff from this list to unmerge them from this weekly plan.</small>
                                    </div>
                                </div>
                            </div>

                            @include('documents.partials.weekly-assigned-activities-card')

                            <div class="col-12 mb-3">
                                    <div class="card border-primary">
                                        <div class="card-header bg-white border-bottom">
                                            <h6 class="mb-0"><i class="ti ti-target me-2"></i>1. Key Objective for the Week</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                    <label for="main_target" class="form-label">
                                                    Objective (s) / Main Target (s) <span class="text-danger">*</span>
                                    </label>
                                    <textarea class="form-control @error('main_target') is-invalid @enderror"
                                        name="main_target"
                                        id="main_target"
                                                    rows="5"
                                                    required
                                                    placeholder="Enter the main objective for this week">{{ old('main_target', $document->main_target) }}</textarea>
                                    @error('main_target')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                                <small class="form-text text-muted">
                                                    This becomes the "Main Target(s)" used by weekly reports.
                                                </small>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label">Status</label>
                                                    <select class="form-select" name="content[objective][status]">
                                                        <option value="" {{ old('content.objective.status', $objective['status'] ?? '') ? '' : 'selected' }}>-- Select Status --</option>
                                                        @foreach(['Not started', 'Completed', 'Carried', 'In Progress'] as $opt)
                                                            <option value="{{ $opt }}" {{ old('content.objective.status', $objective['status'] ?? '') === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-md-8 mb-3">
                                                    <label class="form-label">Reflection / Progress</label>
                                                    <textarea class="form-control" name="content[objective][reflection_progress]" rows="5"
                                                        placeholder="What progress/reflection did you have?">{{ old('content.objective.reflection_progress', $objective['reflection_progress'] ?? '') }}</textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 mb-3">
                                    <div class="card border-secondary">
                                        <div class="card-header bg-white border-bottom">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0"><i class="ti ti-calendar me-2"></i>2. Planned Activities</h6>
                                                <button type="button" class="btn btn-sm btn-outline-primary" id="addActivityRowBtn">
                                                    <i class="ti ti-plus me-1"></i> Add Activity
                                                </button>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table table-bordered align-middle mb-0">
                                                    <thead class="thead-light">
                                                        <tr>
                                                            <th style="width: 100px;">Day</th>
                                                            <th>Activity</th>
                                                            <th style="width: 130px;">Location</th>
                                                            <th style="width: 220px;">Staff/Team</th>
                                                            <th style="width: 250px;">Notes / Inclusion</th>
                                                            <th style="width: 30px;"></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="plannedActivitiesBody">
                                                        @php
                                                            $dayOptions = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                                                            $activityRows = old('content.planned_activities', $plannedActivities ?? []);
                                                            if (!is_array($activityRows) || empty($activityRows)) {
                                                                $activityRows = [['day' => 'Monday', 'activity' => '', 'location' => '', 'team_member_ids' => [], 'notes_inclusion' => '']];
                                                            }
                                                        @endphp
                                                        @foreach($activityRows as $i => $activityRow)
                                                            @php $row = is_array($activityRow) ? $activityRow : []; @endphp
                                                            <tr>
                                                                <td>
                                                                    <select class="form-select" name="content[planned_activities][{{ $i }}][day]">
                                                                        <option value="">-- Select --</option>
                                                                        @foreach($dayOptions as $dayOpt)
                                                                            <option value="{{ $dayOpt }}" {{ old("content.planned_activities.$i.day", $row['day'] ?? '') === $dayOpt ? 'selected' : '' }}>{{ $dayOpt }}</option>
                                                                        @endforeach
                                                                    </select>
                                                                </td>
                                                                <td>
                                                                    <input type="hidden"
                                                                        name="content[planned_activities][{{ $i }}][activity_uid]"
                                                                        value="{{ old("content.planned_activities.$i.activity_uid", $row['activity_uid'] ?? '') }}">
                                                                    <textarea class="form-control" rows="5"
                                                                        name="content[planned_activities][{{ $i }}][activity]"
                                                                        placeholder="Activity">{{ old("content.planned_activities.$i.activity", $row['activity'] ?? '') }}</textarea>
                                                                </td>
                                                                <td>
                                                                    <select class="form-select"
                                                                        name="content[planned_activities][{{ $i }}][location]"
                                                                    >
                                                                        <option value="">-- Select --</option>
                                                                        @foreach(['Office', 'Field', 'Virtual', 'On site'] as $loc)
                                                                            <option value="{{ $loc }}" {{ old("content.planned_activities.$i.location", $row['location'] ?? '') === $loc ? 'selected' : '' }}>{{ $loc }}</option>
                                                                        @endforeach
                                                                    </select>
                                                                </td>
                                                                <td>
                                                                    @php
                                                                        $selectedTeamIds = old("content.planned_activities.$i.team_member_ids", $row['team_member_ids'] ?? []);
                                                                        if (!is_array($selectedTeamIds)) $selectedTeamIds = [];
                                                                    @endphp
                                                                    <select class="form-select" multiple
                                                                        name="content[planned_activities][{{ $i }}][team_member_ids][]">
                                                                        @foreach(($teamUsers ?? collect()) as $teamUser)
                                                                            <option value="{{ $teamUser->id }}" {{ in_array((string)$teamUser->id, array_map('strval', $selectedTeamIds), true) ? 'selected' : '' }}>
                                                                                {{ $teamUser->name }}
                                                                            </option>
                                                                        @endforeach
                                                                    </select>
                                                                </td>
                                                                <td>
                                                                    <textarea class="form-control" rows="5"
                                                                        name="content[planned_activities][{{ $i }}][notes_inclusion]"
                                                                        placeholder="Notes / inclusion">{{ old("content.planned_activities.$i.notes_inclusion", $row['notes_inclusion'] ?? '') }}</textarea>
                                                                </td>
                                                                <td class="text-center align-middle">
                                                                    <button type="button" class="btn btn-sm btn-outline-danger remove-activity-row">&times;</button>
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 mb-3">
                                    <div class="card border-info">
                                        <div class="card-header bg-white border-bottom">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0"><i class="ti ti-users me-2"></i>3. Key Meetings / Engagements</h6>
                                                <button type="button" class="btn btn-sm btn-outline-primary" id="addMeetingRowBtn">
                                                    <i class="ti ti-plus me-1"></i> Add Meeting
                                                </button>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table table-bordered align-middle mb-0">
                                                    <thead class="thead-light">
                                                        <tr>
                                                            <th style="width: 150px;">Date</th>
                                                            <th>Meeting / Engagement</th>
                                                            <th style="width: 200px;">Participant(s)</th>
                                                            <th style="width: 180px;">Location</th>
                                                            <th>Reactions / Notes</th>
                                                            <th style="width: 30px;"></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="keyMeetingsBody">
                                                        @php
                                                            $meetingRows = old('content.key_meetings', $keyMeetings ?? []);
                                                            if (!is_array($meetingRows) || empty($meetingRows)) {
                                                                $meetingRows = [['date' => '', 'meeting' => '', 'participants' => '', 'location' => '', 'reactions_notes' => '']];
                                                            }
                                                        @endphp
                                                        @foreach($meetingRows as $i => $meetingRow)
                                                            @php $row = is_array($meetingRow) ? $meetingRow : []; @endphp
                                                            <tr>
                                                                <td>
                                                                    <input type="date" class="form-control"
                                                                        name="content[key_meetings][{{ $i }}][date]"
                                                                        value="{{ old("content.key_meetings.$i.date", $row['date'] ?? '') }}">
                                                                </td>
                                                                <td>
                                                                    <input class="form-control"
                                                                        name="content[key_meetings][{{ $i }}][meeting]"
                                                                        value="{{ old("content.key_meetings.$i.meeting", $row['meeting'] ?? '') }}"
                                                                        placeholder="e.g., MIT Engagement ...">
                                                                </td>
                                                                <td>
                                                                    <input class="form-control"
                                                                        name="content[key_meetings][{{ $i }}][participants]"
                                                                        value="{{ old("content.key_meetings.$i.participants", $row['participants'] ?? '') }}"
                                                                        placeholder="e.g., Dr. Theo / DUCE / Name of institution">
                                                                </td>
                                                                <td>
                                                                    <select class="form-select"
                                                                        name="content[key_meetings][{{ $i }}][location]"
                                                                    >
                                                                        <option value="">-- Select --</option>
                                                                        @foreach(['Office', 'Field', 'Virtual', 'On site'] as $loc)
                                                                            <option value="{{ $loc }}" {{ old("content.key_meetings.$i.location", $row['location'] ?? '') === $loc ? 'selected' : '' }}>{{ $loc }}</option>
                                                                        @endforeach
                                                                    </select>
                                                                </td>
                                                                <td>
                                                                    <textarea class="form-control" rows="5"
                                                                        name="content[key_meetings][{{ $i }}][reactions_notes]"
                                                                        placeholder="Notes">{{ old("content.key_meetings.$i.reactions_notes", $row['reactions_notes'] ?? '') }}</textarea>
                                                                </td>
                                                                <td class="text-center align-middle">
                                                                    <button type="button" class="btn btn-sm btn-outline-danger remove-meeting-row">&times;</button>
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 mb-3">
                                    <div class="card border-success">
                                        <div class="card-header bg-white border-bottom">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0"><i class="ti ti-dollar-sign me-2"></i>4. Budget</h6>
                                                <button type="button" class="btn btn-sm btn-outline-primary" id="addBudgetRowBtn">
                                                    <i class="ti ti-plus me-1"></i> Add Budget
                                                </button>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table table-bordered align-middle mb-0">
                                                    <thead class="thead-light">
                                                        <tr>
                                                            <th>Budget Item / Activity</th>
                                                            <th style="width: 600px;">Description / Purpose</th>
                                                            <th style="width: 180px;">Amount (TZS)</th>
                                                            <th style="width: 30px;"></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="budgetBody">
                                                        @php
                                                            $budgetRows = old('content.budget', $budget ?? []);
                                                            if (!is_array($budgetRows) || empty($budgetRows)) {
                                                                $budgetRows = [['item' => '', 'description_purpose' => '', 'amount' => '']];
                                                            }
                                                        @endphp
                                                        @foreach($budgetRows as $i => $budgetRow)
                                                            @php $row = is_array($budgetRow) ? $budgetRow : []; @endphp
                                                            <tr>
                                                                <td>
                                                                    <input class="form-control"
                                                                        name="content[budget][{{ $i }}][item]"
                                                                        value="{{ old("content.budget.$i.item", $row['item'] ?? '') }}"
                                                                        placeholder="e.g., Printing books ..">
                                                                </td>
                                                                <td>
                                                                    <textarea class="form-control" rows="5"
                                                                        name="content[budget][{{ $i }}][description_purpose]"
                                                                        placeholder="Purpose">{{ old("content.budget.$i.description_purpose", $row['description_purpose'] ?? '') }}</textarea>
                                                                </td>
                                                                <td>
                                                                    <input class="form-control"
                                                                        name="content[budget][{{ $i }}][amount]"
                                                                        inputmode="numeric"
                                                                        pattern="^[0-9,]+(\.[0-9]{1,2})?$"
                                                                        title="Enter amount like 100,000"
                                                                        value="{{ old("content.budget.$i.amount", $row['amount'] ?? '') }}"
                                                                        placeholder="e.g., 100,000">
                                                                </td>
                                                                <td class="text-center align-middle">
                                                                    <button type="button" class="btn btn-sm btn-outline-danger remove-budget-row">&times;</button>
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                </div>
                            </div>
