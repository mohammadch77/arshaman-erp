@extends('layouts.public')

@section('meta_title', $company->name.' — وبلاگ')
@section('meta_description', 'آخرین مطالب وبلاگ '.$company->name)

@section('content')
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-base-content">وبلاگ {{ $company->name }}</h1>
    </div>

    @if ($categories->isNotEmpty())
        <div class="mb-8 flex flex-wrap gap-2">
            <x-button
                label="همه"
                class="btn-sm {{ $activeCategory ? 'btn-outline' : 'btn-primary' }}"
                link="{{ route('public-blog.index', $company->slug) }}"
            />
            @foreach ($categories as $category)
                <x-button
                    :label="$category->name"
                    class="btn-sm {{ $activeCategory?->id === $category->id ? 'btn-primary' : 'btn-outline' }}"
                    link="{{ route('public-blog.index', [$company->slug, 'category' => $category->slug]) }}"
                />
            @endforeach
        </div>
    @endif

    @if ($posts->isEmpty())
        <x-card shadow>
            <p class="text-center text-base-content/60">هنوز پستی منتشر نشده است.</p>
        </x-card>
    @else
        <div class="grid gap-6 sm:grid-cols-2">
            @foreach ($posts as $post)
                <x-card shadow class="h-full">
                    @if ($post->featured_image_path)
                        <a href="{{ route('public-blog.show', [$company->slug, $post->slug]) }}">
                            <img
                                src="{{ \Illuminate\Support\Facades\Storage::url($post->featured_image_path) }}"
                                alt="{{ $post->title }}"
                                class="mb-4 h-40 w-full rounded-lg object-cover"
                            >
                        </a>
                    @endif

                    @if ($post->category)
                        <x-badge :value="$post->category->name" class="badge-ghost mb-2" />
                    @endif

                    <a href="{{ route('public-blog.show', [$company->slug, $post->slug]) }}" class="link link-hover">
                        <h2 class="text-lg font-bold text-base-content">{{ $post->title }}</h2>
                    </a>

                    <p class="mt-2 text-sm text-base-content/70">
                        {{ \Illuminate\Support\Str::limit(strip_tags($post->content_html ?? ''), 150) }}
                    </p>

                    <div class="mt-4 flex items-center justify-between text-xs text-base-content/50">
                        <span>{{ $post->author?->full_name ?? '—' }}</span>
                        <span>{{ \App\Support\Jalali::toDisplay($post->published_at) }}</span>
                    </div>
                </x-card>
            @endforeach
        </div>

        <div class="mt-8">
            {{ $posts->links() }}
        </div>
    @endif
@endsection
