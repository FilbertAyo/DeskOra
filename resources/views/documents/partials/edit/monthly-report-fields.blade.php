                            @php
                                $dContent = is_array($document->content) ? $document->content : [];
                                $mPlannedOld = old('content.planned', $dContent['planned'] ?? []);
                                $mOffPlanOld = old('content.off_plan', $dContent['off_plan'] ?? [['title' => '', 'description' => '', 'status' => '', 'notes' => '']]);
                                if (! is_array($mOffPlanOld) || count($mOffPlanOld) === 0) {
                                    $mOffPlanOld = [['title' => '', 'description' => '', 'status' => '', 'notes' => '']];
                                }
                                $implStatuses = ['Not started', 'In progress', 'Completed', 'Deferred', 'Not applicable'];
                                $editMonthName = \Carbon\Carbon::create((int) $document->year, (int) $document->month, 1)->format('F Y');
                            @endphp
                            <input type="hidden" name="month" value="{{ $document->month }}">
                            <input type="hidden" name="year" value="{{ $document->year }}">
                            <div class="col-12 mb-3">
                                <p class="text-muted mb-0">
                                    <i class="ti ti-calendar me-1"></i><strong>Reporting period:</strong> {{ $editMonthName }}
                                    <span class="mx-2">|</span>
                                    <a href="{{ route('documents.index', ['type' => 'monthly_report']) }}">Monthly archive</a>
                                </p>
                            </div>

                            <div class="col-12 mb-3">
                                <div class="card border-primary shadow-none">
                                    <div class="card-header bg-white border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                                        <div>
                                            <h6 class="mb-0"><i class="ti ti-chart-line me-2"></i>Monthly report vs year plan</h6>
                                            <small class="text-muted">Planned activities for this month in the author’s sub-departments</small>
                                        </div>
                                        <div class="d-flex flex-wrap gap-2">
                                            @if($monthlyYearPlan)
                                                <a href="{{ route('year-plan.show', $monthlyYearPlan->year) }}" class="btn btn-sm btn-outline-primary">
                                                    <i class="ti ti-external-link me-1"></i> Year plan {{ $monthlyYearPlan->year }}
                                                </a>
                                            @else
                                                <a href="{{ route('year-plan.index') }}" class="btn btn-sm btn-outline-secondary">Year plans</a>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        @if(!$monthlyYearPlan)
                                            <div class="alert alert-warning mb-3">No year plan exists for calendar year <strong>{{ $document->year }}</strong>.</div>
                                        @endif
                                        @if(empty($monthlyHasSubDepartments))
                                            <div class="alert alert-warning mb-3">This user is not assigned to any sub-department. Planned rows may be empty; off-plan reporting still applies.</div>
                                        @endif

                                        @if($monthlyPlannedActivities->isNotEmpty())
                                            <h6 class="text-primary mb-2">Planned activities (year plan)</h6>
                                            <div class="table-responsive mb-4">
                                                <table class="table table-bordered table-striped align-middle">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th style="min-width: 72px;">Code</th>
                                                            <th style="min-width: 200px;">Sub-department / activity</th>
                                                            <th style="min-width: 140px;">Status</th>
                                                            <th>Achievements / progress</th>
                                                            <th>Challenges</th>
                                                            <th>Next steps</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($monthlyPlannedActivities as $idx => $activity)
                                                            @php
                                                                $row = collect($mPlannedOld)->firstWhere('year_plan_activity_id', $activity->id);
                                                                $row = is_array($row) ? $row : [];
                                                            @endphp
                                                            <tr>
                                                                <td class="fw-semibold">{{ $activity->displayCode() }}</td>
                                                                <td>
                                                                    @if($activity->department?->parent)
                                                                        <span class="text-muted small">{{ $activity->department->parent->name }}</span><br>
                                                                    @endif
                                                                    <span class="fw-medium">{{ $activity->department?->name ?? '—' }}</span>
                                                                    <p class="small text-muted mb-0 mt-1">{{ \Illuminate\Support\Str::limit($activity->description, 220) }}</p>
                                                                    <input type="hidden" name="content[planned][{{ $idx }}][year_plan_activity_id]" value="{{ $activity->id }}">
                                                                </td>
                                                                <td>
                                                                    <select class="form-select form-select-sm" name="content[planned][{{ $idx }}][implementation_status]">
                                                                        @foreach($implStatuses as $opt)
                                                                            <option value="{{ $opt }}" {{ ($row['implementation_status'] ?? 'Not started') === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                                                                        @endforeach
                                                                    </select>
                                                                </td>
                                                                <td><textarea class="form-control form-control-sm" rows="2" name="content[planned][{{ $idx }}][achievements]" placeholder="What was delivered?">{{ $row['achievements'] ?? '' }}</textarea></td>
                                                                <td><textarea class="form-control form-control-sm" rows="2" name="content[planned][{{ $idx }}][challenges]" placeholder="Risks / blockers">{{ $row['challenges'] ?? '' }}</textarea></td>
                                                                <td><textarea class="form-control form-control-sm" rows="2" name="content[planned][{{ $idx }}][next_steps]" placeholder="Next month">{{ $row['next_steps'] ?? '' }}</textarea></td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        @elseif($monthlyYearPlan && $monthlyHasSubDepartments)
                                            <p class="text-muted mb-4">No year plan lines for this month in the author’s sub-departments.</p>
                                        @endif

                                        <h6 class="text-primary mb-2">Month summary</h6>
                                        <div class="mb-4">
                                            <textarea class="form-control @error('content.month_summary') is-invalid @enderror" name="content[month_summary]" rows="4" placeholder="Overall narrative">{{ old('content.month_summary', $dContent['month_summary'] ?? '') }}</textarea>
                                            @error('content.month_summary')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                                            <h6 class="text-primary mb-0">Work outside the year plan</h6>
                                            <button type="button" class="btn btn-sm btn-outline-primary" id="editAddMonthlyOffPlanRow">
                                                <i class="ti ti-plus me-1"></i> Add row
                                            </button>
                                        </div>
                                        <div class="table-responsive mb-2">
                                            <table class="table table-bordered table-striped align-middle">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Title</th>
                                                        <th>Description</th>
                                                        <th style="min-width: 120px;">Status</th>
                                                        <th>Notes</th>
                                                        <th style="width: 48px;"></th>
                                                    </tr>
                                                </thead>
                                                <tbody id="editMonthlyOffPlanBody">
                                                    @foreach($mOffPlanOld as $oi => $orow)
                                                        @php $orow = is_array($orow) ? $orow : []; @endphp
                                                        <tr>
                                                            <td><input type="text" class="form-control form-control-sm" name="content[off_plan][{{ $oi }}][title]" value="{{ $orow['title'] ?? '' }}"></td>
                                                            <td><textarea class="form-control form-control-sm" rows="2" name="content[off_plan][{{ $oi }}][description]">{{ $orow['description'] ?? '' }}</textarea></td>
                                                            <td><input type="text" class="form-control form-control-sm" name="content[off_plan][{{ $oi }}][status]" value="{{ $orow['status'] ?? '' }}"></td>
                                                            <td><textarea class="form-control form-control-sm" rows="2" name="content[off_plan][{{ $oi }}][notes]">{{ $orow['notes'] ?? '' }}</textarea></td>
                                                            <td class="text-center align-middle"><button type="button" class="btn btn-sm btn-outline-danger js-edit-remove-off-plan-row">&times;</button></td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                        @error('content.planned')
                                            <div class="text-danger small mb-2">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            @if($document->file_path)
                            <div class="col-12 mb-3">
                                <div class="alert alert-info">
                                    <h6 class="mb-2"><i class="ti ti-file me-2"></i>Optional PDF on file</h6>
                                    <a href="{{ $document->file_url }}" target="_blank" class="btn btn-sm btn-info text-white">
                                        <i class="ti ti-download me-1"></i> View / download
                                    </a>
                                </div>
                            </div>
                            @endif
                            <div class="col-md-6 mb-3">
                                <div class="form-group">
                                    <label for="file" class="form-label">Replace PDF <span class="text-muted">(optional)</span></label>
                                    <div class="input-group">
                                        <div class="custom-file">
                                            <input type="file" class="custom-file-input @error('file') is-invalid @enderror" name="file" id="file" accept=".pdf">
                                            <label class="custom-file-label" for="file" id="fileLabel">Choose PDF…</label>
                                        </div>
                                    </div>
                                    @error('file')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            @if($document->attachment)
                            <div class="col-12 mb-3">
                                <div class="alert alert-light border">
                                    <strong>Current attachment:</strong>
                                    <a href="{{ asset('storage/' . $document->attachment) }}" target="_blank">Open</a>
                                </div>
                            </div>
                            @endif
                            <div class="col-md-6 mb-3">
                                <div class="form-group">
                                    <label for="attachment" class="form-label">Replace attachment <span class="text-muted">(optional)</span></label>
                                    <div class="input-group">
                                        <div class="custom-file">
                                            <input type="file" class="custom-file-input @error('attachment') is-invalid @enderror" name="attachment" id="attachment" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xlsx,.xls">
                                            <label class="custom-file-label" for="attachment" id="attachmentLabel">Choose file…</label>
                                        </div>
                                    </div>
                                    @error('attachment')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            @endif
