@extends('liteback.layout')

@section('title', 'License')

@section('content')
<style>
    .store-container { max-width: 1280px; padding-bottom: 40px; }
    .store-panel { background: #111827; border: 1px solid #1f2937; border-radius: 18px; padding: 24px 28px; margin-bottom: 24px; box-shadow: 0 8px 32px rgba(0, 0, 0, .35); }
    .store-heading { color: #fff; font-size: 1.5rem; font-weight: 800; letter-spacing: -.02em; }
    .store-sub, .drive-note { color: #94a3b8; font-family: 'JetBrains Mono', monospace; font-size: .82rem; }
    .store-label-title { color: #64748b; display: block; font-family: 'JetBrains Mono', monospace; font-size: .72rem; font-weight: 700; letter-spacing: .05em; margin-bottom: 3px; text-transform: uppercase; }
    .store-val { color: #fff; font-family: 'JetBrains Mono', monospace; font-size: .95rem; font-weight: 700; overflow-wrap: anywhere; }
    .store-stat-pill { background: #141e30; border: 1px solid #23334d; border-radius: 12px; height: 100%; padding: 12px 16px; }
    .key-input-box { background: #090d16; border: 1px solid #283955; border-radius: 10px; color: #fff; font-family: 'JetBrains Mono', monospace; font-size: .85rem; padding: 10px 14px; }
    .key-input-box:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, .25); outline: 0; }
    .badge-status { border-radius: 20px; display: inline-flex; font-size: .72rem; font-weight: 800; gap: 6px; padding: 4px 10px; }
    .badge-status-active { background: rgba(16, 185, 129, .2); border: 1px solid #10b981; color: #34d399; }
    .badge-status-warn { background: rgba(245, 158, 11, .2); border: 1px solid #f59e0b; color: #fbbf24; }
    .badge-status-danger { background: rgba(239, 68, 68, .2); border: 1px solid #ef4444; color: #f87171; }
</style>

<div class="store-container">
    <div class="mb-4 pb-2">
        <h1 class="store-heading mb-1"><i class="fas fa-key text-primary mr-2"></i>License</h1>
        <p class="store-sub mb-0">Manage the license assigned to this installation.</p>
    </div>

    @if(session('success')) <div class="alert alert-success mb-4">{{ session('success') }}</div> @endif
    @if(session('warning')) <div class="alert alert-warning mb-4">{{ session('warning') }}</div> @endif
    @if(session('danger')) <div class="alert alert-danger mb-4">{{ session('danger') }}</div> @endif
    @if($errors->any())
        <div class="alert alert-danger mb-4">
            @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
            <div class="mt-2">Need help? <a class="alert-link" href="https://promex.me/opensource-support/" target="_blank" rel="noopener noreferrer">Open a support ticket</a>.</div>
        </div>
    @endif

    <div class="store-panel">
        <div class="row align-items-center">
            <div class="col-lg-7 mb-4 mb-lg-0">
                <div class="d-flex align-items-center mb-2">
                    <span class="store-label-title mb-0 mr-2">License status:</span>
                    @if($license['status'] === 'active')
                        <span class="badge-status badge-status-active"><i class="fas fa-circle" style="font-size:8px;"></i> ACTIVE</span>
                    @elseif($license['status'] === 'grace_period')
                        <span class="badge-status badge-status-warn"><i class="fas fa-clock"></i> GRACE PERIOD</span>
                    @elseif($license['status'] === 'suspended')
                        <span class="badge-status badge-status-danger"><i class="fas fa-ban"></i> SUSPENDED</span>
                    @else
                        <span class="badge-status badge-status-warn"><i class="fas fa-shield-alt"></i> COMMUNITY / TRIAL</span>
                    @endif
                </div>
                <div class="h2 font-weight-bold text-white mb-3">{{ $license['plan'] ?? 'Community Edition' }}</div>
                <div class="row">
                    <div class="col-md-4 mb-2 mb-md-0"><div class="store-stat-pill"><span class="store-label-title">Bound domain</span><span class="store-val">{{ $license['domain'] ?? request()->getHost() }}</span></div></div>
                    <div class="col-md-4 mb-2 mb-md-0"><div class="store-stat-pill"><span class="store-label-title">Valid until</span><span class="store-val text-info">{{ !empty($license['valid_until']) ? date('M d, Y', strtotime($license['valid_until'])) : 'Lifetime / Unlimited' }}</span></div></div>
                    <div class="col-md-4"><div class="store-stat-pill"><span class="store-label-title">License key</span><span class="store-val text-warning">{{ !empty($license['license_key']) ? substr($license['license_key'], 0, 8) . '••••••••' . substr($license['license_key'], -4) : 'Unassigned' }}</span></div></div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="p-3" style="background:#0d1422; border:1px solid #1e2b40; border-radius:14px;">
                    <span class="store-label-title text-white"><i class="fas fa-key text-warning mr-1"></i>Update license key</span>
                    <form action="{{ route('liteback.store.update_license') }}" method="POST">
                        @csrf
                        <div class="form-group mb-2"><input type="text" name="license_key" value="{{ $license['license_key'] ?? '' }}" placeholder="PROMEX-XXXX-XXXX-XXXX" class="form-control key-input-box w-100"></div>
                        <button type="submit" class="btn btn-primary btn-block font-weight-bold"><i class="fas fa-save mr-1"></i> Save &amp; Activate</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="store-panel">
        <h2 class="h5 font-weight-bold text-white mb-1"><i class="fab fa-google-drive text-success mr-2"></i>Request all archives Drive share</h2>
        <p class="store-sub mb-3">Get a free 30-day (one-month) archive grant for one Google or preferred email address.</p>
        @if(session('drive_access'))
            <div class="alert alert-success mb-3">
                {{ session('drive_access.message') }}
                @if(!empty(session('drive_access.folder_url')))<a class="alert-link ml-1" href="{{ session('drive_access.folder_url') }}" target="_blank" rel="noopener noreferrer">Open shared Drive folder</a>@endif
            </div>
        @endif
        <form action="{{ route('liteback.store.archives_drive_access') }}" method="POST">
            @csrf
            <div class="row align-items-end">
                <div class="col-lg-7 mb-3 mb-lg-0">
                    <label class="store-label-title" for="archives-email">Google or preferred email address</label>
                    <input id="archives-email" type="email" name="email" value="{{ old('email') }}" required maxlength="254" autocomplete="email" class="form-control key-input-box w-100" placeholder="customer@gmail.com">
                </div>
                <div class="col-lg-5"><button type="submit" class="btn btn-success btn-block font-weight-bold"><i class="fas fa-paper-plane mr-1"></i> Confirm &amp; send request</button></div>
            </div>
            <div class="custom-control custom-checkbox mt-3">
                <input id="one-email-grant" type="checkbox" name="confirm_one_email_grant" value="1" required class="custom-control-input" {{ old('confirm_one_email_grant') ? 'checked' : '' }}>
                <label class="custom-control-label text-light" for="one-email-grant">I confirm this license receives one 30-day grant for one email address.</label>
            </div>
        </form>
        <p class="drive-note mt-3 mb-0">We send this request only when you submit this form. If it cannot be completed, <a href="https://promex.me/opensource-support/" target="_blank" rel="noopener noreferrer">open a support ticket</a>.</p>
    </div>
</div>
@endsection
