    <!-- PDF Viewer - Full Width -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-none border mb-4">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="ti ti-file-text me-2"></i>{{ $document->display_title }}
                    </h5>
                    <div class="d-flex gap-2">
                        @if(in_array($document->type, [\App\Models\Document::TYPE_WEEKLY_PLAN, \App\Models\Document::TYPE_WEEKLY_REPORT], true))
                            <a href="{{ route('documents.download', $document) }}" class="btn btn-sm btn-info">
                                <i class="ti ti-download me-1"></i> Download PDF
                            </a>
                        @endif
                        @if($document->file_path && $document->hasFile())
                            <a href="{{ $document->file_url }}" target="_blank" class="btn btn-sm btn-info">
                                <i class="ti ti-download me-1"></i> Download PDF
                            </a>
                        @endif
                    </div>
                </div>
                <div class="card-body p-0">
                    @if($document->file_path && $document->hasFile())
                        @include('documents.partials.show.content.pdf-viewer')
                    @else
                        @include('documents.partials.show.form-content')
                    @endif
                </div>
            </div>
        </div>
    </div>
