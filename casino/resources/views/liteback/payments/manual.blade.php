@extends('liteback.layout')

@section('title', 'Manuel Yatırımlar Queue')
@section('page_title', 'Manuel Yatırımlar Queue')

@php
    $methodLabels = [
        'bank' => ['Banka Transferi', 'account_balance', 'info'],
        'havale' => ['Havale / EFT', 'swap_horiz', 'primary'],
        'crypto' => ['Kripto Yatırım', 'currency_bitcoin', 'warning'],
    ];
@endphp

@section('content')
@if(($pendingCount ?? 0) > 0)
<div class="alert alert-warning d-flex align-items-center shadow-sm" role="alert">
    <i class="fas fa-bell fa-lg mr-3"></i>
    <div>
        <strong>{{ $pendingCount }} bekleyen yatırım talebi</strong> onayınızı bekliyor.
    </div>
</div>
@endif

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Bekleyen ve Geçmiş Manuel Banka Transferleri</h3>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Kullanıcı</th>
                        <th>Tutar</th>
                        <th>Yöntem</th>
                        <th>Yatırım Yapılan Hesap (IBAN)</th>
                        <th>Hesap Sahibi Adı</th>
                        <th>Referans / İşlem ID</th>
                        <th>Dekont</th>
                        <th>Durum</th>
                        <th>Gönderim Tarihi</th>
                        <th>İşlem / Yönetici Notu</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($deposits as $deposit)
                        @php
                            $rowAmount = $deposit->amount ?? $deposit->intent_amount;
                            $method = $methodLabels[$deposit->method] ?? ['Banka Transferi', 'payments', 'secondary'];
                        @endphp
                        <tr class="{{ $deposit->status == 0 ? 'table-warning' : '' }}">
                            <td>{{ $deposit->id }}</td>
                            <td>
                                <strong>{{ $deposit->username }}</strong><br>
                                <span class="text-muted text-sm">{{ $deposit->email }}</span>
                            </td>
                            <td>
                                <strong class="text-success">{{ number_format($rowAmount, 2) }} {{ $deposit->currency }}</strong>
                            </td>
                            <td>
                                <span class="badge badge-{{ $method[2] }}">
                                    <i class="fas fa-{{ $method[1] }} mr-1"></i>{{ $method[0] }}
                                </span>
                            </td>
                            <td>
                                @php($accValue = $deposit->account_iban ?: $deposit->account_address)
                                @if($accValue)
                                    <strong>{{ $deposit->account_bank ?: ($deposit->account_network ?: '—') }}</strong><br>
                                    <code>{{ $accValue }}</code>
                                    @if($deposit->account_holder)
                                        <br><span class="text-muted text-sm">{{ $deposit->account_holder }}</span>
                                    @endif
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>{{ $deposit->account_name ?: '—' }}</td>
                            <td><code>{{ $deposit->transaction_id ?: '—' }}</code></td>
                            <td>
                                @if($deposit->screenshot)
                                    @php($isPdf = \Illuminate\Support\Str::endsWith(strtolower($deposit->screenshot), '.pdf'))
                                    <button class="btn btn-xs btn-outline-primary view-screenshot-btn"
                                            data-src="{{ asset($deposit->screenshot) }}"
                                            data-pdf="{{ $isPdf ? '1' : '0' }}"
                                            data-title="Dekont — {{ $deposit->username }} ({{ number_format($rowAmount, 2) }} {{ $deposit->currency }})"
                                            data-toggle="modal"
                                            data-target="#screenshotModal">
                                        <i class="fas fa-{{ $isPdf ? 'file-pdf' : 'image' }} mr-1"></i> Dekontu Gör
                                    </button>
                                @else
                                    <span class="text-muted">Dekont yok</span>
                                @endif
                            </td>
                            <td>
                                @if($deposit->status == 0)
                                    <span class="badge badge-warning"><i class="fas fa-spinner fa-spin mr-1"></i> Beklemede</span>
                                @elseif($deposit->status == 1)
                                    <span class="badge badge-success"><i class="fas fa-check-circle mr-1"></i> Onaylandı</span>
                                @else
                                    <span class="badge badge-danger"><i class="fas fa-times-circle mr-1"></i> Reddedildi</span>
                                @endif
                            </td>
                            <td>{{ $deposit->created_at }}</td>
                            <td>
                                @if($deposit->status == 0)
                                    <div class="d-flex gap-2">
                                        <form action="{{ route('liteback.payments.manual.approve', $deposit->id) }}" method="POST" class="mr-2" onsubmit="return confirm('Are you sure you want to approve this deposit and credit the user balance?');">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success">
                                                <i class="fas fa-check"></i> Onayla
                                            </button>
                                        </form>
                                        <button class="btn btn-sm btn-danger reject-deposit-btn" 
                                                data-id="{{ $deposit->id }}" 
                                                data-action="{{ route('liteback.payments.manual.reject', $deposit->id) }}"
                                                data-toggle="modal" 
                                                data-target="#rejectModal">
                                            <i class="fas fa-times"></i> Reddet
                                        </button>
                                    </div>
                                @else
                                    <span class="text-muted">{{ $deposit->admin_note ?? 'No notes' }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center py-4">
                                <span class="text-muted"><i class="fas fa-inbox fa-2x mb-2 d-block"></i> Geçmişte manuel yatırım bulunamadı.</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($deposits->hasPages())
        <div class="card-footer clearfix">
            {{ $deposits->links() }}
        </div>
    @endif
</div>

<!-- Screenshot Modal -->
<div class="modal fade" id="screenshotModal" tabindex="-1" role="dialog" aria-labelledby="screenshotModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="screenshotModalTitle">Makbuz Kanıtı</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Kapat">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body text-center bg-dark">
                <img id="modalScreenshotImg" src="" class="img-fluid" style="max-height: 70vh;" alt="Dekont">
                <iframe id="modalScreenshotPdf" src="" style="display:none;width:100%;height:70vh;border:0;background:#fff;" title="Dekont PDF"></iframe>
            </div>
            <div class="modal-footer">
                <a id="modalScreenshotDownload" href="" download class="btn btn-primary" target="_blank"><i class="fas fa-download mr-1"></i> Orijinali İndir</a>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" role="dialog" aria-labelledby="rejectModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="rejectForm" action="" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="rejectModalLabel">Yatırım Talebini Reddet</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Kapat">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="admin_note">Reddetme Nedeni / Dahili Not</label>
                        <textarea name="admin_note" id="admin_note" class="form-control" rows="4" placeholder="Neden girin (ör. Geçersiz referans numarası, ekran görüntüsü okunamıyor, para alınmadı)"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-danger">Reddetmeyi Onayla</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        // Handle viewing receipts (image or PDF)
        $('.view-screenshot-btn').on('click', function() {
            var src = $(this).data('src');
            var isPdf = String($(this).data('pdf')) === '1';
            var title = $(this).data('title');
            if (isPdf) {
                $('#modalScreenshotImg').hide().attr('src', '');
                $('#modalScreenshotPdf').show().attr('src', src);
            } else {
                $('#modalScreenshotPdf').hide().attr('src', '');
                $('#modalScreenshotImg').show().attr('src', src);
            }
            $('#modalScreenshotDownload').attr('href', src);
            $('#screenshotModalTitle').text(title);
        });

        // Handle reject button data-fill
        $('.reject-deposit-btn').on('click', function() {
            var actionUrl = $(this).data('action');
            $('#rejectForm').attr('action', actionUrl);
            $('#admin_note').val('');
        });
    });
</script>
@endsection
