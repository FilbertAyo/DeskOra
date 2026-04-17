                        <!-- PDF Viewer -->
                        <div style="min-height: 800px;">
                            <iframe
                                src="{{ $document->file_url }}"
                                style="width: 100%; height: 800px; border: none;"
                                type="application/pdf">
                                <p>Your browser does not support PDFs.
                                    <a href="{{ $document->file_url }}" target="_blank">Download the PDF</a> instead.
                                </p>
                            </iframe>
                        </div>
