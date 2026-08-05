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

    @include('blog.partials.post-content', ['post' => $post])

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
