<script>
    (function () {
        const typeMap = {
            danger: 'error',
            success: 'success',
            warning: 'warning',
            info: 'info',
            error: 'error',
            loading: 'loading',
        };

        window.appToast = function (titleOrOptions, message, type) {
            if (typeof window.toast !== 'function') {
                return;
            }

            if (titleOrOptions && typeof titleOrOptions === 'object') {
                const options = Object.assign({}, titleOrOptions);
                options.type = typeMap[options.type] || options.type || 'info';
                window.toast(options);
                return;
            }

            const payload = {
                type: typeMap[type] || type || 'info',
                title: String(titleOrOptions || 'Notice'),
            };
            if (message) {
                payload.message = String(message);
            }
            window.toast(payload);
        };

        window.addEventListener('gooey-navigate', function (event) {
            const url = event.detail && event.detail.url;
            if (typeof url === 'string' && url !== '') {
                window.location.href = url;
            }
        });
    })();
</script>
