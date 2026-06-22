                            @php
                                $mplanned = is_array($content['planned'] ?? null) ? $content['planned'] : [];
                                $moff = is_array($content['off_plan'] ?? null) ? $content['off_plan'] : [];
                                $msum = trim((string) ($content['month_summary'] ?? ''));
                                $ypIds = collect($mplanned)->pluck('year_plan_activity_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
                                $ypActivities = $ypIds === []
                                    ? collect()
                                    : \App\Models\YearPlanActivity::with(['department.parent'])->whereIn('id', $ypIds)->get()->keyBy('id');
                                $monthTitle = \Carbon\Carbon::create((int) $document->year, (int) $document->month, 1)->format('F Y');
                            @endphp
                            <div class="p-4">
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                    <div>
                                        <h5 class="mb-0">Monthly report — {{ $monthTitle }}</h5>
                                        <small class="text-muted">Year plan alignment and other work</small>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2">
                                        <a href="{{ route('year-plan.show', $document->year) }}" class="btn btn-sm btn-outline-primary">Year plan {{ $document->year }}</a>
                                        <a href="{{ route('documents.monthly-report') }}" class="btn btn-sm btn-light">Monthly archive</a>
                                    </div>
                                </div>

                                @if($msum !== '')
                                    <div class="card shadow-none border mb-4">
                                        <div class="card-header bg-white border-bottom">
                                            <h6 class="mb-0"><i class="ti ti-notes me-2"></i>Month summary</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-0" style="white-space: pre-wrap;">{{ $msum }}</div>
                                        </div>
                                    </div>
                                @endif

                                @if(count($mplanned) > 0)
                                    <div class="card shadow-none border mb-4">
                                        <div class="card-header bg-white border-bottom">
                                            <h6 class="mb-0"><i class="ti ti-chart-line me-2"></i>Year plan activities (this month)</h6>
                                        </div>
                                        <div class="card-body p-0">
                                            <div class="table-responsive">
                                                <table class="table table-bordered table-striped mb-0 align-middle">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>Code</th>
                                                            <th>Activity</th>
                                                            <th>Status</th>
                                                            <th>Achievements</th>
                                                            <th>Challenges</th>
                                                            <th>Next steps</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($mplanned as $row)
                                                            @php $row = is_array($row) ? $row : []; @endphp
                                                            @php $act = $ypActivities->get((int) ($row['year_plan_activity_id'] ?? 0)); @endphp
                                                            <tr>
                                                                <td class="fw-semibold">{{ $act ? $act->displayCode() : '—' }}</td>
                                                                <td>
                                                                    @if($act)
                                                                        @if($act->department?->parent)
                                                                            <span class="text-muted small">{{ $act->department->parent->name }}</span><br>
                                                                        @endif
                                                                        <span class="fw-medium">{{ $act->department?->name ?? '—' }}</span>
                                                                        <p class="small text-muted mb-0 mt-1">{{ $act->description }}</p>
                                                                    @else
                                                                        <span class="text-muted">Activity #{{ $row['year_plan_activity_id'] ?? '—' }}</span>
                                                                    @endif
                                                                </td>
                                                                <td>{{ $row['implementation_status'] ?? '—' }}</td>
                                                                <td>{!! nl2br(e($row['achievements'] ?? '')) !!}</td>
                                                                <td>{!! nl2br(e($row['challenges'] ?? '')) !!}</td>
                                                                <td>{!! nl2br(e($row['next_steps'] ?? '')) !!}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                @if(count($moff) > 0)
                                    <div class="card shadow-none border mb-4">
                                        <div class="card-header bg-white border-bottom">
                                            <h6 class="mb-0"><i class="ti ti-plus me-2"></i>Work outside the year plan</h6>
                                        </div>
                                        <div class="card-body p-0">
                                            <div class="table-responsive">
                                                <table class="table table-bordered table-striped mb-0">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>Title</th>
                                                            <th>Description</th>
                                                            <th>Status</th>
                                                            <th>Notes</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($moff as $row)
                                                            @php $row = is_array($row) ? $row : []; @endphp
                                                            <tr>
                                                                <td>{{ $row['title'] ?? '' }}</td>
                                                                <td>{!! nl2br(e($row['description'] ?? '')) !!}</td>
                                                                <td>{{ $row['status'] ?? '' }}</td>
                                                                <td>{!! nl2br(e($row['notes'] ?? '')) !!}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                @if($msum === '' && count($mplanned) === 0 && count($moff) === 0)
                                    <div class="alert alert-light border">
                                        Structured report fields are empty. @if($document->file_path)Use the PDF tab above if this was filed as a document upload.@endif
                                    </div>
                                @endif

                                @if($document->attachment)
                                    <p class="small text-muted mb-0">
                                        <i class="ti ti-paperclip me-1"></i>
                                        <a href="{{ asset('storage/' . $document->attachment) }}" target="_blank">Supporting attachment</a>
                                    </p>
                                @endif
                            </div>
