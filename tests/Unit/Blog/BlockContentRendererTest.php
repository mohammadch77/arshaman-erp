<?php

use App\Modules\Blog\Services\BlockContentRenderer;

function blockRenderer(): BlockContentRenderer
{
    return new BlockContentRenderer;
}

it('renders a paragraph block', function () {
    $html = blockRenderer()->render([
        ['type' => 'paragraph', 'data' => ['text' => 'سلام دنیا']],
    ]);

    expect($html)->toBe('<p>سلام دنیا</p>');
});

it('renders a header block with clamped level', function () {
    $html = blockRenderer()->render([
        ['type' => 'header', 'data' => ['text' => 'عنوان', 'level' => 1]],
    ]);

    expect($html)->toBe('<h2>عنوان</h2>');

    $html = blockRenderer()->render([
        ['type' => 'header', 'data' => ['text' => 'عنوان', 'level' => 9]],
    ]);

    expect($html)->toBe('<h6>عنوان</h6>');
});

it('renders an unordered and ordered list block', function () {
    $unordered = blockRenderer()->render([
        ['type' => 'list', 'data' => ['style' => 'unordered', 'items' => ['یک', 'دو']]],
    ]);
    expect($unordered)->toBe('<ul><li>یک</li><li>دو</li></ul>');

    $ordered = blockRenderer()->render([
        ['type' => 'list', 'data' => ['style' => 'ordered', 'items' => ['یک', 'دو']]],
    ]);
    expect($ordered)->toBe('<ol><li>یک</li><li>دو</li></ol>');
});

it('renders a list block using the nested @editorjs/list item format', function () {
    $html = blockRenderer()->render([
        ['type' => 'list', 'data' => ['style' => 'unordered', 'items' => [
            ['content' => 'یک', 'items' => [], 'meta' => []],
            ['content' => 'دو', 'items' => [], 'meta' => []],
        ]]],
    ]);

    expect($html)->toBe('<ul><li>یک</li><li>دو</li></ul>');
});

it('renders a quote block with optional caption', function () {
    $html = blockRenderer()->render([
        ['type' => 'quote', 'data' => ['text' => 'نقل قول', 'caption' => 'نویسنده']],
    ]);

    expect($html)->toBe('<blockquote><p>نقل قول</p><figcaption>نویسنده</figcaption></blockquote>');
});

it('renders an image block with escaped url and caption', function () {
    $html = blockRenderer()->render([
        ['type' => 'image', 'data' => ['file' => ['url' => 'https://example.com/a.png'], 'caption' => 'تصویر']],
    ]);

    expect($html)->toBe('<figure><img src="https://example.com/a.png" alt="تصویر">'.'<figcaption>تصویر</figcaption></figure>');
});

it('escapes a script injection attempt inside a paragraph', function () {
    $html = blockRenderer()->render([
        ['type' => 'paragraph', 'data' => ['text' => 'قبل<script>alert(1)</script>بعد']],
    ]);

    expect($html)->not->toContain('<script')
        ->and($html)->toBe('<p>قبلalert(1)بعد</p>');
});

it('silently skips an unknown block type without throwing', function () {
    $html = blockRenderer()->render([
        ['type' => 'paragraph', 'data' => ['text' => 'اول']],
        ['type' => 'embed', 'data' => ['source' => 'https://youtube.com/x']],
        ['type' => 'paragraph', 'data' => ['text' => 'دوم']],
    ]);

    expect($html)->toBe("<p>اول</p>\n<p>دوم</p>");
});

it('silently skips an image block without a url', function () {
    $html = blockRenderer()->render([
        ['type' => 'image', 'data' => []],
    ]);

    expect($html)->toBe('');
});

it('preserves whitelisted inline tags but strips their attributes', function () {
    $html = blockRenderer()->render([
        ['type' => 'paragraph', 'data' => ['text' => '<b onclick="evil()">پررنگ</b>']],
    ]);

    expect($html)->toBe('<p><b>پررنگ</b></p>');
});
