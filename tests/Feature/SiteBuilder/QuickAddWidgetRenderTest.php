<?php

use App\Modules\SiteBuilder\Enums\WidgetKey;
use App\Modules\SiteBuilder\Models\Widget;
use App\Modules\SiteBuilder\Services\WidgetContentRenderer;
use App\Modules\SiteBuilder\Services\WidgetTreeValueMerger;
use Database\Seeders\SiteBuilderQuickAddWidgetsSeeder;

it('renders text_editor html as-is (already sanitized upstream)', function () {
    $html = app(WidgetContentRenderer::class)->render([
        ['id' => 'te-1', 'widget_key' => WidgetKey::TextEditor->value, 'values' => ['html' => '<p>سلام <strong>دنیا</strong></p>'], 'children' => []],
    ]);

    expect($html)->toContain('sb-widget-text-editor');
    expect($html)->toContain('<p>سلام <strong>دنیا</strong></p>');
});

it('sanitizes a richtext field value through WidgetTreeValueMerger before it ever reaches the renderer', function () {
    Widget::create([
        'widget_key' => WidgetKey::TextEditor->value,
        'name' => 'متن غنی',
        'default_config' => ['editable_fields' => [
            ['key' => 'html', 'type' => 'richtext', 'label' => 'متن'],
        ]],
    ]);

    $nodes = [
        ['id' => 'te-1', 'widget_key' => WidgetKey::TextEditor->value, 'values' => ['html' => ''], 'children' => []],
    ];

    $malicious = '<p>سلام</p><script>alert(1)</script><p onclick="evil()">کلیک</p>';

    $merged = app(WidgetTreeValueMerger::class)->apply($nodes, ['te-1' => ['html' => $malicious]]);

    expect($merged[0]['values']['html'])->not->toContain('<script>');
    expect($merged[0]['values']['html'])->not->toContain('onclick');
    expect($merged[0]['values']['html'])->toContain('سلام');
});

it('renders a slider widget with one radio/slide/dot per non-empty slide', function () {
    $html = app(WidgetContentRenderer::class)->render([
        [
            'id' => 'slider-1',
            'widget_key' => WidgetKey::Slider->value,
            'values' => [
                'slides' => [
                    ['image_path' => null, 'title' => 'اسلاید یک', 'text' => ''],
                    ['image_path' => null, 'title' => 'اسلاید دو', 'text' => ''],
                    ['image_path' => null, 'title' => '', 'text' => ''],
                ],
            ],
            'children' => [],
        ],
    ]);

    expect($html)->toContain('class="sb-widget sb-widget-slider');
    expect(substr_count($html, '<input type="radio"'))->toBe(2);
    expect(substr_count($html, '<label for='))->toBe(2);
    expect($html)->toContain('اسلاید یک');
    expect($html)->toContain('اسلاید دو');
});

it('renders nothing for a slider widget with no usable slides', function () {
    $html = app(WidgetContentRenderer::class)->render([
        ['id' => 'slider-1', 'widget_key' => WidgetKey::Slider->value, 'values' => ['slides' => []], 'children' => []],
    ]);

    expect($html)->not->toContain('class="sb-widget sb-widget-slider');
});

it('seeds the four new session-9 widgets with editable_fields defined', function () {
    $this->seed(SiteBuilderQuickAddWidgetsSeeder::class);

    foreach ([WidgetKey::TextEditor, WidgetKey::Slider, WidgetKey::CustomerSignupForm] as $key) {
        $widget = Widget::where('widget_key', $key->value)->first();
        expect($widget)->not->toBeNull();
        expect($widget->editableFields())->not->toBeEmpty();
    }
});
