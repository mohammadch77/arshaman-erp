<?php

namespace App\Modules\Blog\Actions;

use App\Modules\Blog\Models\BlogCategory;
use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UpdateBlogCategory
{
    /**
     * @param  array{name: string, slug: string, description: ?string}  $data
     */
    public function handle(BlogCategory $category, array $data, User $actor): BlogCategory
    {
        Gate::forUser($actor)->authorize('update', $category);

        DB::transaction(function () use ($category, $data, $actor) {
            $category->update([
                ...$data,
                'updated_by_user_id' => $actor->id,
            ]);
        });

        return $category->refresh();
    }
}
