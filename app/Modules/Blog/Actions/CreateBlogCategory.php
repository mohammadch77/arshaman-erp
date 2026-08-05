<?php

namespace App\Modules\Blog\Actions;

use App\Modules\Blog\Models\BlogCategory;
use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateBlogCategory
{
    /**
     * @param  array{owner_company_id: string, name: string, slug: string, description: ?string}  $data
     */
    public function handle(array $data, User $actor): BlogCategory
    {
        Gate::forUser($actor)->authorize('create', [BlogCategory::class, $data['owner_company_id']]);

        return DB::transaction(function () use ($data, $actor) {
            return BlogCategory::create([
                ...$data,
                'created_by_user_id' => $actor->id,
                'updated_by_user_id' => $actor->id,
            ]);
        });
    }
}
