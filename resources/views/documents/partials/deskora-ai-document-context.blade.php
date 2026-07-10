@if(config('services.groq.api_key') && isset($document) && in_array($document->type, [\App\Models\Document::TYPE_WEEKLY_PLAN, \App\Models\Document::TYPE_WEEKLY_REPORT], true))
    @php
        $relatedForAi = $relatedDocument ?? $document->relatedWeeklyDocument();
        $deskoraAiPlanId = $document->type === \App\Models\Document::TYPE_WEEKLY_PLAN
            ? $document->id
            : ($relatedForAi?->type === \App\Models\Document::TYPE_WEEKLY_PLAN ? $relatedForAi->id : null);
        $deskoraAiReportId = $document->type === \App\Models\Document::TYPE_WEEKLY_REPORT
            ? $document->id
            : ($relatedForAi?->type === \App\Models\Document::TYPE_WEEKLY_REPORT ? $relatedForAi->id : null);
        $deskoraAiTitle = $document->period
            ? 'Week '.$document->period->week_number.' of '.$document->period->year
            : 'Weekly submission';
    @endphp
    @push('deskora-ai-context')
        <script type="application/json" id="deskora-ai-document-context">
            {!! json_encode([
                'planId' => $deskoraAiPlanId,
                'reportId' => $deskoraAiReportId,
                'title' => $deskoraAiTitle,
                'staff' => $document->user?->name,
            ], JSON_THROW_ON_ERROR) !!}
        </script>
    @endpush
@endif
