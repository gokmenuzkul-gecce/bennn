@extends('liteback.layout')

@section('title', 'Liteback - Users Manager')
@section('page_title', 'Oyuncu ve Kullanıcı Yönetimi')

@section('content')
    <!-- Quick Add User Card -->
    <div class="card mb-3">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0 font-weight-bold"><i class="fas fa-user-plus mr-1 text-success"></i> Oyuncu Hesabı Oluştur</h5>
            <button class="btn btn-sm btn-outline-secondary" type="button" data-toggle="collapse" data-target="#addUserCollapse">
                Formu Aç/Kapat
            </button>
        </div>
        <div class="collapse" id="addUserCollapse">
            <div class="card-body">
                <form method="post" action="{{ route('liteback.users.store') }}">
                    @csrf
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label class="small font-weight-bold">Kullanıcı Adı *</label>
                            <input type="text" name="username" class="form-control" placeholder="Oyuncu kullanıcı adı" required>
                        </div>
                        <div class="form-group col-md-3">
                            <label class="small font-weight-bold">Telefon (WhatsApp OTP)</label>
                            <input type="text" name="phone" class="form-control" placeholder="+961 70 123456">
                        </div>
                        <div class="form-group col-md-3">
                            <label class="small font-weight-bold">E-posta (İsteğe Bağlı)</label>
                            <input type="email" name="email" class="form-control" placeholder="player@domain.com">
                        </div>
                        <div class="form-group col-md-3">
                            <label class="small font-weight-bold">Parola *</label>
                            <input type="password" name="password" class="form-control" placeholder="En az 6 karakter" required>
                        </div>
                        <div class="form-group col-md-3">
                            <label class="small font-weight-bold">Başlangıç Bakiyesi (Coin)</label>
                            <input type="number" step="100" min="0" name="balance" class="form-control" placeholder="e.g. 50000" value="50000">
                        </div>
                        <div class="form-group col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-success btn-block"><i class="fas fa-save mr-1"></i> Kullanıcı Oluştur</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Search & Filter Card -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <form class="form-row align-items-center" method="get" action="{{ route('liteback.users.index') }}">
                <div class="col-md-5 my-1">
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                        </div>
                        <input type="text" name="q" class="form-control" placeholder="ID, kullanıcı adı, telefon, e-posta veya davet kodu ile ara..." value="{{ $term }}">
                    </div>
                </div>
                <div class="col-md-3 my-1">
                    <select name="status" class="form-control">
                        <option value="all" {{ $statusFilter === 'all' ? 'selected' : '' }}>Tüm Durumlar</option>
                        <option value="active" {{ $statusFilter === 'active' ? 'selected' : '' }}>Yalnızca Aktif</option>
                        <option value="blocked" {{ $statusFilter === 'blocked' ? 'selected' : '' }}>Yalnızca Engelli / Yasaklı</option>
                    </select>
                </div>
                <div class="col-md-2 my-1">
                    <select name="balance_filter" class="form-control">
                        <option value="all" {{ $balanceFilter === 'all' ? 'selected' : '' }}>Tüm Bakiyeler</option>
                        <option value="positive" {{ $balanceFilter === 'positive' ? 'selected' : '' }}>With Coins (> 0)</option>
                    </select>
                </div>
                <div class="col-md-2 my-1 d-flex">
                    <button type="submit" class="btn btn-primary btn-block mr-1">Filtrele</button>
                    @if($term || $statusFilter !== 'all' || $balanceFilter !== 'all')
                        <a href="{{ route('liteback.users.index') }}" class="btn btn-outline-secondary" title="Filtreleri Temizle"><i class="fas fa-times"></i></a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Users Table Card -->
    <div class="card">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Oyuncu Dizini <span class="badge badge-light ml-2">{{ $users->total() }} Total</span></h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 align-middle">
                    <thead class="thead-light">
                    <tr>
                        <th style="width: 70px;">ID</th>
                        <th>Oyuncu Kimliği</th>
                        <th>Telefon / OTP</th>
                        <th>Ref Kodu</th>
                        <th style="width: 140px;">Bakiye</th>
                        <th style="width: 100px;">Durum</th>
                        <th style="width: 280px;">Coin Ayarla</th>
                        <th style="width: 130px;" class="text-right">İşlemler</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($users as $user)
                        <tr>
                            <td class="font-weight-bold text-muted">#{{ $user->id }}</td>
                            <td>
                                <div class="font-weight-bold text-dark">{{ $user->username }}</div>
                                @if($user->email)
                                    <small class="text-muted"><i class="fas fa-envelope mr-1"></i>{{ $user->email }}</small>
                                @endif
                            </td>
                            <td>
                                @if($user->phone)
                                    <span class="badge badge-secondary font-weight-normal"><i class="fab fa-whatsapp text-success mr-1"></i>{{ $user->phone }}</span>
                                    @if($user->phone_verified_at)
                                        <i class="fas fa-check-circle text-success ml-1" title="Telefon Doğrulandı"></i>
                                    @endif
                                @else
                                    <span class="text-muted small">Telefon yok</span>
                                @endif
                            </td>
                            <td>
                                @if($user->invite_code)
                                    <code class="text-primary font-weight-bold">{{ $user->invite_code }}</code>
                                @else
                                    <span class="text-muted small">-</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge badge-success px-2 py-1 font-weight-bold" style="font-size: 13px;">
                                    {{ number_format($user->balance ?? 0, 0) }} C
                                </span>
                            </td>
                            <td>
                                @if($user->is_blocked)
                                    <span class="badge badge-danger"><i class="fas fa-ban mr-1"></i>Engellendi</span>
                                @else
                                    <span class="badge badge-success"><i class="fas fa-check mr-1"></i>Aktif</span>
                                @endif
                            </td>
                            <td>
                                <form method="post" action="{{ route('liteback.users.balance', $user->id) }}" class="form-inline">
                                    @csrf
                                    <div class="input-group input-group-sm w-100">
                                        <select name="direction" class="custom-select" style="max-width: 75px;">
                                            <option value="add">+ Add</option>
                                            <option value="deduct">- Sub</option>
                                        </select>
                                        <input type="number" step="100" min="1" name="amount" class="form-control" placeholder="Tutar" style="max-width: 90px;" required>
                                        <div class="input-group-append">
                                            <button type="submit" class="btn btn-outline-primary" title="Coin Ayarını Uygula">
                                                <i class="fas fa-exchange-alt"></i>
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </td>
                            <td class="text-right">
                                <div class="btn-group btn-group-sm">
                                    <!-- Activity Dossier Button -->
                                    <button type="button" class="btn btn-outline-info btn-view-dossier" data-user-id="{{ $user->id }}" data-username="{{ $user->username }}" title="Etkinlik Dosyasını Gör">
                                        <i class="fas fa-chart-line"></i>
                                    </button>

                                    <!-- Status Toggle Button -->
                                    <form method="post" action="{{ route('liteback.users.toggle_status', $user->id) }}" class="d-inline" onsubmit="return confirm('Change status for user {{ $user->username }}?');">
                                        @csrf
                                        @if($user->is_blocked)
                                            <button type="submit" class="btn btn-outline-success" title="Oyuncunun Engellini Kaldır"><i class="fas fa-unlock"></i></button>
                                        @else
                                            <button type="submit" class="btn btn-outline-warning" title="Oyuncuyu Engelle"><i class="fas fa-ban"></i></button>
                                        @endif
                                    </form>

                                    <!-- Delete Button -->
                                    <form method="post" action="{{ route('liteback.users.delete', $user->id) }}" class="d-inline" onsubmit="return confirm('PERMANENTLY delete user {{ $user->username }} and all transaction records?');">
                                        @csrf
                                        @method('delete')
                                        <button type="submit" class="btn btn-outline-danger" title="Kullanıcıyı Sil"><i class="fas fa-trash-alt"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">
                                <i class="fas fa-users-slash fa-2x mb-2 d-block"></i>
                                Filtre kriterlerine uyan oyuncu bulunamadı.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($users->hasPages())
            <div class="card-footer bg-white d-flex justify-content-center">
                {{ $users->links() }}
            </div>
        @endif
    </div>

    <!-- User Activity Dossier Modal -->
    <div class="modal fade" id="userDossierModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title font-weight-bold" id="dossierModalTitle">Oyuncu Etkinlik Dosyası</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Kapat">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="dossierModalBody">
                    <div class="text-center py-3">
                        <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
                        <p class="mt-2 text-muted">Oyuncu verileri yükleniyor...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    $('.btn-view-dossier').on('click', function() {
        const userId = $(this).data('user-id');
        const username = $(this).data('username');
        $('#dossierModalTitle').text('Player Dossier: ' + username);
        $('#dossierModalBody').html('<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i><p class="mt-2 text-muted">Etkinlik kaydı alınıyor...</p></div>');
        $('#userDossierModal').modal('show');

        $.getJSON('/liteback/users/' + userId + '/detail', function(res) {
            if (res.success) {
                const u = res.user;
                const s = res.stats;
                let html = `
                    <div class="row mb-3">
                        <div class="col-6">
                            <small class="text-muted d-block">Üyelik Tarihi</small>
                            <strong>${u.created_at}</strong>
                        </div>
                        <div class="col-6 text-right">
                            <small class="text-muted d-block">Referans Kodu</small>
                            <span class="badge badge-primary font-weight-bold">${u.invite_code || 'None'}</span>
                        </div>
                    </div>
                    <hr>
                    <h6 class="font-weight-bold mb-3"><i class="fas fa-gamepad mr-1 text-primary"></i> Oyun Hacmi</h6>
                    <div class="row text-center mb-3">
                        <div class="col-4">
                            <div class="p-2 border rounded bg-light">
                                <div class="font-weight-bold text-dark h5 mb-0">${s.sports_bets_count}</div>
                                <small class="text-muted">Spor Bahisleri</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 border rounded bg-light">
                                <div class="font-weight-bold text-dark h5 mb-0">${s.lotto_tickets_count}</div>
                                <small class="text-muted">Loto Biletleri</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 border rounded bg-light">
                                <div class="font-weight-bold text-dark h5 mb-0">${s.prediction_votes_count}</div>
                                <small class="text-muted">Tahminler</small>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info py-2 small mb-0">
                        <i class="fas fa-user-friends mr-1"></i> Kazanılan Doğrudan Referanslar: <strong>${s.referrals_count}</strong>
                    </div>
                `;
                $('#dossierModalBody').html(html);
            } else {
                $('#dossierModalBody').html('<div class="alert alert-danger mb-0">' + (res.message || 'Error loading dossier') + '</div>');
            }
        }).fail(function() {
            $('#dossierModalBody').html('<div class="alert alert-danger mb-0">Oyuncu uç noktasına bağlanılamadı.</div>');
        });
    });
});
</script>
@endsection
