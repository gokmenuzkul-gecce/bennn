@extends('liteback.layout')

@section('title', 'Payment Ağ Geçidi Ayarları')
@section('page_title', 'Payment Ağ Geçidi Ayarları')

@section('content')
<div class="row">
    <div class="col-md-10 offset-md-1">
        <form action="{{ route('liteback.payments.settings.update') }}" method="POST">
            @csrf

            <!-- STRIPE CONFIGURATION -->
            <div class="card card-primary card-outline mb-4">
                <div class="card-header">
                    <h3 class="card-title"><i class="fab fa-stripe text-primary mr-2"></i> Stripe Entegrasyonu</h3>
                </div>
                <div class="card-body">
                    <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Stripe'ı Etkinleştir</label>
                        <div class="col-sm-9">
                            <select name="payment_stripe_enabled" class="form-control">
                                <option value="1" {{ settings('payment_stripe_enabled', config('payments.drivers.stripe.enabled') ? '1' : '0') == '1' ? 'selected' : '' }}>Evet</option>
                                <option value="0" {{ settings('payment_stripe_enabled', config('payments.drivers.stripe.enabled') ? '1' : '0') == '0' ? 'selected' : '' }}>Hayır</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Stripe Genel Anahtarı</label>
                        <div class="col-sm-9">
                            <input type="text" name="payment_stripe_public_key" class="form-control" value="{{ settings('payment_stripe_public_key', config('payments.drivers.stripe.public_key')) }}" placeholder="pk_live_...">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Stripe Gizli Anahtarı</label>
                        <div class="col-sm-9">
                            <input type="password" name="payment_stripe_secret_key" class="form-control" value="{{ settings('payment_stripe_secret_key', config('payments.drivers.stripe.secret_key')) }}" placeholder="sk_live_...">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Webhook İmzalama Gizli Anahtarı</label>
                        <div class="col-sm-9">
                            <input type="password" name="payment_stripe_webhook_secret" class="form-control" value="{{ settings('payment_stripe_webhook_secret', config('payments.drivers.stripe.webhook_secret')) }}" placeholder="whsec_...">
                            <small class="text-muted">Set up a webhook to endpoint: <code>{{ route('payment.webhook.stripe') }}</code> listening to <code>checkout.session.completed</code> event.</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PAYPAL CONFIGURATION -->
            <div class="card card-info card-outline mb-4">
                <div class="card-header">
                    <h3 class="card-title"><i class="fab fa-paypal text-info mr-2"></i> PayPal Entegrasyonu</h3>
                </div>
                <div class="card-body">
                    <div class="form-group row">
                        <label class="col-sm-3 col-form-label">PayPal'ı Etkinleştir</label>
                        <div class="col-sm-9">
                            <select name="payment_paypal_enabled" class="form-control">
                                <option value="1" {{ settings('payment_paypal_enabled', config('payments.drivers.paypal.enabled') ? '1' : '0') == '1' ? 'selected' : '' }}>Evet</option>
                                <option value="0" {{ settings('payment_paypal_enabled', config('payments.drivers.paypal.enabled') ? '1' : '0') == '0' ? 'selected' : '' }}>Hayır</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-sm-3 col-form-label">PayPal Modu</label>
                        <div class="col-sm-9">
                            <select name="payment_paypal_mode" class="form-control">
                                <option value="sandbox" {{ settings('payment_paypal_mode', config('payments.drivers.paypal.mode', 'sandbox')) == 'sandbox' ? 'selected' : '' }}>Sandbox / Test</option>
                                <option value="live" {{ settings('payment_paypal_mode', config('payments.drivers.paypal.mode', 'sandbox')) == 'live' ? 'selected' : '' }}>Canlı / Üretim</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-sm-3 col-form-label">İstemci ID</label>
                        <div class="col-sm-9">
                            <input type="text" name="payment_paypal_client_id" class="form-control" value="{{ settings('payment_paypal_client_id', config('payments.drivers.paypal.client_id')) }}" placeholder="Enter PayPal İstemci ID">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-sm-3 col-form-label">İstemci Gizli Anahtarı</label>
                        <div class="col-sm-9">
                            <input type="password" name="payment_paypal_secret" class="form-control" value="{{ settings('payment_paypal_secret', config('payments.drivers.paypal.secret')) }}" placeholder="Enter PayPal Client Secret">
                        </div>
                    </div>
                </div>
            </div>

            <!-- BTCPAY CONFIGURATION -->
            <div class="card card-danger card-outline mb-4">
                <div class="card-header">
                    <h3 class="card-title"><i class="fab fa-bitcoin text-danger mr-2"></i> BTCPay Sunucu Entegrasyonu</h3>
                </div>
                <div class="card-body">
                    <div class="form-group row">
                        <label class="col-sm-3 col-form-label">BTCPay'i Etkinleştir</label>
                        <div class="col-sm-9">
                            <select name="payment_btcpay_enabled" class="form-control">
                                <option value="1" {{ settings('payment_btcpay_enabled', config('payments.drivers.btcpay.enabled') ? '1' : '0') == '1' ? 'selected' : '' }}>Evet</option>
                                <option value="0" {{ settings('payment_btcpay_enabled', config('payments.drivers.btcpay.enabled') ? '1' : '0') == '0' ? 'selected' : '' }}>Hayır</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-sm-3 col-form-label">BTCPay Sunucu URL</label>
                        <div class="col-sm-9">
                            <input type="text" name="payment_btcpay_host" class="form-control" value="{{ settings('payment_btcpay_host', config('payments.drivers.btcpay.host')) }}" placeholder="https://btcpay.yourdomain.com">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Mağaza ID</label>
                        <div class="col-sm-9">
                            <input type="text" name="payment_btcpay_store_id" class="form-control" value="{{ settings('payment_btcpay_store_id', config('payments.drivers.btcpay.store_id')) }}" placeholder="Enter BTCPay Mağaza ID">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-sm-3 col-form-label">API Anahtarı</label>
                        <div class="col-sm-9">
                            <input type="password" name="payment_btcpay_api_key" class="form-control" value="{{ settings('payment_btcpay_api_key', config('payments.drivers.btcpay.api_key')) }}" placeholder="Enter API Key">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Webhook Gizli Anahtarı</label>
                        <div class="col-sm-9">
                            <input type="password" name="payment_btcpay_webhook_secret" class="form-control" value="{{ settings('payment_btcpay_webhook_secret', config('payments.drivers.btcpay.webhook_secret')) }}" placeholder="Enter Webhook İmzalama Gizli Anahtarı">
                            <small class="text-muted">Set up a webhook to endpoint: <code>{{ route('payment.webhook.btcpay') }}</code> listening to <code>InvoiceSettled</code> and <code>InvoicePaid</code> events.</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- MANUAL PAYMENT -->
            <div class="card card-warning card-outline mb-4">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-university text-warning mr-2"></i> Manuel Banka / Mobil Transferler</h3>
                </div>
                <div class="card-body">
                    <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Manuel Transferi Etkinleştir</label>
                        <div class="col-sm-9">
                            <select name="payment_manual_enabled" class="form-control">
                                <option value="1" {{ settings('payment_manual_enabled', config('payments.drivers.manual.enabled') ? '1' : '0') == '1' ? 'selected' : '' }}>Evet</option>
                                <option value="0" {{ settings('payment_manual_enabled', config('payments.drivers.manual.enabled') ? '1' : '0') == '0' ? 'selected' : '' }}>Hayır</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Ödeme Talimatları</label>
                        <div class="col-sm-9">
                            <textarea name="payment_manual_instructions" class="form-control" rows="6" placeholder="Enter instructions for the player...">{{ settings('payment_manual_instructions', config('payments.drivers.manual.instructions')) }}</textarea>
                            <small class="text-muted">Bu metin, kullanıcılar manuel banka transferi yatırımı başlattığında, kanıt yüklemeden önce parayı nasıl/nereye transfer edeceklerini bildirmek için gösterilecektir.</small>
                    </div>
                </div>
            </div>

            <!-- XTOPAY CONFIGURATION -->
            <div class="card card-success card-outline mb-4">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-coins text-success mr-2"></i> XtoPay — Anında KYC'siz USDT Ağ Geçidi</h3>
                </div>
                <div class="card-body">
                    <p class="text-muted">Kayıt olun <a href="https://xto.377.live/" target="_blank" rel="noopener noreferrer">xto.377.live</a> site adı tanımlayıcınızı anında almak için.</p>
                    <div class="form-group row">
                        <label class="col-sm-3 col-form-label">XtoPay'i Etkinleştir</label>
                        <div class="col-sm-9">
                            <select name="payment_xto_enabled" class="form-control">
                                <option value="1" {{ settings('payment_xto_enabled', config('payments.drivers.xtopay.enabled') ? '1' : '0') == '1' ? 'selected' : '' }}>Evet</option>
                                <option value="0" {{ settings('payment_xto_enabled', config('payments.drivers.xtopay.enabled') ? '1' : '0') == '0' ? 'selected' : '' }}>Hayır</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Site Adı Tanımlayıcısı</label>
                        <div class="col-sm-9">
                            <input type="text" name="payment_xto_website_name" class="form-control" value="{{ settings('payment_xto_website_name', config('payments.drivers.xtopay.website_name')) }}" placeholder="e.g. one">
                            <small class="text-muted">XtoPay'e kaydolduğunuzda verilen site adı tanımlayıcısını kullanın.</small>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Satıcı JWT Belirteci</label>
                        <div class="col-sm-9">
                            <input type="password" name="payment_xto_token" class="form-control" value="{{ env('XTO_PAY_TOKEN', settings('payment_xto_token', config('payments.drivers.xtopay.token'))) }}" placeholder="Enter Satıcı JWT Belirteci">
                            <small class="text-muted">Gizli belirteç güvenli bir şekilde şuraya kaydedilecek: <code>.env</code> dosyası (<code>XTO_PAY_TOKEN</code>) veritabanı sızıntılarını önlemek için.</small>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-sm-3 col-form-label">İzin Verilen Yöntemler (virgülle ayrılmış)</label>
                        <div class="col-sm-9">
                            <input type="text" name="payment_xto_methods" class="form-control" value="{{ settings('payment_xto_methods', config('payments.drivers.xtopay.allowed_methods')) }}" placeholder="TRC20_USDT,POLYGON_USDT,BSC_USDT,ERC20_USDT,POLYGON_USDC,BSC_USDC,ERC20_USDC">
                            <small class="text-muted">Virgülle ayrılmış kripto faturalandırma yöntemleri listesi. Standart: <code>TRC20_USDT,POLYGON_USDT,BSC_USDT,ERC20_USDT,POLYGON_USDC,BSC_USDC,ERC20_USDC</code></small>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-sm-3 col-form-label">Webhook Geri Çağırma URL</label>
                        <div class="col-sm-9">
                            <input type="text" class="form-control" value="{{ route('payment.webhook.xtopay') }}" readonly>
                            <small class="text-muted">Durum bildirimleri almak için bu webhook URL'sini XtoPay satıcı ağ geçidi panel ayarlarında yapılandırın.</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-right pb-5">
                <button type="submit" class="btn btn-success btn-lg px-5">Ağ Geçidi Ayarlarını Kaydet</button>
            </div>
        </form>
    </div>
</div>
@endsection
