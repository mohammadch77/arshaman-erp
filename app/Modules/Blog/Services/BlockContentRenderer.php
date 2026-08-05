<?php

namespace App\Modules\Blog\Services;

class BlockContentRenderer
{
    private const ALLOWED_INLINE_TAGS = '<b><strong><i><em><u>';

    /**
     * @param  array<int, array{type?: string, data?: array}>  $blocks
     */
    public function render(array $blocks): string
    {
        $html = [];

        foreach ($blocks as $block) {
            $type = $block['type'] ?? null;
            $data = $block['data'] ?? [];

            $rendered = match ($type) {
                'paragraph' => $this->renderParagraph($data),
                'header' => $this->renderHeader($data),
                'list' => $this->renderList($data),
                'quote' => $this->renderQuote($data),
                'image' => $this->renderImage($data),
                default => null,
            };

            if ($rendered !== null) {
                $html[] = $rendered;
            }
        }

        return implode("\n", $html);
    }

    private function renderParagraph(array $data): ?string
    {
        if (! isset($data['text']) || ! is_string($data['text'])) {
            return null;
        }

        return '<p>'.$this->sanitizeInline($data['text']).'</p>';
    }

    private function renderHeader(array $data): ?string
    {
        if (! isset($data['text']) || ! is_string($data['text'])) {
            return null;
        }

        $level = max(2, min(6, (int) ($data['level'] ?? 2)));
        $text = $this->sanitizeInline($data['text']);

        return "<h{$level}>{$text}</h{$level}>";
    }

    private function renderList(array $data): ?string
    {
        if (! isset($data['items']) || ! is_array($data['items'])) {
            return null;
        }

        // @editorjs/list (nested-list format) emits each item as {content, items, meta},
        // not a plain string — only the top-level `content` is rendered here; nested
        // sub-items are out of scope for this Session (silent skip, per plan).
        $items = [];
        foreach ($data['items'] as $item) {
            if (is_string($item)) {
                $items[] = $item;
            } elseif (is_array($item) && isset($item['content']) && is_string($item['content'])) {
                $items[] = $item['content'];
            }
        }

        if ($items === []) {
            return null;
        }

        $tag = ($data['style'] ?? 'unordered') === 'ordered' ? 'ol' : 'ul';

        $itemsHtml = implode('', array_map(
            fn (string $item) => '<li>'.$this->sanitizeInline($item).'</li>',
            $items
        ));

        return "<{$tag}>{$itemsHtml}</{$tag}>";
    }

    private function renderQuote(array $data): ?string
    {
        if (! isset($data['text']) || ! is_string($data['text'])) {
            return null;
        }

        $text = $this->sanitizeInline($data['text']);
        $caption = isset($data['caption']) && is_string($data['caption']) && trim($data['caption']) !== ''
            ? '<figcaption>'.$this->sanitizeInline($data['caption']).'</figcaption>'
            : '';

        return "<blockquote><p>{$text}</p>{$caption}</blockquote>";
    }

    private function renderImage(array $data): ?string
    {
        $url = $data['file']['url'] ?? $data['url'] ?? null;

        if (! is_string($url) || $url === '') {
            return null;
        }

        $caption = isset($data['caption']) && is_string($data['caption'])
            ? $this->sanitizeInline($data['caption'])
            : '';

        $captionHtml = $caption !== '' ? "<figcaption>{$caption}</figcaption>" : '';

        return '<figure><img src="'.e($url).'" alt="'.e($caption).'">'.$captionHtml.'</figure>';
    }

    private function sanitizeInline(string $text): string
    {
        $stripped = strip_tags($text, self::ALLOWED_INLINE_TAGS);

        // strip_tags keeps attributes on whitelisted tags (a well-known PHP gotcha) —
        // without this, `<b onclick="evil()">` would pass through untouched.
        return preg_replace('/<(\/?)(b|strong|i|em|u)\b[^>]*>/i', '<$1$2>', $stripped);
    }
}
