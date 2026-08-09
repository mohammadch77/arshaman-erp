<?php

namespace App\Modules\Blog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Blog\Models\BlogCategory;
use App\Modules\Blog\Models\BlogPost;
use App\Modules\Core\Models\Company;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * صفحات عمومی وبلاگ برای بازدیدکننده مهمان — بدون middleware auth. برخلاف
 * BelongsToCompany که به CompanyContext session تکیه دارد (که برای مهمان
 * بی‌معناست)، ایزولاسیون شرکت اینجا صریح و دستی است: هر کوئری
 * withoutGlobalScope('owner_company') می‌شود (نه withoutGlobalScopes تا SoftDeletes بماند) و owner_company_id از companySlug مسیر گرفته
 * می‌شود، نه از session.
 */
class PublicBlogController extends Controller
{
    public function index(string $companySlug, Request $request): View
    {
        $company = Company::where('slug', $companySlug)->firstOrFail();

        $categories = BlogCategory::withoutGlobalScope('owner_company')
            ->where('owner_company_id', $company->id)
            ->orderBy('name')
            ->get();

        $activeCategory = null;

        if ($request->filled('category')) {
            $activeCategory = $categories->firstWhere('slug', $request->query('category'));
        }

        $posts = BlogPost::withoutGlobalScope('owner_company')
            ->where('owner_company_id', $company->id)
            ->published()
            ->when($activeCategory, fn ($query) => $query->where('category_id', $activeCategory->id))
            ->with(['category', 'author'])
            ->latest('published_at')
            ->paginate(10)
            ->withQueryString();

        return view('public.blog.index', [
            'company' => $company,
            'posts' => $posts,
            'categories' => $categories,
            'activeCategory' => $activeCategory,
        ]);
    }

    public function show(string $companySlug, string $postSlug): View
    {
        $company = Company::where('slug', $companySlug)->firstOrFail();

        $post = BlogPost::withoutGlobalScope('owner_company')
            ->where('owner_company_id', $company->id)
            ->where('slug', $postSlug)
            ->published()
            ->with(['category', 'tags', 'author'])
            ->firstOrFail();

        $relatedPosts = BlogPost::withoutGlobalScope('owner_company')
            ->where('owner_company_id', $company->id)
            ->where('id', '!=', $post->id)
            ->when($post->category_id, fn ($query) => $query->where('category_id', $post->category_id), fn ($query) => $query->whereRaw('1 = 0'))
            ->published()
            ->latest('published_at')
            ->limit(3)
            ->get();

        return view('public.blog.show', [
            'company' => $company,
            'post' => $post,
            'relatedPosts' => $relatedPosts,
        ]);
    }
}
