@if(config('services.groq.api_key'))
    @php
        $offcanvasId = 'deskoraAiAssistantOffcanvas';
        $offcanvasLabelId = $offcanvasId.'Label';
        $suggestedQuestions = config('ai-assistant.suggested_questions', []);
        $documentSummaryLabel = config('ai-assistant.document_summary_label', 'Summarize this weekly submission');
    @endphp

    {{-- Deskora AI first-visit tour (Intro.js) — remove later with the tour script/CSS below --}}
    <link rel="stylesheet" href="{{ asset('assets/css/plugins/introjs.min.css') }}">

    @stack('deskora-ai-context')

    <button
        type="button"
        class="deskora-ai-fab btn p-0 border-0"
        data-bs-toggle="offcanvas"
        data-bs-target="#{{ $offcanvasId }}"
        aria-controls="{{ $offcanvasId }}"
        aria-label="Ask Deskora AI">
        <x-deskora-ai-icon class="deskora-ai-fab__logo" :size="36" />
    </button>

    <div class="offcanvas offcanvas-end deskora-ai-offcanvas" tabindex="-1" id="{{ $offcanvasId }}" aria-labelledby="{{ $offcanvasLabelId }}" style="width: min(480px, 100vw);">
        <div class="offcanvas-header border-bottom">
            <div class="d-flex align-items-center gap-2">
                <x-deskora-ai-icon class="deskora-ai-offcanvas__logo" :size="28" />
                <div>
                    <h5 class="offcanvas-title mb-0" id="{{ $offcanvasLabelId }}">Ask Deskora AI</h5>
                    <div class="small text-muted" id="deskoraAiMeta"></div>
                </div>
            </div>
            <button type="button" class="btn-close text-reset deskora-ai-offcanvas-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body d-flex flex-column">
            <div id="deskoraAiWelcome" class="deskora-ai-welcome mb-3">
                <p class="small text-muted mb-0">
                    Ask anything about how the DeskOra portal works — year plans, weekly matrix, impacts, weekly and monthly reports, documents, petty cash, action logs, and more.
                </p>
            </div>

            <div id="deskoraAiThread" class="deskora-ai-thread flex-grow-1 overflow-auto mb-3"></div>

            <div id="deskoraAiError" class="alert alert-danger d-none mb-3"></div>

            <div id="deskoraAiSuggestions" class="deskora-ai-suggestions mb-3">
                <div class="small fw-semibold text-muted mb-2">Suggested questions</div>
                <div id="deskoraAiSuggestionsList" class="d-flex flex-wrap gap-2">
                    @foreach($suggestedQuestions as $suggestion)
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-secondary deskora-ai-suggestion-btn"
                            data-action="ask"
                            data-question="{{ $suggestion }}">
                            {{ $suggestion }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="deskora-ai-input deskora-ai-input--prompt border-top pt-3 mt-auto">
                <div class="deskora-ai-prompt-shell">
                    <label for="deskoraAiQuestionInput" class="deskora-ai-prompt-label">Ask Deskora anything</label>
                    <textarea
                        id="deskoraAiQuestionInput"
                        class="form-control deskora-ai-prompt-field"
                        rows="3"
                        maxlength="1000"
                        placeholder="What tasks are assigned to me this week?"></textarea>
                    <div class="d-flex justify-content-between align-items-center mt-2 gap-2 flex-wrap">
                        <small class="text-muted deskora-ai-input-hint" id="deskoraAiInputHint">AI-generated responses may be incorrect. Verify before taking action.</small>
                        <button type="button" class="btn btn-warning btn-sm deskora-ai-ask-btn" id="deskoraAiAskBtn">
                            <x-deskora-ai-icon class="me-1 deskora-ai-btn-logo" :size="16" />
                            Ask
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .deskora-ai-icon {
            display: inline-block;
            flex-shrink: 0;
            background-color: currentColor;
            -webkit-mask: url('{{ asset('assets/images/deskora-ai-mask.png') }}') center / contain no-repeat;
            mask: url('{{ asset('assets/images/deskora-ai-mask.png') }}') center / contain no-repeat;
        }

        .deskora-ai-fab {
            position: fixed;
            right: clamp(0.75rem, 3vw, 1.25rem);
            bottom: clamp(0.75rem, 3vw, 1.25rem);
            z-index: 1045;
            width: clamp(3rem, 11vw, 4.25rem);
            height: clamp(3rem, 11vw, 4.25rem);
            border-radius: 50%;
            background: linear-gradient(135deg, #fad02c 0%, #f5b800 100%);
            box-shadow: 0 4px 14px rgba(245, 184, 0, 0.45);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: clamp(0.45rem, 2vw, 0.85rem);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
            isolation: isolate;
        }

        .deskora-ai-fab::before {
            content: '';
            position: absolute;
            inset: -4px;
            border-radius: 50%;
            border: 2px solid rgba(250, 208, 44, 0.55);
            animation: deskoraFabGlow 2.5s ease-in-out infinite;
            pointer-events: none;
            z-index: -1;
        }

        .deskora-ai-fab::after {
            content: '';
            position: absolute;
            inset: -8px;
            border-radius: 50%;
            box-shadow: 0 0 0 0 rgba(250, 208, 44, 0.35);
            animation: deskoraFabPulse 2.5s ease-in-out infinite;
            pointer-events: none;
            z-index: -2;
        }

        @keyframes deskoraFabGlow {
            0%, 100% {
                opacity: 0.45;
                transform: scale(1);
            }
            50% {
                opacity: 1;
                transform: scale(1.04);
            }
        }

        @keyframes deskoraFabPulse {
            0%, 100% {
                box-shadow: 0 0 0 0 rgba(250, 208, 44, 0.25);
            }
            50% {
                box-shadow: 0 0 18px 6px rgba(250, 208, 44, 0.35);
            }
        }

        .deskora-ai-fab:hover,
        .deskora-ai-fab:focus-visible {
            transform: scale(1.05);
            box-shadow: 0 6px 18px rgba(245, 184, 0, 0.55);
        }

        .deskora-ai-fab__logo {
            width: 100%;
            height: 100%;
            max-width: 2.25rem;
            max-height: 2.25rem;
            color: #fff;
        }

        .deskora-ai-btn-logo {
            color: #fff;
        }

        .deskora-ai-offcanvas__logo {
            color: #fad02c;
        }

        @media (max-width: 576px) {
            .deskora-ai-fab__logo {
                max-width: 1.75rem;
                max-height: 1.75rem;
            }
        }

        .deskora-ai-suggestion-btn {
            background: transparent;
            white-space: normal;
            text-align: left;
            max-width: 100%;
        }

        .deskora-ai-suggestion-btn:hover,
        .deskora-ai-suggestion-btn:focus {
            background: var(--bs-light, #f8f9fa);
        }

        .deskora-ai-suggestion-btn--summary {
            border-color: #fad02c;
            color: #9a7200;
        }

        .deskora-ai-thread .qa-item {
            background: var(--bs-light, #f8f9fa);
            border: 1px solid var(--bs-border-color, #e9ecef);
            border-radius: 0.5rem;
            padding: 0.75rem;
            margin-bottom: 0.75rem;
        }

        .deskora-ai-thread .qa-question {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .deskora-ai-thread .qa-answer p {
            margin-bottom: 0.75rem;
            line-height: 1.7;
        }

        .deskora-ai-thread .qa-answer p:last-child {
            margin-bottom: 0;
        }

        .deskora-ai-thread .qa-loading {
            color: var(--bs-secondary-color);
        }

        .deskora-ai-prompt-shell {
            border: 2px solid #fad02c;
            border-radius: 0.85rem;
            padding: 0.85rem 0.95rem;
            background: linear-gradient(180deg, rgba(250, 208, 44, 0.08) 0%, rgba(255, 255, 255, 0.98) 100%);
            box-shadow: 0 0 0 1px rgba(250, 208, 44, 0.15), 0 8px 24px rgba(245, 184, 0, 0.12);
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .deskora-ai-prompt-shell:focus-within {
            border-color: #f5b800;
            box-shadow: 0 0 0 3px rgba(250, 208, 44, 0.22), 0 10px 28px rgba(245, 184, 0, 0.16);
        }

        .deskora-ai-prompt-label {
            display: block;
            font-size: 0.78rem;
            font-weight: 600;
            color: #9a7200;
            margin-bottom: 0.45rem;
            letter-spacing: 0.01em;
        }

        .deskora-ai-prompt-field {
            border: 0;
            background: transparent;
            box-shadow: none;
            padding: 0;
            resize: none;
            font-size: 0.95rem;
            line-height: 1.55;
            min-height: 4.5rem;
        }

        .deskora-ai-prompt-field:focus {
            border: 0;
            background: transparent;
            box-shadow: none;
        }

        .deskora-ai-prompt-field::placeholder {
            color: rgba(108, 117, 125, 0.85);
        }

        .deskora-ai-input-hint {
            max-width: 16rem;
            line-height: 1.35;
        }

        /* Mandatory Deskora AI tour — hide Skip; stack above FAB/offcanvas */
        .introjs-skipbutton {
            display: none !important;
        }

        body.deskora-ai-tour-active .deskora-ai-fab {
            z-index: 10000015;
        }

        body.deskora-ai-tour-panel .deskora-ai-offcanvas.show {
            z-index: 10000010 !important;
        }

        body.deskora-ai-tour-panel .offcanvas-backdrop {
            z-index: 10000005 !important;
        }

        body.deskora-ai-tour-panel .introjs-overlay {
            z-index: 10000000 !important;
        }

        body.deskora-ai-tour-panel .introjs-helperLayer,
        body.deskora-ai-tour-panel .introjs-disableInteraction {
            z-index: 10000020 !important;
        }

        body.deskora-ai-tour-panel .introjs-tooltipReferenceLayer {
            z-index: 10000030 !important;
        }

        body.deskora-ai-tour-active .deskora-ai-offcanvas-close {
            pointer-events: none;
            opacity: 0.35;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const offcanvasEl = document.getElementById('{{ $offcanvasId }}');
            if (!offcanvasEl || typeof bootstrap === 'undefined') {
                return;
            }

            const threadEl = document.getElementById('deskoraAiThread');
            const welcomeEl = document.getElementById('deskoraAiWelcome');
            const metaEl = document.getElementById('deskoraAiMeta');
            const errorEl = document.getElementById('deskoraAiError');
            const suggestionsEl = document.getElementById('deskoraAiSuggestions');
            const suggestionsListEl = document.getElementById('deskoraAiSuggestionsList');
            const inputHintEl = document.getElementById('deskoraAiInputHint');
            const questionInputEl = document.getElementById('deskoraAiQuestionInput');
            const askBtnEl = document.getElementById('deskoraAiAskBtn');
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const generalAskUrl = @json(route('ai-assistant.ask'));
            const documentSummaryUrl = @json(route('documents.ai-summary'));
            const documentAskUrl = @json(route('documents.ai-summary.ask'));
            const documentSummaryLabel = @json($documentSummaryLabel);

            let documentContext = null;
            const contextEl = document.getElementById('deskora-ai-document-context');
            if (contextEl) {
                try {
                    documentContext = JSON.parse(contextEl.textContent || '{}');
                } catch (error) {
                    documentContext = null;
                }
            }

            if (documentContext && (documentContext.planId || documentContext.reportId)) {
                if (metaEl && documentContext.title) {
                    metaEl.textContent = documentContext.title + (documentContext.staff ? ' · ' + documentContext.staff : '');
                }
                if (inputHintEl) {
                    inputHintEl.textContent = 'You can summarize this submission or ask about its plan and report.';
                }
                if (welcomeEl) {
                    welcomeEl.querySelector('p').textContent = 'Summarize this weekly submission or ask questions about the plan and report shown on this page.';
                }

                const summaryBtn = document.createElement('button');
                summaryBtn.type = 'button';
                summaryBtn.className = 'btn btn-sm btn-outline-secondary deskora-ai-suggestion-btn deskora-ai-suggestion-btn--summary';
                summaryBtn.dataset.action = 'summarize';
                summaryBtn.textContent = documentSummaryLabel;
                suggestionsListEl.prepend(summaryBtn);
            }

            let currentDocSummary = '';
            let isAsking = false;

            function showNotifier(title, message, type) {
                if (!window.notifier || typeof window.notifier.show !== 'function') {
                    return;
                }
                window.notifier.show(title, message, type || 'info', '', 6000);
            }

            function renderParagraphs(container, text) {
                container.innerHTML = '';

                const normalized = (text || '').trim();
                if (normalized === '') {
                    return;
                }

                let paragraphs = normalized
                    .split(/\n\s*\n/)
                    .map((part) => part.replace(/\s+/g, ' ').trim())
                    .filter(Boolean);

                if (paragraphs.length === 1) {
                    paragraphs = normalized
                        .split(/(?<=[.!?])\s+(?=[A-Z])/)
                        .map((part) => part.trim())
                        .filter(Boolean);

                    if (paragraphs.length > 4) {
                        const grouped = [];
                        for (let i = 0; i < paragraphs.length; i += 2) {
                            grouped.push(paragraphs.slice(i, i + 2).join(' '));
                        }
                        paragraphs = grouped;
                    }
                }

                paragraphs.forEach((paragraph) => {
                    const paragraphEl = document.createElement('p');
                    paragraphEl.textContent = paragraph;
                    container.appendChild(paragraphEl);
                });
            }

            function appendQaItem(question, answer, isLoading) {
                const itemEl = document.createElement('div');
                itemEl.className = 'qa-item';

                const questionEl = document.createElement('div');
                questionEl.className = 'qa-question small';
                questionEl.textContent = 'You: ' + question;

                const answerEl = document.createElement('div');
                answerEl.className = isLoading ? 'qa-answer small qa-loading' : 'qa-answer small';
                answerEl.textContent = isLoading ? 'Deskora AI is thinking...' : '';

                itemEl.appendChild(questionEl);
                itemEl.appendChild(answerEl);
                threadEl.appendChild(itemEl);
                itemEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

                return answerEl;
            }

            function setAskingState(asking) {
                isAsking = asking;
                askBtnEl.disabled = asking;
                questionInputEl.disabled = asking;

                document.querySelectorAll('.deskora-ai-suggestion-btn').forEach((button) => {
                    button.disabled = asking;
                });
            }

            function hideSuggestionsAfterFirstMessage() {
                if (suggestionsEl) {
                    suggestionsEl.classList.add('d-none');
                }
            }

            async function loadWeeklySummary() {
                if (!documentContext || (!documentContext.planId && !documentContext.reportId)) {
                    showNotifier('Deskora AI', 'No weekly plan or report is available to summarize.', 'warning');
                    return;
                }

                errorEl.classList.add('d-none');
                errorEl.textContent = '';
                welcomeEl.classList.add('d-none');
                hideSuggestionsAfterFirstMessage();

                const answerEl = appendQaItem(documentSummaryLabel, '', true);
                const originalHtml = askBtnEl.innerHTML;
                setAskingState(true);
                askBtnEl.innerHTML = '<i class="ti ti-loader spinning me-1"></i> Thinking...';

                const payload = {};
                if (documentContext.planId) payload.plan_id = documentContext.planId;
                if (documentContext.reportId) payload.report_id = documentContext.reportId;

                try {
                    const response = await fetch(documentSummaryUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify(payload),
                    });

                    const data = await response.json();

                    if (!response.ok) {
                        throw new Error(data.message || 'Could not generate summary.');
                    }

                    currentDocSummary = data.summary || '';
                    if (metaEl) {
                        metaEl.textContent = [data.title || documentContext.title, data.staff || documentContext.staff].filter(Boolean).join(' · ');
                    }

                    answerEl.classList.remove('qa-loading');
                    renderParagraphs(answerEl, currentDocSummary);
                } catch (error) {
                    answerEl.closest('.qa-item')?.remove();
                    errorEl.textContent = error.message || 'Could not generate summary.';
                    errorEl.classList.remove('d-none');
                    showNotifier('Deskora AI', errorEl.textContent, 'danger');
                } finally {
                    setAskingState(false);
                    askBtnEl.innerHTML = originalHtml;
                }
            }

            async function askQuestion(questionText) {
                const question = (questionText ?? questionInputEl.value).trim();

                if (question === '') {
                    showNotifier('Deskora AI', 'Please type a question first.', 'warning');
                    questionInputEl.focus();
                    return;
                }

                if (isAsking) {
                    return;
                }

                errorEl.classList.add('d-none');
                errorEl.textContent = '';
                welcomeEl.classList.add('d-none');
                hideSuggestionsAfterFirstMessage();

                const answerEl = appendQaItem(question, '', true);
                const originalHtml = askBtnEl.innerHTML;
                setAskingState(true);
                askBtnEl.innerHTML = '<i class="ti ti-loader spinning me-1"></i> Thinking...';

                const hasDocumentContext = documentContext && (documentContext.planId || documentContext.reportId);
                const url = hasDocumentContext ? documentAskUrl : generalAskUrl;
                const payload = hasDocumentContext
                    ? {
                        question,
                        summary: currentDocSummary || undefined,
                        plan_id: documentContext.planId || undefined,
                        report_id: documentContext.reportId || undefined,
                    }
                    : { question };

                try {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify(payload),
                    });

                    const data = await response.json();

                    if (!response.ok) {
                        throw new Error(data.message || 'Could not answer your question.');
                    }

                    const answer = data.answer || '';
                    answerEl.classList.remove('qa-loading');
                    renderParagraphs(answerEl, answer);
                    questionInputEl.value = '';
                } catch (error) {
                    answerEl.closest('.qa-item')?.remove();
                    errorEl.textContent = error.message || 'Could not answer your question.';
                    errorEl.classList.remove('d-none');
                    showNotifier('Deskora AI', errorEl.textContent, 'danger');
                } finally {
                    setAskingState(false);
                    askBtnEl.innerHTML = originalHtml;
                    questionInputEl.focus();
                }
            }

            askBtnEl.addEventListener('click', function () {
                askQuestion();
            });

            questionInputEl.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' && !event.shiftKey) {
                    event.preventDefault();
                    askQuestion();
                }
            });

            document.querySelectorAll('.deskora-ai-suggestion-btn').forEach((button) => {
                button.addEventListener('click', function () {
                    if (this.dataset.action === 'summarize') {
                        loadWeeklySummary();
                        return;
                    }

                    const question = this.dataset.question || '';
                    questionInputEl.value = question;
                    askQuestion(question);
                });
            });
        });
    </script>

    <script src="{{ asset('assets/js/plugins/intro.min.js') }}"></script>
    <script>
        // Temporary mandatory first-visit tour — remove with Intro.js assets/CSS above when retiring
        document.addEventListener('DOMContentLoaded', function () {
            const TOUR_STORAGE_KEY = 'deskora_ai_tour_v1';
            const fabEl = document.querySelector('.deskora-ai-fab');
            const offcanvasEl = document.getElementById('{{ $offcanvasId }}');

            if (!fabEl || !offcanvasEl || typeof introJs !== 'function') {
                return;
            }

            try {
                if (window.localStorage.getItem(TOUR_STORAGE_KEY) === '1') {
                    return;
                }
            } catch (error) {
                // If storage is unavailable, still show the tour once this session.
            }

            if (typeof bootstrap === 'undefined' || !bootstrap.Offcanvas) {
                return;
            }

            const suggestionsEl = document.getElementById('deskoraAiSuggestions');
            const inputEl = document.querySelector('.deskora-ai-input');
            const closeBtn = offcanvasEl.querySelector('.deskora-ai-offcanvas-close');
            const offcanvas = bootstrap.Offcanvas.getOrCreateInstance(offcanvasEl);
            const previousBackdrop = offcanvasEl.getAttribute('data-bs-backdrop');
            const previousKeyboard = offcanvasEl.getAttribute('data-bs-keyboard');

            function setTourPanelMode(active) {
                document.body.classList.toggle('deskora-ai-tour-panel', active);
                if (active) {
                    offcanvasEl.setAttribute('data-bs-backdrop', 'static');
                    offcanvasEl.setAttribute('data-bs-keyboard', 'false');
                    if (closeBtn) {
                        closeBtn.setAttribute('disabled', 'disabled');
                    }
                    return;
                }

                if (previousBackdrop === null) {
                    offcanvasEl.removeAttribute('data-bs-backdrop');
                } else {
                    offcanvasEl.setAttribute('data-bs-backdrop', previousBackdrop);
                }

                if (previousKeyboard === null) {
                    offcanvasEl.removeAttribute('data-bs-keyboard');
                } else {
                    offcanvasEl.setAttribute('data-bs-keyboard', previousKeyboard);
                }

                if (closeBtn) {
                    closeBtn.removeAttribute('disabled');
                }
            }

            function stepNeedsPanel(targetElement) {
                return targetElement === offcanvasEl
                    || targetElement === suggestionsEl
                    || targetElement === inputEl;
            }

            function openTourPanel() {
                setTourPanelMode(true);
                if (!offcanvasEl.classList.contains('show')) {
                    offcanvas.show();
                }
            }

            function cleanupTourUi() {
                document.body.classList.remove('deskora-ai-tour-active');
                setTourPanelMode(false);
            }

            const steps = [
                {
                    intro: '<strong>Meet Ask Deskora AI</strong><br><br>'
                        + 'Ask Deskora AI is your in-portal assistant. Use it to learn how DeskOra works — including year plans, the weekly matrix, impacts, weekly and monthly reports, documents, petty cash, and action logs.<br><br>'
                        + 'On weekly plan or report pages, it can also <strong>summarize that submission</strong> and answer questions about the plan or report you are viewing.<br><br>'
                        + 'Responses are AI-generated and may be incorrect, so always verify before taking action.<br><br>'
                        + 'Please follow each step to learn how to use it — you cannot skip this tour.'
                },
                {
                    element: fabEl,
                    intro: 'Tap this yellow <strong>Ask Deskora AI</strong> button anytime to open the assistant from any page.',
                    position: 'left'
                },
                {
                    element: offcanvasEl,
                    intro: 'This is the Deskora AI chat panel. Ask about year plans, the weekly matrix, impacts, weekly and monthly reports, documents, petty cash, action logs, and more.',
                    position: 'left'
                },
                {
                    element: suggestionsEl,
                    intro: 'Use these <strong>suggested questions</strong> for a quick start. On weekly plan or report pages, you may also see a summarize option.',
                    position: 'left'
                },
                {
                    element: inputEl,
                    intro: 'Type your own question here and press <strong>Ask</strong>. AI answers can be wrong — always verify before taking action.',
                    position: 'top'
                }
            ].filter((step) => !step.element || document.body.contains(step.element));

            document.body.classList.add('deskora-ai-tour-active');

            const tour = introJs()
                .setOptions({
                    steps: steps,
                    nextLabel: 'Next',
                    prevLabel: 'Back',
                    doneLabel: 'Done',
                    skipLabel: '',
                    showBullets: true,
                    showButtons: true,
                    exitOnOverlayClick: false,
                    exitOnEsc: false,
                    disableInteraction: true,
                    scrollToElement: true,
                    overlayOpacity: 0.55
                })
                .onbeforechange(function (targetElement) {
                    if (stepNeedsPanel(targetElement)) {
                        openTourPanel();
                        return;
                    }

                    setTourPanelMode(false);
                    if (offcanvasEl.classList.contains('show')) {
                        offcanvas.hide();
                    }
                })
                .onafterchange(function () {
                    const introInstance = this;
                    window.setTimeout(function () {
                        if (typeof introInstance.refresh === 'function') {
                            introInstance.refresh();
                        }
                    }, 350);
                })
                .oncomplete(function () {
                    try {
                        window.localStorage.setItem(TOUR_STORAGE_KEY, '1');
                    } catch (error) {
                        // Ignore storage failures; tour already finished this visit.
                    }
                    cleanupTourUi();
                })
                .onexit(function () {
                    // Do not mark complete on exit — refresh will restart until Done.
                    cleanupTourUi();
                });

            tour.start();
        });
    </script>
@endif
