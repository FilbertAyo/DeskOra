<x-app-layout>
    @include('documents.partials.edit.page-header')
    @include('documents.partials.edit.alerts')

    <div class="row">
        <div class="col-md-12">
            <form method="POST" action="{{ route('documents.update', $document->id) }}" id="editDocumentForm" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <input type="hidden" name="action" id="hiddenAction" value="draft">
                <div class="card shadow-none border">
                    <div class="card-header bg-white border-bottom">
                        <div class="row align-items-center">
                            <div class="col-md-8 mb-2 mb-md-0">
                                <h5 class="mb-0">
                                    <i class="ti ti-file-text me-2"></i>Edit Document
                                    <span class="badge bg-light-info border border-info ml-2">{{ $document->type_name }}</span>
                                </h5>
                            </div>
                            <div class="col-md-4 text-md-end">
                                <small class="text-muted">Update fields below, then save draft or submit.</small>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            @include('documents.partials.edit.document-type-state')

                            @if($document->type === 'weekly_plan')
                                @include('documents.partials.edit.weekly-plan-fields')
                            @endif

                            @if($document->type === 'monthly_report')
                                @include('documents.partials.edit.monthly-report-fields')
                            @endif

                            @if($document->type === 'weekly_report')
                                @include('documents.partials.edit.weekly-report-fields')
                            @endif
                        </div>
                    </div>

                    @include('documents.partials.edit.form-footer')
                </div>
            </form>
        </div>
    </div>

    @include('documents.partials.edit.scripts')
    @include('documents.partials.edit.styles')
</x-app-layout>
