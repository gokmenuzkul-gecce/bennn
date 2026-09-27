@extends('liteback.layout')

@section('title', 'Liteback - ' . $guide['title'])
@section('page_title', $guide['title'])

@section('content')
<div dir="{{ $rtl ? 'rtl' : 'ltr' }}">
    <div class="card border-0 mb-4" style="background:linear-gradient(135deg,#172554,#111827 55%,#073c3c);">
        <div class="card-body p-4 p-md-5">
            <div class="d-flex align-items-start">
                <div class="rounded-circle bg-info d-flex align-items-center justify-content-center mr-3 flex-shrink-0" style="width:48px;height:48px;"><i class="fas fa-question text-white"></i></div>
                <div>
                    <div class="text-info text-xs font-weight-bold text-uppercase mb-1" style="letter-spacing:.14em">Operator console</div>
                    <h2 class="h3 font-weight-bold mb-2">{{ $guide['title'] }}</h2>
                    <p class="text-muted mb-0">{{ $guide['intro'] }}</p>
                </div>
            </div>
        </div>
    </div>

    <nav class="nav nav-pills flex-nowrap overflow-auto mb-4" dir="ltr" aria-label="Help languages">
        @foreach($languages as $code => $label)
            <a class="nav-link mr-2 {{ $locale === $code ? 'active' : '' }}" href="{{ route('liteback.help', $code) }}">{{ $label }}</a>
        @endforeach
    </nav>

    <div class="row">
        @foreach($guide['sections'] as $section)
            <div class="col-lg-6 mb-3">
                <div class="card h-100 mb-0">
                    <div class="card-body d-flex">
                        <div class="text-info mr-3" style="font-size:22px;width:24px;"><i class="fas fa-{{ $section['icon'] }}"></i></div>
                        <div>
                            <h3 class="h6 font-weight-bold mb-2">{{ $section['title'] }}</h3>
                            <p class="text-muted small mb-0" style="line-height:1.6">{{ $section['body'] }}</p>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
