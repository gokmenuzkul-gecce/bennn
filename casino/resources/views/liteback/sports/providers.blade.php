@extends('liteback.layout')

@section('title', 'Spor Bahis Sağlayıcıları')
@section('page_title', 'Spor Bahis Sağlayıcıları')

@section('content')
<div class="row">
    <div class="col-md-10 offset-md-1">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Aktif Oran Sağlayıcısı</h3>
            </div>
            <div class="card-body">
                <p class="text-muted">
                    Battle Odds ve gelişmiş spor bahislerine maç öncesi oranları hangi kaynağın beslediğini seçin.
                    Sağlayıcılar takılabilir bağdaştırıcılardır: lisanslı bir akışı veya kendi anahtarınızı etkinleştirmek
                    platformun geri kalanını değiştirmez.
                </p>

                @if($lastSync)
                    <p class="small text-muted mb-3">
                        Last successful import: {{ \Carbon\Carbon::parse($lastSync)->diffForHumans() }}
                        @if($lastSyncCount !== null) ({{ (int) $lastSyncCount }} fixtures) @endif
                    </p>
                @else
                    <p class="small text-muted mb-3">Henüz hiçbir sağlayıcı içe aktarımı çalıştırılmadı.</p>
                @endif

                @foreach($catalog as $provider)
                    <div class="card mb-3 {{ $provider['selected'] ? 'border-primary' : '' }}">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h5 class="mb-1">
                                        {{ $provider['label'] }}
                                        @if($provider['selected'])
                                            <span class="badge badge-primary">Aktif</span>
                                        @endif
                                    </h5>
                                    <p class="mb-1 small">
                                        <span class="badge {{ $provider['configured'] ? 'badge-success' : 'badge-secondary' }}">
                                            {{ $provider['configured'] ? 'Ready' : 'Yapılandırılmadı' }}
                                        </span>
                                        @if($provider['requires_license'])
                                            <span class="badge badge-info">Lisans gerekli</span>
                                        @endif
                                        <span class="text-muted ml-1">{{ $provider['message'] }}</span>
                                    </p>
                                </div>
                                <div class="text-right">
                                    <button type="button"
                                            class="btn btn-outline-info btn-sm btn-test-provider"
                                            data-provider="{{ $provider['key'] }}">
                                        Bağlantıyı test et
                                    </button>
                                    @unless($provider['selected'])
                                        <form action="{{ route('liteback.sports.providers.select') }}" method="POST" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="provider" value="{{ $provider['key'] }}">
                                            <button type="submit" class="btn btn-primary btn-sm">Bu sağlayıcıyı kullan</button>
                                        </form>
                                    @endunless
                                </div>
                            </div>
                            <div class="small mt-2 d-none provider-test-result" data-provider="{{ $provider['key'] }}"></div>
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

        result.removeClass('d-none text-success text-danger text-muted').addClass('text-muted').text('Testing...');

        $.post('{{ route('liteback.sports.providers.test') }}', {
            _token: '{{ csrf_token() }}',
            provider: provider
        }).done(function(res) {
            const ok = res && res.success;
            result.removeClass('text-muted').addClass(ok ? 'text-success' : 'text-danger')
                .text((res && res.message) || (ok ? 'Connected.' : 'Connection failed.'));
        }).fail(function(xhr) {
            const msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Connection failed.';
            result.removeClass('text-muted').addClass('text-danger').text(msg);
        });
    });
});
</script>
@endsection
