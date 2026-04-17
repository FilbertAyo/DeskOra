                            <div class="alert alert-warning m-4">
                                <i class="ti ti-alert-triangle me-2"></i>
                                <strong>No document content available.</strong>
                                @if($document->canBeEdited())
                                    Please <a href="{{ route('documents.edit', $document->id) }}">complete the online form</a> to view the document.
                                @else
                                    The filled content for this document is not available.
                                @endif
                            </div>
