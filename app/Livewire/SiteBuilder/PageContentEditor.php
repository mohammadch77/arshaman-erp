<?php

namespace App\Livewire\SiteBuilder;

use App\Modules\SiteBuilder\Actions\UpdatePageWidgetValues;
use App\Modules\SiteBuilder\Enums\PageStatus;
use App\Modules\SiteBuilder\Models\Page;
use App\Modules\SiteBuilder\Models\Widget;
use App\Modules\SiteBuilder\Policies\PagePolicy;
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
     * نگاشت widget instance id → [field key => مقدار]، از روی widget_tree
     * فعلی صفحه پر می‌شود. ساختار درخت اینجا نگهداری نمی‌شود، فقط مقادیر.
     *
     * @var array<string, array<string, mixed>>
     */
    public array $fieldValues = [];

    /**
     * @var array<string, array<string, TemporaryUploadedFile>>
     */
    public array $imageUploads = [];

    public string $extra_css = '';

    public string $extra_js = '';

    public string $page_status = '';

    public function mount(string $page): void
    {
        $this->record = Page::with('demo.category')->findOrFail($page);
        $this->authorize('view', $this->record);

        $this->extra_css = (string) ($this->record->extra_css ?? '');
        $this->extra_js = (string) ($this->record->extra_js ?? '');
        $this->page_status = $this->record->page_status->value;

        foreach ($this->editableNodes() as $node) {
            $this->fieldValues[$node['id']] = $node['values'];
        }
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

        $flatten = function (array $nodes) use (&$flatten, $widgetsByKey): array {
            $result = [];

            foreach ($nodes as $node) {
                $widget = $widgetsByKey->get($node['widget_key'] ?? '');
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
                    $result = [...$result, ...$flatten($node['children'])];
                }
            }

            return $result;
        };

        return $flatten($this->record->widget_tree);
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
        $fieldValues = $this->canEditWidgetValues ? $this->fieldValues : [];

        if ($this->canEditWidgetValues) {
            foreach ($this->imageUploads as $nodeId => $fields) {
                foreach ($fields as $fieldKey => $file) {
                    if ($file) {
                        $fieldValues[$nodeId][$fieldKey] = $file->store('sitebuilder/images', 'public');
                    }
                }
            }
        }

        $action->handle(
            $this->record,
            $fieldValues,
            auth()->user(),
            $data['extra_css'] !== '' ? $data['extra_css'] : null,
            $data['extra_js'] !== '' ? $data['extra_js'] : null,
            PageStatus::from($data['page_status']),
        );

        $this->success('محتوای صفحه ذخیره شد.', redirectTo: route('sitebuilder.pages.index'));
    }

    public function render()
    {
        return view('livewire.site-builder.page-content-editor');
    }
}
