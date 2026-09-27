@extends('liteback.layout')

@section('title', $isCedarLibrary ? 'Liteback - CEDAR Games' : 'Liteback - Game & Provider Controls')
@section('page_title', $isCedarLibrary ? 'CEDAR Game Management' : 'Game & Provider Management')

@section('content')
<div class="container-fluid">
    @php
        $listRoute = $isCedarLibrary ? 'liteback.cedar.index' : 'liteback.games.index';
        $inactiveRoute = $isCedarLibrary ? 'liteback.cedar.inactive' : 'liteback.games.inactive';
    @endphp
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle mr-2"></i> {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle mr-2"></i> {{ $errors->first() }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    @if(session('cedar_import_results'))
        <div class="alert alert-light border">
            <strong>CEDAR import details:</strong>
            @foreach(session('cedar_import_results') as $row)
                <span class="badge {{ $row['status'] === 'skipped' ? 'badge-warning' : 'badge-success' }} ml-1">{{ $row['name'] }}: {{ $row['status'] }}</span>
            @endforeach
        </div>
    @endif

    @if(session('legacy_import_results'))
        <div class="alert alert-light border">
            <strong>Legacy import details:</strong>
            @foreach(session('legacy_import_results') as $row)
                <span class="badge {{ $row['status'] === 'skipped' ? 'badge-warning' : 'badge-success' }} ml-1" title="{{ $row['message'] }}">{{ $row['name'] }}: {{ $row['status'] }}</span>
            @endforeach
        </div>
    @endif

    @if($isCedarLibrary)
        <div class="card card-outline card-success mb-4">
            <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                <div>
                    <h3 class="h5 font-weight-bold mb-1"><i class="fas fa-cloud-download-alt mr-2"></i>Licensed CEDAR Catalog</h3>
                    <p class="text-muted mb-0">Pull entitled hosted games, add titles missing from this installation, and refresh existing metadata. Protected math and source remain on PROMEX servers.</p>
                </div>
                <form method="post" action="{{ route('liteback.cedar.sync') }}" class="mt-3 mt-md-0 ml-md-3">
                    @csrf
                    <button class="btn btn-success font-weight-bold" type="submit"><i class="fas fa-sync-alt mr-1"></i> Pull & Install Games</button>
                </form>
            </div>
        </div>
    @else
    <div class="card card-outline {{ $legacyEnabled ? 'card-warning' : 'card-secondary' }} mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h3 class="card-title font-weight-bold"><i class="fas fa-puzzle-piece mr-2"></i>Legacy Compatibility Plugin</h3>
                <div class="small text-muted mt-1">Operator-supplied local files only. Promex does not provide, upload, or claim rights to provider content.</div>
            </div>
            <form method="post" action="{{ route('liteback.games.legacy_plugin') }}">
                @csrf
                <input type="hidden" name="enabled" value="{{ $legacyEnabled ? 0 : 1 }}">
                <button class="btn btn-sm {{ $legacyEnabled ? 'btn-outline-danger' : 'btn-warning' }}" type="submit">
                    {{ $legacyEnabled ? 'Disable Plugin & Games' : 'Enable Plugin' }}
                </button>
            </form>
        </div>
        <div class="card-body">
            @php
                $legacyBackendReady = count(array_filter($legacyDiscovered, fn($row) => $row['backend']));
                $legacyBridgeHints = count(array_filter($legacyDiscovered, fn($row) => $row['bridge_hint']));
            @endphp
            <div class="mb-3">
                <span class="badge badge-secondary">{{ count($legacyDiscovered) }} local frontends discovered</span>
                <span class="badge badge-info">{{ $legacyBackendReady }} matching servers</span>
                <span class="badge badge-dark">{{ $legacyBridgeHints }} bridge hints</span>
                <span class="badge {{ $legacyEnabled ? 'badge-success' : 'badge-secondary' }}">{{ $legacyEnabled ? 'PLUGIN ON' : 'PLUGIN OFF' }}</span>
            </div>
            @if(count($legacyDiscovered))
                <details class="small mb-3">
                    <summary class="text-info" style="cursor:pointer">Show discovered folder names</summary>
                    <div class="mt-2">
                        @foreach(array_slice($legacyDiscovered, 0, 100) as $candidate)
                            <span class="badge {{ $candidate['backend'] ? 'badge-light border' : 'badge-secondary' }} mr-1 mb-1" title="{{ $candidate['backend'] ? 'Matching server found' : 'Server.php missing' }}">{{ $candidate['name'] }}</span>
                        @endforeach
                        @if(count($legacyDiscovered) > 100)<span class="text-muted">and {{ count($legacyDiscovered) - 100 }} more…</span>@endif
                    </div>
                </details>
            @endif
            <form method="post" action="{{ route('liteback.games.import_legacy') }}" enctype="multipart/form-data">
                @csrf
                <div class="form-row">
                    <div class="col-lg-7 mb-2">
                        <textarea class="form-control" name="names" rows="2" placeholder="Explicit folder names, separated by commas or new lines" {{ $legacyEnabled ? '' : 'disabled' }}></textarea>
                    </div>
                    <div class="col-lg-3 mb-2">
                        <input class="form-control-file" type="file" name="csv" accept=".csv,.txt" {{ $legacyEnabled ? '' : 'disabled' }}>
                    </div>
                    <div class="col-lg-2 mb-2">
                        <button class="btn btn-warning btn-block" type="submit" {{ $legacyEnabled ? '' : 'disabled' }}>Register Disabled</button>
                    </div>
                </div>
                <div class="custom-control custom-checkbox">
                    <input class="custom-control-input" id="legacy-rights-attested" type="checkbox" name="rights_attested" value="1" required {{ $legacyEnabled ? '' : 'disabled' }}>
                    <label class="custom-control-label" for="legacy-rights-attested">I confirm I am authorized to use these selected frontend and backend files.</label>
                </div>
                <p class="small text-muted mt-2 mb-0">Discovery never activates a game. Imported games remain off until their normal Activate control passes plugin, file, attestation, and license checks.</p>
            </form>
        </div>
    </div>
    @endif

    <!-- Metric Stat Widgets -->
    <div class="row">
        <div class="col-lg-3 col-6">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3>{{ $games->total() }}</h3>
                    <p>Total Filtered Games</p>
                </div>
                <div class="icon">
                    <i class="fas fa-gamepad"></i>
                </div>
                <a href="{{ route($listRoute) }}" class="small-box-footer">Reset Filter <i class="fas fa-arrow-circle-right"></i></a>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3>{{ $totalActive }}</h3>
                    <p>Active Games (Playable)</p>
                </div>
                <div class="icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <a href="{{ route($listRoute, ['status' => 'active']) }}" class="small-box-footer">View Active Only <i class="fas fa-arrow-circle-right"></i></a>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-danger">
                <div class="inner">
                    <h3>{{ $totalDisabled }}</h3>
                    <p>Disabled Games (Hidden)</p>
                </div>
                <div class="icon">
                    <i class="fas fa-ban"></i>
                </div>
                <a href="{{ route($inactiveRoute) }}" class="small-box-footer">View Disabled Only <i class="fas fa-arrow-circle-right"></i></a>
            </div>
        </div>
        <div class="col-lg-3 col-6">
            <div class="small-box bg-secondary">
                <div class="inner">
                    <h3>{{ count($providers) }}</h3>
                    <p>Active Providers / Categories</p>
                </div>
                <div class="icon">
                    <i class="fas fa-layer-group"></i>
                </div>
                <a href="#providers-section" class="small-box-footer">Scroll to Killswitches <i class="fas fa-arrow-circle-down"></i></a>
            </div>
        </div>
    </div>

    <!-- Provider Bulk Controls Card -->
    <div class="card card-outline card-primary mb-4" id="providers-section">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title font-weight-bold">
                <i class="fas fa-toggle-on text-primary mr-2"></i> Provider Bulk Controls (Killswitches)
            </h3>
            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                    <i class="fas fa-minus"></i>
                </button>
            </div>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">
                Instantly enable or disable all games for an entire game provider/studio across the entire casino lobby with 1-click.
            </p>
            <div class="row">
                @foreach($providers as $p)
                    <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
                        <div class="p-3 border rounded bg-light h-100 d-flex flex-col justify-content-between shadow-sm">
                            <div>
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h6 class="font-weight-bold mb-0 text-dark">{{ $p->title }}</h6>
                                    <span class="badge badge-secondary">{{ $p->total_games }} Games</span>
                                </div>
                                <div class="small mb-3">
                                    <span class="badge badge-success mr-1"><i class="fas fa-check"></i> {{ $p->active_games }} Active</span>
                                    @if($p->disabled_games > 0)
                                        <span class="badge badge-danger"><i class="fas fa-ban"></i> {{ $p->disabled_games }} Off</span>
                                    @else
                                        <span class="badge badge-light border text-muted">0 Off</span>
                                    @endif
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                <a href="{{ route($listRoute, ['category_id' => $p->id]) }}" class="btn btn-xs btn-outline-info" title="Filter list to {{ $p->title }}">
                                    <i class="fas fa-filter"></i> Filter
                                </a>
                                <div class="btn-group btn-group-sm">
                                    <form method="post" action="{{ route('liteback.games.bulk_provider_toggle') }}" class="d-inline" onsubmit="return confirm('Enable all {{ $p->total_games }} games for provider \'{{ $p->title }}\'?');">
                                        @csrf
                                        <input type="hidden" name="category_id" value="{{ $p->id }}">
                                        <input type="hidden" name="action" value="enable">
                                        <button type="submit" class="btn btn-xs btn-outline-success font-weight-bold" {{ $p->disabled_games == 0 ? 'disabled' : '' }}>
                                            Enable All
                                        </button>
                                    </form>
                                    <form method="post" action="{{ route('liteback.games.bulk_provider_toggle') }}" class="d-inline ml-1" onsubmit="return confirm('Disable all {{ $p->total_games }} games for provider \'{{ $p->title }}\'?');">
                                        @csrf
                                        <input type="hidden" name="category_id" value="{{ $p->id }}">
                                        <input type="hidden" name="action" value="disable">
                                        <button type="submit" class="btn btn-xs btn-outline-danger font-weight-bold" {{ $p->active_games == 0 ? 'disabled' : '' }}>
                                            Disable All
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Games Table & Filter Card -->
    <div class="card card-outline card-secondary">
        <div class="card-header">
            <form class="form-row align-items-center" method="get" action="{{ route($listRoute) }}">
                <div class="col-md-3 col-sm-6 mb-2">
                    <div class="input-group input-group-sm">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                        </div>
                        <input type="text" name="q" class="form-control" placeholder="Search title, system name, ID" value="{{ $term }}">
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <select name="category_id" class="form-control form-control-sm">
                        <option value="">-- All Providers / Categories --</option>
                        @foreach($providers as $prov)
                            <option value="{{ $prov->id }}" {{ $selectedCategory == $prov->id ? 'selected' : '' }}>
                                {{ $prov->title }} ({{ $prov->total_games }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 col-sm-4 mb-2">
                    <select name="status" class="form-control form-control-sm">
                        <option value="all" {{ $selectedStatus === 'all' ? 'selected' : '' }}>All Statuses</option>
                        <option value="active" {{ $selectedStatus === 'active' ? 'selected' : '' }}>Active Only</option>
                        <option value="disabled" {{ $selectedStatus === 'disabled' ? 'selected' : '' }}>Disabled Only</option>
                    </select>
                </div>
                <div class="col-md-2 col-sm-4 mb-2">
                    <select name="per_page" class="form-control form-control-sm">
                        <option value="25" {{ request('per_page', 25) == 25 ? 'selected' : '' }}>25 per page</option>
                        <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50 per page</option>
                        <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100 per page</option>
                    </select>
                </div>
                <div class="col-md-2 col-sm-4 mb-2 d-flex">
                    <button type="submit" class="btn btn-sm btn-primary flex-fill mr-1"><i class="fas fa-filter"></i> Apply</button>
                    <a href="{{ route($listRoute) }}" class="btn btn-sm btn-outline-secondary mr-1" title="Clear Filters"><i class="fas fa-undo"></i></a>
                    @unless($isCedarLibrary)
                        <button type="button" class="btn btn-sm btn-success" data-toggle="modal" data-target="#addManualGameModal" title="Add Custom / External Game"><i class="fas fa-plus"></i></button>
                    @endunless
                </div>
            </form>
        </div>

        <!-- Bulk Action Form & Controls -->
        @if($remoteCatalogError)
            <div class="alert alert-warning m-3 mb-0 py-2"><i class="fas fa-cloud-slash mr-1"></i>{{ $remoteCatalogError }} Remote games remain unavailable until the Hub responds.</div>
        @endif
        <form id="bulk-action-form" method="post" action="{{ route('liteback.games.bulk_action') }}">
            @csrf
            <div class="bg-light px-3 py-2 border-bottom d-flex justify-content-between align-items-center flex-wrap">
                <div class="d-flex align-items-center my-1">
                    <div class="custom-control custom-checkbox mr-3">
                        <input type="checkbox" class="custom-control-input" id="check-all">
                        <label class="custom-control-label font-weight-normal small" for="check-all">Select All On Page</label>
                    </div>
                    <span class="badge badge-info mr-3" id="selected-counter">0 Selected</span>
                </div>
                <div class="btn-group btn-group-sm my-1">
                    <button type="submit" name="action" value="enable" class="btn btn-outline-success font-weight-bold bulk-btn" disabled onclick="return confirm('Enable all selected games?');">
                        <i class="fas fa-check"></i> Enable Selected
                    </button>
                    <button type="submit" name="action" value="disable" class="btn btn-outline-danger font-weight-bold bulk-btn" disabled onclick="return confirm('Disable all selected games?');">
                        <i class="fas fa-ban"></i> Disable Selected
                    </button>
                    <button type="submit" formaction="{{ route('liteback.games.bulk_delivery') }}" name="delivery_mode" value="LOCAL" class="btn btn-outline-secondary font-weight-bold bulk-btn" disabled>
                        <i class="fas fa-server"></i> Use Local
                    </button>
                    <button type="submit" formaction="{{ route('liteback.games.bulk_delivery') }}" name="delivery_mode" value="PROMEX_REMOTE" class="btn btn-outline-primary font-weight-bold bulk-btn" disabled>
                        <i class="fas fa-cloud"></i> Use Promex Remote
                    </button>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0 align-middle">
                        <thead class="thead-dark small">
                        <tr>
                            <th style="width: 40px;" class="text-center">#</th>
                            <th style="width: 70px;">ID</th>
                            <th style="width: 60px;">Icon</th>
                            <th>Game Title</th>
                            <th>System Name</th>
                            <th>Source</th>
                            <th>Provider(s)</th>
                            <th>Bet Limits & Denom</th>
                            <th style="width: 110px;" class="text-center">Status</th>
                            <th style="width: 180px;" class="text-right">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($games as $game)
                            <tr class="{{ $game->view == 0 ? 'table-warning' : '' }}">
                                <td class="text-center">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" name="game_ids[]" value="{{ $game->id }}" class="custom-control-input game-checkbox" id="chk-{{ $game->id }}">
                                        <label class="custom-control-label" for="chk-{{ $game->id }}"></label>
                                    </div>
                                </td>
                                <td class="font-weight-bold text-muted">{{ $game->id }}</td>
                                <td>
                                    <img src="{{ asset('frontend/Default/ico/' . $game->name . '.jpg') }}" 
                                         alt="{{ $game->name }}" 
                                         class="rounded shadow-sm" 
                                         style="width:42px;height:42px;object-fit:cover;"
                                         onerror="this.onerror=null;this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'42\' height=\'42\' viewBox=\'0 0 42 42\'><rect fill=\'%23343a40\' width=\'42\' height=\'42\'/><text fill=\'%23adb5bd\' font-family=\'sans-serif\' font-size=\'10\' font-weight=\'bold\' x=\'50%25\' y=\'55%25\' text-anchor=\'middle\' dominant-baseline=\'middle\'>SLOT</text></svg>';">
                                </td>
                                <td>
                                    <div class="font-weight-bold text-dark">{{ $game->title }}</div>
                                </td>
                                <td>
                                    <code class="small text-secondary">{{ $game->name }}</code>
                                </td>
                                <td>
                                    @if(isset($game->source_type) && $game->source_type === 'legacy_compat')
                                        <span class="badge badge-warning" title="Operator-supplied local content; rights attested {{ $game->legacy_rights_attested_at }} by admin #{{ $game->legacy_rights_attested_by }}"><i class="fas fa-puzzle-piece mr-1"></i> Legacy Plugin</span>
                                    @elseif(isset($game->source_type) && $game->source_type === 'cedar_game')
                                        <span class="badge badge-dark" title="{{ $game->custom_path }}"><i class="fas fa-tree mr-1"></i> CEDAR</span>
                                    @elseif(isset($game->source_type) && $game->source_type === 'custom_folder')
                                        <span class="badge badge-warning" title="{{ $game->custom_path }}"><i class="fas fa-folder mr-1"></i> Custom</span>
                                    @elseif(isset($game->source_type) && $game->source_type === 'external_url')
                                        <span class="badge badge-info" title="{{ $game->custom_path }}"><i class="fas fa-globe mr-1"></i> Ext URL</span>
                                    @else
                                        <span class="badge badge-secondary"><i class="fas fa-cube mr-1"></i> Default</span>
                                    @endif
                                    @if(($game->delivery_mode ?? 'LOCAL') === 'PROMEX_REMOTE')
                                        @php($remoteReady = ($game->source_type ?? '') === 'legacy_compat'
                                            ? $legacyCdnAvailable
                                            : isset($remoteCatalog[$game->name]))
                                        <span class="badge {{ $remoteReady ? 'badge-primary' : 'badge-danger' }} d-block mt-1">
                                            <i class="fas fa-cloud mr-1"></i>{{ $remoteReady ? 'PROMEX CDN' : 'REMOTE UNENTITLED' }}
                                        </span>
                                    @else
                                        <span class="badge badge-light border d-block mt-1"><i class="fas fa-server mr-1"></i>LOCAL</span>
                                    @endif
                                </td>
                                <td>
                                    @forelse($game->category_names ?? [] as $cname)
                                        <span class="badge badge-info">{{ $cname }}</span>
                                    @empty
                                        <span class="text-muted small">None</span>
                                    @endforelse
                                </td>
                                <td>
                                    <div class="text-muted small">Bets: <strong class="text-dark">{{ $game->bet ?: '0.01-1.00' }}</strong></div>
                                    <div class="text-muted small">Denom: <strong class="text-dark">{{ $game->denomination ?: '1.00' }}</strong></div>
                                </td>
                                <td class="text-center">
                                    @php($effective = (int)$game->view === 1
                                        && (($game->source_type ?? '') !== 'legacy_compat' || $legacyEnabled)
                                        && (($game->delivery_mode ?? 'LOCAL') !== 'PROMEX_REMOTE'
                                            || (($game->source_type ?? '') === 'legacy_compat' ? $legacyCdnAvailable : isset($remoteCatalog[$game->name]))))
                                    @if($effective)
                                        <span class="badge badge-success px-2 py-1"><i class="fas fa-check-circle mr-1"></i> ACTIVE</span>
                                    @elseif((int)$game->view === 1)
                                        <span class="badge badge-warning px-2 py-1"><i class="fas fa-ban mr-1"></i> {{ ($game->source_type ?? '') === 'legacy_compat' ? 'PLUGIN BLOCKED' : 'HUB BLOCKED' }}</span>
                                    @else
                                        <span class="badge badge-danger px-2 py-1"><i class="fas fa-ban mr-1"></i> DISABLED</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <div class="btn-group btn-group-sm">
                                        <!-- Quick Toggle View Button -->
                                        <button type="button" class="btn btn-sm {{ (int)$game->view === 1 ? 'btn-outline-danger' : 'btn-outline-success' }} btn-toggle-game" 
                                                data-id="{{ $game->id }}" 
                                                title="{{ (int)$game->view === 1 ? 'Disable this game' : 'Enable this game' }}">
                                            @if((int)$game->view === 1)
                                                <i class="fas fa-eye-slash mr-1"></i> Disable
                                            @else
                                                <i class="fas fa-eye mr-1"></i> Enable
                                            @endif
                                        </button>

                                        <!-- Edit Limits Modal Button -->
                                        <button type="button" class="btn btn-sm btn-outline-secondary btn-edit-limits"
                                                data-id="{{ $game->id }}"
                                                data-title="{{ $game->title }}"
                                                data-bet="{{ $game->bet }}"
                                                data-denomination="{{ $game->denomination }}"
                                                title="Edit Bet & Denomination Limits">
                                            <i class="fas fa-sliders-h"></i>
                                        </button>

                                        <!-- Edit Source Modal Button -->
                                        <button type="button" class="btn btn-sm btn-outline-info btn-edit-source"
                                                data-id="{{ $game->id }}"
                                                data-title="{{ $game->title }}"
                                                data-source="{{ $game->source_type ?? 'default' }}"
                                                data-path="{{ $game->custom_path ?? '' }}"
                                                title="{{ ($game->source_type ?? '') === 'legacy_compat' ? 'Managed by Legacy Compatibility' : 'Configure Game Source / Host Location' }}"
                                                {{ ($game->source_type ?? '') === 'legacy_compat' ? 'disabled' : '' }}>
                                            <i class="fas fa-network-wired"></i>
                                        </button>
                                        @if(in_array(($game->source_type ?? ''), ['cedar_game', 'legacy_compat'], true))
                                            <button type="button" class="btn btn-sm btn-outline-primary btn-delivery-toggle"
                                                    data-id="{{ $game->id }}"
                                                    data-mode="{{ ($game->delivery_mode ?? 'LOCAL') === 'PROMEX_REMOTE' ? 'LOCAL' : 'PROMEX_REMOTE' }}"
                                                    title="Switch between local and licensed Promex CDN delivery">
                                                <i class="fas {{ ($game->delivery_mode ?? 'LOCAL') === 'PROMEX_REMOTE' ? 'fa-server' : 'fa-cloud' }}"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-4 text-muted">
                                    <i class="fas fa-search fa-2x mb-2 d-block text-secondary"></i>
                                    No games found matching the criteria.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </form>

        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap">
            <div class="text-muted small">
                Showing {{ $games->firstItem() ?? 0 }} to {{ $games->lastItem() ?? 0 }} of {{ $games->total() }} games
            </div>
            <div>
                {{ $games->links() }}
            </div>
        </div>
    </div>
</div>

<!-- Edit Limits Modal -->
<div class="modal fade" id="editLimitsModal" tabindex="-1" role="dialog" aria-labelledby="editLimitsModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="edit-limits-form" method="post" action="">
                @csrf
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title font-weight-bold" id="editLimitsModalLabel">
                        <i class="fas fa-sliders-h mr-2"></i> Edit Game Limits & Parameters
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label class="font-weight-bold">Game Title</label>
                        <input type="text" id="modal-game-title" class="form-control" readonly>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold" for="modal-game-bet">Bet Steps / Denominations</label>
                        <input type="text" name="bet" id="modal-game-bet" class="form-control" placeholder="e.g. 0.01, 0.02, 0.05, 0.10, 0.20, 0.50, 1.00" required>
                        <small class="form-text text-muted">Comma-separated bet amounts available to player spins.</small>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold" for="modal-game-denom">Game Denomination</label>
                        <input type="number" step="0.01" name="denomination" id="modal-game-denom" class="form-control" placeholder="e.g. 1.00" required>
                        <small class="form-text text-muted">Base coin denomination multiplier for this game.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary font-weight-bold">
                        <i class="fas fa-save mr-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Source Modal -->
<div class="modal fade" id="editSourceModal" tabindex="-1" role="dialog" aria-labelledby="editSourceModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="edit-source-form" method="post" action="">
                @csrf
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title font-weight-bold" id="editSourceModalLabel">
                        <i class="fas fa-network-wired mr-2 text-info"></i> Configure Game Source
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label class="font-weight-bold">Game Title</label>
                        <input type="text" id="modal-source-title" class="form-control" readonly>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold" for="modal-source-type">Source / Hosting Method</label>
                        <select name="source_type" id="modal-source-type" class="form-control" required>
                            <option value="default">Standard Core (Local Blade / Legacy Files)</option>
                            <option value="custom_folder">Custom Directory (Subfolder in /public or /storage)</option>
                            <option value="external_url">External Hosted URL / Reverse Proxy / Iframe</option>
                            <option value="cedar_game">CEDAR Remake (validated /CedarGames manifest)</option>
                        </select>
                        <small class="form-text text-muted">Select how this game launcher is hosted and delivered to players.</small>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold" for="modal-source-path">Custom Path or External Launch URL</label>
                        <input type="text" name="custom_path" id="modal-source-path" class="form-control" placeholder="e.g. https://provider.com/launcher?token=xyz or /custom_games/my_slot/index.html">
                        <small class="form-text text-muted">
                            Leave empty for <code>default</code>. For <code>custom_folder</code>, enter relative web path. For <code>external_url</code>, enter full HTTPS launch URL.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary font-weight-bold">
                        <i class="fas fa-save mr-1"></i> Save Source
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Import isolated CEDAR remakes -->
<div class="modal fade" id="importCedarGamesModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document"><div class="modal-content">
        <form method="post" action="{{ route('liteback.games.import_cedar') }}" enctype="multipart/form-data">
            @csrf
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title"><i class="fas fa-tree mr-2"></i>Import CEDAR Remakes</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">Each name must match a folder under <code>/CedarGames</code> containing a valid <code>game.json</code>. Existing legacy games are never overwritten.</p>
                <div class="form-group">
                    <label class="font-weight-bold">Folder names</label>
                    <textarea name="names" rows="4" class="form-control" placeholder="CedarHercules, CedarAnotherGame"></textarea>
                    <small class="form-text text-muted">Separate names with commas, spaces, or new lines.</small>
                </div>
                <div class="form-group">
                    <label class="font-weight-bold">Or upload CSV</label>
                    <input type="file" name="csv" class="form-control-file" accept=".csv,.txt">
                    <small class="form-text text-muted">Use <code>name</code> as the optional first-column header.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-dark">Validate & Import</button>
            </div>
        </form>
    </div></div>
</div>

<!-- Add Manual Game Modal -->
<div class="modal fade" id="addManualGameModal" tabindex="-1" role="dialog" aria-labelledby="addManualGameModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form method="post" action="{{ route('liteback.games.store_manual') }}">
                @csrf
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title font-weight-bold" id="addManualGameModalLabel">
                        <i class="fas fa-plus-circle mr-2"></i> Register New Custom / Manual Game
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">Game Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" placeholder="e.g. Sweet Bonanza Deluxe" required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">System Identifier (Slug / Code) <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. SweetBonanzaDeluxe" required>
                            <small class="form-text text-muted">Unique alphanumeric identifier without spaces.</small>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">Game Provider / Category <span class="text-danger">*</span></label>
                            <select name="category_id" class="form-control" required>
                                @foreach($providers as $p)
                                    <option value="{{ $p->id }}">{{ $p->title }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">Source Type <span class="text-danger">*</span></label>
                            <select name="source_type" class="form-control" required>
                                <option value="external_url">External Hosted URL (Iframe / Reverse Proxy)</option>
                                <option value="custom_folder">Custom Folder (/custom_games/...)</option>
                                <option value="default">Standard Core Game</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="font-weight-bold">Launch Path / URL</label>
                        <input type="text" name="custom_path" class="form-control" placeholder="https://external-provider.com/game?session=123 or /custom_games/my_slot/index.html">
                        <small class="form-text text-muted">Enter full HTTPS URL or local web path where player will be directed.</small>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">Bet Steps</label>
                            <input type="text" name="bet" class="form-control" value="0.01, 0.02, 0.05, 0.10, 0.20, 0.50, 1.00">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="font-weight-bold">Denomination Multiplier</label>
                            <input type="number" step="0.01" name="denomination" class="form-control" value="1.00">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success font-weight-bold">
                        <i class="fas fa-plus mr-1"></i> Register & Activate Game
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Check all checkbox logic
    const checkAll = document.getElementById('check-all');
    const checkboxes = document.querySelectorAll('.game-checkbox');
    const bulkBtns = document.querySelectorAll('.bulk-btn');
    const counter = document.getElementById('selected-counter');

    function updateBulkState() {
        const checkedCount = document.querySelectorAll('.game-checkbox:checked').length;
        counter.innerText = `${checkedCount} Selected`;
        bulkBtns.forEach(btn => btn.disabled = checkedCount === 0);
    }

    if (checkAll) {
        checkAll.addEventListener('change', function() {
            checkboxes.forEach(cb => cb.checked = checkAll.checked);
            updateBulkState();
        });
    }

    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateBulkState);
    });

    // 1-Click Toggle Game View via AJAX or Form
    document.querySelectorAll('.btn-toggle-game').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const gameId = this.dataset.id;
            const originalHtml = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            fetch(`/liteback/games/${gameId}/toggle-view`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Error: ' + data.message);
                    this.disabled = false;
                    this.innerHTML = originalHtml;
                }
            })
            .catch(err => {
                // Fallback to reload
                window.location.reload();
            });
        });
    });

    // Edit Limits Modal Trigger
    const editModal = $('#editLimitsModal');
    const editForm = document.getElementById('edit-limits-form');
    document.querySelectorAll('.btn-edit-limits').forEach(btn => {
        btn.addEventListener('click', function() {
            const gameId = this.dataset.id;
            const title = this.dataset.title;
            const bet = this.dataset.bet;
            const denom = this.dataset.denomination;

            document.getElementById('modal-game-title').value = title;
            document.getElementById('modal-game-bet').value = bet || '0.01, 0.02, 0.05, 0.10, 0.20';
            document.getElementById('modal-game-denom').value = denom || '1.00';
            editForm.action = `/liteback/games/${gameId}/update-params`;

            editModal.modal('show');
        });
    });

    // Edit Source Modal Trigger
    const editSourceModal = $('#editSourceModal');
    const editSourceForm = document.getElementById('edit-source-form');
    document.querySelectorAll('.btn-edit-source').forEach(btn => {
        btn.addEventListener('click', function() {
            const gameId = this.dataset.id;
            const title = this.dataset.title;
            const source = this.dataset.source || 'default';
            const path = this.dataset.path || '';

            document.getElementById('modal-source-title').value = title;
            document.getElementById('modal-source-type').value = source;
            document.getElementById('modal-source-path').value = path;
            editSourceForm.action = `/liteback/games/${gameId}/update-source`;

            editSourceModal.modal('show');
        });
    });

    document.querySelectorAll('.btn-delivery-toggle').forEach(btn => {
        btn.addEventListener('click', function() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `/liteback/games/${this.dataset.id}/delivery`;
            form.innerHTML = `@csrf<input type="hidden" name="delivery_mode" value="${this.dataset.mode}">`;
            document.body.appendChild(form);
            form.submit();
        });
    });
});
</script>
@endsection
