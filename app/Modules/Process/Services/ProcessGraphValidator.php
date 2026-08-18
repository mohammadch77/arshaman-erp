<?php

namespace App\Modules\Process\Services;

use App\Modules\Process\Enums\AssignmentType;
use App\Modules\Process\Enums\ConditionOperator;
use App\Modules\Process\Enums\StepType;
use App\Modules\Process\Enums\TransitionResult;
use Illuminate\Validation\ValidationException;

/**
 * اعتبارسنجی کامل ساختار یک تعریف فرایند *قبل* از نوشتن در دیتابیس — روی
 * آرایه‌های خام فرم (step_key به‌عنوان شناسه‌ی گره، چون در این لحظه هنوز
 * UUID واقعی ساخته نشده)، نه مدل‌های Eloquent.
 *
 * سه ضمانت با هم:
 * ۱. هر مرحله (بسته به نوعش) دقیقاً همان تعداد/نوع گذار خروجی مجاز را دارد
 *    (start=۱، approval/condition=۲ با نتیجه‌ی متفاوت، end=۰).
 * ۲. همه‌ی مراحل از start قابل‌دسترسی‌اند (بدون مرحله‌ی یتیم).
 * ۳. گراف چرخه ندارد (DFS سه‌رنگ) — چون طبق ضمانت ۱ هر مرحله‌ی غیر-end
 *    دقیقاً گذار خروجی برای *هر* نتیجه‌ی ممکنش دارد و طبق ضمانت ۲ از start
 *    قابل‌دسترسی است، نبودِ چرخه یعنی هر مسیر ممکن قطعاً به یک end می‌رسد —
 *    نیازی به بررسی جداگانه‌ی «هر مسیر به پایان می‌رسد» نیست.
 */
class ProcessGraphValidator
{
    private const ROLES = ['holding_admin', 'accountant', 'operator', 'viewer'];

    /**
     * @param  array<int, array<string, mixed>>  $steps
     * @param  array<int, array<string, mixed>>  $transitions
     * @param  array<int, array<string, mixed>>  $requestFormFields  فقط برای فرایند آزاد
     *                                                                (subjectType===null) استفاده می‌شود —
     *                                                                whitelist شرط از روی فیلدهای فرم
     *                                                                خودِ همین تعریف (بخش ۲ Session جاری).
     *
     * @throws ValidationException
     */
    public function validate(?string $subjectType, array $steps, array $transitions, array $requestFormFields = []): void
    {
        $errors = [];

        if ($steps === []) {
            throw ValidationException::withMessages(['graph' => ['فرایند باید حداقل یک مرحله داشته باشد.']]);
        }

        $stepKeys = array_map(fn ($step) => $step['step_key'] ?? null, $steps);

        if (array_filter($stepKeys, fn ($key) => $key === null || $key === '') !== []) {
            $errors[] = 'همه‌ی مراحل باید یک کلید معتبر داشته باشند.';
        }

        if (count($stepKeys) !== count(array_unique($stepKeys))) {
            $errors[] = 'کلید مراحل باید در کل فرایند یکتا باشد.';
        }

        $stepsByKey = [];
        foreach ($steps as $step) {
            $key = $step['step_key'] ?? null;
            if ($key !== null && $key !== '') {
                $stepsByKey[$key] = $step;
            }
        }

        $startSteps = array_filter($steps, fn ($step) => ($step['step_type'] ?? null) === StepType::Start->value);
        $endSteps = array_filter($steps, fn ($step) => ($step['step_type'] ?? null) === StepType::End->value);

        if (count($startSteps) !== 1) {
            $errors[] = 'فرایند باید دقیقاً یک مرحله‌ی «شروع» داشته باشد.';
        }

        if ($endSteps === []) {
            $errors[] = 'فرایند باید حداقل یک مرحله‌ی «پایان» داشته باشد.';
        }

        // اعتبارسنجی فیلدهای اختصاصی هر نوع مرحله.
        foreach ($steps as $step) {
            $label = $step['name'] ?? ($step['step_key'] ?? '(بی‌نام)');
            $type = $step['step_type'] ?? null;

            if ($type === StepType::Approval->value) {
                $assignmentType = $step['assignment_type'] ?? null;

                if (! in_array($assignmentType, [AssignmentType::Role->value, AssignmentType::SpecificUser->value], true)) {
                    $errors[] = "مرحله‌ی تأیید «{$label}» باید نوع واگذاری (نقش یا کاربر مشخص) داشته باشد.";
                } elseif ($assignmentType === AssignmentType::Role->value && ! in_array($step['assigned_role'] ?? null, self::ROLES, true)) {
                    $errors[] = "مرحله‌ی تأیید «{$label}» باید یک نقش معتبر برای واگذاری داشته باشد.";
                } elseif ($assignmentType === AssignmentType::SpecificUser->value && empty($step['assigned_user_id'])) {
                    $errors[] = "مرحله‌ی تأیید «{$label}» باید یک کاربر مشخص برای واگذاری داشته باشد.";
                }
            }

            if ($type === StepType::Condition->value) {
                // دو منبع whitelist موازی برای فیلد شرط: فرایند وصل‌شده به ماژول از
                // config/processes.php (برنامه‌نویس تعریف کرده)، فرایند آزاد از
                // فیلدهای فرم خودِ همین تعریف (همان ادمینی که فرم را ساخته، شرط را
                // هم می‌سازد — بخش ۲ Session جاری، امن است).
                $allowedFields = $subjectType === null
                    ? array_values(array_filter(array_map(fn ($f) => $f['key'] ?? null, $requestFormFields)))
                    : config("processes.condition_fields.{$subjectType}", []);

                $field = $step['condition_field'] ?? null;

                if (! in_array($field, $allowedFields, true)) {
                    $errors[] = "فیلد شرط مرحله‌ی «{$label}» باید یکی از فیلدهای مجاز همین فرایند باشد.";
                }

                if (! in_array($step['condition_operator'] ?? null, array_map(fn ($case) => $case->value, ConditionOperator::cases()), true)) {
                    $errors[] = "مرحله‌ی شرط «{$label}» باید یک عملگر معتبر داشته باشد.";
                }

                if (empty($step['condition_value']) && $step['condition_value'] !== '0') {
                    $errors[] = "مرحله‌ی شرط «{$label}» باید یک مقدار مقایسه داشته باشد.";
                }
            }

            if ($type === StepType::RequesterInput->value) {
                $fields = $step['step_form_fields'] ?? [];

                if ($fields === []) {
                    $errors[] = "مرحله‌ی «{$label}» (تکمیل اطلاعات توسط درخواست‌دهنده) باید حداقل یک فیلد فرم داشته باشد.";
                }
            }
        }

        // نگاشت گذارها بر اساس مرحله‌ی مبدا + بررسی معتبربودن مقصد.
        $outgoingByStep = [];
        foreach ($transitions as $transition) {
            $from = $transition['from_step_key'] ?? null;
            $to = $transition['to_step_key'] ?? null;
            $onResult = $transition['on_result'] ?? null;

            if (! isset($stepsByKey[$from])) {
                $errors[] = 'یک گذار به یک مرحله‌ی مبدا نامعتبر اشاره می‌کند.';

                continue;
            }

            if (! isset($stepsByKey[$to])) {
                $errors[] = "گذار خروجی مرحله‌ی «{$stepsByKey[$from]['name']}» به یک مرحله‌ی مقصد نامعتبر اشاره می‌کند.";

                continue;
            }

            $outgoingByStep[$from][] = ['to' => $to, 'on_result' => $onResult];
        }

        // تعداد/نوع گذار خروجی مجاز بر اساس نوع مرحله.
        foreach ($steps as $step) {
            $key = $step['step_key'] ?? null;
            if ($key === null || $key === '' || ! isset($stepsByKey[$key])) {
                continue;
            }

            $label = $step['name'] ?? $key;
            $type = $step['step_type'] ?? null;
            $outgoing = $outgoingByStep[$key] ?? [];
            $results = array_column($outgoing, 'on_result');

            match ($type) {
                StepType::Start->value => count($outgoing) === 1
                    ? null
                    : $errors[] = "مرحله‌ی شروع «{$label}» باید دقیقاً یک گذار خروجی (مرحله‌ی بعد) داشته باشد.",
                StepType::Approval->value => $this->expectExactResults(
                    $outgoing, $results,
                    [TransitionResult::Approved->value, TransitionResult::Rejected->value],
                    "مرحله‌ی تأیید «{$label}» باید دقیقاً دو گذار خروجی داشته باشد: «اگر تأیید شد» و «اگر رد شد».",
                    $errors,
                ),
                StepType::Condition->value => $this->expectExactResults(
                    $outgoing, $results,
                    [TransitionResult::ConditionTrue->value, TransitionResult::ConditionFalse->value],
                    "مرحله‌ی شرط «{$label}» باید دقیقاً دو گذار خروجی داشته باشد: «اگر شرط درست بود» و «اگر نادرست بود».",
                    $errors,
                ),
                StepType::RequesterInput->value => count($outgoing) === 1 && $results === [TransitionResult::Default->value]
                    ? null
                    : $errors[] = "مرحله‌ی «{$label}» باید دقیقاً یک گذار خروجی (بعد از ارسال) داشته باشد.",
                StepType::End->value => count($outgoing) === 0
                    ? null
                    : $errors[] = "مرحله‌ی پایان «{$label}» نباید هیچ گذار خروجی داشته باشد.",
                default => null,
            };
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(['graph' => $errors]);
        }

        // از این‌جا به بعد گراف ساختاری سالم است (هر گره‌ی غیر-end دقیقاً
        // گذار لازم را دارد، هر گذار به یک مرحله‌ی واقعی می‌رود) — فقط
        // دسترس‌پذیری از start و نبودِ چرخه باقی می‌ماند.
        $startKey = array_values($startSteps)[0]['step_key'];

        $reachable = $this->reachableFrom($startKey, $outgoingByStep);
        $orphanKeys = array_diff(array_keys($stepsByKey), $reachable);

        if ($orphanKeys !== []) {
            $orphanLabels = array_map(fn ($key) => $stepsByKey[$key]['name'] ?? $key, $orphanKeys);
            throw ValidationException::withMessages([
                'graph' => ['این مراحل از مرحله‌ی شروع قابل‌دسترسی نیستند (یتیم): '.implode('، ', $orphanLabels)],
            ]);
        }

        $cycleKey = $this->findCycle($startKey, $outgoingByStep);

        if ($cycleKey !== null) {
            $label = $stepsByKey[$cycleKey]['name'] ?? $cycleKey;
            throw ValidationException::withMessages([
                'graph' => ["چرخه در گراف فرایند پیدا شد (حول مرحله‌ی «{$label}») — هر مسیر باید نهایتاً به یک مرحله‌ی پایان برسد."],
            ]);
        }
    }

    /**
     * @param  array<int, array{to: string, on_result: ?string}>  $outgoing
     * @param  array<int, ?string>  $results
     * @param  array<int, string>  $expected
     * @param  array<int, string>  $errors
     */
    private function expectExactResults(array $outgoing, array $results, array $expected, string $message, array &$errors): void
    {
        sort($results);
        $expectedSorted = $expected;
        sort($expectedSorted);

        if (count($outgoing) !== 2 || $results !== $expectedSorted) {
            $errors[] = $message;
        }
    }

    /**
     * @param  array<string, array<int, array{to: string, on_result: ?string}>>  $outgoingByStep
     * @return array<int, string>
     */
    private function reachableFrom(string $startKey, array $outgoingByStep): array
    {
        $visited = [$startKey => true];
        $queue = [$startKey];

        while ($queue !== []) {
            $current = array_shift($queue);

            foreach ($outgoingByStep[$current] ?? [] as $edge) {
                if (! isset($visited[$edge['to']])) {
                    $visited[$edge['to']] = true;
                    $queue[] = $edge['to'];
                }
            }
        }

        return array_keys($visited);
    }

    /**
     * DFS سه‌رنگ استاندارد برای تشخیص چرخه؛ اولین گره‌ای که در حال بازدید
     * دوباره دیده شود (خاکستری) را برمی‌گرداند، یا null اگر چرخه‌ای نبود.
     *
     * @param  array<string, array<int, array{to: string, on_result: ?string}>>  $outgoingByStep
     */
    private function findCycle(string $startKey, array $outgoingByStep): ?string
    {
        $state = []; // key => 'gray'|'black'

        $visit = function (string $node) use (&$visit, &$state, $outgoingByStep): ?string {
            $state[$node] = 'gray';

            foreach ($outgoingByStep[$node] ?? [] as $edge) {
                $next = $edge['to'];

                if (($state[$next] ?? null) === 'gray') {
                    return $next;
                }

                if (($state[$next] ?? null) !== 'black') {
                    $found = $visit($next);
                    if ($found !== null) {
                        return $found;
                    }
                }
            }

            $state[$node] = 'black';

            return null;
        };

        return $visit($startKey);
    }
}
