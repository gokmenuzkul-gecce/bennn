@extends('liteback.layout')

@section('title', 'Kumarhane Cüzdan İşlemleri')
@section('page_title', 'Kumarhane Cüzdan İşlemleri')

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Sağlayıcı Cüzdan Hareketleri</h3>
                <div class="card-tools">
                    <a href="{{ route('liteback.casino.providers') }}" class="btn btn-sm btn-outline-light">
                        <i class="fas fa-plug"></i> Sağlayıcılar
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm table-striped mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Sağlayıcı</th>
                            <th>Oyuncu</th>
                            <th>İşlem</th>
                            <th>İşlem ID</th>
                            <th>Bahis</th>
                            <th>Kazanç</th>
                            <th>Tutar</th>
                            <th>Son Bakiye</th>
                            <th>Durum</th>
                            <th>Tarih</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transactions as $tx)
                            <tr>
                                <td>{{ $tx->id }}</td>
                                <td>{{ $providerLabels[$tx->provider_key] ?? $tx->provider_key }}</td>
                                <td>#{{ $tx->user_id }}</td>
                                <td>
                                    <span class="badge badge-{{ $tx->operation === 'Deposit' ? 'success' : ($tx->operation === 'Withdraw' ? 'warning' : 'info') }}">
                                        {{ $tx->operation }}
                                    </span>
                                </td>
                                <td class="small"><code>{{ \Illuminate\Support\Str::limit($tx->transaction_id, 20) }}</code></td>
                                <td>{{ number_format((float) $tx->bet_amount, 2) }}</td>
                                <td>{{ number_format((float) $tx->win_amount, 2) }}</td>
                                <td>{{ number_format((float) $tx->amount, 2) }}</td>
                                <td>{{ number_format((float) $tx->balance_after, 2) }}</td>
                                <td>
                                    <span class="badge {{ $tx->status === 'rolled_back' ? 'badge-danger' : 'badge-secondary' }}">
                                        {{ $tx->status === 'rolled_back' ? 'İade edildi' : 'Tamamlandı' }}
                                    </span>
                                </td>
                                <td class="small">{{ $tx->created_at }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="text-center text-muted py-4">Henüz cüzdan işlemi yok.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($transactions->hasPages())
                <div class="card-footer">{{ $transactions->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
