{{-- DeskOra Go command palette (Ctrl/Cmd + K) --}}
<link rel="stylesheet" href="{{ asset('assets/css/deskora-go.css') }}">

<div id="deskora-go" class="deskora-go" hidden aria-hidden="true">
    <div class="deskora-go__backdrop" data-deskora-go-close></div>
    <div class="deskora-go__panel" role="dialog" aria-modal="true" aria-label="DeskOra Go">
        <div class="deskora-go__search-wrap">
            <i class="ti ti-search deskora-go__search-icon" aria-hidden="true"></i>
            <input
                id="deskora-go-input"
                class="deskora-go__input"
                type="search"
                autocomplete="off"
                spellcheck="false"
                placeholder="Jump to a page… (type to filter)"
                aria-controls="deskora-go-results"
            >
            <kbd class="deskora-go__kbd">esc</kbd>
        </div>
        <ul id="deskora-go-results" class="deskora-go__results" role="listbox"></ul>
        <div class="deskora-go__footer">
            <span><kbd>↑</kbd><kbd>↓</kbd> navigate</span>
            <span><kbd>enter</kbd> open</span>
            <span><kbd>esc</kbd> close</span>
        </div>
    </div>
</div>

<script>
(function () {
    const root = document.getElementById('deskora-go');
    const input = document.getElementById('deskora-go-input');
    const results = document.getElementById('deskora-go-results');
    if (!root || !input || !results) return;

    const commandsUrl = @json(route('commands.index'));
    let commands = [];
    let filtered = [];
    let activeIndex = 0;
    let loaded = false;
    let loading = false;

    function isOpen() {
        return !root.hasAttribute('hidden');
    }

    function openPalette() {
        root.removeAttribute('hidden');
        root.setAttribute('aria-hidden', 'false');
        document.body.classList.add('deskora-go-open');
        input.value = '';
        activeIndex = 0;
        if (typeof window.trackFeatureUsage === 'function') {
            window.trackFeatureUsage('feature.ai.command_palette');
        }
        ensureLoaded().then(() => {
            render(input.value);
            input.focus();
        });
    }

    function closePalette() {
        root.setAttribute('hidden', '');
        root.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('deskora-go-open');
    }

    async function ensureLoaded() {
        if (loaded || loading) return;
        loading = true;
        results.innerHTML = '<li class="deskora-go__empty">Loading…</li>';
        try {
            const response = await fetch(commandsUrl, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!response.ok) throw new Error('Failed to load commands');
            const data = await response.json();
            commands = Array.isArray(data.commands) ? data.commands : [];
            loaded = true;
        } catch (e) {
            results.innerHTML = '<li class="deskora-go__empty">Could not load commands.</li>';
        } finally {
            loading = false;
        }
    }

    function normalize(value) {
        return String(value || '').toLowerCase().trim();
    }

    function matches(command, query) {
        if (!query) return true;
        const haystack = [command.label, command.description]
            .concat(command.keywords || [])
            .map(normalize)
            .join(' ');
        return query.split(/\s+/).every((part) => haystack.includes(part));
    }

    function render(query) {
        const q = normalize(query);
        filtered = commands.filter((command) => matches(command, q)).slice(0, 12);
        activeIndex = 0;

        if (!filtered.length) {
            results.innerHTML = '<li class="deskora-go__empty">No matching pages.</li>';
            return;
        }

        results.innerHTML = filtered.map((command, index) => `
            <li class="deskora-go__item${index === 0 ? ' is-active' : ''}" role="option" data-index="${index}">
                <a href="${command.url}" class="deskora-go__link">
                    <span class="deskora-go__label">${escapeHtml(command.label)}</span>
                    <span class="deskora-go__desc">${escapeHtml(command.description || '')}</span>
                </a>
            </li>
        `).join('');
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function setActive(index) {
        const items = results.querySelectorAll('.deskora-go__item');
        if (!items.length) return;
        activeIndex = (index + items.length) % items.length;
        items.forEach((item, i) => item.classList.toggle('is-active', i === activeIndex));
        items[activeIndex].scrollIntoView({ block: 'nearest' });
    }

    function activateSelected() {
        const items = results.querySelectorAll('.deskora-go__item a');
        if (!items[activeIndex]) return;
        window.location.href = items[activeIndex].getAttribute('href');
    }

    document.addEventListener('keydown', (event) => {
        const key = event.key;
        const isPaletteShortcut = (key === 'k' || key === 'K') && (event.metaKey || event.ctrlKey);

        if (isPaletteShortcut) {
            event.preventDefault();
            if (isOpen()) closePalette();
            else openPalette();
            return;
        }

        if (!isOpen()) return;

        if (key === 'Escape') {
            event.preventDefault();
            closePalette();
        } else if (key === 'ArrowDown') {
            event.preventDefault();
            setActive(activeIndex + 1);
        } else if (key === 'ArrowUp') {
            event.preventDefault();
            setActive(activeIndex - 1);
        } else if (key === 'Enter') {
            event.preventDefault();
            activateSelected();
        }
    });

    input.addEventListener('input', () => render(input.value));
    root.addEventListener('click', (event) => {
        if (event.target.closest('[data-deskora-go-close]')) {
            closePalette();
        }
    });

    document.querySelectorAll('[data-deskora-go-open]').forEach((el) => {
        el.addEventListener('click', (event) => {
            event.preventDefault();
            openPalette();
        });
    });
})();
</script>
