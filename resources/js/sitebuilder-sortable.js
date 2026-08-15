import Sortable from 'sortablejs';

/**
 * درگ‌اند‌دراپ widget_tree در PageContentEditor/PageCreateFlow. هر لیست
 * sortable (سطح بالای صفحه یا فرزندان یک محفظه — نگاه کن
 * partials/widget-tree.blade.php) با همین یک تابع مقداردهی اولیه می‌شود؛ همه
 * با یک group مشترک متصل‌اند تا ویجت از هر لیستی به هر لیست دیگری قابل‌کشیدن
 * باشد. خودِ آرایه widget_tree هرگز مستقیم از JS دست‌کاری نمی‌شود — فقط بعد
 * از رها‌کردن، شناسه/مقصد/محل به Livewire (onDrop → $wire.moveWidgetNode)
 * پاس داده می‌شود تا سرور خودش با WidgetTreeReorderer جابه‌جایی را (با چک
 * حلقه‌ی بی‌نهایت) روی state واقعی کامپوننت اعمال کند.
 */
window.initSitebuilderSortable = function (listEl, { group, onDrop }) {
    if (listEl.sortableInstance) {
        return listEl.sortableInstance;
    }

    listEl.sortableInstance = Sortable.create(listEl, {
        group,
        handle: '.sb-drag-handle',
        animation: 150,
        // درگ مبتنی بر ماوس به‌جای HTML5 Drag-and-Drop بومی — رفتار یکدست‌تری
        // بین مرورگرها روی لیست‌های تودرتو (محفظه داخل محفظه) می‌دهد و برخلاف
        // DnD بومی، داخل یک والد با overflow/اسکرول هم درست کار می‌کند.
        forceFallback: true,
        fallbackOnBody: true,
        swapThreshold: 0.65,
        onMove(evt) {
            const dragged = evt.dragged;

            // یک محفظه نمی‌تواند داخل خودش یا فرزندانش رها شود — این‌جا فقط
            // بازخورد فوری UI است، چک واقعی/غیرقابل‌دور‌زدن سمت سرور در
            // WidgetTreeReorderer::move() انجام می‌شود.
            if (dragged.contains(evt.related) || dragged === evt.related) {
                return false;
            }

            return true;
        },
        onEnd(evt) {
            const draggedId = evt.item.dataset.nodeId;
            const targetList = evt.to;
            const targetParentId = targetList.dataset.parentId || null;
            const index = Array.from(targetList.children).filter((el) => el.dataset && el.dataset.nodeId).indexOf(evt.item);

            if (! draggedId || index === -1) {
                return;
            }

            onDrop(draggedId, targetParentId, index);
        },
    });

    return listEl.sortableInstance;
};
