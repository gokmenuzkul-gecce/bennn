$(document).ready(function () {
    // Search functionality
    let searchTimeout;

    $('#game-search').on('keyup', function () {
        clearTimeout(searchTimeout);
        const query = $(this).val();

        searchTimeout = setTimeout(function () {
            $('.game-card').each(function () {
                const title = $(this).data('title').toLowerCase();
                if (title.includes(query.toLowerCase())) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        }, 300);
    });

    // Modal Functionality
    $('.open-modal').on('click', function (e) {
        e.preventDefault();
        const target = $(this).data('target');
        if (!target) {
            return;
        }

        $('.modal').removeClass('show');
        const $modal = $('#' + target);
        $modal.addClass('show');

        if (target === 'modal-profile') {
            loadProfileData();
        }
    });

    $('.close-modal').on('click', function () {
        $(this).closest('.modal').removeClass('show');
    });

    $(window).on('click', function (e) {
        if ($(e.target).hasClass('modal')) {
            $('.modal').removeClass('show');
        }
    });

    // Member tabs
    $('.member-tab').on('click', function () {
        const tab = $(this).data('tab');
        const wrapper = $(this).closest('.modal-content');

        $(this).addClass('active').siblings().removeClass('active');
        wrapper.find('.member-panel').removeClass('active');
        wrapper.find('#' + tab).addClass('active');

        if (tab === 'member-profile') {
            loadProfileData();
        } else if (tab === 'member-transactions') {
            loadTransactions();
        }
    });

    // (Login & Register are handled natively in modals.blade.php with real-time UI feedback)

    // Load Profile Data
    function loadProfileData() {
        const container = $('#profile-data');

        $.ajax({
            url: '/profile/ajax',
            method: 'GET',
            dataType: 'json',
            success: function (response) {
                if (response.profile) {
                    const csrf = $('meta[name="csrf-token"]').attr('content');
                    const email = response.profile.email || '';
                    const balance = `${response.profile.balance} ${response.currency || ''}`.trim();
                    const rating = response.profile.rating || '';

                    container.html(`
                        <div class="profile-simple">
                            <div class="profile-row">
                                <div>
                                    <p class="muted">Balance</p>
                                    <p class="profile-value balance-link" id="profile-balance">${balance}</p>
                                </div>
                                <button class="ghost-link" id="profile-topup">Top up</button>
                            </div>
                            <div class="profile-row">
                                <div>
                                    <p class="muted">Rating</p>
                                    <p class="profile-value">${rating}</p>
                                </div>
                            </div>
                            <form id="profile-form">
                                <input type="hidden" name="_token" value="${csrf}">
                                <div class="form-group">
                                    <label for="profile-email">Email</label>
                                    <input type="email" id="profile-email" name="email" value="${email}" required>
                                </div>
                                <div class="form-group">
                                    <label for="profile-password">New password</label>
                                    <input type="password" id="profile-password" name="password" placeholder="Leave blank to keep current">
                                </div>
                                <div class="form-group">
                                    <label for="profile-password-confirm">Confirm password</label>
                                    <input type="password" id="profile-password-confirm" name="password_confirmation" placeholder="Confirm new password">
                                </div>
                                <button type="submit" class="btn-primary">Save</button>
                                <div class="form-message" id="profile-message"></div>
                            </form>
                        </div>
                    `);

                    container.find('#profile-balance, #profile-topup').on('click', function () {
                        const tabBtn = $('.member-tab[data-tab="member-topup"]');
                        tabBtn.trigger('click');
                    });

                    $('#profile-form').on('submit', function (e) {
                        e.preventDefault();
                        const form = $(this);
                        const messageDiv = $('#profile-message');
                        messageDiv.text('Saving...').removeClass('error').show();

                        $.ajax({
                            url: '/profile/details/update',
                            method: 'POST',
                            data: form.serialize(),
                            success: function () {
                                messageDiv.text('Profile updated.').removeClass('error');
                                loadProfileData();
                            },
                            error: function (xhr) {
                                let errorMsg = 'Update failed';
                                if (xhr.responseJSON && xhr.responseJSON.error) {
                                    errorMsg = xhr.responseJSON.error;
                                } else if (xhr.responseJSON && xhr.responseJSON[0]) {
                                    errorMsg = xhr.responseJSON[0];
                                }
                                messageDiv.text(errorMsg).addClass('error').show();
                            }
                        });
                    });
                }
            },
            error: function () {
                container.html('<p class="error">Failed to load profile data.</p>');
            }
        });
    }

    function loadTransactions(page = 1) {
        const container = $('#transactions-panel');
        container.html('<div class="loading">Loading...</div>');

        $.ajax({
            url: '/profile/transactions',
            method: 'GET',
            data: { page },
            success: function (response) {
                if (!response || !response.data) {
                    container.html('<p class="error">No transactions found.</p>');
                    return;
                }

                const rows = response.data.map(item => {
                    return `
                        <div class="txn-row">
                            <div class="txn-main">
                                <span class="pill subtle ${item.direction}">${item.direction}</span>
                                <span class="txn-amount">${item.amount_formatted}</span>
                            </div>
                            <div class="txn-meta">
                                <span>${item.source || 'manual'}</span>
                                <span>${item.created_at}</span>
                            </div>
                            <div class="txn-note">${item.note || ''}</div>
                        </div>
                    `;
                }).join('');

                const prevDisabled = response.meta.prev_page ? '' : 'disabled';
                const nextDisabled = response.meta.next_page ? '' : 'disabled';

                container.html(`
                    <div class="txn-list">${rows || '<p class="muted">No transactions yet.</p>'}</div>
                    <div class="txn-pagination">
                        <button class="ghost-link ${prevDisabled}" data-page="${response.meta.prev_page || ''}">Prev</button>
                        <span class="muted">Page ${response.meta.page}</span>
                        <button class="ghost-link ${nextDisabled}" data-page="${response.meta.next_page || ''}">Next</button>
                    </div>
                `);

                container.find('.txn-pagination button').on('click', function () {
                    const targetPage = $(this).data('page');
                    if (!targetPage) return;
                    loadTransactions(targetPage);
                });
            },
            error: function () {
                container.html('<p class="error">Failed to load transactions.</p>');
            }
        });
    }

    // Topup
    $('#topup-form').on('submit', function (e) {
        e.preventDefault();
        const form = $(this);
        const messageDiv = $('#topup-message');
        messageDiv.text('Creating payment...').removeClass('error').show();

        $.ajax({
            url: '/topup/create',
            method: 'POST',
            data: form.serialize(),
            success: function (response) {
                if (response.payment_url) {
                    messageDiv.text('Redirecting to payment...');
                    window.open(response.payment_url, '_blank');
                } else {
                    messageDiv.text('Payment URL missing.').addClass('error');
                }
            },
            error: function (xhr) {
                let errorMsg = 'Failed to create payment';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    errorMsg = xhr.responseJSON.error;
                }
                messageDiv.text(errorMsg).addClass('error');
            }
        });
    });

    // Mobile Bottom Sheet Navigation & Profile Hub Handlers
    const $mobileSheet = $('#mobile-bottom-sheet');
    const $mobileOverlay = $('#mobile-sheet-overlay');

    function openMobileSheet() {
        if ($mobileOverlay.length && $mobileSheet.length) {
            $mobileOverlay.removeClass('hidden');
            setTimeout(function() {
                $mobileSheet.removeClass('translate-y-full');
            }, 10);
        }
    }

    function closeMobileSheet() {
        if ($mobileOverlay.length && $mobileSheet.length) {
            $mobileSheet.addClass('translate-y-full');
            setTimeout(function() {
                $mobileOverlay.addClass('hidden');
            }, 300);
        }
    }

    $(document).on('click', '#btn-open-mobile-menu, .js-open-mobile-sheet', function(e) {
        e.preventDefault();
        openMobileSheet();
    });

    $(document).on('click', '#btn-close-bottom-sheet, #sheet-drag-handle, #mobile-sheet-overlay', function(e) {
        closeMobileSheet();
    });

    // Header hamburger drawer (works on every viewport)
    const $siteDrawer = $('#site-drawer');
    const $siteDrawerOverlay = $('#site-drawer-overlay');

    function openSiteDrawer() {
        $siteDrawerOverlay.addClass('is-open');
        $siteDrawer.addClass('is-open').attr('aria-hidden', 'false');
    }

    function closeSiteDrawer() {
        $siteDrawer.removeClass('is-open').attr('aria-hidden', 'true');
        $siteDrawerOverlay.removeClass('is-open');
    }

    $(document).on('click', '#btn-open-site-drawer', function(e) {
        e.preventDefault();
        openSiteDrawer();
    });

    $(document).on('click', '#btn-close-site-drawer, #site-drawer-overlay', function(e) {
        closeSiteDrawer();
    });

    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            closeSiteDrawer();
        }
    });

});
