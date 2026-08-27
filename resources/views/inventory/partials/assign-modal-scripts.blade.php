@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const modalEl = document.getElementById('assignAssetModal');
        if (!modalEl) return;

        const modal = new bootstrap.Modal(modalEl);
        const form = document.getElementById('assignAssetForm');
        const cascade = modalEl.querySelector('.js-ownership-cascade');
        const returnInput = form.querySelector('input[name="return_url"]');

        function openAssign(data) {
            form.action = `/inventory/assets/${data.id}/assign`;
            document.getElementById('assignAssetName').textContent = data.name || 'Asset';
            document.getElementById('assignAssetCode').textContent = data.code ? `(${data.code})` : '';
            if (returnInput) {
                returnInput.value = window.location.href;
            }
            if (cascade) {
                cascade.dataset.selectedDept = data.department || '';
                cascade.dataset.selectedUser = data.user || '';
                if (window.InventoryPickers) {
                    window.InventoryPickers.rebind(modalEl);
                }
            }
            modal.show();
        }

        document.querySelectorAll('.assign-btn').forEach(function (button) {
            button.addEventListener('click', function () {
                openAssign(this.dataset);
            });
        });

        @if(session('open_assign_modal'))
            const firstAssign = document.querySelector('.assign-btn');
            if (firstAssign) {
                openAssign(firstAssign.dataset);
            } else {
                @if(! empty($assignFallbackUrl))
                    form.action = @json($assignFallbackUrl);
                @endif
                if (returnInput) {
                    returnInput.value = window.location.href;
                }
                modal.show();
            }
        @endif
    });
</script>
@endpush
