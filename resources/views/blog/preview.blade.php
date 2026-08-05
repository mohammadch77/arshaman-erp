@extends('layouts.public')

@section('meta_title', 'پیش‌نمایش — '.$post->title)

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-box border border-warning bg-warning/10 p-4">
        <div class="flex items-center gap-2">
            <x-icon :name="theme_icon('preview')" class="h-5 w-5 text-warning" />
            <span class="text-sm text-base-content">
                این یک پیش‌نمایش داخلی است — <x-badge :value="$post->post_status->label()" class="badge-warning badge-sm" /> — برای بازدیدکننده مهمان قابل مشاهده نیست.
            </span>
        </div>
        <x-button label="بازگشت به فهرست پست‌ها" class="btn-sm btn-ghost" link="{{ route('blog.posts.index') }}" />
    </div>

    @include('blog.partials.post-content', ['post' => $post])
@endsection
