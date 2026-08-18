<?php

namespace App\Modules\Process\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * منبع واحد آپلود فیلدهای نوع 'file' در فرم‌های فرایند (فرم درخواست آزاد،
 * فرم اضافه‌ی مرحله) — همان الگوی مفهومی آپلود عکس ماژول SiteBuilder
 * (Storage::put روی دیسک public، مسیر root-relative برای نمایش)، فقط اینجا
 * نوع فایل هم PDF/تصویر مجاز است. هر دو Livewire component (NewProcessRequest،
 * MyProcessTasks، MyProcessRequests) از همین یک متد store() استفاده می‌کنند
 * تا قاعده‌ی نوع/حجم مجاز یک‌جا (config/processes.php) تعریف بماند.
 */
class ProcessFileUploader
{
    /**
     * @throws ValidationException
     */
    public static function store(UploadedFile|TemporaryUploadedFile $file): string
    {
        $config = config('processes.file_upload');

        Validator::make(
            ['file' => $file],
            ['file' => [
                'file',
                'mimes:'.implode(',', $config['allowed_extensions']),
                'max:'.$config['max_kilobytes'],
            ]],
            [
                'file.mimes' => 'نوع فایل مجاز نیست — فرمت‌های مجاز: '.implode('، ', $config['allowed_extensions']).'.',
                'file.max' => 'حجم فایل نباید بیشتر از '.round($config['max_kilobytes'] / 1024, 1).' مگابایت باشد.',
            ]
        )->validate();

        return $file->store($config['path'], $config['disk']);
    }

    /**
     * مسیر ذخیره‌شده (root-relative روی دیسک public) را به src قابل‌رندر/دانلود
     * تبدیل می‌کند — دقیقاً همان منطق App\Modules\SiteBuilder\Support\StorageUrl،
     * تکرارشده اینجا چون ماژول‌ها هرگز مستقیم به هم وابسته نیستند (بند ۴ CLAUDE.md).
     */
    public static function url(string $path): string
    {
        if ($path === '') {
            return '';
        }

        if (preg_match('#^(https?://|//|/)#i', $path) === 1) {
            return $path;
        }

        return '/storage/'.ltrim($path, '/');
    }

    public static function originalNameFromPath(string $path): string
    {
        return basename($path);
    }
}
