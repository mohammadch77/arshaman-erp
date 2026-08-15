<?php

use App\Modules\SiteBuilder\Services\WidgetTreeReorderer;

function wtrTree(): array
{
    return [
        [
            'id' => 'title-1',
            'widget_key' => 'title',
            'values' => ['text' => 'عنوان'],
            'children' => [],
        ],
        [
            'id' => 'container-a',
            'widget_key' => 'container',
            'values' => [],
            'children' => [
                ['id' => 'title-a1', 'widget_key' => 'title', 'values' => [], 'children' => []],
                ['id' => 'title-a2', 'widget_key' => 'title', 'values' => [], 'children' => []],
            ],
        ],
        [
            'id' => 'container-b',
            'widget_key' => 'container',
            'values' => [],
            'children' => [
                ['id' => 'title-b1', 'widget_key' => 'title', 'values' => [], 'children' => []],
            ],
        ],
    ];
}

it('reorders siblings within the same container', function () {
    $reordered = app(WidgetTreeReorderer::class)->move(wtrTree(), 'title-a2', 'container-a', 0);

    expect(array_column($reordered[1]['children'], 'id'))->toBe(['title-a2', 'title-a1']);
});

it('moves a node from one container to another', function () {
    $reordered = app(WidgetTreeReorderer::class)->move(wtrTree(), 'title-a1', 'container-b', 1);

    expect(array_column($reordered[1]['children'], 'id'))->toBe(['title-a2']);
    expect(array_column($reordered[2]['children'], 'id'))->toBe(['title-b1', 'title-a1']);
});

it('moves a node from a container up to the root level', function () {
    $reordered = app(WidgetTreeReorderer::class)->move(wtrTree(), 'title-a1', null, 0);

    expect(array_column($reordered, 'id'))->toBe(['title-a1', 'title-1', 'container-a', 'container-b']);
    expect(array_column($reordered[2]['children'], 'id'))->toBe(['title-a2']);
});

it('moves a root-level node down into a container', function () {
    $reordered = app(WidgetTreeReorderer::class)->move(wtrTree(), 'title-1', 'container-a', 2);

    expect(array_column($reordered, 'id'))->toBe(['container-a', 'container-b']);
    expect(array_column($reordered[0]['children'], 'id'))->toBe(['title-a1', 'title-a2', 'title-1']);
});

it('moves a whole container (with its children) to the root level', function () {
    $reordered = app(WidgetTreeReorderer::class)->move(wtrTree(), 'container-a', null, 0);

    expect(array_column($reordered, 'id'))->toBe(['container-a', 'title-1', 'container-b']);
    expect(array_column($reordered[0]['children'], 'id'))->toBe(['title-a1', 'title-a2']);
});

it('rejects dropping a container inside itself', function () {
    app(WidgetTreeReorderer::class)->move(wtrTree(), 'container-a', 'container-a', 0);
})->throws(InvalidArgumentException::class);

it('rejects dropping a container inside one of its own descendants', function () {
    $tree = wtrTree();
    // container-a گیرد یک محفظه‌ی تودرتو به‌عنوان فرزند
    $tree[1]['children'][] = [
        'id' => 'container-a-nested',
        'widget_key' => 'container',
        'values' => [],
        'children' => [],
    ];

    app(WidgetTreeReorderer::class)->move($tree, 'container-a', 'container-a-nested', 0);
})->throws(InvalidArgumentException::class);

it('rejects dropping into a widget that is not a container', function () {
    app(WidgetTreeReorderer::class)->move(wtrTree(), 'title-a1', 'title-1', 0);
})->throws(InvalidArgumentException::class);

it('rejects moving a node id that does not exist in the tree', function () {
    app(WidgetTreeReorderer::class)->move(wtrTree(), 'not-a-real-id', null, 0);
})->throws(InvalidArgumentException::class);

it('rejects a target container that does not exist', function () {
    app(WidgetTreeReorderer::class)->move(wtrTree(), 'title-1', 'not-a-real-container', 0);
})->throws(InvalidArgumentException::class);

it('clamps an out-of-range target index instead of failing', function () {
    $reordered = app(WidgetTreeReorderer::class)->move(wtrTree(), 'title-1', null, 999);

    expect(array_column($reordered, 'id'))->toBe(['container-a', 'container-b', 'title-1']);
});

it('appends a brand new node to the root level', function () {
    $newNode = ['id' => 'brand-new', 'widget_key' => 'button', 'values' => [], 'children' => []];

    $updated = app(WidgetTreeReorderer::class)->addNode(wtrTree(), null, $newNode);

    expect(array_column($updated, 'id'))->toBe(['title-1', 'container-a', 'container-b', 'brand-new']);
});

it('appends a brand new node inside a container', function () {
    $newNode = ['id' => 'brand-new', 'widget_key' => 'button', 'values' => [], 'children' => []];

    $updated = app(WidgetTreeReorderer::class)->addNode(wtrTree(), 'container-a', $newNode);

    expect(array_column($updated[1]['children'], 'id'))->toBe(['title-a1', 'title-a2', 'brand-new']);
});

it('rejects adding a new node into a target that is not a container', function () {
    $newNode = ['id' => 'brand-new', 'widget_key' => 'button', 'values' => [], 'children' => []];

    app(WidgetTreeReorderer::class)->addNode(wtrTree(), 'title-1', $newNode);
})->throws(InvalidArgumentException::class);

it('rejects adding a new node into a container that does not exist', function () {
    $newNode = ['id' => 'brand-new', 'widget_key' => 'button', 'values' => [], 'children' => []];

    app(WidgetTreeReorderer::class)->addNode(wtrTree(), 'not-a-real-container', $newNode);
})->throws(InvalidArgumentException::class);
