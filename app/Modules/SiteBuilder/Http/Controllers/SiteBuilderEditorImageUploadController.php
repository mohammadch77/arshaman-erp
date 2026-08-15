<?php

namespace App\Modules\SiteBuilder\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SiteBuilder\Models\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * آپلود تصویر داخل ویجت text_editor (Quill) — مسیر جدا از
 * Blog\Http\Controllers\EditorImageUploadController عمداً، چون آن یکی
 * BlogPostPolicy::create را authorize می‌کند (ماژول متفاوت؛ بند ۴ CLAUDE.md:
 * ماژول‌ها فقط از طریق Action/Event با هم حرف می‌زنند، نه اشتراک مستقیم route).
 */
class SiteBuilderEditorImageUploadController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        Gate::authorize('create', Page::class);

        $request->validate([
            'image' => ['required', 'image', 'max:2048'],
        ]);

        $path = $request->file('image')->store('sitebuilder/images', 'public');

        return response()->json([
            'success' => 1,
            'file' => [
                'url' => Storage::url($path),
            ],
        ]);
    }
}
