<?php

namespace App\Modules\Blog\Actions;

use App\Modules\Blog\Models\BlogTag;
use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UpdateBlogTag
{
    /**
     * @param  array{name: string, slug: string}  $data
     */
    public function handle(BlogTag $tag, array $data, User $actor): BlogTag
    {
        Gate::forUser($actor)->authorize('update', $tag);

        DB::transaction(function () use ($tag, $data, $actor) {
            $tag->update([
                ...$data,
                'updated_by_user_id' => $actor->id,
            ]);
        });

        return $tag->refresh();
    }
}
