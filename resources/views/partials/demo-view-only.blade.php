<script>
    (function () {
        const message = @json(config('demo.message'));

        function showDemoBlocked() {
            if (window.notifier) {
                notifier.show('Demo mode', message, 'warning', '', 5500);
                return;
            }

            window.alert(message);
        }

        function requestUrl(input) {
            if (typeof input === 'string') {
                return input;
            }

            if (input && typeof input.url === 'string') {
                return input.url;
            }

            return '';
        }

        function requestMethod(input, init) {
            const fromInit = init && init.method ? String(init.method) : '';
            if (fromInit) {
                return fromInit.toUpperCase();
            }

            if (input && typeof input.method === 'string') {
                return input.method.toUpperCase();
            }

            return 'GET';
        }

        function isLogoutUrl(url) {
            return /\/logout(?:\?|$)/i.test(url || '');
        }

        function isMutatingMethod(method) {
            return ['POST', 'PUT', 'PATCH', 'DELETE'].indexOf(method) !== -1;
        }

        document.addEventListener('submit', function (event) {
            const form = event.target;
            if (!(form instanceof HTMLFormElement)) {
                return;
            }

            if (isLogoutUrl(form.getAttribute('action') || form.action || '')) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            showDemoBlocked();
        }, true);

        const originalFetch = window.fetch;
        window.fetch = function (input, init) {
            const method = requestMethod(input, init);
            const url = requestUrl(input);

            if (isMutatingMethod(method) && !isLogoutUrl(url)) {
                showDemoBlocked();
                return Promise.resolve(new Response(JSON.stringify({ message: message }), {
                    status: 403,
                    headers: { 'Content-Type': 'application/json' },
                }));
            }

            return originalFetch.apply(this, arguments);
        };

        const originalOpen = XMLHttpRequest.prototype.open;
        const originalSend = XMLHttpRequest.prototype.send;

        XMLHttpRequest.prototype.open = function (method, url) {
            this.__demoMethod = String(method || 'GET').toUpperCase();
            this.__demoUrl = String(url || '');
            return originalOpen.apply(this, arguments);
        };

        XMLHttpRequest.prototype.send = function () {
            if (isMutatingMethod(this.__demoMethod) && !isLogoutUrl(this.__demoUrl)) {
                showDemoBlocked();
                this.abort();
                return;
            }

            return originalSend.apply(this, arguments);
        };
    })();
</script>
