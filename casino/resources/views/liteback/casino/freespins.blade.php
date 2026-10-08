@extends('liteback.layout')

@section('title', 'Freespin Yönetimi')
@section('page_title', 'Freespin Yönetimi')

@section('content')
<div class="row">
    <div class="col-md-4">
        <div class="card card-success">
            <div class="card-header">
                <h3 class="card-title">Freespin Ver</h3>
            </div>
            <form method="POST" action="{{ route('liteback.casino.freespins.issue') }}">
                @csrf
                <div class="card-body">
                    <div class="form-group">
                        <label>Kullanıcı Adı</label>
                        <input type="text" name="username" class="form-control" required placeholder="ornek_kullanici">
                    </div>
                    <div class="form-group">
                        <label>Oyun</label>
                        <input type="text" name="game_id" class="form-control" required list="freespin-games"
                               placeholder="01tech:BookOfDead">
                        <datalist id="freespin-games">
                            @foreach($games as $game)
                                <option value="{{ $game->provider_game_id }}">
                                    {{ $game->name }}{{ $game->game_type ? ' · ' . $game->game_type : '' }}
                                </option>
                            @endforeach
                        </datalist>
                        <small class="text-muted">"saglayici:oyun" biçiminde. Katalog boşsa elle girin.</small>
                    </div>
                    <div class="form-group">
                        <label>Spin Adedi</label>
                        <input type="number" name="quantity" class="form-control" min="1" max="1000" value="10" required>
                    </div>
                    <div class="form-group">
                        <label>Bahis (TRY)</label>
                        <input type="text" name="bet_amount" class="form-control" value="1.00" required>
                    </div>
                    <div class="form-group">
                        <label>Geçerlilik Sonu</label>
                        <input type="datetime-local" name="valid_until" class="form-control" required>
                        <small class="text-muted">En fazla 1 ay sonrası olabilir.</small>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-gift"></i> Freespin Gönder
                    </button>
                    <a href="{{ route('liteback.casino.providers') }}" class="btn btn-outline-secondary">
                        Sağlayıcılar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Freespin Kampanyaları</h3>
                <div class="card-tools">
                    <form method="GET" action="{{ route('liteback.casino.freespins') }}" class="form-inline">
                        <input type="text" name="q" value="{{ $search }}" class="form-control form-control-sm mr-2"
                               placeholder="kullanıcı / issue_id / oyun">
                        <button class="btn btn-sm btn-outline-light" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm table-striped mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Oyuncu</th>
                            <th>Oyun</th>
                            <th>Spin</th>
                            <th>Bahis</th>
                            <th>Kazanç</th>
                            <th>Durum</th>
                            <th>Geçerlilik</th>
                            <th>Issue ID</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($campaigns as $c)
                            <tr>
                                <td>{{ $c->id }}</td>
                                <td>{{ $usernames[$c->user_id] ?? ('#' . $c->user_id) }}</td>
                                <td class="small">{{ $c->game_id }}</td>
                                <td>{{ $c->quantity }}</td>
                                <td>{{ number_format((float) $c->bet_amount, 2) }}</td>
                                <td>{{ number_format((float) $c->win_amount, 2) }}</td>
                                <td>
                                    @php
                                        $badge = ['issued' => 'info', 'finished' => 'success', 'cancelled' => 'danger'][$c->status] ?? 'secondary';
                                        $label = ['issued' => 'Aktif', 'finished' => 'Tamamlandı', 'cancelled' => 'İptal'][$c->status] ?? $c->status;
                                    @endphp
                                    <span class="badge badge-{{ $badge }}">{{ $label }}</span>
                                </td>
                                <td class="small">{{ $c->valid_until ? $c->valid_until->format('d.m.Y H:i') : '-' }}</td>
                                <td class="small"><code>{{ \Illuminate\Support\Str::limit($c->issue_id, 16) }}</code></td>
                                <td>
                                    @if($c->status === 'issued')
                                        <form method="POST" action="{{ route('liteback.casino.freespins.cancel', $c->id) }}"
                                              onsubmit="return confirm('Bu freespin iptal edilsin mi?');">
                                            @csrf
                                            <button class="btn btn-xs btn-outline-danger" type="submit">İptal</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center text-muted py-4">Henüz freespin kampanyası yok.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer">{{ $campaigns->links() }}</div>
        </div>
    </div>
</div>
@endsection
