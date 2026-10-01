@extends('liteback.layout')

@section('title', 'Sportsbook Dashboard')
@section('page_title', 'Sportsbook Dashboard')

@section('content')
<div class="row">
    <div class="col-lg-3 col-6">
        <div class="small-box bg-info" style="background-color: #17a2b8 !important; color: white; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
            <div class="inner">
                <h3>{{ $stats['total_bets'] }}</h3>
                <p>Verilen Toplam Bahis</p>
            </div>
            <div class="icon" style="float: right; margin-top: -60px; font-size: 40px; opacity: 0.3;"><i class="fas fa-ticket-alt"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-success" style="background-color: #28a745 !important; color: white; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
            <div class="inner">
                <h3>${{ number_format($stats['total_stakes'], 2) }}</h3>
                <p>Toplam Miktarlar</p>
            </div>
            <div class="icon" style="float: right; margin-top: -60px; font-size: 40px; opacity: 0.3;"><i class="fas fa-dollar-sign"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-warning" style="background-color: #ffc107 !important; color: #212529; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
            <div class="inner">
                <h3>${{ number_format($stats['total_payouts'], 2) }}</h3>
                <p>Toplam Ödemeler</p>
            </div>
            <div class="icon" style="float: right; margin-top: -60px; font-size: 40px; opacity: 0.3;"><i class="fas fa-gift"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-danger" style="background-color: #dc3545 !important; color: white; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
            <div class="inner">
                <h3>${{ number_format($stats['net_ggr'], 2) }}</h3>
                <p>Net GGR (gelir)</p>
            </div>
            <div class="icon" style="float: right; margin-top: -60px; font-size: 40px; opacity: 0.3;"><i class="fas fa-chart-line"></i></div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Manuel Komut Çalıştırıcı</h3>
            </div>
            <div class="card-body">
                <p class="text-muted">Lisanslı toplu akışı veya bakım komutlarını çalıştırın. PROMEX, herhangi bir veri döndürmeden önce bu kurulumu doğrular.</p>
                <form action="{{ route('liteback.sports.commands.run') }}" method="POST" class="mb-3">
                    @csrf
                    <div class="form-group">
                        <select name="command" id="sportsCommand" class="form-control" required>
                            <option value="sports:sync:all">sports:sync:all (RUN FULL SEQUENCE - leagues, games, odds, open, cleanup)</option>
                            <option value="sports:sync:upcoming">sports:sync:upcoming (fetch upcoming global pre-match odds - TEST / ONCE)</option>
                            <option value="sports:sync:leagues">sports:sync:leagues (fetch categories/leagues)</option>
                            <option value="sports:sync:games">sports:sync:games (fetch active events)</option>
                            <option value="sports:sync:odds">sports:sync:odds (fetch pre-match odds)</option>
                            <option value="sports:sync:odds-inplay">sports:sync:odds-inplay (fetch live odds)</option>
                            <option value="sports:games:open">sports:games:open (set games open for betting)</option>
                            <option value="sports:events:cleanup">sports:events:cleanup (run cleanup logic)</option>
                        </select>
                    </div>
                    <div id="sportsSelection" class="border rounded p-2 mb-3">
                        <div class="font-weight-bold mb-1">Bu çalıştırmaya dahil edilen sporlar</div>
                        <small class="text-muted d-block mb-2">Mevcut tüm akış sporları varsayılan olarak seçilidir. Bu çalıştırmada atlamak için bir sporun işaretini kaldırın.</small>
                        @if($sportsFeedError)
                            <div class="alert alert-warning py-2 mb-2">{{ $sportsFeedError }}</div>
                        @elseif(empty($availableSports))
                            <div class="text-muted small">Şu anda MAÇ ÖNCESİ spor yok.</div>
                        @else
                            <div class="row">
                                @foreach($availableSports as $sport)
                                    <div class="col-md-6">
                                        <div class="custom-control custom-checkbox mb-1">
                                            <input type="checkbox" class="custom-control-input" id="sport_{{ $loop->index }}" name="sports[]" value="{{ $sport['key'] }}" checked>
                                            <label class="custom-control-label" for="sport_{{ $loop->index }}">{{ $sport['title'] }}</label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Komutu Çalıştır</button>
                </form>
                <hr>
                <form action="{{ route('liteback.sports.odds.clear_active') }}" method="POST" onsubmit="return confirm('Hide every active site odd? Bets and historical records will be preserved.');">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger btn-block font-weight-bold">
                        <i class="fas fa-eraser mr-1"></i> Aktif Site Oranlarını Temizle
                    </button>
                    <small class="text-muted d-block mt-1">
                        Bahisleri veya geçmişi silmeden aktif Battle Odds ve spor bahis piyasalarını gizler. Bir sonraki tam akış senkronizasyonu mevcut oranları geri yükler.
                    </small>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Son Senkronizasyon kayıtları</h3>
            </div>
            <div class="card-body p-0">
                <table class="table table-striped table-valign-middle">
                    <thead>
                        <tr>
                            <th>İş Takma Adı</th>
                            <th>Başlangıç</th>
                            <th>Süre</th>
                            <th>Durum</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cronLogs as $log)
                            <tr>
                                <td><code>{{ $log->job_alias }}</code></td>
                                <td>{{ $log->start_at }}</td>
                                <td>{{ $log->duration }}s</td>
                                <td>
                                    @if($log->error)
                                        <span class="badge badge-danger" title="{{ $log->error }}">Başarısız</span>
                                    @else
                                        <span class="badge badge-success">Başarılı</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted">Henüz kayıt yok.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(function() {
    const toggleSports = function() {
        $('#sportsSelection').toggle($('#sportsCommand').val() === 'sports:sync:all');
    };
    $('#sportsCommand').on('change', toggleSports);
    toggleSports();
});
</script>
@endsection
