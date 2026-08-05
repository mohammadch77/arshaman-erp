<?php

namespace App\Modules\Blog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Blog\Models\BlogPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class EditorImageUploadController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        Gate::authorize('create', BlogPost::class);

        $request->validate([
            'image' => ['required', 'image', 'max:2048'],
        ]);

        $path = $request->file('image')->store('blog/content-images', 'public');

        return response()->json([
            'success' => 1,
            'file' => [
                'url' => Storage::url($path),
            ],
        ]);
    }
}
