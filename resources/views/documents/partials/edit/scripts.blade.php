    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('editDocumentForm');
            const submitBtn = document.getElementById('submitBtn');
            const fileInput = document.getElementById('file');
            const fileLabel = document.getElementById('fileLabel');
            const attachmentInput = document.getElementById('attachment');
            const attachmentLabel = document.getElementById('attachmentLabel');

            // Session monitoring for long-lived weekly edit forms.
            const isWeeklyForm = {!! json_encode(in_array($document->type, ['weekly_plan', 'weekly_report'], true)) !!};
            const sessionPingUrl = "{{ route('session.ping') }}";
            const sessionLifetimeMinutes = Number({!! json_encode((int) config('session.lifetime', 120)) !!});
            const startedAt = Date.now();
            let sessionWarningShown = false;
            let sessionExpiredHandled = false;

            function showNotifier(title, message, type) {
                if (!window.notifier || typeof window.notifier.show !== 'function') return;
                window.notifier.show(title, message, type || 'info', '', 6000);
            }

            function lockWeeklyForm() {
                const saveDraftBtn = document.getElementById('saveDraftBtn');
                const submitBtn = document.getElementById('submitBtn');
                if (saveDraftBtn) saveDraftBtn.disabled = true;
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="ti ti-loader spinning me-1"></i> Refreshing...';
                }
            }

            function startSessionMonitoring() {
                const lifetimeSeconds = Math.max(60, Math.floor(sessionLifetimeMinutes * 60));
                const localWarnAtSeconds = Math.floor(lifetimeSeconds * 0.9);

                const maybeWarnLocally = () => {
                    if (sessionWarningShown || sessionExpiredHandled) return;
                    const elapsedSeconds = (Date.now() - startedAt) / 1000;
                    if (elapsedSeconds >= localWarnAtSeconds) {
                        sessionWarningShown = true;
                        showNotifier(
                            'Session expiring',
                            'Your session will expire soon. Please save your work or refresh the page.',
                            'warning'
                        );
                    }
                };

                setInterval(maybeWarnLocally, 60000);

                setInterval(async () => {
                    if (sessionExpiredHandled) return;

                    try {
                        const res = await fetch(sessionPingUrl, {
                            method: 'GET',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            credentials: 'same-origin'
                        });

                        if (res.status === 200) return;

                        if (res.status === 401 || res.status === 419) {
                            sessionExpiredHandled = true;
                            showNotifier(
                                'Session expired',
                                'Please refresh the page and try again.',
                                'danger'
                            );
                            lockWeeklyForm();
                        }
                    } catch (e) {
                        // Ignore transient network failures.
                    }
                }, 120000);
            }

            if (isWeeklyForm) {
                startSessionMonitoring();
            }

            // Update file label when file is selected
            if (fileInput && fileLabel) {
                fileInput.addEventListener('change', function(e) {
                    const fileName = e.target.files[0]?.name || 'Choose new PDF file...';
                    fileLabel.textContent = fileName;
                });
            }

            const editMonthlyOffPlanBody = document.getElementById('editMonthlyOffPlanBody');
            const editAddMonthlyOffPlanRow = document.getElementById('editAddMonthlyOffPlanRow');
            if (editAddMonthlyOffPlanRow && editMonthlyOffPlanBody) {
                editAddMonthlyOffPlanRow.addEventListener('click', function () {
                    const idx = editMonthlyOffPlanBody.querySelectorAll('tr').length;
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td><input type="text" class="form-control form-control-sm" name="content[off_plan][${idx}][title]"></td>
                        <td><textarea class="form-control form-control-sm" rows="2" name="content[off_plan][${idx}][description]"></textarea></td>
                        <td><input type="text" class="form-control form-control-sm" name="content[off_plan][${idx}][status]"></td>
                        <td><textarea class="form-control form-control-sm" rows="2" name="content[off_plan][${idx}][notes]"></textarea></td>
                        <td class="text-center align-middle"><button type="button" class="btn btn-sm btn-outline-danger js-edit-remove-off-plan-row">&times;</button></td>
                    `;
                    editMonthlyOffPlanBody.appendChild(tr);
                });
                editMonthlyOffPlanBody.addEventListener('click', function (e) {
                    const btn = e.target.closest('.js-edit-remove-off-plan-row');
                    if (!btn) return;
                    const rows = editMonthlyOffPlanBody.querySelectorAll('tr');
                    if (rows.length <= 1) return;
                    btn.closest('tr').remove();
                });
            }

            // Update attachment label when file is selected
            if (attachmentInput && attachmentLabel) {
                attachmentInput.addEventListener('change', function(e) {
                    const fileName = e.target.files[0]?.name || 'Choose new attachment file...';
                    attachmentLabel.textContent = fileName;
                });
            }

            // Handle target accomplishment dropdown for weekly reports
            const targetAccomplishedSelect = document.getElementById('target_accomplished_status');
            const targetExplanationContainer = document.getElementById('targetExplanationContainer');
            const targetExplanationTextarea = document.getElementById('target_explanation');
            const addActivityRowBtn = document.getElementById('addActivityRowBtn');
            const plannedActivitiesBody = document.getElementById('plannedActivitiesBody');
            const addMeetingRowBtn = document.getElementById('addMeetingRowBtn');
            const keyMeetingsBody = document.getElementById('keyMeetingsBody');
            const addBudgetRowBtn = document.getElementById('addBudgetRowBtn');
            const budgetBody = document.getElementById('budgetBody');
            const addReportActivityRowBtn = document.getElementById('addReportActivityRowBtn');
            const reportActivityProgressBody = document.getElementById('reportActivityProgressBody');
            const addReportMeetingRowBtn = document.getElementById('addReportMeetingRowBtn');
            const reportMeetingsBody = document.getElementById('reportMeetingsBody');
            const addAchievementRowBtn = document.getElementById('addAchievementRowBtn');
            const achievementsBody = document.getElementById('achievementsBody');
            const addChallengeRowBtn = document.getElementById('addChallengeRowBtn');
            const challengesMitigationBody = document.getElementById('challengesMitigationBody');
            const reportActivityOptionsSource = reportActivityProgressBody
                ? reportActivityProgressBody.querySelector('select[name^="content[activity_progress]"][name$="[activity_output]"]')
                : null;
            window.__reportActivityOptionsHtml = reportActivityOptionsSource ? reportActivityOptionsSource.innerHTML : '<option value="">-- Select Activity --</option>';

            function buildStaffCheckboxFieldHtml(name, selectedIds, options) {
                options = options || {};
                if (typeof window.StaffCheckboxSelect === 'undefined') {
                    return '';
                }

                return window.StaffCheckboxSelect.buildHtml({
                    users: window.StaffCheckboxSelect.getTeamUsers(),
                    name: name,
                    selected: selectedIds || [],
                    showTitle: false,
                    compact: true,
                    disabled: !!options.disabled,
                });
            }

            function updateReportActivityRowNumbers() {
                if (!reportActivityProgressBody) return;
                reportActivityProgressBody.querySelectorAll('.js-report-activity-row-no').forEach((cell, idx) => {
                    cell.textContent = String(idx + 1);
                });
            }
            function updateAchievementRowNumbers() {
                if (!achievementsBody) return;
                achievementsBody.querySelectorAll('.js-achievement-row-no').forEach((cell, idx) => {
                    cell.textContent = String(idx + 1);
                });
            }
            function syncOffPlanInputForSelect(selectEl) {
                if (!selectEl) return;
                const cell = selectEl.closest('td');
                if (!cell) return;
                const wrap = cell.querySelector('[data-off-plan-input-wrap="true"]');
                const input = wrap ? wrap.querySelector('input[name$="[off_plan_activity]"]') : null;
                const showOffPlan = selectEl.value === '__off_plan__';
                if (!wrap || !input) return;
                wrap.style.display = showOffPlan ? '' : 'none';
                input.required = showOffPlan;
                if (!showOffPlan) {
                    input.value = '';
                }
            }

            function toggleTargetExplanation() {
                if (targetAccomplishedSelect && targetExplanationContainer && targetExplanationTextarea) {
                    const value = targetAccomplishedSelect.value;
                    if (value === 'not_completed' || value === 'partially_completed') {
                        // Not/partially accomplished - show explanation
                        targetExplanationContainer.style.display = '';
                        targetExplanationTextarea.required = true;
                    } else {
                        // Completed - hide explanation
                        targetExplanationContainer.style.display = 'none';
                        targetExplanationTextarea.required = false;
                        if (value === 'completed') {
                            targetExplanationTextarea.value = '';
                        }
                    }
                }
            }

            if (targetAccomplishedSelect) {
                targetAccomplishedSelect.addEventListener('change', toggleTargetExplanation);
                // Check initial state
                toggleTargetExplanation();
            }

            if (addActivityRowBtn && plannedActivitiesBody) {
                let activityIndex = plannedActivitiesBody.querySelectorAll('tr').length;

                addActivityRowBtn.addEventListener('click', function() {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>
                            <select class="form-select" name="content[planned_activities][${activityIndex}][day]">
                                <option value="">-- Select --</option>
                                <option value="Monday">Monday</option>
                                <option value="Tuesday">Tuesday</option>
                                <option value="Wednesday">Wednesday</option>
                                <option value="Thursday">Thursday</option>
                                <option value="Friday">Friday</option>
                                <option value="Saturday">Saturday</option>
                            </select>
                        </td>
                        <td><input type="hidden" name="content[planned_activities][${activityIndex}][activity_uid]" value=""><input type="hidden" name="content[planned_activities][${activityIndex}][carry_over_source_weekly_report_id]" value=""><textarea class="form-control" rows="5" name="content[planned_activities][${activityIndex}][activity]" placeholder="Activity"></textarea></td>
                        <td>
                            <select class="form-select" name="content[planned_activities][${activityIndex}][location]">
                                <option value="">-- Select --</option>
                                <option value="Office">Office</option>
                                <option value="Field">Field</option>
                                <option value="Virtual">Virtual</option>
                                <option value="On site">On site</option>
                            </select>
                        </td>
                        <td>
                            ${buildStaffCheckboxFieldHtml(`content[planned_activities][${activityIndex}][team_member_ids][]`)}
                        </td>
                        <td><textarea class="form-control" rows="5" name="content[planned_activities][${activityIndex}][notes_inclusion]" placeholder="Notes / inclusion"></textarea></td>
                        <td class="text-center align-middle"><button type="button" class="btn btn-sm btn-outline-danger remove-activity-row">&times;</button></td>
                    `;
                    if (plannedActivitiesBody.firstChild) {
                        plannedActivitiesBody.insertBefore(row, plannedActivitiesBody.firstChild);
                    } else {
                        plannedActivitiesBody.appendChild(row);
                    }
                    activityIndex++;
                });

                plannedActivitiesBody.addEventListener('click', function(event) {
                    const btn = event.target.closest('.remove-activity-row');
                    if (!btn) return;

                    const rows = plannedActivitiesBody.querySelectorAll('tr');
                    if (rows.length <= 1) return;
                    btn.closest('tr')?.remove();
                });
            }

            if (addMeetingRowBtn && keyMeetingsBody) {
                let meetingIndex = keyMeetingsBody.querySelectorAll('tr').length;

                addMeetingRowBtn.addEventListener('click', function() {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td><input type="date" class="form-control" name="content[key_meetings][${meetingIndex}][date]"></td>
                        <td><input type="hidden" name="content[key_meetings][${meetingIndex}][meeting_uid]" value=""><input class="form-control" name="content[key_meetings][${meetingIndex}][meeting]" placeholder="Meeting / engagement"></td>
                        <td>
                            ${buildStaffCheckboxFieldHtml(`content[key_meetings][${meetingIndex}][participant_user_ids][]`)}
                        </td>
                        <td>
                            <select class="form-select" name="content[key_meetings][${meetingIndex}][location]">
                                <option value="">-- Select --</option>
                                <option value="Office">Office</option>
                                <option value="Field">Field</option>
                                <option value="Virtual">Virtual</option>
                                <option value="On site">On site</option>
                            </select>
                        </td>
                        <td><textarea class="form-control" rows="5" name="content[key_meetings][${meetingIndex}][reactions_notes]" placeholder="What happened?"></textarea></td>
                        <td class="text-center align-middle"><button type="button" class="btn btn-sm btn-outline-danger remove-meeting-row">&times;</button></td>
                    `;
                    keyMeetingsBody.appendChild(row);
                    meetingIndex++;
                });

                keyMeetingsBody.addEventListener('click', function(event) {
                    const btn = event.target.closest('.remove-meeting-row');
                    if (!btn) return;

                    const rows = keyMeetingsBody.querySelectorAll('tr');
                    if (rows.length <= 1) return;
                    btn.closest('tr')?.remove();
                });
            }

            if (addBudgetRowBtn && budgetBody) {
                let budgetIndex = budgetBody.querySelectorAll('tr').length;

                addBudgetRowBtn.addEventListener('click', function() {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td><input class="form-control" name="content[budget][${budgetIndex}][item]" placeholder="e.g., Printing books .."></td>
                        <td><textarea class="form-control" rows="5" name="content[budget][${budgetIndex}][description_purpose]" placeholder="Description / purpose"></textarea></td>
                        <td><input class="form-control" inputmode="numeric" pattern="^[0-9,]+(\.[0-9]{1,2})?$" title="Enter amount like 100,000" name="content[budget][${budgetIndex}][amount]" placeholder="e.g., 100,000"></td>
                        <td class="text-center align-middle"><button type="button" class="btn btn-sm btn-outline-danger remove-budget-row">&times;</button></td>
                    `;
                    budgetBody.appendChild(row);
                    budgetIndex++;
                });

                budgetBody.addEventListener('click', function(event) {
                    const btn = event.target.closest('.remove-budget-row');
                    if (!btn) return;

                    const rows = budgetBody.querySelectorAll('tr');
                    if (rows.length <= 1) return;
                    btn.closest('tr')?.remove();
                });
            }

            if (addReportActivityRowBtn && reportActivityProgressBody) {
                let reportActivityIndex = reportActivityProgressBody.querySelectorAll('tr').length;

                addReportActivityRowBtn.addEventListener('click', function() {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td class="text-center align-middle js-report-activity-row-no"></td>
                        <td>
                            <select class="form-select" name="content[activity_progress][${reportActivityIndex}][activity_output]" data-off-plan-select="true">
                                ${window.__reportActivityOptionsHtml || '<option value="">-- Select Activity --</option>'}
                            </select>
                            <div class="mt-2" data-off-plan-input-wrap="true" style="display:none;">
                                <input type="text" class="form-control" name="content[activity_progress][${reportActivityIndex}][off_plan_activity]" placeholder="Describe off-plan activity">
                            </div>
                        </td>
                        <td>
                            <select class="form-select" name="content[activity_progress][${reportActivityIndex}][progress]">
                                <option value="">-- Select --</option>
                                <option value="Done">Done</option>
                                <option value="Done and carry">Done (recurring)</option>
                                <option value="Partially done">Carry to next week</option>
                                <option value="Not started">Not done</option>
                            </select>
                        </td>
                        <td><textarea class="form-control" rows="5" name="content[activity_progress][${reportActivityIndex}][remarks_challenges]" placeholder="Remarks/Challenges"></textarea></td>
                        <td><textarea class="form-control" rows="5" name="content[activity_progress][${reportActivityIndex}][next_step]" placeholder="Next step"></textarea></td>
                        <td class="text-center align-middle"><button type="button" class="btn btn-sm btn-outline-danger remove-report-activity-row">&times;</button></td>
                    `;
                    if (reportActivityProgressBody.firstChild) {
                        reportActivityProgressBody.insertBefore(row, reportActivityProgressBody.firstChild);
                    } else {
                        reportActivityProgressBody.appendChild(row);
                    }
                    reportActivityIndex++;
                    updateReportActivityRowNumbers();
                    syncOffPlanInputForSelect(row.querySelector('[data-off-plan-select="true"]'));
                });

                reportActivityProgressBody.addEventListener('click', function(event) {
                    const btn = event.target.closest('.remove-report-activity-row');
                    if (!btn) return;
                    const rows = reportActivityProgressBody.querySelectorAll('tr');
                    if (rows.length <= 1) return;
                    btn.closest('tr')?.remove();
                    updateReportActivityRowNumbers();
                });

                updateReportActivityRowNumbers();
                reportActivityProgressBody.querySelectorAll('[data-off-plan-select="true"]').forEach((selectEl) => {
                    syncOffPlanInputForSelect(selectEl);
                });
                reportActivityProgressBody.addEventListener('change', function(event) {
                    const selectEl = event.target.closest('[data-off-plan-select="true"]');
                    if (!selectEl) return;
                    syncOffPlanInputForSelect(selectEl);
                });
            }

            if (addAchievementRowBtn && achievementsBody) {
                let achievementIndex = achievementsBody.querySelectorAll('tr').length;
                addAchievementRowBtn.addEventListener('click', function() {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td class="text-center align-middle js-achievement-row-no"></td>
                        <td><textarea class="form-control" name="content[key_achievements][${achievementIndex}]" rows="5" placeholder="Achievement"></textarea></td>
                        <td class="text-center align-middle"><button type="button" class="btn btn-sm btn-outline-danger remove-achievement-row">&times;</button></td>
                    `;
                    achievementsBody.appendChild(row);
                    achievementIndex++;
                    updateAchievementRowNumbers();
                });
                achievementsBody.addEventListener('click', function(event) {
                    const btn = event.target.closest('.remove-achievement-row');
                    if (!btn) return;
                    const rows = achievementsBody.querySelectorAll('tr');
                    if (rows.length <= 1) return;
                    btn.closest('tr')?.remove();
                    updateAchievementRowNumbers();
                });
                updateAchievementRowNumbers();
            }

            if (addReportMeetingRowBtn && reportMeetingsBody) {
                let reportMeetingIndex = reportMeetingsBody.querySelectorAll('tr').length;

                addReportMeetingRowBtn.addEventListener('click', function() {
                    const row = document.createElement('tr');
                    row.setAttribute('data-report-meeting-readonly', '0');
                    row.innerHTML = `
                        <td>
                            <input type="hidden" name="content[key_meetings][${reportMeetingIndex}][meeting_uid]" value="">
                            <input type="hidden" name="content[key_meetings][${reportMeetingIndex}][source_type]" value="report_manual">
                            <input type="hidden" name="content[key_meetings][${reportMeetingIndex}][source_plan_id]" value="">
                            <input type="hidden" name="content[key_meetings][${reportMeetingIndex}][source_owner_user_id]" value="{{ (string) auth()->id() }}">
                            <input type="hidden" name="content[key_meetings][${reportMeetingIndex}][is_readonly_for_current_user]" value="0">
                            <input type="hidden" name="content[key_meetings][${reportMeetingIndex}][location]" value="">
                            <input type="hidden" name="content[key_meetings][${reportMeetingIndex}][reactions_notes]" value="">
                            <input type="date" class="form-control" name="content[key_meetings][${reportMeetingIndex}][date]">
                        </td>
                        <td><input class="form-control" name="content[key_meetings][${reportMeetingIndex}][meeting]" placeholder="Meeting / engagement"></td>
                        <td>
                            ${buildStaffCheckboxFieldHtml(`content[key_meetings][${reportMeetingIndex}][participant_user_ids][]`)}
                        </td>
                        <td>
                            <select class="form-select" name="content[key_meetings][${reportMeetingIndex}][status]">
                                <option value="">-- Select --</option>
                                <option value="Done">Done</option>
                                <option value="Not done">Not done</option>
                            </select>
                        </td>
                        <td class="text-center align-middle"><button type="button" class="btn btn-sm btn-outline-danger remove-report-meeting-row">&times;</button></td>
                    `;
                    reportMeetingsBody.appendChild(row);
                    reportMeetingIndex++;
                });

                reportMeetingsBody.addEventListener('click', function(event) {
                    const btn = event.target.closest('.remove-report-meeting-row');
                    if (!btn) return;
                    const row = btn.closest('tr');
                    if (!row || row.getAttribute('data-report-meeting-readonly') === '1') return;
                    const rows = reportMeetingsBody.querySelectorAll('tr');
                    if (rows.length <= 1) return;
                    row.remove();
                });
            }

            if (addChallengeRowBtn && challengesMitigationBody) {
                let challengeIndex = challengesMitigationBody.querySelectorAll('tr').length;
                addChallengeRowBtn.addEventListener('click', function() {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td><textarea class="form-control" rows="5" name="content[challenges_mitigation][${challengeIndex}][challenge]" placeholder="Challenge"></textarea></td>
                        <td><textarea class="form-control" rows="5" name="content[challenges_mitigation][${challengeIndex}][action_taken_planned]" placeholder="Action taken/planned"></textarea></td>
                        <td class="text-center align-middle"><button type="button" class="btn btn-sm btn-outline-danger remove-challenge-row">&times;</button></td>
                    `;
                    challengesMitigationBody.appendChild(row);
                    challengeIndex++;
                });
                challengesMitigationBody.addEventListener('click', function(event) {
                    const btn = event.target.closest('.remove-challenge-row');
                    if (!btn) return;
                    const rows = challengesMitigationBody.querySelectorAll('tr');
                    if (rows.length <= 1) return;
                    btn.closest('tr')?.remove();
                });
            }

            // Ensure action is set when buttons are clicked
            const saveDraftBtn = document.getElementById('saveDraftBtn');
            const hiddenActionInput = document.getElementById('hiddenAction');
            
            if (saveDraftBtn && hiddenActionInput) {
                saveDraftBtn.addEventListener('click', function() {
                    hiddenActionInput.value = 'draft';
                });
            }

            const continueBtn = document.getElementById('continueBtn');
            if (continueBtn && hiddenActionInput) {
                continueBtn.addEventListener('click', function() {
                    hiddenActionInput.value = 'continue';
                });
            }
            
            if (submitBtn && hiddenActionInput) {
                submitBtn.addEventListener('click', function() {
                    hiddenActionInput.value = 'submit';
                });
            }

            const documentType = {!! json_encode($document->type) !!};

            form.addEventListener('submit', function(e) {
                const clickedButton = e.submitter;
                const action = clickedButton?.value || document.getElementById('hiddenAction')?.value || 'draft';
                
                // Ensure the hidden action field is set to the correct value
                if (hiddenActionInput) {
                    hiddenActionInput.value = action;
                }

                // Submission-time guards (parity with create screen).
                // Drafts may be saved incomplete; submissions must be valid.
                if (action === 'submit') {
                    if (documentType === 'weekly_plan') {
                        const mainTarget = document.getElementById('main_target');
                        if (mainTarget && !mainTarget.value.trim()) {
                            e.preventDefault();
                            alert('Please enter the main target for this week before submitting.');
                            mainTarget.focus();
                            return false;
                        }
                    }
                }
                
                // Validate file if provided
                if (fileInput && fileInput.files && fileInput.files.length > 0) {
                    const file = fileInput.files[0];
                    
                    // Validate file type
                    if (file.type !== 'application/pdf') {
                        e.preventDefault();
                        alert('Please select a valid PDF file.');
                        return false;
                    }

                    // Validate file size (10MB)
                    if (file.size > 10 * 1024 * 1024) {
                        e.preventDefault();
                        alert('File size must be less than 10MB.');
                        return false;
                    }
                }
                
                if (action === 'submit') {
                    if (!confirm('Are you sure you want to submit this document? Once submitted, it cannot be edited.')) {
                        e.preventDefault();
                        return false;
                    }
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="ti ti-loader spinning me-1"></i> Submitting...';
                    }
                } else {
                    const saveDraftBtn = document.getElementById('saveDraftBtn');
                    if (saveDraftBtn) {
                        saveDraftBtn.disabled = true;
                        saveDraftBtn.innerHTML = '<i class="ti ti-loader spinning me-1"></i> Saving...';
                    }
                }
            });
        });
    </script>
