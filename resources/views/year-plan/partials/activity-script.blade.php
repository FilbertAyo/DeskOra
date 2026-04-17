<script>
    document.addEventListener('DOMContentLoaded', function() {
        const activityModal = document.getElementById('activityModal');
        const deptSelect = document.getElementById('activityDepartmentId');
        const createForm = document.getElementById('activityCreateForm');

        document.querySelectorAll('.btn-add-activity').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-department-id');
                if (id && deptSelect) deptSelect.value = id;
            });
        });

        const topBtn = document.getElementById('btnAddActivityTop');
        if (topBtn && activityModal) {
            topBtn.addEventListener('click', function() {
                if (deptSelect) deptSelect.value = '';
                if (createForm) createForm.reset();
            });
        }

        const editForm = document.getElementById('editActivityForm');
        const editModalEl = document.getElementById('editActivityModal');
        const baseUpdateUrl = @json(url('/year-plans/' . $yearPlan->year . '/activities'));

        function decodeActivityPayload(b64) {
            if (!b64) {
                return {};
            }
            try {
                const binary = atob(b64);
                const bytes = new Uint8Array(binary.length);
                for (let i = 0; i < binary.length; i++) {
                    bytes[i] = binary.charCodeAt(i);
                }
                const text = new TextDecoder('utf-8').decode(bytes);
                return JSON.parse(text);
            } catch (err) {
                return {};
            }
        }

        document.querySelectorAll('.btn-edit-activity').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const payload = decodeActivityPayload(this.getAttribute('data-activity-b64'));
                if (!payload.id) {
                    return;
                }
                editForm.action = baseUpdateUrl + '/' + payload.id;
                const deptEl = document.getElementById('editActivityDepartmentId');
                if (deptEl) {
                    deptEl.value = String(payload.department_id);
                }
                document.getElementById('editSortOrder').value = payload.sort_order;
                document.getElementById('editDescription').value = payload.description != null ? payload.description : '';
                document.getElementById('editFrequency').value = payload.frequency != null ? payload.frequency : '';
                document.getElementById('editUnitCount').value = payload.unit_count != null ? payload.unit_count : '';
                document.getElementById('editUnitValue').value = payload.unit_value_tzs != null ? payload.unit_value_tzs : '';
                const months = Array.isArray(payload.planned_months) ? payload.planned_months : [];
                document.querySelectorAll('.edit-month-cb').forEach(function(cb) {
                    const v = parseInt(cb.value, 10);
                    cb.checked = months.includes(v) || months.includes(String(v));
                });
                if (editModalEl && typeof bootstrap !== 'undefined') {
                    bootstrap.Modal.getOrCreateInstance(editModalEl).show();
                }
            });
        });
    });
</script>
