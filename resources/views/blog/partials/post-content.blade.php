{{--
    محتوای مشترک نمایش یک پست — هم در صفحه عمومی وبلاگ (public/blog/show.blade.php)
    هم در پیش‌نمایش داخلی (blog/preview.blade.php، مسیر blog.posts.preview) استفاده
    می‌شود. فقط به $post نیاز دارد.
--}}
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
