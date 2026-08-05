<?php

namespace App\Modules\Blog\Actions;

use App\Modules\Blog\Models\BlogPost;
use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\Gate;

class DeleteBlogPost
{
    public function handle(BlogPost $post, User $actor): void
    {
        Gate::forUser($actor)->authorize('delete', $post);

        $post->update(['updated_by_user_id' => $actor->id]);
        $post->delete();
    }
}
