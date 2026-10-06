@extends('liteback.layout')

@section('title', 'Kumarhane Sağlayıcıları')
@section('page_title', 'Kumarhane Sağlayıcıları')

@section('content')
<div class="row">
    <div class="col-md-3 col-sm-6 col-12">
        <div class="small-box bg-info">
            <div class="inner">
                <h3>{{ number_format($stats['transactions']) }}</h3>
                <p>Cüzdan İşlemi</p>
            </div>
            <div class="icon"><i class="fas fa-exchange-alt"></i></div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 col-12">
        <div class="small-box bg-success">
            <div class="inner">
                <h3>{{ number_format($stats['players']) }}</h3>
                <p>Eşlenen Oyuncu</p>
            </div>
            <div class="icon"><i class="fas fa-users"></i></div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 col-12">
        <div class="small-box bg-warning">
            <div class="inner">
                <h3>{{ number_format($stats['volume'], 2) }}</h3>
                <p>Toplam Hacim</p>
            </div>
            <div class="icon"><i class="fas fa-coins"></i></div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 col-12">
        <div class="small-box bg-primary">
            <div class="inner">
                <h3>{{ count($catalog) }}</h3>
                <p>Sağlayıcı</p>
            </div>
            <div class="icon"><i class="fas fa-plug"></i></div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Bağlı Oyun Sağlayıcıları</h3>
                <div class="card-tools">
                    <a href="{{ route('liteback.casino.transactions') }}" class="btn btn-sm btn-outline-light">
                        <i class="fas fa-list"></i> Cüzdan İşlemleri
                    </a>
                </div>
            </div>
            <div class="card-body">
                <p class="text-muted">
                    Site bakiyesi bu sağlayıcılarla gerçek zamanlı senkron çalışır. Sağlayıcı oyun sunucusu
                    bakiye sorgulama ve bahis/ödeme işlemleri için aşağıdaki callback adresine istek gönderir:
                    <code>{{ $catalog[0]['callback_url'] ?? '' }}</code>
                </p>

                @foreach($catalog as $provider)
                    <div class="card mb-3 {{ $provider['enabled'] ? 'border-success' : 'border-secondary' }}">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    {{ $provider['label'] }}
                                    <span class="badge {{ $provider['configured'] ? 'badge-success' : 'badge-secondary' }} ml-2">
                                        {{ $provider['configured'] ? 'Yapılandırıldı' : 'Eksik' }}
                                    </span>
                                    <span class="badge {{ $provider['enabled'] ? 'badge-primary' : 'badge-danger' }} ml-1">
                                        {{ $provider['enabled'] ? 'Aktif' : 'Kapalı' }}
                                    </span>
                                </h5>
                                <div>
                                    <button type="button" class="btn btn-outline-info btn-sm btn-test-provider"
                                            data-provider="{{ $provider['key'] }}">Bağlantıyı Test Et</button>
                                    <form action="{{ route('liteback.casino.providers.toggle') }}" method="POST" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="provider" value="{{ $provider['key'] }}">
                                        <button type="submit" class="btn btn-sm {{ $provider['enabled'] ? 'btn-outline-danger' : 'btn-outline-success' }}">
                                            {{ $provider['enabled'] ? 'Kapat' : 'Aç' }}
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="small text-muted mb-2">{{ $provider['message'] }}</div>
                            <div class="small mb-2 d-none provider-test-result" data-provider="{{ $provider['key'] }}"></div>

                            <form action="{{ route('liteback.casino.providers.update') }}" method="POST">
                                @csrf
                                <input type="hidden" name="provider" value="{{ $provider['key'] }}">
                                @if($provider['key'] === 'gregmorn')
                                    <div class="row">
                                        <div class="col-md-3">
                                            <label class="small mb-0">Office Base URL</label>
                                            <input type="text" name="office_base_url" class="form-control form-control-sm"
                                                   value="{{ $provider['endpoint'] }}" placeholder="https://office-api-dev.gregmorn.org">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="small mb-0">Client Base URL</label>
                                            <input type="text" name="client_base_url" class="form-control form-control-sm"
                                                   placeholder="https://client-api-dev.gregmorn.org">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="small mb-0">Login</label>
                                            <input type="text" name="login" class="form-control form-control-sm"
                                                   placeholder="operatör hesabı">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="small mb-0">Password</label>
                                            <input type="text" name="password" class="form-control form-control-sm"
                                                   placeholder="boş bırak = değişmez">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="small mb-0">Secret Key</label>
                                            <input type="text" name="secret_key" class="form-control form-control-sm"
                                                   placeholder="boş bırak = değişmez">
                                        </div>
                                    </div>
                                    <div class="row mt-1">
                                        <div class="col-md-4">
                                            <label class="small mb-0">User ID (boş = login yanıtından)</label>
                                            <input type="text" name="user_id" class="form-control form-control-sm"
                                                   placeholder="uuid">
                                        </div>
                                        <div class="col-md-5">
                                            <label class="small mb-0">Callback URL (salt okunur)</label>
                                            <input type="text" class="form-control form-control-sm" value="{{ $provider['callback_url'] }}" readonly>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="small mb-0">Para Birimi</label>
                                            <input type="text" class="form-control form-control-sm" value="TRY" readonly>
                                        </div>
                                    </div>
                                @elseif($provider['key'] === 'oroplay')
                                    <div class="row">
                                        <div class="col-md-4">
                                            <label class="small mb-0">Base URL</label>
                                            <input type="text" name="base_url" class="form-control form-control-sm"
                                                   value="{{ $provider['endpoint'] }}" placeholder="https://api.oroplay.com/api/v2">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="small mb-0">Client ID</label>
                                            <input type="text" name="client_id" class="form-control form-control-sm"
                                                   placeholder="stg-TRY-...">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="small mb-0">Client Secret</label>
                                            <input type="text" name="client_secret" class="form-control form-control-sm"
                                                   placeholder="boş bırak = değişmez">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="small mb-0">Para Birimi</label>
                                            <input type="text" class="form-control form-control-sm" value="TRY" readonly>
                                        </div>
                                    </div>
                                @else
                                    <div class="row">
                                        <div class="col-md-3">
                                            <label class="small mb-0">Endpoint</label>
                                            <input type="text" name="endpoint" class="form-control form-control-sm"
                                                   value="{{ $provider['endpoint'] }}" placeholder="pk2api.loginxgamesapi.com">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="small mb-0">Agent ID</label>
                                            <input type="text" name="agent_id" class="form-control form-control-sm"
                                                   placeholder="agent kimliği">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="small mb-0">API Token</label>
                                            <input type="text" name="api_token" class="form-control form-control-sm"
                                                   placeholder="boş bırak = değişmez">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="small mb-0">Secret Key</label>
                                            <input type="text" name="secret_key" class="form-control form-control-sm"
                                                   placeholder="boş bırak = değişmez">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="small mb-0">Callback URL (salt okunur)</label>
                                            <input type="text" class="form-control form-control-sm" value="{{ $provider['callback_url'] }}" readonly>
                                        </div>
                                    </div>
                                @endif
                                <button type="submit" class="btn btn-primary btn-sm mt-2">Kaydet</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(function() {
    $('.btn-test-provider').on('click', function() {
        const button = $(this);
        const provider = button.data('provider');
        const result = $('.provider-test-result[data-provider="' + provider + '"]');

        result.removeClass('d-none text-success text-danger text-muted').addClass('text-muted').text('Test ediliyor...');

        $.post('{{ route('liteback.casino.providers.test') }}', {
            _token: '{{ csrf_token() }}',
            provider: provider
        }).done(function(res) {
            const ok = res && res.success;
            result.removeClass('text-muted').addClass(ok ? 'text-success' : 'text-danger')
                .text((res && res.message) || (ok ? 'Bağlantı başarılı.' : 'Bağlantı başarısız.'));
        }).fail(function(xhr) {
            const msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Bağlantı başarısız.';
            result.removeClass('text-muted').addClass('text-danger').text(msg);
        });
    });
});
</script>
@endsection
