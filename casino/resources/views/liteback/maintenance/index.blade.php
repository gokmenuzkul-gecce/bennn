@extends('liteback.layout')
@section('page_title', 'Backup & Update')
@section('content')
<div class="container-fluid">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @foreach($errors->all() as $error)<div class="alert alert-danger">{{ $error }}</div>@endforeach
    <div class="card"><div class="card-body">
        <h4>Backup <small class="text-muted">Recommended before updates</small></h4>
        <p>Create a core-code ZIP with a database export. Vendor, legacy games, Cedar assets, uploads and environment secrets are excluded. Keep your environment file and uploads separately. Backup and restoration are your responsibility.</p>
        <form method="POST" action="{{ route('liteback.maintenance.backup') }}">@csrf<button class="btn btn-outline-primary" type="submit">Create backup</button></form>
        @foreach($backups as $backup)<div class="mt-2"><a href="{{ route('liteback.maintenance.backup.download', basename($backup)) }}">Download {{ basename($backup) }}</a> <small>{{ number_format(filesize($backup)/1048576, 1) }} MB</small></div>@endforeach
    </div></div>
    <p>Core version <strong>{{ $version }}</strong>. Updates are applied only when you select and install a patch.</p>
    @if(!$licensed)
        <div class="alert alert-info">This installation is not licensed for PROMEX managed updates. <a href="https://github.com/promexdotme/laravel-social-gaming" target="_blank" rel="noopener noreferrer">Get free GitHub updates</a> or activate your license under Store &amp; License.</div>
    @else
        @if($demo)<div class="alert alert-warning">Local practice mode: only signed practice patches are accepted. Your core version stays unchanged. Disable practice mode before using this installation live.</div>@endif
        <ul class="nav nav-tabs mb-3" role="tablist">
            @foreach(['core' => 'Core Updates', 'features' => 'Features & Cedar', 'legacy' => 'Legacy Games', 'history' => 'Installed Patches'] as $tab => $label)
                <li class="nav-item"><a class="nav-link {{ $loop->first ? 'active' : '' }}" data-toggle="tab" href="#patch-{{ $tab }}">{{ $label }}</a></li>
            @endforeach
        </ul>
        @if($catalogError)<div class="alert alert-warning">{{ $catalogError }}</div>@endif
        <div class="tab-content">
        @foreach(['core', 'features', 'legacy'] as $tab)
            <div class="tab-pane {{ $loop->first ? 'active' : '' }}" id="patch-{{ $tab }}">
                <div class="card"><div class="card-body">
                @if($tab === 'legacy')<p>Download the patch and copy its <code>files/games/GameName</code> contents into your own <code>games/GameName</code> folder. Then verify the replacement here. Games are hosted on your own domain.</p>
                @elseif($tab === 'features')<p>Optional integrations declare their required patches. Cedar game assets stay on the protected CDN.</p>
                @else<p>Install core patches in order. Missing prerequisites and changed local files block installation.</p>@endif
                <a class="btn btn-outline-secondary mb-3" href="{{ route('liteback.maintenance.index', ['check' => 1]) }}">Check available patches</a>
                @php($visible = array_filter($catalog, function ($row) use ($tab) { $c = $row['manifest']['component']; return $tab === 'core' ? in_array($c, ['core', 'demo'], true) : ($tab === 'legacy' ? str_starts_with($c, 'legacy:') : str_starts_with($c, 'feature:') || str_starts_with($c, 'cedar:')); }))
                @forelse($visible as $row)
                    <div class="border rounded p-3 mb-2"><h5>{{ $row['manifest']['title'] }}</h5><p>{{ $row['manifest']['from'] }} → {{ $row['manifest']['to'] }} — {{ $row['manifest']['notes'] }}</p>
                    @if($row['blocked'])<p class="text-warning">{{ $row['blocked'] }}</p>@endif
                    <form method="POST" action="{{ route('liteback.maintenance.fetch') }}">@csrf<input type="hidden" name="slug" value="{{ $row['slug'] }}"><button class="btn btn-primary">Download &amp; review</button></form></div>
                @empty<p class="text-muted">No patches loaded in this tab. Check available patches to fetch updates from the licensed Hub.</p>@endforelse
                </div></div>
            </div>
        @endforeach
        <div class="tab-pane" id="patch-history"><div class="card"><div class="card-body table-responsive">
            <table class="table"><thead><tr><th>Patch</th><th>Version</th><th>Status</th><th>Date</th><th>Result</th></tr></thead><tbody>
            @forelse(array_reverse($state['history']) as $run)<tr><td>{{ $run['id'] }}</td><td>{{ $run['from'] }} → {{ $run['to'] }}</td><td>{{ $run['status'] }}</td><td>{{ $run['at'] }}</td><td>{{ $run['message'] }}</td></tr>
            @empty<tr><td colspan="5">No patches installed. This is the baseline installation.</td></tr>@endforelse
            </tbody></table>
        </div></div></div></div>
        @if($preview)
            @php($m = $preview['manifest'])
            <div class="card border-primary"><div class="card-body"><h4>2. Review: {{ $m['title'] }}</h4>
                <p>{{ $m['component'] }}: {{ $m['from'] }} → {{ $m['to'] }}</p><p>{{ $m['notes'] }}</p>
                <p>{{ count($m['files']) }} files, {{ count($m['delete']) }} removals, {{ count($m['migrations']) }} migrations. Prerequisites: {{ implode(', ', $m['requires']) ?: 'None' }}.</p>
                <details><summary>Files affected</summary><ul>@foreach($m['files'] as $path => $hashes)<li>{{ $hashes['before'] === null ? 'Add' : 'Replace' }} {{ $path }}</li>@endforeach @foreach($m['delete'] as $path => $hash)<li>Remove {{ $path }}</li>@endforeach</ul></details>
                @if($preview['blocked'])<div class="alert alert-warning mt-3">{{ $preview['blocked'] }}</div>@endif
                <p class="mt-3">Backup recommended. Installation does not create a backup or restore changes automatically.</p>
                @if(str_starts_with($m['component'], 'legacy:'))
                    <a class="btn btn-outline-primary" href="{{ route('liteback.maintenance.package') }}">Download reviewed ZIP</a>
                    <form class="mt-2" method="POST" action="{{ route('liteback.maintenance.verify-legacy') }}">@csrf<button class="btn btn-primary">3. Verify my manual replacement</button></form>
                @else
                    <form method="POST" action="{{ route('liteback.maintenance.install') }}">@csrf<button class="btn btn-primary" {{ $preview['blocked'] ? 'disabled' : '' }}>3. Install this patch</button></form>
                @endif
            </div></div>
        @endif
    @endif
</div>
@endsection
