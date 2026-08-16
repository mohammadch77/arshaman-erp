<?php

namespace App\Livewire\SiteBuilder;

use App\Modules\Core\Services\CompanyContext;
use App\Modules\SiteBuilder\Actions\CreatePageFromDemo;
use App\Modules\SiteBuilder\Enums\WidgetKey;
use App\Modules\SiteBuilder\Models\Page;
use App\Modules\SiteBuilder\Models\PageCategory;
use App\Modules\SiteBuilder\Models\PageDemo;
use App\Modules\SiteBuilder\Models\Widget;
use App\Modules\SiteBuilder\Services\WidgetContentRenderer;
use App\Modules\SiteBuilder\Services\WidgetTreeReorderer;
use App\Modules\SiteBuilder\Services\WidgetTreeValueMerger;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

/**
 * جایگزین PageDemoGallery — جریان انتخاب دمو و ویرایش محتوا را در یک صفحه
 * واحد ادغام می‌کند. تا لحظه‌ی ذخیره صریح («ذخیره و انتشار پیش‌نویس») هیچ
 * رکورد pages ساخته نمی‌شود؛ همه‌چیز (انتخاب دمو، مقادیر فیلدها، پیش‌نمایش)
 * روی workingWidgetTree در حافظه کار می‌کند. PageContentEditor (ویرایش صفحه
 * از‌قبل‌موجود) کاملاً مستقل و دست‌نخورده می‌ماند.
 */
class PageCreateFlow extends Component
{
    use Toast, WithFileUploads;

    public ?string $selectedDemoId = null;

    /**
     * کپی در-حافظه widget_tree دموی انتخاب‌شده. هرگز مستقیم در دیتابیس
     * نوشته نمی‌شود — فقط ورودی WidgetTreeValueMerger برای پیش‌نمایش و،
     * در لحظه‌ی ذخیره نهایی، ورودی CreatePageFromDemo.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $workingWidgetTree = [];

    /** @var array<string, array<string, mixed>> */
    public array $fieldValues = [];

    /** @var array<string, mixed> */
    public array $imageUploads = [];

    /** @var array<string, array<string, string>> */
    public array $linesRaw = [];

    public string $title = '';

    public string $slug = '';

    public bool $slugManuallyEdited = false;

    public string $meta_title = '';

    public string $meta_description = '';

    public string $previewHtml = '';

    /**
     * محفظه‌ی فعلاً «انتخاب‌شده به‌عنوان مقصد» برای پنل افزودن ویجت — دقیقاً
     * همان مفهوم PageContentEditor::$activeContainerId.
     */
    public ?string $activeContainerId = null;

    public function mount(): void
    {
        $this->authorize('create', Page::class);
    }

    public function getCategoriesProperty()
    {
        return PageCategory::with(['demos' => fn ($query) => $query->where('is_active', true)])
            ->orderBy('name')
            ->get()
            ->filter(fn (PageCategory $category) => $category->demos->isNotEmpty());
    }

    /**
     * انتخاب یک دمو (یا انتخاب دوباره‌ی دموی دیگر) — همیشه از صفر: کل
     * workingWidgetTree/fieldValues/imageUploads/linesRaw بازنشانی می‌شوند تا
     * هیچ مقدار باقی‌مانده از دموی قبلی سرریز نکند.
     */
    public function selectDemo(string $demoId): void
    {
        $demo = PageDemo::findOrFail($demoId);

        $this->selectedDemoId = $demoId;
        $this->workingWidgetTree = $demo->widget_tree;
        $this->fieldValues = [];
        $this->imageUploads = [];
        $this->linesRaw = [];
        $this->activeContainerId = null;

        $this->seedFieldValues();
        $this->refreshPreview();
    }

    /**
     * بازگشت به حالت انتخاب دمو — بدون این، کاربر راهی برای عوض‌کردن دمو
     * جز رفرش کامل صفحه نداشت.
     */
    public function backToDemoSelection(): void
    {
        $this->selectedDemoId = null;
        $this->workingWidgetTree = [];
        $this->fieldValues = [];
        $this->imageUploads = [];
        $this->linesRaw = [];
        $this->previewHtml = '';
        $this->activeContainerId = null;
    }

    /**
     * دقیقاً همان منطق PageContentEditor::mount() برای مقداردهی اولیه
     * fieldValues/imageUploads/linesRaw — با این تفاوت که منبع workingWidgetTree
     * در حافظه است، نه record ذخیره‌شده.
     */
    private function seedFieldValues(): void
    {
        foreach ($this->editableNodes() as $node) {
            $this->fieldValues[$node['id']] = $node['values'];

            foreach ($node['fields'] as $field) {
                if ($field['type'] === 'lines') {
                    $lines = $node['values'][$field['key']] ?? [];
                    $this->linesRaw[$node['id']][$field['key']] = is_array($lines) ? implode("\n", $lines) : '';

                    continue;
                }

                if (! array_key_exists($field['key'], $this->fieldValues[$node['id']])) {
                    $this->fieldValues[$node['id']][$field['key']] = $field['type'] === 'repeater' ? [] : ($field['default'] ?? null);
                }

                if ($field['type'] === 'image' && ! array_key_exists($field['key'], $this->imageUploads[$node['id']] ?? [])) {
                    $this->imageUploads[$node['id']][$field['key']] = null;
                }
            }
        }
    }

    /**
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
     * پیش‌نمایش زنده از روی workingWidgetTree (در حافظه) + fieldValues فعلی —
     * دقیقاً همان WidgetTreeValueMerger/WidgetContentRenderer مسیر ذخیره واقعی،
     * تا هرگز escape/whitelist پیش‌نمایش از رندر نهایی جدا نیفتد.
     */
    public function refreshPreview(): void
    {
        if ($this->selectedDemoId === null) {
            $this->previewHtml = '';

            return;
        }

        $merger = app(WidgetTreeValueMerger::class);
        $renderer = app(WidgetContentRenderer::class);

        $previewTree = $merger->apply($this->workingWidgetTree, $this->fieldValuesForPreview());

        $this->previewHtml = $renderer->render($previewTree);
    }

    /**
     * جابه‌جایی یک نود در workingWidgetTree (drag-and-drop) — فقط در حافظه،
     * دقیقاً مثل PageContentEditor::moveWidgetNode() ولی چون هیچ رکورد
     * pages ای تا create() ساخته نشده، نیازی به authorize جدا نیست: مجوز
     * ساخت صفحه از قبل در mount() احراز شده و کل جریان مختص همان کاربر است.
     */
    public function moveWidgetNode(string $draggedId, ?string $targetParentId, int $targetIndex): void
    {
        if ($this->selectedDemoId === null) {
            return;
        }

        $theme = $this->workingWidgetTree['theme'] ?? null;
        $nodes = $this->workingWidgetTree;
        unset($nodes['theme']);
        $nodes = array_values($nodes);

        try {
            $reordered = app(WidgetTreeReorderer::class)->move($nodes, $draggedId, $targetParentId, $targetIndex);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return;
        }

        $this->workingWidgetTree = $theme !== null ? ['theme' => $theme] + $reordered : $reordered;

        $this->refreshPreview();
    }

    /**
     * حذف یک نود از workingWidgetTree — دقیقاً همان PageContentEditor::deleteWidget()
     * ولی بدون authorize جدا (همان استدلال moveWidgetNode/addWidget بالا: هیچ
     * رکورد pages ای هنوز وجود ندارد، مجوز ساخت صفحه از قبل در mount() احراز شده).
     */
    public function deleteWidget(string $nodeId): void
    {
        if ($this->selectedDemoId === null) {
            return;
        }

        $theme = $this->workingWidgetTree['theme'] ?? null;
        $nodes = $this->workingWidgetTree;
        unset($nodes['theme']);
        $nodes = array_values($nodes);

        try {
            $updated = app(WidgetTreeReorderer::class)->remove($nodes, $nodeId);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return;
        }

        $this->workingWidgetTree = $theme !== null ? ['theme' => $theme] + $updated : $updated;

        if ($this->activeContainerId === $nodeId) {
            $this->activeContainerId = null;
        }

        $this->refreshPreview();
    }

    public function setActiveContainer(?string $nodeId): void
    {
        if ($nodeId === null) {
            $this->activeContainerId = null;

            return;
        }

        $node = $this->findNodeById($this->widgetTreeWithoutTheme(), $nodeId);

        if ($node !== null && ($node['widget_key'] ?? null) === WidgetKey::Container->value) {
            $this->activeContainerId = $nodeId;
        }
    }

    /**
     * افزودن یک نود کاملاً تازه — دقیقاً همان PageContentEditor::addWidget()
     * ولی بدون authorize جدا (همان استدلال moveWidgetNode بالا: مجوز ساخت
     * صفحه از قبل در mount() احراز شده، هیچ رکورد pages ای هنوز وجود ندارد).
     */
    public function addWidget(string $widgetKey): void
    {
        if ($this->selectedDemoId === null) {
            return;
        }

        $widget = Widget::where('widget_key', $widgetKey)->first();

        if ($widget === null) {
            $this->error('این ویجت در کاتالوگ پیدا نشد.');

            return;
        }

        $fields = $widget->editableFields();
        $newNode = [
            'id' => (string) Str::uuid(),
            'widget_key' => $widgetKey,
            'instance_label' => $widget->name.' جدید',
            'values' => $this->defaultValuesForFields($fields),
            'children' => [],
        ];

        $theme = $this->workingWidgetTree['theme'] ?? null;
        $nodes = $this->workingWidgetTree;
        unset($nodes['theme']);
        $nodes = array_values($nodes);

        try {
            $updated = app(WidgetTreeReorderer::class)->addNode($nodes, $this->activeContainerId, $newNode);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return;
        }

        $this->workingWidgetTree = $theme !== null ? ['theme' => $theme] + $updated : $updated;

        $this->fieldValues[$newNode['id']] = $newNode['values'];

        foreach ($fields as $field) {
            if ($field['type'] === 'lines') {
                $this->linesRaw[$newNode['id']][$field['key']] = '';

                continue;
            }

            if ($field['type'] === 'image') {
                $this->imageUploads[$newNode['id']][$field['key']] = null;
            }
        }

        $this->refreshPreview();
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<string, mixed>
     */
    private function defaultValuesForFields(array $fields): array
    {
        $values = [];

        foreach ($fields as $field) {
            $values[$field['key']] = $field['type'] === 'repeater' ? [] : ($field['default'] ?? null);
        }

        return $values;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function widgetTreeWithoutTheme(): array
    {
        $nodes = $this->workingWidgetTree;
        unset($nodes['theme']);

        return array_values($nodes);
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<string, mixed>|null
     */
    private function findNodeById(array $nodes, string $id): ?array
    {
        foreach ($nodes as $node) {
            if (! is_array($node) || ! isset($node['id'])) {
                continue;
            }

            if ($node['id'] === $id) {
                return $node;
            }

            if (! empty($node['children'])) {
                $found = $this->findNodeById($node['children'], $id);

                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    public function getQuickAddWidgetsProperty()
    {
        $keys = config('sitebuilder.quick_add_widgets', []);

        return Widget::whereIn('widget_key', $keys)->get()->sortBy(fn ($widget) => array_search($widget->widget_key, $keys, true))->values();
    }

    public function getActiveContainerLabelProperty(): ?string
    {
        if ($this->activeContainerId === null) {
            return null;
        }

        $node = $this->findNodeById($this->widgetTreeWithoutTheme(), $this->activeContainerId);

        return $node['instance_label'] ?? null;
    }

    /**
     * ساختار کامل و تودرتوی workingWidgetTree برای رندر درخت درگ‌اند‌دراپ —
     * نگاه کن PageContentEditor::getWidgetTreeUiProperty() برای دلیل کامل.
     */
    public function getWidgetTreeUiProperty(): array
    {
        return $this->buildTreeUi($this->workingWidgetTree);
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

    public function getPreviewDocumentProperty(): string
    {
        return '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8">'
            .'<style>body{margin:0;padding:1rem;}</style>'
            .'</head><body>'.$this->previewHtml.'</body></html>';
    }

    /**
     * fieldValues فعلی + جایگزینی هر آپلود تازه (هنوز ذخیره‌نشده روی دیسک) با
     * temporaryUrl() آن — فقط برای پیش‌نمایش، نه ذخیره نهایی.
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

    public function getEditableNodesProperty(): array
    {
        return $this->editableNodes();
    }

    protected function editableNodes(): array
    {
        if ($this->selectedDemoId === null) {
            return [];
        }

        $widgetsByKey = Widget::query()->get()->keyBy('widget_key');

        $flatten = function (array $nodes) use (&$flatten, $widgetsByKey): array {
            $result = [];

            foreach ($nodes as $node) {
                // کلید ریشه 'theme' (نگاه کن WidgetContentRenderer) یک نود واقعی نیست، رد می‌شود.
                if (! is_array($node) || ! isset($node['widget_key'])) {
                    continue;
                }

                $widget = $widgetsByKey->get($node['widget_key']);
                $fields = $widget?->editableFields() ?? [];

                if (! empty($fields)) {
                    $result[] = [
                        'id' => $node['id'],
                        'section_label' => $node['instance_label'] ?? $widget->name,
                        'fields' => $fields,
                        'values' => $node['values'] ?? [],
                    ];
                }

                if (! empty($node['children'])) {
                    $result = [...$result, ...$flatten($node['children'])];
                }
            }

            return $result;
        };

        return $flatten($this->workingWidgetTree);
    }

    public function updatedTitle(): void
    {
        if (! $this->slugManuallyEdited) {
            $this->slug = $this->generateSlug($this->title);
        }
    }

    public function updatedSlug(): void
    {
        $this->slugManuallyEdited = true;
    }

    protected function generateSlug(string $source): string
    {
        $slug = Str::slug($source);

        return $slug !== '' ? $slug : Str::slug(Str::random(8));
    }

    protected function rules(): array
    {
        $companyId = app(CompanyContext::class)->id();

        return [
            'selectedDemoId' => ['required', 'uuid', 'exists:page_demos,id'],
            'title' => ['required', 'string', 'max:150'],
            'slug' => [
                'required', 'string', 'max:150', 'alpha_dash',
                Rule::unique('pages', 'slug')->where('owner_company_id', $companyId),
            ],
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:160'],
        ];
    }

    /**
     * تنها لحظه‌ای که رکورد pages واقعی ساخته می‌شود — با workingWidgetTree
     * ویرایش‌شده (نه widget_tree خام دمو).
     */
    public function create(CreatePageFromDemo $action, CompanyContext $companyContext): void
    {
        $data = $this->validate();

        $fieldValues = $this->fieldValuesWithLinesResolved();
        $this->mergeUploadedFiles($this->imageUploads, $fieldValues);

        $finalTree = app(WidgetTreeValueMerger::class)->apply($this->workingWidgetTree, $fieldValues);

        $action->handle([
            'owner_company_id' => $companyContext->id(),
            'page_demo_id' => $data['selectedDemoId'],
            'title' => $data['title'],
            'slug' => $data['slug'],
            'meta_title' => $data['meta_title'] !== '' ? $data['meta_title'] : null,
            'meta_description' => $data['meta_description'] !== '' ? $data['meta_description'] : null,
        ], auth()->user(), $finalTree);

        $this->success('صفحه ساخته شد.', redirectTo: route('sitebuilder.pages.index'));
    }

    /**
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
        return view('livewire.site-builder.page-create-flow');
    }
}
