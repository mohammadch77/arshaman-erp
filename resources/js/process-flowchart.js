import mermaid from 'mermaid';

/**
 * فلوچارت تعریف فرایند (بخش ۴.۱ Session جاری) — سرور فقط رشته‌ی syntax
 * مرمید را می‌سازد (ProcessFlowchartBuilder)، این‌جا فقط رندرش می‌کند. مثل
 * process-sortable.js/sitebuilder-sortable.js یک تابع سراسری ساده روی
 * window، بدون کامپوننت جدا — چون فقط یک بار در مودال «مشاهده فلوچارت»
 * صدا زده می‌شود.
 */
mermaid.initialize({ startOnLoad: false, securityLevel: 'strict', theme: 'neutral' });

window.renderProcessFlowchart = async function (containerEl, definitionString) {
    containerEl.innerHTML = '';

    const id = 'process-flowchart-'.concat(Math.random().toString(36).slice(2));

    try {
        const { svg } = await mermaid.render(id, definitionString);
        containerEl.innerHTML = svg;
    } catch (error) {
        containerEl.innerHTML = '<p class="text-error text-sm">رسم فلوچارت ممکن نشد.</p>';
        // eslint-disable-next-line no-console
        console.error('Process flowchart render error', error);
    }
};
