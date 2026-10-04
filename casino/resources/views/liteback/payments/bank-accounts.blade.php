@extends('liteback.layout')

@section('title', 'Ödeme Hesapları (IBAN Havuzu)')
@section('page_title', 'Ödeme Hesapları (IBAN Havuzu)')

@php
    $methodLabels = [
        'bank' => ['Banka Transferi', 'university', 'info'],
        'havale' => ['Havale / EFT', 'exchange-alt', 'primary'],
        'crypto' => ['Kripto Yatırım', 'bitcoin', 'warning'],
    ];
@endphp

@section('content')
<div class="row">
    <div class="col-lg-5">
        <div class="card card-primary card-outline">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-plus-circle mr-1"></i> Yeni Ödeme Hesabı Ekle</h3>
            </div>
            <form id="ba-create-form" action="{{ route('liteback.payments.bank-accounts.store') }}" method="POST">
                @csrf
                <div class="card-body">
                    <p class="text-muted text-sm">İstediğiniz kadar IBAN ekleyebilirsiniz. Oyuncu banka/havale yatırımı seçtiğinde sistem aktif hesaplardan <strong>rastgele</strong> birini gösterir.</p>

                    <div class="form-group">
                        <label>Yöntem</label>
                        <select name="method" id="ba-method" class="form-control" required>
                            <option value="bank">Banka Transferi</option>
                            <option value="havale">Havale / EFT</option>
                            <option value="crypto">Kripto Yatırım</option>
                        </select>
                    </div>

                    <div class="ba-bank-fields">
                        <div class="form-group">
                            <label>Banka Adı</label>
                            <input type="text" name="bank" class="form-control" placeholder="Örn: İş Bankası">
                        </div>
                        <div class="form-group">
                            <label>Hesap Sahibi</label>
                            <input type="text" name="holder" class="form-control" placeholder="Örn: Veli Yılmaz">
                        </div>
                        <div class="form-group">
                            <label>IBAN <span class="text-danger">*</span></label>
                            <input type="text" name="iban" class="form-control" placeholder="TRXXXXXXXXXXXXXXXXXXXXXXXX">
                        </div>
                    </div>

                    <div class="ba-crypto-fields" style="display:none;">
                        <div class="form-group">
                            <label>Ağ / Coin</label>
                            <input type="text" name="network" class="form-control" placeholder="Örn: TRC20 USDT">
                        </div>
                        <div class="form-group">
                            <label>Cüzdan Adresi <span class="text-danger">*</span></label>
                            <input type="text" name="address" class="form-control" placeholder="TXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX">
                        </div>
                        <div class="form-group">
                            <label>Not / Memo (opsiyonel)</label>
                            <input type="text" name="memo" class="form-control" placeholder="Varsa ağ notu / memo">
                        </div>
                    </div>

                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="ba-active" name="active" value="1" checked>
                        <label class="custom-control-label" for="ba-active">Aktif (rastgele seçime dahil)</label>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Hesabı Ekle</button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Kayıtlı Ödeme Hesapları</h3>
                <div class="card-tools">
                    <a href="{{ route('liteback.payments.bank-accounts.index') }}" class="btn btn-sm {{ !$activeFilter ? 'btn-primary' : 'btn-outline-primary' }}">Tümü</a>
                    @foreach($methods as $m)
                        @php($c = $counts[$m] ?? null)
                        <a href="{{ route('liteback.payments.bank-accounts.index', ['method' => $m]) }}"
                           class="btn btn-sm {{ $activeFilter === $m ? 'btn-primary' : 'btn-outline-primary' }}">
                            {{ $methodLabels[$m][0] }}
                            <span class="badge badge-light ml-1">{{ (int) ($c->active ?? 0) }}/{{ (int) ($c->total ?? 0) }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Yöntem</th>
                                <th>Banka / Ağ</th>
                                <th>Hesap Sahibi</th>
                                <th>IBAN / Adres</th>
                                <th>Durum</th>
                                <th>İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($accounts as $acc)
                                @php($m = $methodLabels[$acc->method] ?? ['Banka', 'university', 'secondary'])
                                <tr>
                                    <td>{{ $acc->id }}</td>
                                    <td><span class="badge badge-{{ $m[2] }}"><i class="fas fa-{{ $m[1] }} mr-1"></i>{{ $m[0] }}</span></td>
                                    <td>{{ $acc->bank ?: ($acc->network ?: '—') }}</td>
                                    <td>{{ $acc->holder ?: '—' }}</td>
                                    <td>
                                        <code class="ba-copy" data-value="{{ $acc->iban ?: $acc->address }}" title="Kopyalamak için tıklayın">{{ $acc->iban ?: ($acc->address ?: '—') }}</code>
                                    </td>
                                    <td>
                                        @if($acc->active)
                                            <span class="badge badge-success"><i class="fas fa-check mr-1"></i>Aktif</span>
                                        @else
                                            <span class="badge badge-secondary"><i class="fas fa-pause mr-1"></i>Pasif</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex">
                                            <button class="btn btn-xs btn-outline-primary mr-1 ba-edit-btn"
                                                    data-id="{{ $acc->id }}"
                                                    data-method="{{ $acc->method }}"
                                                    data-bank="{{ $acc->bank }}"
                                                    data-holder="{{ $acc->holder }}"
                                                    data-iban="{{ $acc->iban }}"
                                                    data-network="{{ $acc->network }}"
                                                    data-address="{{ $acc->address }}"
                                                    data-memo="{{ $acc->memo }}"
                                                    data-active="{{ $acc->active }}"
                                                    data-action="{{ route('liteback.payments.bank-accounts.update', $acc->id) }}"
                                                    data-toggle="modal" data-target="#editAccountModal">
                                                <i class="fas fa-pen"></i>
                                            </button>
                                            <form action="{{ route('liteback.payments.bank-accounts.toggle', $acc->id) }}" method="POST" class="mr-1">
                                                @csrf
                                                <button type="submit" class="btn btn-xs btn-outline-secondary" title="{{ $acc->active ? 'Pasife al' : 'Aktife al' }}">
                                                    <i class="fas fa-{{ $acc->active ? 'toggle-on' : 'toggle-off' }}"></i>
                                                </button>
                                            </form>
                                            <form action="{{ route('liteback.payments.bank-accounts.destroy', $acc->id) }}" method="POST" onsubmit="return confirm('Bu ödeme hesabı silinsin mi?');">
                                                @csrf
                                                <button type="submit" class="btn btn-xs btn-outline-danger"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <span class="text-muted"><i class="fas fa-inbox fa-2x mb-2 d-block"></i> Henüz ödeme hesabı eklenmemiş.</span>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Account Modal -->
<div class="modal fade" id="editAccountModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="editAccountForm" action="" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Ödeme Hesabını Düzenle</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Kapat"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Yöntem</label>
                        <select name="method" id="ea-method" class="form-control" required>
                            <option value="bank">Banka Transferi</option>
                            <option value="havale">Havale / EFT</option>
                            <option value="crypto">Kripto Yatırım</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Banka Adı</label>
                        <input type="text" name="bank" id="ea-bank" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Hesap Sahibi</label>
                        <input type="text" name="holder" id="ea-holder" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>IBAN</label>
                        <input type="text" name="iban" id="ea-iban" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Ağ / Coin</label>
                        <input type="text" name="network" id="ea-network" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Cüzdan Adresi</label>
                        <input type="text" name="address" id="ea-address" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Not / Memo</label>
                        <input type="text" name="memo" id="ea-memo" class="form-control">
                    </div>
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="ea-active" name="active" value="1">
                        <label class="custom-control-label" for="ea-active">Aktif</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    function toggleMethodFields(select, bankSel, cryptoSel) {
        var isCrypto = select.value === 'crypto';
        document.querySelector(bankSel).style.display = isCrypto ? 'none' : '';
        document.querySelector(cryptoSel).style.display = isCrypto ? '' : 'none';
    }

    $(document).ready(function() {
        $('#ba-method').on('change', function() { toggleMethodFields(this, '.ba-bank-fields', '.ba-crypto-fields'); });

        $('.ba-edit-btn').on('click', function() {
            var b = $(this);
            $('#editAccountForm').attr('action', b.data('action'));
            $('#ea-method').val(b.data('method'));
            $('#ea-bank').val(b.data('bank'));
            $('#ea-holder').val(b.data('holder'));
            $('#ea-iban').val(b.data('iban'));
            $('#ea-network').val(b.data('network'));
            $('#ea-address').val(b.data('address'));
            $('#ea-memo').val(b.data('memo'));
            $('#ea-active').prop('checked', b.data('active') == 1);
        });

        $('.ba-copy').on('click', function() {
            var value = $(this).data('value');
            if (!value) return;
            var el = this;
            navigator.clipboard && navigator.clipboard.writeText(value);
            var old = el.textContent;
            el.textContent = 'Kopyalandı';
            setTimeout(function() { el.textContent = old; }, 1000);
        });
    });
</script>
@endsection
