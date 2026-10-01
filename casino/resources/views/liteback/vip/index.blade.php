@extends('liteback.layout')

@section('title', 'Liteback - VIP Kulübü ve Rakeback Management')
@section('page_title', 'VIP Club & Loyalty Rakeback Engine')

@section('content')
<div class="container-fluid">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle mr-2"></i> {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif

    @if(isset($errors) && $errors->any())
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle mr-2"></i> {{ $errors->first() }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif

    <!-- Metric Stat Widgets -->
    <div class="row">
        <div class="col-lg-3 col-6">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3>${{ number_format($totalRakebackPaid, 2) }}</h3>
                    <p>Ödenen Toplam Rakeback</p>
                </div>
                <div class="icon"><i class="fas fa-hand-holding-usd"></i></div>
                <span class="small-box-footer">Kredilenen Anında Nakit</span>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3>${{ number_format($totalUnclaimedRakeback, 2) }}</h3>
                    <p>Alınmamış Rakeback Kasası</p>
                </div>
                <div class="icon"><i class="fas fa-vault"></i></div>
                <span class="small-box-footer">Oyuncu Talebi Bekliyor</span>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-primary">
                <div class="inner">
                    <h3>${{ number_format($totalBonusesPaid, 2) }}</h3>
                    <p>Ödenen Seviye Atlama Bonusları</p>
                </div>
                <div class="icon"><i class="fas fa-gift"></i></div>
                <span class="small-box-footer">Kilometre Taşı Nakit Ödülleri</span>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3>{{ number_format($totalCirculatingXp) }}</h3>
                    <p>Dolaşımdaki VIP XP</p>
                </div>
                <div class="icon"><i class="fas fa-medal"></i></div>
                <span class="small-box-footer">Tüm Oyunlarda Birikmiş</span>
            </div>
        </div>
    </div>

    <!-- Tier Member Distribution Pills -->
    <div class="card card-outline card-secondary mb-4">
        <div class="card-header">
            <h3 class="card-title font-weight-bold"><i class="fas fa-users-cog mr-2"></i> Kademeler Arası VIP Üye Dağılımı</h3>
        </div>
        <div class="card-body py-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                @foreach($tiers as $tName => $tData)
                <div class="text-center p-3 rounded bg-light border flex-fill mx-1 mb-2">
                    <span class="badge font-weight-bold" style="background-color: {{ $tData['badge_color'] }}; color: #fff;">{{ $tName }}</span>
                    <h4 class="font-weight-bold mt-2 mb-0">{{ number_format($tierDistribution[$tName] ?? 0) }}</h4>
                    <small class="text-muted">Oyuncular</small>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Tier Configuration Form -->
    <div class="card card-outline card-primary mb-4">
        <div class="card-header">
            <h3 class="card-title font-weight-bold"><i class="fas fa-sliders-h mr-2"></i> VIP Sadakat Kademesi Yapılandırmaları</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('liteback.vip.settings') }}" method="POST">
                @csrf
                <div class="table-responsive">
                    <table class="table table-bordered table-sm text-center">
                        <thead class="thead-light">
                            <tr>
                                <th>VIP Kademesi</th>
                                <th>Gerekli XP Eşiği</th>
                                <th>Rakeback Oranı (%)</th>
                                <th>Seviye Atlama Nakit Bonusu ($)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($tiers as $tName => $t)
                            @php $slug = strtolower(str_replace(' ', '_', $tName)); @endphp
                            <tr>
                                <td class="font-weight-bold text-left align-middle" style="color: {{ $t['badge_color'] }};">
                                    <i class="fas fa-crown mr-1"></i> {{ $tName }}
                                </td>
                                <td>
                                    <input type="number" name="vip_{{ $slug }}_threshold" class="form-control form-control-sm text-center font-weight-bold" value="{{ $t['threshold'] }}">
                                </td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <input type="number" step="0.1" name="vip_{{ $slug }}_rakeback" class="form-control text-center font-weight-bold" value="{{ $t['rakeback'] }}">
                                        <div class="input-group-append"><span class="input-group-text">%</span></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend"><span class="input-group-text">$</span></div>
                                        <input type="number" step="1" name="vip_{{ $slug }}_bonus" class="form-control text-center font-weight-bold" value="{{ $t['level_bonus'] }}">
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <button type="submit" class="btn btn-primary font-weight-bold mt-2"><i class="fas fa-save mr-1"></i> VIP Yapılandırmalarını Kaydet</button>
            </form>
        </div>
    </div>

    <!-- High-Roller Member Management Table -->
    <div class="card card-outline card-warning mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title font-weight-bold"><i class="fas fa-gem text-warning mr-2"></i> VIP Üyeler ve Yüksek Oyuncular</h3>
            <div class="card-tools">
                <form method="GET" action="{{ route('liteback.vip.index') }}" class="form-inline">
                    <div class="input-group input-group-sm" style="width: 250px;">
                        <input type="text" name="search" class="form-control float-right" placeholder="Kullanıcı veya kademe ara..." value="{{ $search }}">
                        <div class="input-group-append">
                            <button type="submit" class="btn btn-default"><i class="fas fa-search"></i></button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <div class="card-body table-responsive p-0">
            <table class="table table-hover table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Kullanıcı</th>
                        <th>Mevcut Kademe</th>
                        <th>Birikmiş XP</th>
                        <th>Alınmamış Rakeback</th>
                        <th>Alınan Toplam</th>
                        <th>Manuel Kademe Geçersiz Kılma</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($vipUsers as $u)
                    <tr>
                        <td>{{ $u->id }}</td>
                        <td>
                            <strong>{{ $u->username }}</strong>
                            <div class="text-muted small">{{ $u->email ?? 'No email' }}</div>
                        </td>
                        <td>
                            <span class="badge badge-pill font-weight-bold" style="background-color: {{ $tiers[$u->vip_level]['badge_color'] ?? '#999' }}; color: #fff;">
                                {{ $u->vip_level }}
                            </span>
                        </td>
                        <td><strong class="text-primary font-monospace">{{ number_format($u->vip_xp) }} XP</strong></td>
                        <td><strong class="text-warning">${{ number_format($u->unclaimed_rakeback, 2) }}</strong></td>
                        <td><strong class="text-success">${{ number_format($u->total_rakeback_claimed, 2) }}</strong></td>
                        <td>
                            <form action="{{ route('liteback.vip.users.tier', $u->id) }}" method="POST" class="form-inline">
                                @csrf
                                <select name="vip_level" class="form-control form-control-sm mr-2 font-weight-bold">
                                    @foreach($tiers as $tn => $td)
                                        <option value="{{ $tn }}" {{ $u->vip_level === $tn ? 'selected' : '' }}>{{ $tn }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-xs btn-outline-success font-weight-bold">Uygula</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">Aramayla eşleşen VIP oyuncu yok.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($vipUsers->hasPages())
        <div class="card-footer clearfix">
            {{ $vipUsers->appends(['search' => $search])->links() }}
        </div>
        @endif
    </div>

    <!-- Recent VIP Claims Ledger -->
    <div class="card card-outline card-info">
        <div class="card-header">
            <h3 class="card-title font-weight-bold"><i class="fas fa-history mr-2"></i> Son VIP Ödül Talep Etkinliği</h3>
        </div>
        <div class="card-body table-responsive p-0">
            <table class="table table-hover table-sm text-nowrap">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Zaman</th>
                        <th>Kullanıcı</th>
                        <th>Tür</th>
                        <th>Kademe</th>
                        <th>Alınan Tutar</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentClaims as $claim)
                    <tr>
                        <td>#{{ $claim->id }}</td>
                        <td>{{ $claim->created_at ? $claim->created_at->format('Y-m-d H:i') : '-' }}</td>
                        <td><strong>{{ $claim->user->username ?? 'ID: ' . $claim->user_id }}</strong></td>
                        <td>
                            @if($claim->type === 'rakeback')
                                <span class="badge badge-success">Rakeback</span>
                            @else
                                <span class="badge badge-warning">Seviye Atlama Bonusu</span>
                            @endif
                        </td>
                        <td><span class="badge badge-light border">{{ $claim->tier }}</span></td>
                        <td><strong class="text-success">+${{ number_format($claim->amount, 2) }}</strong></td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">Henüz VIP ödülü alınmadı.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($recentClaims->hasPages())
        <div class="card-footer clearfix">
            {{ $recentClaims->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
