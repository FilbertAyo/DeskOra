                            <!-- Document Type (Read-only) -->
                            <div class="col-md-6 mb-3">
                                <div class="form-group">
                                    <label class="form-label">Document Type</label>
                                    <div class="input-group">
                                       
                                        <input type="text"
                                            class="form-control"
                                            value="{{ $document->type_name }}"
                                            disabled>
                                    </div>
                                    <small class="form-text text-muted">Document type cannot be changed</small>
                                </div>
                            </div>

                            <!-- State (Read-only) -->
                            <div class="col-md-6 mb-3">
                                <div class="form-group">
                                    <label class="form-label">State</label>
                                    <div class="input-group">
                                      
                                        <input type="text"
                                            class="form-control"
                                            value="{{ $document->state_name }}"
                                            disabled>
                                    </div>
                                    <small class="form-text text-muted">Current document state</small>
                                </div>
                            </div>
