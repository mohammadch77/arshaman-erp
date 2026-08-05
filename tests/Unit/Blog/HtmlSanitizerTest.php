<?php

use Mews\Purifier\Facades\Purifier;

it('strips an onerror attribute from an img tag', function () {
    $clean = Purifier::clean('<img src=x onerror=alert(1)>');

    expect($clean)->not->toContain('onerror')
        ->and($clean)->not->toContain('alert(1)');
});

it('removes script tags entirely', function () {
    $clean = Purifier::clean('<p>سلام</p><script>alert(1)</script>');

    expect($clean)->not->toContain('<script')
        ->and($clean)->toContain('سلام');
});

it('neutralizes a javascript: link href', function () {
    $clean = Purifier::clean('<a href="javascript:alert(1)">لینک</a>');

    expect($clean)->not->toContain('javascript:');
});

it('preserves whitelisted tags and safe attributes', function () {
    $clean = Purifier::clean('<h2>تیتر</h2><p><strong>پررنگ</strong> و <a href="https://example.com">لینک</a></p>');

    expect($clean)->toContain('<h2>تیتر</h2>')
        ->and($clean)->toContain('<strong>پررنگ</strong>')
        ->and($clean)->toContain('href="https://example.com"');
});

it('preserves the data-list attribute distinguishing bullet from ordered list items', function () {
    $clean = Purifier::clean('<ol><li data-list="bullet">آیتم بولت</li><li data-list="ordered">آیتم شماره‌دار</li></ol>');

    expect($clean)->toContain('data-list="bullet"')
        ->and($clean)->toContain('data-list="ordered"');
});

it('removes disallowed tags like div and style while keeping the text', function () {
    $clean = Purifier::clean('<div style="color:red">متن</div>');

    expect($clean)->not->toContain('<div')
        ->and($clean)->not->toContain('style=')
        ->and($clean)->toContain('متن');
});
