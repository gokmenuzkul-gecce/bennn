@extends('liteback.layout')

@section('title', 'Account & Login Security')
@section('page_title', 'Account & Login Security')

@section('content')
    @php
        $whatsAppLoginEnabled = (string) settings('enable_whatsapp_otp', '1') === '1';
        $passwordLoginEnabled = (string) settings('enable_password_login', '1') === '1';
        $phoneReady = $user->phone && $user->phone_verified_at && empty($phoneUnavailableReason);
        $preferredMethod = old('preferred_login_method', $phoneReady ? $user->preferred_login_method : 'password');
    @endphp
    @if($user->must_change_password)
        <div class="alert alert-warning">
            <strong>First-time setup required.</strong> Replace the temporary administrator password before using the platform.
            Phone verification is optional and does not require completion now. Use password sign-in to finish setup, then configure WhatsApp delivery.
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-header">
            <h5 class="card-title mb-0">Contact details and sign-in preference</h5>
        </div>
        <div class="card-body">
            <form method="post" action="{{ route('liteback.profile.password.update') }}">
                @csrf
                <div class="row">
                    <div class="form-group col-md-6">
                        <label for="email">Email address</label>
                        <input type="email" name="email" id="email" class="form-control" required maxlength="191" value="{{ old('email', $user->email) }}" autocomplete="email">
                        <small class="text-muted">Changing this requires your current password.</small>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="phone">Mobile / WhatsApp number</label>
                        <input type="tel" name="phone" id="phone" class="form-control" maxlength="32" value="{{ old('phone', $user->phone) }}" placeholder="+96170123456" autocomplete="tel">
                        <small class="text-muted">
                            @if($user->phone && $user->phone_verified_at)
                                <span class="text-success">Verified.</span>
                            @elseif($user->phone)
                                <span class="text-warning">Verification pending. Save your changes, then use the verification controls below.</span>
                            @else
                                Add a number to use phone-first login.
                            @endif
                        </small>
                    </div>
                </div>

                <div class="form-group">
                    <label for="preferred_login_method">Preferred sign-in</label>
                    <select name="preferred_login_method" id="preferred_login_method" class="form-control" required>
                        @if($whatsAppLoginEnabled && ($phoneReady || !$passwordLoginEnabled))
                        <option value="phone" {{ $preferredMethod === 'phone' ? 'selected' : '' }}>Phone / WhatsApp code</option>
                        @endif
                        @if($passwordLoginEnabled)
                        <option value="password" {{ $preferredMethod === 'password' ? 'selected' : '' }}>Username, email, or phone + password</option>
                        @endif
                    </select>
                    <small class="text-muted">Verify your saved number and configure WhatsApp delivery before selecting phone sign-in. Saving a new, unverified number keeps password sign-in selected when enabled.</small>
                </div>

                <hr>
                <h6 class="font-weight-bold">{{ $user->must_change_password ? 'Choose your permanent password' : 'Change password (optional)' }}</h6>
                <div class="row">
                    <div class="form-group col-md-6">
                        <label for="password">Yeni parola</label>
                        <input type="password" name="password" id="password" class="form-control" minlength="8" {{ $user->must_change_password ? 'required' : '' }} autocomplete="new-password">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="password_confirmation">Confirm new password</label>
                        <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" minlength="8" {{ $user->must_change_password ? 'required' : '' }} autocomplete="new-password">
                    </div>
                </div>

                <div class="form-group border rounded p-3 bg-light">
                    <label for="current_password" class="font-weight-bold">Mevcut parola</label>
                    <input type="password" name="current_password" id="current_password" class="form-control" required autocomplete="current-password">
                    <small class="text-muted">Required to change your email, phone, login preference, or password.</small>
                </div>

                <button type="submit" class="btn btn-primary">Save account security</button>
            </form>
        </div>
    </div>
    <div class="card shadow-sm mt-3">
        <div class="card-body">
            <h5>Verify your WhatsApp number</h5>
            <p>Save the number above first. A code verifies ownership of that saved number; adding a number does not verify it automatically.</p>
            @if($user->must_change_password)
                <div class="alert alert-info">Choose your permanent password first. Then return here to verify your phone. No license is required to complete password setup.</div>
            @elseif(!empty($phoneUnavailableReason))
                <div class="alert alert-warning">{{ $phoneUnavailableReason }}</div>
            @elseif(!$user->phone)
                <p>Save a mobile number with its country code to enable verification.</p>
            @elseif(!$user->phone_verified_at)
                <form method="post" action="{{ route('liteback.profile.phone.send') }}" class="mb-3">
                    @csrf
                    <label for="verification_password">Mevcut parola</label>
                    <input type="password" id="verification_password" name="current_password" class="form-control mb-2" required autocomplete="current-password">
                    <button class="btn btn-primary" type="submit">Send WhatsApp verification code</button>
                    <small class="d-block text-muted">Sends to {{ $user->phone }}. Codes expire after 10 minutes; wait one minute before resending.</small>
                </form>
                <form method="post" action="{{ route('liteback.profile.phone.verify') }}">
                    @csrf
                    <label for="otp_code">Six-digit code</label>
                    <input id="otp_code" name="otp_code" class="form-control mb-2" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required>
                    <button class="btn btn-primary" type="submit">Verify phone</button>
                </form>
            @else
                <p class="text-success">Your saved number is verified.</p>
            @endif
        </div>
    </div>
@endsection
