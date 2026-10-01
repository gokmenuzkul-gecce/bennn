<!-- Auth Modal (Login / WhatsApp OTP) -->
@php
    $whatsAppLoginEnabled = (string) settings('enable_whatsapp_otp', '1') === '1';
    $passwordLoginEnabled = (string) settings('enable_password_login', '1') === '1';
@endphp
<div id="modal-login" class="modal">
    <div class="modal-content glass-panel border border-white/10 shadow-2xl">
        <button type="button" class="close-modal" aria-label="Kapat">&times;</button>
        
        @if($whatsAppLoginEnabled && $passwordLoginEnabled)
        <!-- Auth Method Tabs -->
        <div class="flex gap-2 mb-6 border-b border-white/[0.08] pb-3">
            <button type="button" id="tab-whatsapp" class="flex-1 py-2.5 rounded-xl text-xs font-bold bg-primary text-white uppercase tracking-wider shadow-sm transition-all flex items-center justify-center gap-1.5">
                <span>📱</span>
                <span>WhatsApp OTP</span>
            </button>
            <button type="button" id="tab-standard" class="flex-1 py-2.5 rounded-xl text-xs font-bold bg-white/[0.04] text-on-surface-muted hover:text-white uppercase tracking-wider transition-all flex items-center justify-center gap-1.5">
                <span>👤</span>
                <span>Standart Giriş</span>
            </button>
        </div>
        @endif

        @if($whatsAppLoginEnabled)
        <!-- WhatsApp OTP Form -->
        <div id="form-container-whatsapp" class="space-y-4">
            <div>
                <h2 class="text-lg font-bold text-white tracking-tight">WhatsApp ile Anında Erişim</h2>
                <p class="text-xs text-on-surface-muted mt-1">6 haneli giriş doğrulama kodu almak için WhatsApp numaranızı girin.</p>
            </div>

            <!-- Step 1: Send Phone Number -->
            <form id="otp-send-form" class="space-y-3.5">
                @csrf
                <div class="form-group">
                    <label for="otp-phone">Mobil / WhatsApp Numarası</label>
                    <input type="text" id="otp-phone" name="phone" placeholder="+961 70 123 456 or +1 202 555 0143" required class="font-mono-jet text-sm">
                </div>
                <button type="submit" class="btn-primary" id="btn-send-otp">
                    Doğrulama Kodu Gönder
                </button>
            </form>

            <!-- Step 2: Verify 6-Digit OTP -->
            <form id="otp-verify-form" class="space-y-3.5 hidden">
                @csrf
                <input type="hidden" id="verify-phone-hidden" name="phone">
                <div class="form-group">
                    <label for="otp-code-input">6 Haneli Doğrulama Kodunu Girin</label>
                    <input type="text" id="otp-code-input" name="otp_code" placeholder="123456" maxlength="6" class="font-mono-jet text-center text-xl font-bold tracking-widest text-primary bg-black/40 border border-primary/40 rounded-xl py-3" required>
                </div>
                <div class="form-group">
                    <label for="otp-ref-input">İsteğe Bağlı Referans Kodu</label>
                    <input type="text" id="otp-ref-input" name="ref" placeholder="REF78A1" class="font-mono-jet text-sm">
                </div>
                <button type="submit" class="btn-primary">
                    Doğrula ve Arenaya Gir
                </button>
            </form>

            <div id="otp-status-msg" class="text-xs font-bold text-center mt-2 min-h-[18px]"></div>
        </div>
        @endif

        @if($passwordLoginEnabled)
        <!-- Standard Credentials Form -->
        <div id="form-container-standard" class="space-y-4 {{ $whatsAppLoginEnabled ? 'hidden' : '' }}">
            <div>
                <h2 class="text-lg font-bold text-white tracking-tight">Üye Girişi</h2>
                <p class="text-xs text-on-surface-muted mt-1">Standart bilgilerle hesabınıza ve cüzdanınıza erişin.</p>
            </div>
            <form id="login-form" action="{{ route('frontend.auth.login.post') }}" method="POST" class="space-y-3.5">
                @csrf
                <div class="form-group">
                    <label for="login-username">Kullanıcı Adı, E-posta veya Telefon</label>
                    <input type="text" id="login-username" name="username" required placeholder="Kullanıcı adı, e-posta veya telefon girin">
                </div>
                <div class="form-group">
                    <label for="login-password">Parola</label>
                    <input type="password" id="login-password" name="password" required placeholder="••••••••">
                </div>
                <button type="submit" id="btn-login-submit" class="btn-primary">Giriş Yap</button>
                <div id="login-status-msg" class="text-xs font-bold text-center mt-2 min-h-[18px]"></div>
            </form>
            <p class="text-xs text-on-surface-muted text-center pt-2">
                Hesabınız yok mu? <a href="#" class="text-primary open-modal font-bold hover:underline" data-target="modal-register">Ücretsiz Kayıt Ol</a>
            </p>
        </div>
        @endif
    </div>
</div>

<!-- Fast Register Modal -->
<div id="modal-register" class="modal">
    <div class="modal-content glass-panel border border-white/10 shadow-2xl">
        <button type="button" class="close-modal" aria-label="Kapat">&times;</button>
        <div class="mb-4">
            <h2 class="text-lg font-bold text-white tracking-tight">Ücretsiz Hesap Oluştur</h2>
            <p class="text-xs text-on-surface-muted mt-1">Promex Gaming Suite'e katılın ve anında 50.000 Ücretsiz TRY kazanın!</p>
        </div>
        <form id="register-form" action="{{ route('frontend.register.post') }}" method="POST" class="space-y-3.5">
            @csrf
            <div class="form-group">
                <label for="reg-username">Kullanıcı Adı Seçin</label>
                <input type="text" id="reg-username" name="username" required placeholder="e.g. CedarKing">
            </div>
            <div class="form-group">
                <label for="reg-password">Parola</label>
                <input type="password" id="reg-password" name="password" required placeholder="••••••••">
            </div>
            <div class="form-group">
                <label for="reg-password-confirm">Parolayı Onayla</label>
                <input type="password" id="reg-password-confirm" name="password_confirmation" required placeholder="••••••••">
            </div>
            <button type="submit" id="btn-register-submit" class="btn-primary">Hesap Oluştur ve Coinleri Al</button>
            <div id="register-status-msg" class="text-xs font-bold text-center mt-2 min-h-[18px]"></div>
        </form>
        <p class="text-xs text-on-surface-muted text-center pt-2">
            Zaten kayıtlı mısınız? <a href="#" class="text-primary open-modal font-bold hover:underline" data-target="modal-login">Giriş Yap</a>
        </p>
    </div>
</div>

<!-- Profile / Member Hub Modal -->
<div id="modal-profile" class="modal">
    <div class="modal-content glass-panel max-w-xl border border-white/10 shadow-2xl">
        <button type="button" class="close-modal" aria-label="Kapat">&times;</button>
        @if(Auth::check())
            @php
                $u = Auth::user();
                $affiliateService = app(\VanguardLTE\Services\AffiliateService::class);
                $affStats = $affiliateService->getReferralStats($u);
                $isEligible99 = !empty($u->first_name) && !empty($u->last_name) && !empty($u->email);
            @endphp
            <div class="space-y-3">
                <div class="flex justify-between items-start">
                    <div>
                        <h2 class="text-xl font-extrabold text-white tracking-tight">Oyuncu Profili</h2>
                        <span class="text-xs text-primary font-mono-jet font-bold">@ {{ $u->username }}</span>
                    </div>
                    @if(in_array((int)$u->role_id, [2, 3, 4, 5, 6]) || $u->hasRole('admin') || $u->hasRole('manager') || (env('ADMIN_PHONE') && $u->phone == env('ADMIN_PHONE')))
                    <span class="text-[10px] bg-amber-400/20 text-amber-300 border border-amber-400/40 px-3 py-1 rounded-full font-mono-jet font-bold uppercase tracking-wider flex items-center gap-1.5 shadow-sm">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-ping"></span>
                        <span>YÖNETİCİ / PERSONEL</span>
                    </span>
                    @else
                    <span class="text-[10px] bg-primary/10 text-primary border border-primary/20 px-3 py-1 rounded-full font-mono-jet font-bold">
                        VIP PLATINUM
                    </span>
                    @endif
                </div>

                @if(in_array((int)$u->role_id, [2, 3, 4, 5, 6]) || $u->hasRole('admin') || $u->hasRole('manager') || (env('ADMIN_PHONE') && $u->phone == env('ADMIN_PHONE')))
                <!-- Direct Liteback Yönetici Konsolu Quick Access -->
                <div class="p-3.5 bg-gradient-to-r from-amber-500/20 via-amber-600/15 to-[#121622] border border-amber-500/40 rounded-2xl flex items-center justify-between shadow-lg shadow-amber-500/10">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-10 h-10 rounded-xl bg-amber-500/20 border border-amber-500/35 flex items-center justify-center text-amber-400 shadow-sm flex-shrink-0">
                            <span class="material-symbols-outlined text-2xl">admin_panel_settings</span>
                        </div>
                        <div class="min-w-0">
                            <div class="text-xs font-bold text-amber-300 uppercase tracking-wide flex items-center gap-1.5">
                                <span>Operatör Yönetici Konsolu</span>
                                <span class="text-[9px] bg-amber-400 text-black font-extrabold px-1.5 py-0.5 rounded font-mono-jet">ROLE {{ $u->role_id }}</span>
                            </div>
                            <div class="text-[10px] text-on-surface-subtle mt-0.5 truncate">Liteback konsoluna, canlı güncelleyiciye ve mağazaya doğrudan erişim</div>
                        </div>
                    </div>
                    <a href="{{ route('liteback.users.index') }}" target="_blank" class="bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-black font-extrabold text-xs px-3.5 py-2 rounded-xl uppercase tracking-wider transition-all shadow-md shadow-amber-500/20 no-underline flex items-center gap-1 flex-shrink-0">
                        <span>Konsol</span>
                        <span class="material-symbols-outlined text-sm">open_in_new</span>
                    </a>
                </div>
                @endif

                <!-- Reward Eligibility Status Pill -->
                <div>
                    @if($isEligible99)
                        <div class="bg-primary/10 border border-primary/30 text-primary px-3 py-2 rounded-xl text-xs font-bold font-mono-jet flex items-center gap-2">
                            <span>✅</span> <span>99$+ GERÇEK SOSYAL ÖDÜL VE ÖDÜLLERE UYGUN</span>
                        </div>
                    @else
                        <div class="bg-accent-gold/10 border border-accent-gold/30 text-accent-gold px-3 py-2 rounded-xl text-xs font-bold font-mono-jet flex items-center gap-2">
                            <span>⚠️</span> <span>ÖDÜLLERE UYGUN OLMAK İÇİN AD/SOYAD VE E-POSTA DOLDURUN</span>
                        </div>
                    @endif
                </div>

                @if(settings('enable_cashout', '1') == '1')
                <!-- Quick Cashout Action Banner in Profile -->
                <div class="mt-2 p-3 bg-gradient-to-r from-accent-gold/10 to-transparent border border-accent-gold/25 rounded-2xl flex items-center justify-between">
                    <div>
                        <div class="text-xs font-bold text-white flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-accent-gold text-base">payments</span>
                            <span>Ödül Kullanımı ve Para Çekme</span>
                        </div>
                        <div class="text-[10px] text-on-surface-muted mt-0.5 font-mono-jet">
                            Rate: {{ number_format(settings('coins_per_dollar', 100)) }} Puan = 1,00$ USD (1¢/puan)
                        </div>
                    </div>
                    <button type="button" class="bg-accent-gold hover:bg-accent-gold/90 text-black font-extrabold text-[11px] px-3.5 py-1.5 rounded-xl uppercase tracking-wider transition-all open-modal shadow-sm" data-target="modal-cashout">
                        Para Çek
                    </button>
                </div>
                @endif
            </div>

            <!-- Profile Fields Form -->
            <form id="profile-update-form" class="space-y-3 mt-4">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="form-group">
                        <label for="prof-firstname">Ad</label>
                        <input type="text" id="prof-firstname" name="first_name" value="{{ $u->first_name }}" placeholder="Jean">
                    </div>
                    <div class="form-group">
                        <label for="prof-lastname">Soyad</label>
                        <input type="text" id="prof-lastname" name="last_name" value="{{ $u->last_name }}" placeholder="Khoury">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="form-group">
                        <label for="prof-username">Kullanıcı Adı</label>
                        <input type="text" id="prof-username" name="username" value="{{ $u->username }}" required>
                    </div>
                    <div class="form-group">
                        <label for="prof-email">E-posta Adresi</label>
                        <input type="email" id="prof-email" name="email" value="{{ $u->email }}" placeholder="player@domain.com">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="form-group">
                        <label for="prof-phone">Mobil / WhatsApp Numarası</label>
                        <input type="tel" id="prof-phone" name="phone" value="{{ $u->phone }}" placeholder="+96170123456">
                        <span class="text-[10px] {{ $u->phone_verified_at ? 'text-primary' : 'text-on-surface-muted' }}">
                            {{ $u->phone_verified_at ? 'Verified' : 'Numarayı değiştirmek parolanızı ve yeni bir OTP gerektirir.' }}
                        </span>
                    </div>
                    <div class="form-group">
                        <label for="prof-login-method">Tercih Edilen Giriş</label>
                        <select id="prof-login-method" name="preferred_login_method">
                            @if($whatsAppLoginEnabled)
                            <option value="phone" {{ ($u->preferred_login_method ?: ($u->phone ? 'phone' : 'password')) === 'phone' ? 'selected' : '' }}>Telefon / WhatsApp kodu (önerilir)</option>
                            @endif
                            @if($passwordLoginEnabled)
                            <option value="password" {{ ($u->preferred_login_method ?: ($u->phone ? 'phone' : 'password')) === 'password' ? 'selected' : '' }}>Kullanıcı adı, e-posta veya telefon + parola</option>
                            @endif
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="form-group">
                        <label for="prof-new-password">Yeni Parola (İsteğe Bağlı)</label>
                        <input type="password" id="prof-new-password" name="new_password" minlength="8" autocomplete="new-password">
                    </div>
                    <div class="form-group">
                        <label for="prof-new-password-confirmation">Yeni Parolayı Onayla</label>
                        <input type="password" id="prof-new-password-confirmation" name="new_password_confirmation" minlength="8" autocomplete="new-password">
                    </div>
                </div>

                <div class="form-group p-3 rounded-xl border border-white/10 bg-black/20">
                    <label for="prof-current-password">Mevcut Parola</label>
                    <input type="password" id="prof-current-password" name="current_password" autocomplete="current-password" placeholder="E-posta, telefon, giriş yöntemi veya parola değişiklikleri için gereklidir">
                </div>

                <button type="submit" class="w-full bg-secondary hover:bg-secondary-light text-white py-2.5 rounded-xl font-bold text-xs uppercase tracking-wider transition-all shadow-md shadow-secondary/20">
                    Profili Kaydet ve Uygunluğu Al
                </button>
                <div id="profile-update-msg" class="text-xs font-bold text-center mt-1"></div>
            </form>

            <!-- Referral Link Widget -->
            <div class="bg-[#182030] p-4 rounded-2xl border border-white/[0.08] space-y-3 mt-4">
                <div class="flex justify-between items-center">
                    <span class="text-xs font-bold text-white uppercase tracking-wider">🏆 3 Kademeli Referans Bağlantısı</span>
                    <span class="text-[10px] text-accent-gold font-bold">BAHİS KOMİSYONUNDAN %1,0'E KADAR KAZANIN</span>
                </div>
                <div class="flex gap-2">
                    <input type="text" readonly value="{{ $affStats['referral_link'] ?? '' }}" class="w-full text-xs p-2.5 rounded-xl bg-black/40 border border-white/10 font-mono-jet text-white focus:outline-none">
                    <button type="button" onclick="navigator.clipboard.writeText('{{ $affStats['referral_link'] ?? '' }}'); alert('Referral link copied!');" class="btn-glow bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-white px-3 py-2 rounded-xl text-xs font-bold uppercase transition-colors flex-shrink-0">
                        Kopyala
                    </button>
                </div>
                
                <div class="grid grid-cols-3 gap-2 text-center text-xs font-mono-jet pt-1">
                    <div class="bg-black/30 p-2.5 rounded-xl border border-white/5">
                        <span class="block text-[10px] text-on-surface-muted uppercase">Kademe 1</span>
                        <span class="font-bold text-primary text-base">{{ $affStats['tier1']['count'] ?? $affStats['tier1_count'] ?? 0 }}</span>
                    </div>
                    <div class="bg-black/30 p-2.5 rounded-xl border border-white/5">
                        <span class="block text-[10px] text-on-surface-muted uppercase">Kademe 2</span>
                        <span class="font-bold text-secondary text-base">{{ $affStats['tier2']['count'] ?? $affStats['tier2_count'] ?? 0 }}</span>
                    </div>
                    <div class="bg-black/30 p-2.5 rounded-xl border border-white/5">
                        <span class="block text-[10px] text-on-surface-muted uppercase">Kademe 3</span>
                        <span class="font-bold text-accent-gold text-base">{{ $affStats['tier3']['count'] ?? $affStats['tier3_count'] ?? 0 }}</span>
                    </div>
                </div>
                <div class="text-center pt-1">
                    <a href="{{ route('frontend.affiliates.index') }}" class="text-[11px] text-primary hover:underline font-bold">Tam Ortaklık Panelini Aç &rarr;</a>
                </div>
            </div>

            <!-- Footer Details -->
            <div class="pt-4 border-t border-white/[0.08] flex justify-between items-center">
                <span class="text-xs text-on-surface-muted">Bakiye: <strong class="text-primary font-mono-jet text-sm">{{ number_format($u->balance, 0) }} CEDARS</strong></span>
                <div class="flex items-center gap-3">
                    @if(in_array((int)$u->role_id, [2, 3, 4, 5, 6]) || $u->hasRole('admin') || $u->hasRole('manager') || (env('ADMIN_PHONE') && $u->phone == env('ADMIN_PHONE')))
                        <a href="{{ route('liteback.users.index') }}" target="_blank" class="text-xs font-bold text-amber-400 hover:text-amber-300 uppercase flex items-center gap-1 no-underline">
                            <span class="material-symbols-outlined text-sm">admin_panel_settings</span>
                            <span>Yönetici Konsolu</span>
                        </a>
                        <span class="text-white/20">•</span>
                    @endif
                    <a href="{{ route('frontend.auth.logout') }}" class="text-xs font-bold text-accent-rose uppercase hover:underline">Çıkış Yap</a>
                </div>
            </div>
        @else
            <div class="text-center py-6">
                <p class="text-xs text-on-surface-muted">Profilinize erişmek için lütfen giriş yapın.</p>
                <button type="button" class="mt-3 btn-primary open-modal" data-target="modal-login">Giriş Yap</button>
            </div>
        @endif
    </div>
</div>


<!-- Cashout & Prize Redemption Modal -->
<div id="modal-cashout" class="modal">
    <div class="modal-content glass-panel max-w-xl border border-white/10 shadow-2xl">
        <button type="button" class="close-modal" aria-label="Kapat">&times;</button>
        @if(Auth::check())
            @php
                $u = Auth::user();
                $cashoutRate = (float) (function_exists('settings') ? settings('coins_per_dollar', 100) : 100);
                if ($cashoutRate <= 0) $cashoutRate = 100;
                $cashoutMin = (float) (function_exists('settings') ? settings('min_cashout_coins', 2000) : 2000);
                $rawMethods = function_exists('settings') ? settings('cashout_methods', 'USDT (TRC-20), Whish Money, OMT, Banka Transferi, PayPal, Nakit Temsilcisi') : 'USDT (TRC-20), Whish Money, OMT, Banka Transferi, PayPal, Nakit Temsilcisi';
                $availableMethods = array_map('trim', explode(',', $rawMethods));
                $userWithdrawals = \VanguardLTE\Withdraw::where('user_id', $u->id)->orderBy('id', 'desc')->take(6)->get();
            @endphp
            <div class="space-y-4">
                <!-- Header -->
                <div class="flex justify-between items-start">
                    <div>
                        <h2 class="text-xl font-extrabold text-white tracking-tight flex items-center gap-2">
                            <span class="material-symbols-outlined text-accent-gold text-2xl">payments</span>
                            <span>Ödül Kullanımı ve Para Çekme</span>
                        </h2>
                        <p class="text-xs text-on-surface-muted mt-1">Cedar Puanlarınızı gerçek paraya veya kripto ödüllerine dönüştürün.</p>
                    </div>
                    <span class="text-[10px] bg-accent-gold/10 text-accent-gold border border-accent-gold/20 px-2.5 py-1 rounded-full font-mono-jet font-bold">
                        {{ number_format($cashoutRate) }} PTS = $1.00
                    </span>
                </div>

                <!-- Balance & Rate Info Card -->
                <div class="grid grid-cols-2 gap-2 bg-[#121622] p-3 rounded-2xl border border-white/[0.08]">
                    <div>
                        <span class="text-[10px] text-on-surface-muted uppercase font-bold tracking-wider block">Kullanılabilir Bakiye</span>
                        <div class="flex items-baseline gap-1 mt-0.5">
                            <span class="font-mono-jet text-lg font-bold text-primary" id="cashout-modal-user-balance">{{ number_format($u->balance, 0) }}</span>
                            <span class="text-[11px] text-primary-light font-bold">PUAN</span>
                        </div>
                        <span class="text-[10px] text-on-surface-subtle font-mono-jet">~${{ number_format($u->balance / $cashoutRate, 2) }} USD</span>
                    </div>
                    <div class="text-right border-l border-white/[0.06] pl-3">
                        <span class="text-[10px] text-on-surface-muted uppercase font-bold tracking-wider block">Minimum Para Çekme</span>
                        <div class="flex items-baseline justify-end gap-1 mt-0.5">
                            <span class="font-mono-jet text-lg font-bold text-accent-gold">{{ number_format($cashoutMin, 0) }}</span>
                            <span class="text-[11px] text-accent-gold font-bold">PUAN</span>
                        </div>
                        <span class="text-[10px] text-on-surface-subtle font-mono-jet">=${{ number_format($cashoutMin / $cashoutRate, 2) }} USD</span>
                    </div>
                </div>

                <!-- Cashout Request Form -->
                <form id="cashout-request-form" class="space-y-3.5">
                    @csrf
                    <!-- Step 1: Method Selection -->
                    <div class="form-group">
                        <label for="cashout-method" class="text-xs font-bold text-white flex items-center justify-between">
                            <span>1. Para Çekme Yöntemini Seçin</span>
                            <span class="text-[10px] text-on-surface-subtle">Anında manuel kuyruk</span>
                        </label>
                        <select id="cashout-method" name="method" required class="w-full text-xs p-2.5 rounded-xl bg-black/40 border border-white/10 text-white focus:outline-none focus:border-primary">
                            @foreach($availableMethods as $m)
                                @if(!empty($m))
                                    <option value="{{ $m }}">{{ $m }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>

                    <!-- Step 2: Points Amount -->
                    <div class="form-group">
                        <div class="flex justify-between items-center mb-1">
                            <label for="cashout-amount" class="text-xs font-bold text-white">2. Kullanılacak Tutar (Puan)</label>
                            <span class="text-[10px] text-accent-gold font-mono-jet font-bold">
                                Nakit Değeri: $<span id="cashout-live-fiat">0.00</span> USD
                            </span>
                        </div>
                        <input type="number" id="cashout-amount" name="amount" min="{{ $cashoutMin }}" step="100" placeholder="e.g. {{ number_format($cashoutMin, 0) }}" required class="font-mono-jet text-sm">
                        <!-- Quick presets -->
                        <div class="flex gap-1.5 mt-2">
                            <button type="button" class="btn-cashout-preset text-[10px] bg-white/[0.04] hover:bg-white/[0.1] text-on-surface-muted hover:text-white px-2.5 py-1 rounded-lg font-mono-jet transition-colors" data-amount="{{ $cashoutMin }}">
                                Min ({{ number_format($cashoutMin, 0) }})
                            </button>
                            <button type="button" class="btn-cashout-preset text-[10px] bg-white/[0.04] hover:bg-white/[0.1] text-on-surface-muted hover:text-white px-2.5 py-1 rounded-lg font-mono-jet transition-colors" data-amount="5000">
                                5,000 ($50)
                            </button>
                            <button type="button" class="btn-cashout-preset text-[10px] bg-white/[0.04] hover:bg-white/[0.1] text-on-surface-muted hover:text-white px-2.5 py-1 rounded-lg font-mono-jet transition-colors" data-amount="10000">
                                10,000 ($100)
                            </button>
                            <button type="button" class="btn-cashout-preset text-[10px] bg-white/[0.04] hover:bg-white/[0.1] text-primary hover:text-primary-light px-2.5 py-1 rounded-lg font-mono-jet transition-colors" data-amount="{{ floor($u->balance) }}">
                                Maks Tümü
                            </button>
                        </div>
                    </div>

                    <!-- Step 3: Destination Account / Wallet Details -->
                    <div class="form-group">
                        <label for="cashout-wallet" class="text-xs font-bold text-white flex items-center justify-between">
                            <span>3. Hedef Hesap / Cüzdan Bilgileri</span>
                            <span class="text-[10px] text-on-surface-subtle">Manuel giriş</span>
                        </label>
                        <input type="text" id="cashout-wallet" name="wallet" placeholder="USDT TRC20 adresi / Whish telefon numarası / Banka IBAN girin" required class="font-mono-jet text-xs">
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" id="btn-submit-cashout" class="w-full bg-gradient-to-r from-accent-gold to-amber-600 hover:from-amber-400 hover:to-amber-500 text-black font-extrabold py-3 rounded-xl text-xs uppercase tracking-wider transition-all shadow-lg shadow-amber-500/20 flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined text-base">send_money</span>
                        <span>Para Çekme Talebini Gönder</span>
                    </button>

                    <div id="cashout-status-msg" class="text-xs font-bold text-center mt-2 min-h-[18px]"></div>
                </form>

                <!-- History / Recent Requests Accordion -->
                <div class="pt-3 border-t border-white/[0.08]">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-xs font-bold text-white uppercase tracking-wider">Son Para Çekme İşlemleriniz</span>
                        <span class="text-[10px] text-on-surface-subtle">Kasiyer incelemesi bekliyor</span>
                    </div>

                    <div id="cashout-history-list" class="space-y-1.5 max-h-44 overflow-y-auto custom-scrollbar pr-1">
                        @forelse($userWithdrawals as $req)
                            @php
                                $usdVal = $req->fiat_amount ?: round(($req->coin_amount ?: $req->amount) / $cashoutRate, 2);
                            @endphp
                            <div class="bg-black/30 p-2.5 rounded-xl border border-white/5 flex items-center justify-between text-xs">
                                <div>
                                    <div class="font-mono-jet font-bold text-white flex items-center gap-1.5">
                                        <span>{{ number_format($req->coin_amount ?: $req->amount, 0) }} Pts</span>
                                        <span class="text-emerald-400 font-bold">(${{ number_format($usdVal, 2) }} USD)</span>
                                    </div>
                                    <div class="text-[10px] text-on-surface-subtle truncate max-w-[230px] mt-0.5">
                                        <span class="text-white/70">{{ $req->method ?: 'Manual' }}:</span> {{ $req->wallet }}
                                    </div>
                                </div>
                                <div class="text-right">
                                    @if($req->status == 0)
                                        <span class="inline-flex items-center gap-1 text-[10px] bg-amber-500/10 text-amber-400 border border-amber-500/30 px-2 py-0.5 rounded-full font-bold">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                                            Beklemede
                                        </span>
                                    @elseif($req->status == 1)
                                        <span class="inline-flex items-center gap-1 text-[10px] bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 px-2 py-0.5 rounded-full font-bold">
                                            <span>✓</span> Ödendi
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 text-[10px] bg-rose-500/10 text-rose-400 border border-rose-500/30 px-2 py-0.5 rounded-full font-bold">
                                            <span>✕</span> Reddedildi
                                        </span>
                                    @endif
                                    <div class="text-[9px] text-on-surface-subtle mt-0.5">
                                        {{ $req->created_at ? date('M d, H:i', strtotime($req->created_at)) : '' }}
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-3 text-on-surface-subtle text-xs" id="no-cashout-history">
                                Henüz para çekme talebi yok.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        @else
            <div class="text-center py-6">
                <p class="text-xs text-on-surface-muted">Para çekme talebinde bulunmak için lütfen giriş yapın.</p>
                <button type="button" class="mt-3 btn-primary open-modal" data-target="modal-login">Giriş Yap</button>
            </div>
        @endif
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Global Modal Functions
    window.openModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('active', 'show');
        }
    };

    window.closeModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('active', 'show');
        }
    };

    // Delegated click handler for modal open/close triggers anywhere in DOM
    document.addEventListener('click', function(e) {
        const openBtn = e.target.closest('.open-modal, [data-target]');
        if (openBtn) {
            const targetId = openBtn.getAttribute('data-target');
            if (targetId && document.getElementById(targetId)) {
                e.preventDefault();
                e.stopPropagation();
                window.openModal(targetId);
            }
        }

        if (e.target.matches('.close-modal') || e.target.closest('.close-modal')) {
            const modal = e.target.closest('.modal');
            if (modal) {
                modal.classList.remove('active', 'show');
            }
        }

        if (e.target.classList.contains('modal')) {
            e.target.classList.remove('active', 'show');
        }
    });

    // Login Method Tabs
    const tabWhatsapp = document.getElementById('tab-whatsapp');
    const tabStandard = document.getElementById('tab-standard');
    const containerWhatsapp = document.getElementById('form-container-whatsapp');
    const containerStandard = document.getElementById('form-container-standard');

    if (tabWhatsapp && tabStandard && containerWhatsapp && containerStandard) {
        tabWhatsapp.addEventListener('click', function() {
            tabWhatsapp.className = "flex-1 py-2.5 rounded-xl text-xs font-bold bg-primary text-white uppercase tracking-wider shadow-sm transition-all flex items-center justify-center gap-1.5";
            tabStandard.className = "flex-1 py-2.5 rounded-xl text-xs font-bold bg-white/[0.04] text-on-surface-muted hover:text-white uppercase tracking-wider transition-all flex items-center justify-center gap-1.5";
            containerWhatsapp.classList.remove('hidden');
            containerStandard.classList.add('hidden');
        });

        tabStandard.addEventListener('click', function() {
            tabStandard.className = "flex-1 py-2.5 rounded-xl text-xs font-bold bg-primary text-white uppercase tracking-wider shadow-sm transition-all flex items-center justify-center gap-1.5";
            tabWhatsapp.className = "flex-1 py-2.5 rounded-xl text-xs font-bold bg-white/[0.04] text-on-surface-muted hover:text-white uppercase tracking-wider transition-all flex items-center justify-center gap-1.5";
            containerStandard.classList.remove('hidden');
            containerWhatsapp.classList.add('hidden');
        });
    }

    // OTP Send Form Handler
    const otpSendForm = document.getElementById('otp-send-form');
    const otpVerifyForm = document.getElementById('otp-verify-form');
    const statusMsg = document.getElementById('otp-status-msg');

    if (otpSendForm) {
        otpSendForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const phoneVal = document.getElementById('otp-phone').value;

            statusMsg.className = "text-xs font-bold text-center mt-2 text-secondary";
            statusMsg.innerText = "Sending WhatsApp Verification Code...";

            fetch('/auth/phone/otp', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ phone: phoneVal })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    sessionStorage.setItem('whatsapp_phone', data.phone);
                    document.getElementById('verify-phone-hidden').value = data.phone;

                    statusMsg.className = "text-xs font-bold text-center mt-2 text-primary";
                    if (data.devmode && data.otp) {
                        statusMsg.innerText = "⚡ DEV OTP: [" + data.otp + "]";
                        document.getElementById('otp-code-input').value = data.otp;
                    } else {
                        statusMsg.innerText = data.message;
                    }
                    otpSendForm.classList.add('hidden');
                    otpVerifyForm.classList.remove('hidden');
                } else {
                    statusMsg.className = "text-xs font-bold text-center mt-2 text-accent-rose";
                    statusMsg.innerText = data.message;
                }
            });
        });
    }

    if (otpVerifyForm) {
        otpVerifyForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const phoneHidden = document.getElementById('verify-phone-hidden').value;
            const phoneVal = phoneHidden || sessionStorage.getItem('whatsapp_phone') || document.getElementById('otp-phone').value;
            const otpCode = document.getElementById('otp-code-input').value;
            const refCode = document.getElementById('otp-ref-input').value;

            statusMsg.className = "text-xs font-bold text-center mt-2 text-secondary";
            statusMsg.innerText = "Verifying Code & Logging In...";

            fetch('/auth/phone/verify', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ phone: phoneVal, otp_code: otpCode, ref: refCode })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    statusMsg.className = "text-xs font-bold text-center mt-2 text-primary";
                    statusMsg.innerText = data.message;
                    setTimeout(() => {
                        window.location.reload();
                    }, 500);
                } else {
                    statusMsg.className = "text-xs font-bold text-center mt-2 text-accent-rose";
                    statusMsg.innerText = data.message;
                }
            })
            .catch(err => {
                statusMsg.className = "text-xs font-bold text-center mt-2 text-accent-rose";
                statusMsg.innerText = "Server Error: " + err.message;
            });
        });
    }

    // Standart Giriş Form Handler
    const loginForm = document.getElementById('login-form');
    const loginStatusMsg = document.getElementById('login-status-msg');
    const loginBtn = document.getElementById('btn-login-submit');

    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const usernameInput = document.getElementById('login-username').value.trim();
            const passwordInput = document.getElementById('login-password').value;

            if (!usernameInput || !passwordInput) {
                if (loginStatusMsg) {
                    loginStatusMsg.className = "text-xs font-bold text-center mt-2 text-accent-rose";
                    loginStatusMsg.innerText = "Please enter both username and password.";
                }
                return;
            }

            if (loginBtn) {
                loginBtn.disabled = true;
                loginBtn.innerHTML = '<span class="inline-block animate-spin mr-1.5">⏳</span> Signing In...';
            }
            if (loginStatusMsg) {
                loginStatusMsg.className = "text-xs font-bold text-center mt-2 text-secondary";
                loginStatusMsg.innerText = "Verifying credentials...";
            }

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
            const formData = new URLSearchParams();
            formData.append('_token', csrfToken);
            formData.append('username', usernameInput);
            formData.append('password', passwordInput);
            formData.append('is_ajax', '1');

            fetch('/login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: formData.toString()
            })
            .then(async (res) => {
                const data = await res.json().catch(() => ({}));
                if (res.ok && (data.link || res.status === 200)) {
                    if (loginStatusMsg) {
                        loginStatusMsg.className = "text-xs font-bold text-center mt-2 text-primary";
                        loginStatusMsg.innerText = "✓ Login successful! Entering arena...";
                    }
                    setTimeout(() => {
                        window.location.reload();
                    }, 400);
                } else {
                    if (loginBtn) {
                        loginBtn.disabled = false;
                        loginBtn.innerHTML = 'Sign In';
                    }
                    let err = "Invalid username or password.";
                    if (res.status === 419) {
                        err = "Session expired. Please refresh the page.";
                    } else if (data.error) {
                        err = data.error;
                    } else if (data.message && data.message !== 'CSRF token mismatch.') {
                        err = data.message;
                    } else if (Array.isArray(data) && data[0] && !data[0].includes('Unknown')) {
                        err = data[0];
                    }
                    if (loginStatusMsg) {
                        loginStatusMsg.className = "text-xs font-bold text-center mt-2 text-accent-rose";
                        loginStatusMsg.innerText = err;
                    }
                }
            })
            .catch((err) => {
                if (loginBtn) {
                    loginBtn.disabled = false;
                    loginBtn.innerHTML = 'Sign In';
                }
                if (loginStatusMsg) {
                    loginStatusMsg.className = "text-xs font-bold text-center mt-2 text-accent-rose";
                    loginStatusMsg.innerText = "Connection error. Please try again.";
                }
            });
        });
    }

    // Fast Register Form Handler
    const registerForm = document.getElementById('register-form');
    const registerStatusMsg = document.getElementById('register-status-msg');
    const registerBtn = document.getElementById('btn-register-submit');

    if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const regUser = document.getElementById('reg-username').value.trim();
            const regPass = document.getElementById('reg-password').value;
            const regPassConf = document.getElementById('reg-password-confirm').value;

            if (regPass !== regPassConf) {
                if (registerStatusMsg) {
                    registerStatusMsg.className = "text-xs font-bold text-center mt-2 text-accent-rose";
                    registerStatusMsg.innerText = "Passwords do not match.";
                }
                return;
            }

            if (registerBtn) {
                registerBtn.disabled = true;
                registerBtn.innerHTML = '<span class="inline-block animate-spin mr-1.5">⏳</span> Creating Account...';
            }
            if (registerStatusMsg) {
                registerStatusMsg.className = "text-xs font-bold text-center mt-2 text-secondary";
                registerStatusMsg.innerText = "Setting up account & 50.000 TRY...";
            }

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
            const formData = new URLSearchParams();
            formData.append('_token', csrfToken);
            formData.append('username', regUser);
            formData.append('password', regPass);
            formData.append('password_confirmation', regPassConf);
            formData.append('is_ajax', '1');

            fetch('/register', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: formData.toString()
            })
            .then(async (res) => {
                const data = await res.json().catch(() => ({}));
                if (res.ok && (data.success || res.status === 200)) {
                    if (registerStatusMsg) {
                        registerStatusMsg.className = "text-xs font-bold text-center mt-2 text-primary";
                        registerStatusMsg.innerText = "✓ Account created! Welcome!";
                    }
                    setTimeout(() => {
                        window.location.reload();
                    }, 400);
                } else {
                    if (registerBtn) {
                        registerBtn.disabled = false;
                        registerBtn.innerHTML = 'Hesap Oluştur ve Coinleri Al';
                    }
                    let err = data.error || (data.errors ? Object.values(data.errors).flat()[0] : null) || "Registration failed. Please check inputs.";
                    if (registerStatusMsg) {
                        registerStatusMsg.className = "text-xs font-bold text-center mt-2 text-accent-rose";
                        registerStatusMsg.innerText = err;
                    }
                }
            })
            .catch(() => {
                if (registerBtn) {
                    registerBtn.disabled = false;
                    registerBtn.innerHTML = 'Hesap Oluştur ve Coinleri Al';
                }
                if (registerStatusMsg) {
                    registerStatusMsg.className = "text-xs font-bold text-center mt-2 text-accent-rose";
                    registerStatusMsg.innerText = "Connection error. Please try again.";
                }
            });
        });
    }

    // Profile Update Form Handler
    const profileForm = document.getElementById('profile-update-form');
    const profMsg = document.getElementById('profile-update-msg');

    if (profileForm) {
        profileForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const username = document.getElementById('prof-username').value;
            const email = document.getElementById('prof-email').value;
            const firstName = document.getElementById('prof-firstname').value;
            const lastName = document.getElementById('prof-lastname').value;
            const phone = document.getElementById('prof-phone').value;
            const preferredLoginMethod = document.getElementById('prof-login-method').value;
            const currentPassword = document.getElementById('prof-current-password').value;
            const newPassword = document.getElementById('prof-new-password').value;
            const newPasswordConfirmation = document.getElementById('prof-new-password-confirmation').value;

            profMsg.className = "text-xs font-bold text-center mt-2 text-secondary";
            profMsg.innerText = "Saving Profile...";

            fetch('/auth/profile/update', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    username: username,
                    email: email,
                    phone: phone,
                    preferred_login_method: preferredLoginMethod,
                    current_password: currentPassword,
                    new_password: newPassword,
                    new_password_confirmation: newPasswordConfirmation,
                    first_name: firstName,
                    last_name: lastName
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    profMsg.className = "text-xs font-bold text-center mt-2 text-primary";
                    profMsg.innerText = data.message;
                    setTimeout(() => {
                        window.location.reload();
                    }, 600);
                } else {
                    profMsg.className = "text-xs font-bold text-center mt-2 text-accent-rose";
                    profMsg.innerText = data.message;
                }
            });
        });
    }

    @if(session('modal'))
        setTimeout(function() {
            window.openModal('{{ session('modal') }}');
        }, 150);
    @endif

    // Cashout Modal Dynamic Calculator & Presets
    const cashoutAmountInput = document.getElementById('cashout-amount');
    const cashoutLiveFiat = document.getElementById('cashout-live-fiat');
    const cashoutRate = {{ (float)(function_exists('settings') ? settings('coins_per_dollar', 100) : 100) ?: 100 }};

    function updateCashoutLiveCalc() {
        if (!cashoutAmountInput || !cashoutLiveFiat) return;
        const val = parseFloat(cashoutAmountInput.value) || 0;
        const usd = (val / cashoutRate).toFixed(2);
        cashoutLiveFiat.innerText = usd;
    }

    if (cashoutAmountInput) {
        cashoutAmountInput.addEventListener('input', updateCashoutLiveCalc);
    }

    document.querySelectorAll('.btn-cashout-preset').forEach(btn => {
        btn.addEventListener('click', function() {
            const amt = this.getAttribute('data-amount');
            if (cashoutAmountInput) {
                cashoutAmountInput.value = amt;
                updateCashoutLiveCalc();
            }
        });
    });

    // Cashout Request AJAX Submission
    const cashoutForm = document.getElementById('cashout-request-form');
    const cashoutStatus = document.getElementById('cashout-status-msg');
    const cashoutBtn = document.getElementById('btn-submit-cashout');

    if (cashoutForm) {
        cashoutForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const method = document.getElementById('cashout-method').value;
            const amount = document.getElementById('cashout-amount').value;
            const wallet = document.getElementById('cashout-wallet').value;

            cashoutStatus.className = "text-xs font-bold text-center mt-2 text-secondary";
            cashoutStatus.innerText = "Submitting Cashout Request...";
            cashoutBtn.disabled = true;

            fetch('/profile/withdraw', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    method: method,
                    amount: amount,
                    wallet: wallet
                })
            })
            .then(res => res.json())
            .then(data => {
                cashoutBtn.disabled = false;
                if (data.success) {
                    cashoutStatus.className = "text-xs font-bold text-center mt-2 text-primary";
                    cashoutStatus.innerText = data.message;

                    // Update balances in UI
                    if (data.new_balance) {
                        const balElem = document.getElementById('cashout-modal-user-balance');
                        if (balElem) balElem.innerText = data.new_balance;
                        const sideBal = document.getElementById('user-coin-balance-sidebar');
                        if (sideBal) sideBal.innerText = data.new_balance;
                        const topBal = document.getElementById('topbar-balance');
                        if (topBal) topBal.innerText = data.new_balance;
                        const menuBal = document.getElementById('account-menu-balance');
                        if (menuBal) menuBal.innerText = data.new_balance;
                        const mobBal = document.getElementById('user-coin-balance-mobile');
                        if (mobBal) mobBal.innerText = data.new_balance;
                    }

                    // Prepend new item to history
                    const historyList = document.getElementById('cashout-history-list');
                    const noHist = document.getElementById('no-cashout-history');
                    if (noHist) noHist.remove();

                    if (historyList && data.withdrawal) {
                        const newCard = document.createElement('div');
                        newCard.className = "bg-black/30 p-2.5 rounded-xl border border-white/5 flex items-center justify-between text-xs animate-pulse";
                        newCard.innerHTML = `
                            <div>
                                <div class="font-mono-jet font-bold text-white flex items-center gap-1.5">
                                    <span>${data.withdrawal.coins} Pts</span>
                                    <span class="text-emerald-400 font-bold">($${data.withdrawal.usd} USD)</span>
                                </div>
                                <div class="text-[10px] text-on-surface-subtle truncate max-w-[230px] mt-0.5">
                                    <span class="text-white/70">${data.withdrawal.method}:</span> ${data.withdrawal.wallet}
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="inline-flex items-center gap-1 text-[10px] bg-amber-500/10 text-amber-400 border border-amber-500/30 px-2 py-0.5 rounded-full font-bold">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                                    Beklemede
                                </span>
                                <div class="text-[9px] text-on-surface-subtle mt-0.5">${data.withdrawal.date}</div>
                            </div>
                        `;
                        historyList.prepend(newCard);
                    }

                    cashoutForm.reset();
                    updateCashoutLiveCalc();
                } else {
                    cashoutStatus.className = "text-xs font-bold text-center mt-2 text-accent-rose";
                    cashoutStatus.innerText = data.message || "Error submitting cashout request.";
                }
            })
            .catch(err => {
                cashoutBtn.disabled = false;
                cashoutStatus.className = "text-xs font-bold text-center mt-2 text-accent-rose";
                cashoutStatus.innerText = "Network Error: " + err.message;
            });
        });
    }

});
</script>
