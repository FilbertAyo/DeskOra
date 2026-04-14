<section>
    <div class="row align-items-start">
        <!-- Left Side - Profile Image -->
        <div class="col-md-4 col-lg-3">
            <div class="card shadow-none border">
                <div class="card-body text-center p-4">
                    <div class="profile-picture mb-3">
                        <button type="button" class="avatar avatar-xxl bg-transparent border-0 p-0" data-bs-toggle="modal" data-bs-target="#exampleModal"
                           style="margin: 0; cursor: pointer; position: relative; display: inline-block;">
                            <img src="{{ asset(Auth::user()->file ?? 'assets/images/profile.png') }}" alt="Profile Picture"
                                class="avatar-img rounded-circle shadow-sm" style="width: 150px; height: 150px; object-fit: cover;" />
                            <div class="avatar-overlay">
                                <i class="ti ti-camera"></i>
                            </div>
                        </button>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#exampleModal">
                        <i class="ti ti-edit-2 mr-1"></i>Change Photo
                    </button>
                    <p class="text-muted small mt-2 mb-0">JPG, PNG or JPEG. Max 2MB</p>
                </div>
            </div>
        </div>

        <!-- Right Side - User Information -->
        <div class="col-md-8 col-lg-9">
            <div class="card shadow-none border">
                <div class="card-body p-4">
                    <!-- User Name and Contact -->
                    <div class="mb-4">
                        <h3 class="mb-2">{{ $user->name }}</h3>
                        <div class="d-flex flex-wrap gap-3 text-muted">
                            <div class="d-flex align-items-center mr-4">
                                <i class="ti ti-mail mr-2 text-primary"></i>
                                <span>{{ $user->email }}</span>
                            </div>
                            <div class="d-flex align-items-center">
                                <i class="ti ti-phone mr-2 text-primary"></i>
                                <span>{{ $user->phone }}</span>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- Update Contact & Notifications -->
                    <form action="{{ route('profile.update') }}" method="POST" class="mb-4">
                        @csrf
                        @method('PATCH')
                        <div class="form-row">
                            <div class="form-group col-md-12">
                                <label for="name">Full Name</label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $user->name) }}" required>
                                @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-group col-md-12">
                                <label for="email">Email</label>
                                <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', $user->email) }}" required>
                                @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-12">
                                <label for="phone">Phone</label>
                                <input type="text" class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone', $user->phone) }}" placeholder="0XXXXXXXXX">
                                @error('phone')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-group col-md-12">
                                <label for="notification_channel">Notification Channel</label>
                                <select id="notification_channel" name="notification_channel" class="form-control @error('notification_channel') is-invalid @enderror">
                                    @php($currentChannel = old('notification_channel', $user->notification_channel ?? 'sms'))
                                    <option value="sms" {{ $currentChannel === 'sms' ? 'selected' : '' }}>SMS (default)</option>
                                    <option value="email" {{ $currentChannel === 'email' ? 'selected' : '' }}>Email</option>
                                    <option value="both" {{ $currentChannel === 'both' ? 'selected' : '' }}>Both</option>
                                </select>
                                @error('notification_channel')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="text-right">
                            <button type="submit" class="btn btn-primary">
                                <i class="ti ti-save mr-1"></i>Save Changes
                            </button>
                        </div>
                    </form>

                    <!-- Department and Branch Information -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="info-item p-3 bg-light rounded">
                                <div class="d-flex align-items-center">
                                    
                                    <div>
                                        <small class="text-muted d-block">Department</small>
                                        <strong class="text-dark">{{ $user->department->name ?? 'N/A' }}</strong>
                                    </div>
                                </div>
                            </div>
                        </div>


                        <div class="col-md-6 mb-3">
                            <div class="info-item p-3 bg-light rounded">
                                <div class="d-flex align-items-center">
                                  
                                    <div>
                                        <small class="text-muted d-block">Member Since</small>
                                        <strong class="text-dark">{{ $user->created_at->format('M d, Y') }}</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Profile Image Modal -->
    <div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title" id="exampleModalLabel">
                        <i class="ti ti-image mr-2"></i>Upload Profile Image
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <form action="{{ route('profile.image') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="form-group">
                            <label for="profile_image" class="form-label">Choose Image</label>
                            <div class="custom-file">
                                <input type="file" name="profile_image" class="custom-file-input" id="profile_image"
                                       accept="image/jpeg,image/jpg,image/png" required>
                                <label class="custom-file-label" for="profile_image">Select file...</label>
                            </div>
                            <small class="form-text text-muted">
                                <i class="ti ti-info mr-1"></i>Accepted formats: JPG, JPEG, PNG. Maximum size: 2MB
                            </small>
                        </div>
                        <div class="text-right mt-4">
                            <button type="button" class="btn btn-secondary mr-2" data-bs-dismiss="modal">Cancel</button>
                            <x-primary-button label="Upload Image" />
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <style>
        .avatar-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .avatar:hover .avatar-overlay {
            opacity: 1;
        }
        .avatar-overlay i {
            color: white;
            font-size: 1.5rem;
        }
        .icon-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .info-item {
            transition: all 0.2s ease;
        }
        .info-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
    </style>
</section>
