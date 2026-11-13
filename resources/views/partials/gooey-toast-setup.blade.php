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
                const text = options.message ? String(options.message) : String(options.title || 'Notice');
                window.toast({
                    type: typeMap[options.type] || options.type || 'info',
                    title: text,
                    duration: options.duration,
                });
                return;
            }

            window.toast({
                type: typeMap[type] || type || 'info',
                title: message ? String(message) : String(titleOrOptions || 'Notice'),
            });
        };

        window.addEventListener('gooey-navigate', function (event) {
            const url = event.detail && event.detail.url;
            if (typeof url === 'string' && url !== '') {
                window.location.href = url;
            }
        });
    })();
</script>
