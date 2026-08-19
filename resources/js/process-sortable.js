import Sortable from 'sortablejs';

/**
 * درگ‌اند‌دراپ ردیف‌های «گذارها» در ProcessDefinitionForm (مرحله ۴ ویزارد) —
 * فقط ترتیب نمایش (transitionOrder/display_order) را عوض می‌کند، هیچ اثری
 * روی from_step_id/to_step_id/on_result واقعی ندارد (نگاه کن
 * ProcessDefinitionForm::moveTransitionRow). یک لیست تخت ساده، بدون تودرتو —
 * برخلاف sitebuilder-sortable.js نیازی به group/nested container ندارد.
 */
window.initProcessTransitionSortable = function (listEl, { onDrop }) {
    if (listEl.sortableInstance) {
        return listEl.sortableInstance;
    }

    listEl.sortableInstance = Sortable.create(listEl, {
        handle: '.pd-drag-handle',
        animation: 150,
        forceFallback: true,
        fallbackOnBody: true,
        swapThreshold: 0.65,
        onEnd(evt) {
            const stepKey = evt.item.dataset.stepKey;
            const newIndex = Array.from(evt.to.children)
                .filter((el) => el.dataset && el.dataset.stepKey)
                .indexOf(evt.item);

            if (! stepKey || newIndex === -1) {
                return;
            }

            onDrop(stepKey, newIndex);
        },
    });

    return listEl.sortableInstance;
};

/**
 * درگ‌اند‌دراپ عمومی‌تر — برای فیلدهای فرم (درخواست/مرحله) و خودِ مراحل در
 * فهرست خلاصه‌ی مرحله ۳ (بخش ۵ بازطراحی). برخلاف initProcessTransitionSortable
 * (که مقید به data-step-key/pd-drag-handle است)، اینجا هم selector دستگیره
 * هم attribute شناسه قابل‌تنظیم‌اند تا در چند نوع لیست مختلف (فیلد فرم
 * درخواست، فیلد فرم مرحله، خودِ مراحل) دوباره استفاده شود.
 */
window.initProcessFieldSortable = function (listEl, { onDrop, handle = '.pd-field-drag-handle' }) {
    if (listEl.sortableInstance) {
        return listEl.sortableInstance;
    }

    listEl.sortableInstance = Sortable.create(listEl, {
        handle,
        animation: 150,
        forceFallback: true,
        fallbackOnBody: true,
        swapThreshold: 0.65,
        onEnd(evt) {
            const fieldKey = evt.item.dataset.fieldKey;
            const newIndex = Array.from(evt.to.children)
                .filter((el) => el.dataset && el.dataset.fieldKey)
                .indexOf(evt.item);

            if (! fieldKey || newIndex === -1) {
                return;
            }

            onDrop(fieldKey, newIndex);
        },
    });

    return listEl.sortableInstance;
};
