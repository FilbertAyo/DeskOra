@if(config('services.gemini.api_key'))
    @php
        $offcanvasId = 'documentAiSummaryOffcanvas';
        $offcanvasLabelId = $offcanvasId.'Label';
    @endphp

    <div class="offcanvas offcanvas-end" tabindex="-1" id="{{ $offcanvasId }}" aria-labelledby="{{ $offcanvasLabelId }}" style="width: min(480px, 100vw);">
        <div class="offcanvas-header border-bottom">
            <div>
                <h5 class="offcanvas-title mb-1" id="{{ $offcanvasLabelId }}">AI Summary</h5>
                <div class="small text-muted" id="documentAiSummaryMeta"></div>
            </div>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body d-flex flex-column">
            <div id="documentAiSummaryLoading" class="text-center py-5 d-none">
                <i class="ti ti-loader spinning fs-2 text-primary d-block mb-3"></i>
                <p class="text-muted mb-0">Generating summary... this may take up to 30 seconds.</p>
            </div>
            <div id="documentAiSummaryError" class="alert alert-danger d-none mb-0"></div>
            <div id="documentAiSummaryContent" class="document-ai-summary-prose"></div>

            <div id="documentAiQaSection" class="d-none mt-4 pt-3 border-top">
                <label for="documentAiQuestionInput" class="form-label small fw-semibold mb-2">
                    <i class="ti ti-message-circle me-1"></i> Ask about this plan and report
                </label>
                <textarea
                    id="documentAiQuestionInput"
                    class="form-control form-control-sm"
                    rows="3"
                    maxlength="500"
                    placeholder="Example: Was the main target accomplished?"></textarea>
                <div class="d-flex justify-content-between align-items-center mt-2">
                    <small class="text-muted">Answers use only this week's plan and report.</small>
                    <button type="button" class="btn btn-primary btn-sm" id="documentAiAskBtn">
                        <i class="ti ti-send me-1"></i> Ask
                    </button>
                </div>
                <div id="documentAiQaThread" class="document-ai-qa-thread mt-3"></div>
            </div>

            <div class="text-end mt-3 pt-3 border-top">
                <button class="btn btn-light-secondary btn-sm" type="button" data-bs-dismiss="offcanvas">
                    Close
                </button>
            </div>
        </div>
    </div>

    <style>
        .document-ai-summary-prose p {
            margin-bottom: 1rem;
            line-height: 1.75;
            color: var(--bs-body-color);
            text-align: left;
        }

        .document-ai-summary-prose p:last-child {
            margin-bottom: 0;
        }

        .document-ai-qa-thread .qa-item {
            background: var(--bs-light, #f8f9fa);
            border: 1px solid var(--bs-border-color, #e9ecef);
            border-radius: 0.5rem;
            padding: 0.75rem;
            margin-bottom: 0.75rem;
        }

        .document-ai-qa-thread .qa-question {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .document-ai-qa-thread .qa-answer p {
            margin-bottom: 0.75rem;
            line-height: 1.7;
        }

        .document-ai-qa-thread .qa-answer p:last-child {
            margin-bottom: 0;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const offcanvasEl = document.getElementById('{{ $offcanvasId }}');
            if (!offcanvasEl || typeof bootstrap === 'undefined') {
                return;
            }

            const offcanvas = bootstrap.Offcanvas.getOrCreateInstance(offcanvasEl);
            const titleEl = document.getElementById('{{ $offcanvasLabelId }}');
            const metaEl = document.getElementById('documentAiSummaryMeta');
            const loadingEl = document.getElementById('documentAiSummaryLoading');
            const errorEl = document.getElementById('documentAiSummaryError');
            const contentEl = document.getElementById('documentAiSummaryContent');
            const qaSectionEl = document.getElementById('documentAiQaSection');
            const questionInputEl = document.getElementById('documentAiQuestionInput');
            const askBtnEl = document.getElementById('documentAiAskBtn');
            const qaThreadEl = document.getElementById('documentAiQaThread');
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const summaryUrl = @json(route('documents.ai-summary'));
            const askUrl = @json(route('documents.ai-summary.ask'));

            let currentContext = {
                planId: '',
                reportId: '',
                summaryText: '',
            };

            function showNotifier(title, message, type) {
                if (!window.notifier || typeof window.notifier.show !== 'function') {
                    return;
                }
                window.notifier.show(title, message, type || 'info', '', 6000);
            }

            function resetPanel() {
                loadingEl.classList.add('d-none');
                errorEl.classList.add('d-none');
                errorEl.textContent = '';
                contentEl.innerHTML = '';
                qaSectionEl.classList.add('d-none');
                qaThreadEl.innerHTML = '';
                questionInputEl.value = '';
                currentContext = { planId: '', reportId: '', summaryText: '' };
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

            function appendQaItem(question, answer) {
                const itemEl = document.createElement('div');
                itemEl.className = 'qa-item';

                const questionEl = document.createElement('div');
                questionEl.className = 'qa-question small';
                questionEl.textContent = 'Q: ' + question;

                const answerEl = document.createElement('div');
                answerEl.className = 'qa-answer small';

                itemEl.appendChild(questionEl);
                itemEl.appendChild(answerEl);
                qaThreadEl.appendChild(itemEl);

                renderParagraphs(answerEl, answer);
                itemEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }

            async function loadWeeklySummary(planId, reportId, summaryTitle, summaryStaff) {
                if (!planId && !reportId) {
                    showNotifier('AI Summary', 'No weekly plan or report is available to summarize.', 'warning');
                    return;
                }

                resetPanel();
                titleEl.textContent = summaryTitle || 'AI Summary';
                metaEl.textContent = ['Weekly Plan & Report', summaryStaff].filter(Boolean).join(' · ');
                loadingEl.classList.remove('d-none');
                offcanvas.show();

                const payload = {};
                if (planId) payload.plan_id = planId;
                if (reportId) payload.report_id = reportId;

                try {
                    const response = await fetch(summaryUrl, {
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
                    loadingEl.classList.add('d-none');

                    if (!response.ok) {
                        throw new Error(data.message || 'Could not generate summary.');
                    }

                    titleEl.textContent = data.title || summaryTitle || 'AI Summary';
                    metaEl.textContent = [data.type, data.staff || summaryStaff].filter(Boolean).join(' · ');
                    renderParagraphs(contentEl, data.summary || '');

                    currentContext = {
                        planId: planId || '',
                        reportId: reportId || '',
                        summaryText: data.summary || '',
                    };
                    qaSectionEl.classList.remove('d-none');
                } catch (error) {
                    loadingEl.classList.add('d-none');
                    errorEl.textContent = error.message || 'Could not generate summary.';
                    errorEl.classList.remove('d-none');
                    showNotifier('AI Summary', errorEl.textContent, 'danger');
                }
            }

            async function askQuestion() {
                const question = questionInputEl.value.trim();
                if (question === '') {
                    showNotifier('AI Q&A', 'Please type a question first.', 'warning');
                    questionInputEl.focus();
                    return;
                }

                if (!currentContext.planId && !currentContext.reportId) {
                    showNotifier('AI Q&A', 'Open a summary first before asking questions.', 'warning');
                    return;
                }

                const originalHtml = askBtnEl.innerHTML;
                askBtnEl.disabled = true;
                questionInputEl.disabled = true;
                askBtnEl.innerHTML = '<i class="ti ti-loader spinning me-1"></i> Thinking...';

                const payload = {
                    question,
                    summary: currentContext.summaryText,
                };
                if (currentContext.planId) payload.plan_id = currentContext.planId;
                if (currentContext.reportId) payload.report_id = currentContext.reportId;

                try {
                    const response = await fetch(askUrl, {
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

                    appendQaItem(data.question || question, data.answer || '');
                    questionInputEl.value = '';
                } catch (error) {
                    showNotifier('AI Q&A', error.message || 'Could not answer your question.', 'danger');
                } finally {
                    askBtnEl.disabled = false;
                    questionInputEl.disabled = false;
                    askBtnEl.innerHTML = originalHtml;
                    questionInputEl.focus();
                }
            }

            askBtnEl.addEventListener('click', askQuestion);
            questionInputEl.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' && !event.shiftKey) {
                    event.preventDefault();
                    askQuestion();
                }
            });

            document.querySelectorAll('.ai-document-summary-btn').forEach((button) => {
                button.addEventListener('click', function (event) {
                    event.preventDefault();

                    loadWeeklySummary(
                        this.dataset.planId || '',
                        this.dataset.reportId || '',
                        this.dataset.summaryTitle || '',
                        this.dataset.summaryStaff || ''
                    );
                });
            });
        });
    </script>
@endif
