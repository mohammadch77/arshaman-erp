<?php

namespace App\Modules\Blog\Actions;

use App\Modules\Blog\Models\BlogTag;
use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateBlogTag
{
    /**
     * @param  array{owner_company_id: string, name: string, slug: string}  $data
     */
    public function handle(array $data, User $actor): BlogTag
    {
        Gate::forUser($actor)->authorize('create', [BlogTag::class, $data['owner_company_id']]);

        return DB::transaction(function () use ($data, $actor) {
            return BlogTag::create([
                ...$data,
                'created_by_user_id' => $actor->id,
                'updated_by_user_id' => $actor->id,
            ]);
        });
    }
}
