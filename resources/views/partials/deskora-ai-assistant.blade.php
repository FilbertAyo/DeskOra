@if(config('services.groq.api_key'))
    @php
        $offcanvasId = 'deskoraAiAssistantOffcanvas';
        $offcanvasLabelId = $offcanvasId.'Label';
        $suggestedQuestions = config('ai-assistant.suggested_questions', []);
        $documentSummaryLabel = config('ai-assistant.document_summary_label', 'Summarize this weekly submission');
    @endphp

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
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body d-flex flex-column">
            <div id="deskoraAiWelcome" class="deskora-ai-welcome mb-3">
                <p class="small text-muted mb-0">
                    Ask anything about how the portal works — weekly plans, reports, documents, petty cash, action logs, and more.
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

            <div class="deskora-ai-input border-top pt-3 mt-auto">
                <label for="deskoraAiQuestionInput" class="form-label small fw-semibold mb-2">Your question</label>
                <textarea
                    id="deskoraAiQuestionInput"
                    class="form-control form-control-sm"
                    rows="3"
                    maxlength="1000"
                    placeholder="Example: How do I submit a weekly plan?"></textarea>
                <div class="d-flex justify-content-between align-items-center mt-2">
                    <small class="text-muted" id="deskoraAiInputHint">AI-generated responses may be incorrect. Verify before taking action.</small>
                    <button type="button" class="btn btn-warning btn-sm deskora-ai-ask-btn" id="deskoraAiAskBtn">
                        <x-deskora-ai-icon class="me-1 deskora-ai-btn-logo" :size="16" />
                        Ask
                    </button>
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
@endif
