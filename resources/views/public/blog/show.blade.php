@extends('layouts.public')

@section('meta_title', $post->meta_title ?: $post->title)
@section('meta_description', $post->meta_description ?: \Illuminate\Support\Str::limit(strip_tags($post->content_html ?? ''), 160))
@section('meta_type', 'article')
@if ($post->featured_image_path)
    @section('meta_image', \Illuminate\Support\Facades\Storage::url($post->featured_image_path))
@endif

@section('content')
    <a
        href="{{ route('public-blog.index', $company->slug) }}"
        class="mb-6 inline-flex items-center gap-1 text-sm text-base-content/60 link link-hover"
    >
        <x-icon :name="theme_icon('back')" class="h-4 w-4" />
        بازگشت به وبلاگ
    </a>

    <article>
        @if ($post->featured_image_path)
            <img
                src="{{ \Illuminate\Support\Facades\Storage::url($post->featured_image_path) }}"
                alt="{{ $post->title }}"
                class="mb-6 h-64 w-full rounded-xl object-cover"
            >
        @endif

        @if ($post->category)
            <x-badge :value="$post->category->name" class="badge-ghost mb-3" />
        @endif

        <h1 class="text-3xl font-bold text-base-content">{{ $post->title }}</h1>

        <div class="mt-3 flex flex-wrap items-center gap-4 text-sm text-base-content/60">
            <span>{{ $post->author?->full_name ?? '—' }}</span>
            <span>{{ \App\Support\Jalali::toDisplayDateTime($post->published_at) }}</span>
            <span class="inline-flex items-center gap-1">
                <x-icon :name="theme_icon('reading-time')" class="h-4 w-4" />
                {{ \App\Support\Farsi::toDigits($post->display_reading_time) }} دقیقه مطالعه
            </span>
        </div>

        <div class="prose prose-sm mt-8 max-w-none">
            {!! $post->content_html !!}
        </div>

        @if ($post->tags->isNotEmpty())
            <div class="mt-8 flex flex-wrap gap-2">
                @foreach ($post->tags as $tag)
                    <x-badge :value="$tag->name" class="badge-outline" />
                @endforeach
            </div>
        @endif
    </article>

    @if ($relatedPosts->isNotEmpty())
        <div class="mt-12">
            <h2 class="mb-4 text-lg font-bold text-base-content">مطالب مرتبط</h2>

            <div class="grid gap-6 sm:grid-cols-3">
                @foreach ($relatedPosts as $related)
                    <x-card shadow class="h-full">
                        <a href="{{ route('public-blog.show', [$company->slug, $related->slug]) }}" class="link link-hover">
                            <h3 class="font-bold text-base-content">{{ $related->title }}</h3>
                        </a>
                        <p class="mt-2 text-xs text-base-content/50">
                            {{ \App\Support\Jalali::toDisplay($related->published_at) }}
                        </p>
                    </x-card>
                @endforeach
            </div>
        </div>
    @endif
@endsection
