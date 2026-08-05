<?php

namespace App\Modules\Blog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Blog\Models\BlogPost;
use App\Modules\Core\Models\Company;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * پیش‌نمایش داخلی — برخلاف PublicBlogController::show، محدودیت published()
 * (وضعیت/published_at) ندارد؛ دقیقاً همان چیزی که آدرس مهمان نشان می‌دهد را
 * برای هر پستی، صرف‌نظر از post_status، به کاربر مجاز نشان می‌دهد.
 * findOrFail عمداً global scope مدل (BelongsToCompany) را حفظ می‌کند — مثل
 * BlogPostForm::mount، فقط پست شرکت فعال سوییچر قابل پیش‌نمایش است.
 */
class BlogPostPreviewController extends Controller
{
    public function __invoke(string $post): View
    {
        $blogPost = BlogPost::with(['category', 'tags', 'author'])->findOrFail($post);

        Gate::authorize('view', $blogPost);

        $company = Company::findOrFail($blogPost->owner_company_id);

        return view('blog.preview', [
            'company' => $company,
            'post' => $blogPost,
        ]);
    }
}
