<?php

namespace App\Modules\Process\Services;

use App\Modules\Process\Enums\StepType;
use App\Modules\Process\Enums\TransitionResult;
use App\Modules\Process\Models\ProcessDefinition;

/**
 * ساخت رشته‌ی syntax مرمید (Mermaid.js) از ساختار واقعی یک تعریف فرایند
 * (بخش ۴.۱ Session جاری) — فقط سرور این رشته را می‌سازد، رندر واقعی SVG
 * کاملاً سمت کلاینت است (resources/js/process-flowchart.js). شکل هر گره
 * بر اساس step_type فرق می‌کند: شروع/پایان بیضی، تأیید مستطیل، شرط لوزی،
 * تکمیل اطلاعات توسط درخواست‌دهنده مستطیل با گوشه‌ی بریده.
 */
class ProcessFlowchartBuilder
{
    public function build(ProcessDefinition $definition): string
    {
        $definition->loadMissing('steps.outgoingTransitions.toStep');

        $lines = ['flowchart TD'];

        // شناسه‌ی گره در مرمید از UUID واقعی مشتق می‌شود اما هر کاراکتر غیرمجاز
        // (خط‌تیره و مشابه) با _ جایگزین می‌شود — مرمید فقط حروف/عدد/زیرخط را
        // برای شناسه‌ی بدون‌نقل‌قول به‌طور قابل‌اعتماد می‌پذیرد.
        $nodeIds = [];
        foreach ($definition->steps as $step) {
            $nodeIds[$step->id] = 'n_'.preg_replace('/[^a-zA-Z0-9_]/', '_', $step->id);
        }

        foreach ($definition->steps as $step) {
            $nodeId = $nodeIds[$step->id];
            $label = $this->escapeLabel($step->name !== '' ? $step->name : $step->step_key);

            $lines[] = match ($step->step_type) {
                StepType::Start, StepType::End => "    {$nodeId}([\"{$label}\"])",
                StepType::Condition => "    {$nodeId}{\"{$label}\"}",
                StepType::RequesterInput => "    {$nodeId}[/\"{$label}\"/]",
                default => "    {$nodeId}[\"{$label}\"]",
            };
        }

        foreach ($definition->steps as $step) {
            foreach ($step->outgoingTransitions as $transition) {
                $from = $nodeIds[$transition->from_step_id] ?? null;
                $to = $nodeIds[$transition->to_step_id] ?? null;

                if ($from === null || $to === null) {
                    continue;
                }

                $edgeLabel = $this->resultLabel($transition->on_result);

                $lines[] = $edgeLabel !== null
                    ? "    {$from} -->|{$edgeLabel}| {$to}"
                    : "    {$from} --> {$to}";
            }
        }

        return implode("\n", $lines);
    }

    private function resultLabel(?TransitionResult $result): ?string
    {
        // نتیجه‌ی 'default' (تنها گذار خروجی مرحله‌ی requester_input) برچسب
        // نمی‌گیرد — یک مسیر بدون شاخه، برچسب «ارسال شد» رویش اضافه‌کاری بود.
        return $result === null || $result === TransitionResult::Default ? null : $result->label();
    }

    /**
     * کاراکترهای خاص Mermaid (نقل‌قول، خط جدید) که داخل یک برچسب بین کوتیشن
     * می‌توانند syntax را بشکنند، خنثی می‌شوند.
     */
    private function escapeLabel(string $label): string
    {
        return str_replace(['"', "\n", "\r"], ["'", ' ', ''], $label);
    }
}
