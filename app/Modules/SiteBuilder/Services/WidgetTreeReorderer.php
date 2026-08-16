<?php

namespace App\Modules\SiteBuilder\Services;

use App\Modules\SiteBuilder\Enums\WidgetKey;
use InvalidArgumentException;

/**
 * جابه‌جایی (drag-and-drop) یک نود داخل widget_tree — بین container ها یا در
 * سطح ریشه. تنها مسیر مجاز تغییر *ساختار* درخت (برخلاف WidgetTreeValueMerger
 * که فقط مقدار داخل نودهای موجود را عوض می‌کند). هرگز نودی اضافه/حذف
 * نمی‌کند، فقط جای یک نود موجود را عوض می‌کند.
 *
 * ورودی/خروجی این سرویس عمداً فقط لیست نودها (بدون کلید 'theme' سطح ریشه)
 * است — caller مسئول جداکردن/چسباندن دوباره 'theme' است، چون این سرویس نباید
 * چیزی درباره آن کلید خاص بداند.
 */
class WidgetTreeReorderer
{
    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<int, array<string, mixed>>
     *
     * @throws InvalidArgumentException اگر نود مبدا پیدا نشود، مقصد یک محفظه نباشد،
     *                                  یا مقصد خودِ نود یا یکی از فرزندانش باشد (حلقه بی‌نهایت).
     */
    public function move(array $nodes, string $draggedId, ?string $targetParentId, int $targetIndex): array
    {
        [$draggedNode, $remaining] = $this->extract($nodes, $draggedId);

        if ($draggedNode === null) {
            throw new InvalidArgumentException('ویجت جابه‌جاشونده در ساختار صفحه پیدا نشد.');
        }

        if ($targetParentId !== null) {
            if ($targetParentId === $draggedId || $this->containsDescendant($draggedNode, $targetParentId)) {
                throw new InvalidArgumentException('یک محفظه نمی‌تواند داخل خودش یا فرزندانش قرار بگیرد.');
            }
        }

        return $this->insert($remaining, $targetParentId, $draggedNode, $targetIndex);
    }

    /**
     * نود با شناسه $id را از هرجای درخت (هر عمقی) پیدا و حذف می‌کند.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array{0: array<string, mixed>|null, 1: array<int, array<string, mixed>>}
     */
    private function extract(array $nodes, string $id): array
    {
        $result = [];
        $found = null;

        foreach ($nodes as $node) {
            if (! is_array($node) || ! isset($node['id'])) {
                // نودهای بی‌شکل (مثلاً هیچ‌وقت اینجا رخ نمی‌دهد چون caller کلید
                // 'theme' را قبلاً جدا کرده) دست‌نخورده رد می‌شوند.
                $result[] = $node;

                continue;
            }

            if ($found === null && $node['id'] === $id) {
                $found = $node;

                continue;
            }

            if (! empty($node['children'])) {
                [$childFound, $remainingChildren] = $this->extract($node['children'], $id);

                if ($childFound !== null) {
                    $found = $childFound;
                    $node['children'] = $remainingChildren;
                }
            }

            $result[] = $node;
        }

        return [$found, $result];
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function containsDescendant(array $node, string $id): bool
    {
        foreach ($node['children'] ?? [] as $child) {
            if (! is_array($child)) {
                continue;
            }

            if (($child['id'] ?? null) === $id) {
                return true;
            }

            if ($this->containsDescendant($child, $id)) {
                return true;
            }
        }

        return false;
    }

    /**
     * افزودن یک نود کاملاً تازه (پنل «افزودن ویجت» با کلیک) — همان منطق insert()
     * موجود را دوباره‌استفاده می‌کند، فقط همیشه در انتهای مقصد (ریشه یا داخل یک
     * محفظه) می‌گذارد. برخلاف move()، اینجا نیازی به extract/بررسی حلقه نیست
     * چون نود تازه از قبل جایی در درخت نبوده است.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<string, mixed>  $newNode
     * @return array<int, array<string, mixed>>
     *
     * @throws InvalidArgumentException اگر مقصد یک محفظه نباشد یا پیدا نشود.
     */
    public function addNode(array $nodes, ?string $targetParentId, array $newNode): array
    {
        return $this->insert($nodes, $targetParentId, $newNode, PHP_INT_MAX);
    }

    /**
     * حذف یک نود مشخص (هر عمقی) از درخت — دوباره‌استفاده از همان extract()
     * که move() هم برای برداشتن نود مبدا از جای قبلی‌اش استفاده می‌کند، فقط
     * اینجا نود برداشته‌شده دور ریخته می‌شود، نه جای دیگری درج. اگر نود یک
     * محفظه باشد، همه‌ی فرزندانش هم چون داخل همان زیردرخت هستند خودکار با آن
     * حذف می‌شوند (extract درخت را عمیق پیمایش می‌کند، نه فقط یک لایه) — این
     * یک تصمیم عمدی است: نگه‌داشتن فرزندانِ یک محفظه‌ی حذف‌شده در ریشه یا هر
     * جای دیگر یعنی بازآرایی ساختاری خاموش که کاربر انتظارش را نداشته. هشدار
     * این پیامد در UI (widget-tree-node.blade.php، متن wire:confirm) داده
     * می‌شود، نه اینجا.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<int, array<string, mixed>>
     *
     * @throws InvalidArgumentException اگر نود مشخص‌شده در ساختار صفحه پیدا نشود.
     */
    public function remove(array $nodes, string $id): array
    {
        [$found, $remaining] = $this->extract($nodes, $id);

        if ($found === null) {
            throw new InvalidArgumentException('ویجت موردنظر برای حذف در ساختار صفحه پیدا نشد.');
        }

        return $remaining;
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<string, mixed>  $draggedNode
     * @return array<int, array<string, mixed>>
     */
    private function insert(array $nodes, ?string $targetParentId, array $draggedNode, int $targetIndex): array
    {
        if ($targetParentId === null) {
            return $this->insertAtIndex($nodes, $draggedNode, $targetIndex);
        }

        $found = false;

        $walk = function (array $nodes) use (&$walk, &$found, $targetParentId, $draggedNode, $targetIndex): array {
            foreach ($nodes as &$node) {
                if (! is_array($node) || ! isset($node['id'])) {
                    continue;
                }

                if (! $found && $node['id'] === $targetParentId) {
                    if (($node['widget_key'] ?? null) !== WidgetKey::Container->value) {
                        throw new InvalidArgumentException('فقط داخل یک محفظه می‌شود ویجت رها کرد.');
                    }

                    $node['children'] = $this->insertAtIndex($node['children'] ?? [], $draggedNode, $targetIndex);
                    $found = true;

                    continue;
                }

                if (! $found && ! empty($node['children'])) {
                    $node['children'] = $walk($node['children']);
                }
            }

            return $nodes;
        };

        $result = $walk($nodes);

        if (! $found) {
            throw new InvalidArgumentException('محفظه مقصد در ساختار صفحه پیدا نشد.');
        }

        return $result;
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<string, mixed>  $node
     * @return array<int, array<string, mixed>>
     */
    private function insertAtIndex(array $nodes, array $node, int $index): array
    {
        $index = max(0, min($index, count($nodes)));
        array_splice($nodes, $index, 0, [$node]);

        return $nodes;
    }
}
