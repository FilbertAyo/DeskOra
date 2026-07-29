@push('scripts')
<script src="{{ asset('assets/js/plugins/tinymce/tinymce.min.js') }}"></script>
<script>
    (function () {
        const select = document.getElementById('signatory_id');
        const namePreview = document.getElementById('signatory_name_preview');
        const titlePreview = document.getElementById('signatory_title_preview');

        function syncPreview() {
            const option = select.options[select.selectedIndex];
            namePreview.value = option && option.value ? (option.dataset.name || '') : '';
            titlePreview.value = option && option.value ? (option.dataset.title || '') : '';
        }

        select.addEventListener('change', syncPreview);
        syncPreview();

        function forceUppercase(el) {
            el.addEventListener('input', function () {
                const start = el.selectionStart;
                const end = el.selectionEnd;
                el.value = el.value.toLocaleUpperCase('en-US');
                if (typeof start === 'number' && typeof end === 'number') {
                    el.setSelectionRange(start, end);
                }
            });
        }

        forceUppercase(document.getElementById('recipient'));
        forceUppercase(document.getElementById('subject'));

        tinymce.init({
            selector: '#body',
            height: 360,
            menubar: false,
            branding: false,
            plugins: 'lists link autoresize',
            toolbar: 'undo redo | bold italic underline | bullist numlist | removeformat',
            content_style: "body { font-family: Calibri, Carlito, 'Segoe UI', sans-serif; font-size: 12pt; line-height: 1.4; text-align: justify; } p { margin: 0 0 0.6em; text-align: justify; }",
            setup: function (editor) {
                editor.on('init', function () {
                    editor.getBody().style.fontFamily = "Calibri, Carlito, 'Segoe UI', sans-serif";
                    editor.getBody().style.textAlign = 'justify';
                });
            }
        });

        const form = document.getElementById('officialLetterForm');
        const submitBtn = document.getElementById('officialLetterSubmitBtn');
        let isSubmitting = false;

        form.addEventListener('submit', function (event) {
            if (isSubmitting) {
                event.preventDefault();
                return;
            }

            if (window.tinymce) {
                tinymce.triggerSave();
            }

            isSubmitting = true;
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.setAttribute('aria-busy', 'true');
                const label = submitBtn.querySelector('.official-letter-submit-label');
                const busy = submitBtn.querySelector('.official-letter-submit-busy');
                if (label) {
                    label.classList.add('d-none');
                }
                if (busy) {
                    busy.classList.remove('d-none');
                }
            }
        });
    })();
</script>
@endpush
