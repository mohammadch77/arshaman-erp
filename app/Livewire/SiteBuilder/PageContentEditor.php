<?php

namespace App\Livewire\SiteBuilder;

use App\Modules\SiteBuilder\Actions\UpdatePageWidgetValues;
use App\Modules\SiteBuilder\Enums\PageStatus;
use App\Modules\SiteBuilder\Enums\WidgetKey;
use App\Modules\SiteBuilder\Models\Page;
use App\Modules\SiteBuilder\Models\Widget;
use App\Modules\SiteBuilder\Policies\PagePolicy;
use App\Modules\SiteBuilder\Services\WidgetContentRenderer;
use App\Modules\SiteBuilder\Services\WidgetTreeReorderer;
use App\Modules\SiteBuilder\Services\WidgetTreeValueMerger;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

class PageContentEditor extends Component
{
    use Toast, WithFileUploads;

    public Page $record;

    /**
     * کپی در-حافظه widget_tree صفحه — ترتیب/محل نودها ممکن است با
     * moveWidgetNode() (drag-and-drop) از ساختار ذخیره‌شده در دیتابیس جلوتر
     * باشد؛ رکورد در دیتابیس تا لحظه‌ی save() دست‌نخورده می‌ماند. مطلقاً
     * همان نقشی که workingWidgetTree در PageCreateFlow دارد.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $widgetTree = [];

    /**
     * نگاشت widget instance id → [field key => مقدار]، از روی widgetTree
     * فعلی پر می‌شود. جابه‌جایی ساختاری (drag-and-drop) این نگاشت را دست
     * نمی‌زند — چون کلید هر مقدار id خودِ نود است، نه محل آن در درخت.
     *
     * @var array<string, array<string, mixed>>
     */
    public array $fieldValues = [];

    /**
     * درخت تودرتوی فایل‌های آپلودی، دقیقاً هم‌ساختار با fieldValues (هم برای
     * فیلد تصویر ساده [nodeId][fieldKey] هم برای زیرفیلد تصویر داخل یک
     * repeater [nodeId][fieldKey][rowIndex][subKey]).
     *
     * @var array<string, mixed>
     */
    public array $imageUploads = [];

    /**
     * نسخه نمایشی (چندخطی، رشته‌ای) فیلدهای نوع 'lines' برای بایند به
     * textarea — چون fieldValues واقعی برای این نوع آرایه‌ای از رشته‌هاست و
     * textarea نمی‌تواند مستقیم به آرایه بایند شود. فقط موقع save() به آرایه
     * تبدیل و در fieldValues نوشته می‌شود.
     *
     * @var array<string, array<string, string>>
     */
    public array $linesRaw = [];

    public string $extra_css = '';

    public string $extra_js = '';

    public string $page_status = '';

    /**
     * HTML/CSS پیش‌نمایش زنده — همیشه از روی fieldValues فعلی فرم (نه رکورد
     * ذخیره‌شده در دیتابیس) با refreshPreview() ساخته می‌شود. ذخیره واقعی
     * صفحه هرگز از این دو property نمی‌آید.
     */
    public string $previewHtml = '';

    public string $previewCss = '';

    public function mount(string $page): void
    {
        $this->record = Page::with('demo.category')->findOrFail($page);
        $this->authorize('view', $this->record);

        $this->widgetTree = $this->record->widget_tree;

        $this->extra_css = (string) ($this->record->extra_css ?? '');
        $this->extra_js = (string) ($this->record->extra_js ?? '');
        $this->page_status = $this->record->page_status->value;

        foreach ($this->editableNodes() as $node) {
            $this->fieldValues[$node['id']] = $node['values'];

            foreach ($node['fields'] as $field) {
                if ($field['type'] === 'lines') {
                    $lines = $node['values'][$field['key']] ?? [];
                    $this->linesRaw[$node['id']][$field['key']] = is_array($lines) ? implode("\n", $lines) : '';

                    continue;
                }

                // اگر دمو کلید این فیلد را اصلاً نداشته باشد (مثلاً یک فیلد
                // تصویر اختیاری مثل customer_photo)، مسیرش هرگز در fieldValues
                // ساخته نمی‌شود و @entangle داخل <x-file> کامپوننت Mary UI با
                // خطا مواجه می‌شود چون هیچ property ای در آن مسیر پیدا نمی‌کند.
                // پس هر فیلد تعریف‌شده صریحاً یک مقدار پیش‌فرض می‌گیرد.
                if (! array_key_exists($field['key'], $this->fieldValues[$node['id']])) {
                    $this->fieldValues[$node['id']][$field['key']] = $field['type'] === 'repeater' ? [] : ($field['default'] ?? null);
                }

                // <x-file> همیشه wire:model را روی imageUploads (نه fieldValues)
                // می‌بندد — همان مشکل بالا، برای مسیر دیگری.
                if ($field['type'] === 'image' && ! array_key_exists($field['key'], $this->imageUploads[$node['id']] ?? [])) {
                    $this->imageUploads[$node['id']][$field['key']] = null;
                }
            }
        }

        $this->refreshPreview();
    }

    /**
     * یک ردیف خالی به انتهای یک فیلد repeater اضافه می‌کند — کلیدهای ردیف از
     * روی item_fields همان فیلد ساخته می‌شوند. زیرفیلدهای نوع image علاوه بر
     * fieldValues، مسیر imageUploads خودشان را هم از پیش می‌سازند — دقیقاً
     * همان دلیل مقداردهی پیش‌فرض در mount() برای فیلد تصویر top-level: بدون
     * آن، @entangle داخل <x-file> بلافاصله بعد از رندر ردیف جدید خطا می‌دهد.
     *
     * @param  array<int, array{key: string, type: string}>  $itemFields
     */
    public function addRepeaterRow(string $nodeId, string $fieldKey, array $itemFields): void
    {
        $emptyRow = [];

        foreach ($itemFields as $itemField) {
            $emptyRow[$itemField['key']] = '';

            if (($itemField['type'] ?? null) === 'image') {
                $rowIndex = count($this->fieldValues[$nodeId][$fieldKey] ?? []);
                $this->imageUploads[$nodeId][$fieldKey][$rowIndex][$itemField['key']] = null;
            }
        }

        $this->fieldValues[$nodeId][$fieldKey][] = $emptyRow;

        $this->refreshPreview();
    }

    public function removeRepeaterRow(string $nodeId, string $fieldKey, int $index): void
    {
        unset($this->fieldValues[$nodeId][$fieldKey][$index]);
        $this->fieldValues[$nodeId][$fieldKey] = array_values($this->fieldValues[$nodeId][$fieldKey] ?? []);

        unset($this->imageUploads[$nodeId][$fieldKey][$index]);

        if (isset($this->imageUploads[$nodeId][$fieldKey])) {
            $this->imageUploads[$nodeId][$fieldKey] = array_values($this->imageUploads[$nodeId][$fieldKey]);
        }

        $this->refreshPreview();
    }

    /**
     * پیش‌نمایش زنده را از روی fieldValues *فعلی* فرم بازمی‌سازد — رکورد
     * صفحه در دیتابیس دست‌نخورده می‌ماند. از همان WidgetTreeValueMerger و
     * WidgetContentRenderer سمت سرور استفاده می‌کند که مسیر ذخیره واقعی
     * (UpdatePageWidgetValues) استفاده می‌کند؛ یک منبع واحد یعنی پیش‌نمایش
     * هرگز از رندر نهایی امن (whitelist ویجت + escape) جدا نمی‌افتد.
     *
     * از سمت کلاینت با یک تایمر debounce واحد صدا زده می‌شود (نه چند
     * wire:model.live مستقل روی هر فیلد) — دقیقاً همان الگوی
     * scheduleAutosave در BlogPostForm که جلوی race چند تایمر همزمان را
     * می‌گیرد.
     */
    public function refreshPreview(): void
    {
        $merger = app(WidgetTreeValueMerger::class);
        $renderer = app(WidgetContentRenderer::class);

        $previewTree = $merger->apply($this->widgetTree, $this->fieldValuesForPreview());

        $this->previewHtml = $renderer->render($previewTree);
        $this->previewCss = $this->extra_css;
    }

    /**
     * جابه‌جایی یک نود در widgetTree (drag-and-drop) — فقط در حافظه، رکورد
     * دیتابیس دست‌نخورده می‌ماند تا save(). همان authorize صریح مسیر ذخیره
     * (PagePolicy::update — operator فقط روی draft) اینجا هم رعایت می‌شود
     * چون جابه‌جایی ساختاری یک ویرایش محتوایی است، نه فقط نمایشی (بند ۹
     * CLAUDE.md — authorize داخل خودِ مسیر تغییر، نه فقط UI). محفظه‌ای‌که
     * $targetParentId=null باشد یعنی سطح بالای صفحه.
     */
    public function moveWidgetNode(string $draggedId, ?string $targetParentId, int $targetIndex): void
    {
        if (! app(PagePolicy::class)->update(auth()->user(), $this->record)) {
            $this->error('اجازه جابه‌جایی ویجت‌ها را ندارید.');

            return;
        }

        $theme = $this->widgetTree['theme'] ?? null;
        $nodes = $this->widgetTree;
        unset($nodes['theme']);
        $nodes = array_values($nodes);

        try {
            $reordered = app(WidgetTreeReorderer::class)->move($nodes, $draggedId, $targetParentId, $targetIndex);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return;
        }

        $this->widgetTree = $theme !== null ? ['theme' => $theme] + $reordered : $reordered;

        $this->refreshPreview();
    }

    /**
     * مثل fieldValuesWithLinesResolved()، به‌علاوه جایگزینی هر آپلود تازه‌ (که
     * هنوز روی دیسک ذخیره نشده) با temporaryUrl() آن — فقط برای پیش‌نمایش.
     * بدون این، تصویری که همین الان انتخاب شده ولی هنوز save() نشده هرگز در
     * iframe دیده نمی‌شد، چون refreshPreview() فقط widget_tree ذخیره‌شده را
     * می‌خواند، نه imageUploads.
     *
     * @return array<string, array<string, mixed>>
     */
    private function fieldValuesForPreview(): array
    {
        $fieldValues = $this->fieldValuesWithLinesResolved();
        $this->mergeTemporaryImageUrls($this->imageUploads, $fieldValues);

        return $fieldValues;
    }

    /**
     * @param  array<array-key, mixed>  $uploads
     * @param  array<array-key, mixed>  $target
     */
    private function mergeTemporaryImageUrls(array $uploads, array &$target): void
    {
        foreach ($uploads as $key => $value) {
            if ($value instanceof TemporaryUploadedFile) {
                $target[$key] = $value->temporaryUrl();

                continue;
            }

            if (is_array($value)) {
                if (! isset($target[$key]) || ! is_array($target[$key])) {
                    $target[$key] = [];
                }

                $this->mergeTemporaryImageUrls($value, $target[$key]);
            }
        }
    }

    /**
     * سند HTML کامل پیش‌نمایش برای srcdoc یک iframe مجزا — تا استایل‌های
     * extra_css صفحه با استایل خودِ پنل ادمین تداخل نکند. </style> داخل
     * extra_css خنثی می‌شود تا محتوای CSS نتواند زودتر از انتظار از تگ
     * <style> خارج شود.
     */
    public function getPreviewDocumentProperty(): string
    {
        $safeCss = str_ireplace('</style', '<\\/style', $this->previewCss);

        return '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8">'
            .'<style>body{margin:0;padding:1rem;font-family:inherit;}'.$safeCss.'</style>'
            .'</head><body>'.$this->previewHtml.'</body></html>';
    }

    /**
     * fieldValues فعلی فرم به‌علاوه تبدیل نوع 'lines' (linesRaw چندخطی →
     * آرایه) — هم save() هم refreshPreview() از همین متد استفاده می‌کنند تا
     * قانون تبدیل هرگز بین ذخیره و پیش‌نمایش جدا نیفتد.
     *
     * @return array<string, array<string, mixed>>
     */
    private function fieldValuesWithLinesResolved(): array
    {
        $fieldValues = $this->fieldValues;

        foreach ($this->editableNodes() as $node) {
            foreach ($node['fields'] as $field) {
                if ($field['type'] === 'lines') {
                    $raw = (string) ($this->linesRaw[$node['id']][$field['key']] ?? '');
                    $lines = array_values(array_filter(array_map('trim', explode("\n", $raw)), fn ($line) => $line !== ''));
                    $fieldValues[$node['id']][$field['key']] = $lines;
                }
            }
        }

        return $fieldValues;
    }

    /**
     * لیست تخت نودهایی که حداقل یک فیلد قابل‌ویرایش دارند، با تعریف فیلدها
     * از روی Widget::editableFields(). ترتیب و تودرتویی درخت اصلی حفظ نمی‌شود
     * چون فقط برای رندر فرم لازم است، نه ذخیره.
     */
    public function getEditableNodesProperty(): array
    {
        return $this->editableNodes();
    }

    protected function editableNodes(): array
    {
        $widgetsByKey = Widget::query()->get()->keyBy('widget_key');
        $result = [];

        $walk = function (array $nodes) use (&$walk, $widgetsByKey, &$result): void {
            foreach ($nodes as $node) {
                if (! is_array($node) || ! isset($node['widget_key'])) {
                    // کلید ریشه 'theme' یک نود واقعی نیست، رد می‌شود.
                    continue;
                }

                $widget = $widgetsByKey->get($node['widget_key']);
                $fields = $widget?->editableFields() ?? [];

                if (! empty($fields)) {
                    $result[] = [
                        'id' => $node['id'],
                        // instance_label تشخیص هر نمونه از یک نوع ویجت را ممکن می‌کند —
                        // مثلاً دو ویجت 'title' با برچسب «عنوان اصلی» و «عنوان بخش داستان».
                        // اگر دمو (قدیمی‌تر) این کلید را نداشت، به نام عمومی نوع ویجت برمی‌گردیم.
                        'section_label' => $node['instance_label'] ?? $widget->name,
                        'fields' => $fields,
                        'values' => $node['values'] ?? [],
                    ];
                }

                if (! empty($node['children'])) {
                    $walk($node['children']);
                }
            }
        };

        $walk($this->widgetTree);

        return $result;
    }

    /**
     * ساختار کامل و تودرتوی widgetTree برای رندر درخت درگ‌اند‌دراپ در UI —
     * برخلاف editableNodes() (که فقط نودهای دارای فیلد را مسطح برمی‌گرداند)،
     * این متد *همه* نودها (از جمله محفظه‌های بدون فیلد) را با همان تودرتویی
     * اصلی نگه می‌دارد، چون خودِ ساختار محفظه‌ها است که باید در UI دیده و
     * drop-target شود.
     */
    public function getWidgetTreeUiProperty(): array
    {
        return $this->buildTreeUi($this->widgetTree);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildTreeUi(array $nodes): array
    {
        $widgetsByKey = Widget::query()->get()->keyBy('widget_key');

        $map = function (array $nodes) use (&$map, $widgetsByKey): array {
            $result = [];

            foreach ($nodes as $node) {
                if (! is_array($node) || ! isset($node['widget_key'])) {
                    // کلید ریشه 'theme' یک نود واقعی نیست، رد می‌شود.
                    continue;
                }

                $widget = $widgetsByKey->get($node['widget_key']);

                $result[] = [
                    'id' => $node['id'],
                    'widget_key' => $node['widget_key'],
                    'is_container' => $node['widget_key'] === WidgetKey::Container->value,
                    'section_label' => $node['instance_label'] ?? ($widget->name ?? $node['widget_key']),
                    'fields' => $widget?->editableFields() ?? [],
                    'values' => $node['values'] ?? [],
                    'children' => $map($node['children'] ?? []),
                ];
            }

            return $result;
        };

        return $map($nodes);
    }

    public function getCanPublishProperty(): bool
    {
        return app(PagePolicy::class)->canPublish(auth()->user(), $this->record->owner_company_id);
    }

    /**
     * فیلدهای ویجت فقط زمانی قابل‌ویرایش‌اند که PagePolicy::update() اجازه دهد
     * (holding_admin همیشه، operator فقط روی draft) — extra_css/extra_js از این
     * قید مستقل‌اند و همیشه فعال می‌مانند (طبق canEditExtraCode).
     */
    public function getCanEditWidgetValuesProperty(): bool
    {
        return app(PagePolicy::class)->update(auth()->user(), $this->record);
    }

    protected function rules(): array
    {
        return [
            'extra_css' => ['nullable', 'string'],
            'extra_js' => ['nullable', 'string'],
            'page_status' => ['required', Rule::in(array_map(fn ($case) => $case->value, PageStatus::cases()))],
        ];
    }

    public function save(UpdatePageWidgetValues $action): void
    {
        $data = $this->validate();

        // اگر operator اجازه ویرایش مقادیر ویجت را ندارد (صفحه published)،
        // چیزی از این مسیر ارسال نمی‌شود — فقط extra_css/extra_js مستقل ذخیره می‌شود.
        $fieldValues = $this->canEditWidgetValues ? $this->fieldValuesWithLinesResolved() : [];

        if ($this->canEditWidgetValues) {
            $this->mergeUploadedFiles($this->imageUploads, $fieldValues);
        }

        $action->handle(
            $this->record,
            $fieldValues,
            auth()->user(),
            $data['extra_css'] !== '' ? $data['extra_css'] : null,
            $data['extra_js'] !== '' ? $data['extra_js'] : null,
            PageStatus::from($data['page_status']),
            // اگر operator اجازه ویرایش/جابه‌جایی ویجت‌ها را ندارد، ساختار
            // ارسال نمی‌شود — moveWidgetNode() هم خودش از قبل چنین کاربری
            // را رد کرده بود، پس widgetTree اینجا با نسخه دیتابیس یکی است؛
            // ارسال‌نکردنش از این authorize('update') اضافه (که مسیر
            // extra_css-فقط operator را می‌شکست) جلوگیری می‌کند.
            $this->canEditWidgetValues ? $this->widgetTree : null,
        );

        $this->success('محتوای صفحه ذخیره شد.', redirectTo: route('sitebuilder.pages.index'));
    }

    /**
     * درخت uploads را (هر عمقی — تصویر تک یا تصویر داخل یک ردیف repeater)
     * پیمایش می‌کند و هر فایل واقعی آپلودشده را ذخیره و مسیرش را در همان
     * مسیر (path) داخل $target می‌نویسد؛ non-file/null نادیده گرفته می‌شود.
     *
     * @param  array<array-key, mixed>  $uploads
     * @param  array<array-key, mixed>  $target
     */
    private function mergeUploadedFiles(array $uploads, array &$target): void
    {
        foreach ($uploads as $key => $value) {
            if ($value instanceof TemporaryUploadedFile) {
                $target[$key] = $value->store('sitebuilder/images', 'public');

                continue;
            }

            if (is_array($value)) {
                if (! isset($target[$key]) || ! is_array($target[$key])) {
                    $target[$key] = [];
                }

                $this->mergeUploadedFiles($value, $target[$key]);
            }
        }
    }

    public function render()
    {
        return view('livewire.site-builder.page-content-editor');
    }
}
