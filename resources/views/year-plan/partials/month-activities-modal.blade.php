<div class="modal fade" id="monthActivitiesModal" tabindex="-1" aria-labelledby="monthActivitiesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="monthActivitiesModalLabel">Month activities</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div id="monthActivitiesEmpty" class="text-center text-muted py-5 px-3 d-none">
                    No activities planned for this month.
                </div>
                <div class="table-responsive" id="monthActivitiesTableWrap">
                    <table class="table table-bordered table-hover table-striped mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 90px;">Code</th>
                                <th style="width: 180px;">Department</th>
                                <th>Description</th>
                                <th class="text-end" style="width: 130px;">Total (TZS)</th>
                            </tr>
                        </thead>
                        <tbody id="monthActivitiesTableBody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                <a href="#" class="btn btn-outline-primary" id="monthActivitiesOpenYearPlan">
                    <i class="ti ti-external-link me-1"></i> Open year plan
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const modalEl = document.getElementById('monthActivitiesModal');
        if (!modalEl) {
            return;
        }

        const titleEl = document.getElementById('monthActivitiesModalLabel');
        const emptyEl = document.getElementById('monthActivitiesEmpty');
        const tableWrapEl = document.getElementById('monthActivitiesTableWrap');
        const tableBodyEl = document.getElementById('monthActivitiesTableBody');
        const openYearPlanEl = document.getElementById('monthActivitiesOpenYearPlan');

        modalEl.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            if (!button || !button.classList.contains('month-activity-badge')) {
                return;
            }

            const year = button.getAttribute('data-year') || '';
            const monthLabel = button.getAttribute('data-month-label') || '';
            const activitiesB64 = button.getAttribute('data-activities-b64') || '';
            const showUrl = button.getAttribute('data-show-url') || '#';

            titleEl.textContent = monthLabel + ' ' + year + ' activities';
            openYearPlanEl.href = showUrl;

            let activities = [];
            if (activitiesB64) {
                try {
                    activities = JSON.parse(atob(activitiesB64));
                } catch (error) {
                    activities = [];
                }
            }

            tableBodyEl.innerHTML = '';

            if (!Array.isArray(activities) || activities.length === 0) {
                emptyEl.textContent = 'No activities planned for ' + monthLabel + ' ' + year + '.';
                emptyEl.classList.remove('d-none');
                tableWrapEl.classList.add('d-none');
                return;
            }

            emptyEl.classList.add('d-none');
            tableWrapEl.classList.remove('d-none');

            activities.forEach(function(activity) {
                const row = document.createElement('tr');
                row.innerHTML =
                    '<td class="fw-semibold">' + escapeHtml(activity.code || '—') + '</td>' +
                    '<td class="text-wrap" style="white-space: normal;">' + escapeHtml(activity.department || '—') + '</td>' +
                    '<td class="text-wrap" style="white-space: normal;">' + escapeHtml(activity.description || '') + '</td>' +
                    '<td class="text-end">' + escapeHtml(activity.total || '0.00') + '</td>';
                tableBodyEl.appendChild(row);
            });
        });

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    });
</script>
