@extends('layouts.public-site')

@php
    $faviconUrl = $siteSetting?->favicon_path
        ? '/storage/'.ltrim($siteSetting->favicon_path, '/')
        : asset('images/theme/favicon.svg');

    $displayTitle = $page->meta_title ?: ($siteSetting?->site_title ?: $company->name);
    $displayDescription = $page->meta_description ?: ($siteSetting?->site_tagline ?: '');
@endphp

@section('meta_title', $displayTitle)
@section('meta_description', $displayDescription)

@if (! empty($page->extra_css))
    @section('extra_css')
{!! $page->extra_css !!}
    @endsection
@endif

@if (! empty($page->extra_js))
    @section('extra_js')
{!! $page->extra_js !!}
    @endsection
@endif

@section('content')
    {!! $headerHtml !!}

    <main>
        {!! $bodyHtml !!}
    </main>

    {!! $footerHtml !!}
@endsection
