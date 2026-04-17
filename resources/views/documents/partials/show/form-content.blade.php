                        @php
                            $content = is_array($document->content) ? $document->content : [];
                        @endphp

                        @if($document->type === \App\Models\Document::TYPE_MONTHLY_REPORT)
                            @include('documents.partials.show.content.monthly-report')
                        @elseif(in_array($document->type, [\App\Models\Document::TYPE_WEEKLY_PLAN, \App\Models\Document::TYPE_WEEKLY_REPORT]) && !empty($content))
                            @if($document->type === \App\Models\Document::TYPE_WEEKLY_PLAN)
                                @include('documents.partials.show.content.weekly-plan')
                            @elseif($document->type === \App\Models\Document::TYPE_WEEKLY_REPORT)
                                @include('documents.partials.show.content.weekly-report-inline')
                            @endif
                        @else
                            @include('documents.partials.show.content.no-form-content')
                        @endif
