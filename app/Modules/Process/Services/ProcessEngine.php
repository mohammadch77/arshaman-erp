<?php

namespace App\Modules\Process\Services;

use App\Modules\Core\Models\User;
use App\Modules\Process\Enums\AssignmentType;
use App\Modules\Process\Enums\ConditionOperator;
use App\Modules\Process\Enums\LogAction;
use App\Modules\Process\Enums\ProcessStatus;
use App\Modules\Process\Enums\StepType;
use App\Modules\Process\Enums\TransitionResult;
use App\Modules\Process\Exceptions\ProcessCycleDetectedException;
use App\Modules\Process\Models\ProcessDefinition;
use App\Modules\Process\Models\ProcessInstance;
use App\Modules\Process\Models\ProcessInstanceLog;
use App\Modules\Process\Models\ProcessStep;
use App\Modules\Process\Models\ProcessTransition;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

/**
 * موتور اجرای واقعی گردش‌کار: یک process_instance را از مرحله‌ی start تا یک
 * مرحله‌ی end (از میان مراحل approval/condition) جابه‌جا می‌کند.
 *
 * تصمیم طراحی — status نهایی instance: هر مرحله‌ی end با نتیجه‌ی همان
 * انتقالی که به آن رسیده تعیین می‌شود (approved/condition_true → approved،
 * rejected/condition_false → rejected)، نه با نام‌گذاری step_key. این قرارداد
 * ساده و قطعی است و نیازی به یک ستون متادیتای جدید روی process_steps ندارد؛
 * هر گراف جدیدی که در آینده ساخته شود همین قانون را رایگان می‌گیرد.
 *
 * محافظت در برابر چرخه: هر فراخوانی بیرونی (startInstance/advance) یک
 * مجموعه‌ی visited مخصوص به خودش دارد که فقط در طول همان زنجیره‌ی خودکار
 * condition→condition→... جمع می‌شود؛ اگر یک مرحله دوباره در همان زنجیره
 * دیده شود یعنی چرخه‌ی واقعی در گراف است. یک سقف عمق (MAX_AUTO_ADVANCE_STEPS)
 * هم به‌عنوان لایه‌ی دفاعی دوم وجود دارد.
 */
class ProcessEngine
{
    private const MAX_AUTO_ADVANCE_STEPS = 50;

    public function startInstance(
        ProcessDefinition $definition,
        User $actor,
        ?Model $subject = null,
        ?array $requestData = null,
    ): ProcessInstance {
        $startStep = $definition->steps()->where('step_type', StepType::Start->value)->first();

        if ($startStep === null) {
            throw new InvalidArgumentException('این تعریف فرایند هیچ مرحله‌ی شروعی ندارد.');
        }

        if ($definition->subject_type !== null && ($subject === null || $subject::class !== $definition->subject_type)) {
            throw new InvalidArgumentException('این تعریف فرایند به یک سوژه از نوع مشخص وصل است — سوژه‌ی داده‌شده مطابق نیست.');
        }

        if ($definition->subject_type === null && $subject !== null) {
            throw new InvalidArgumentException('این تعریف فرایند آزاد است (بدون subject_type)؛ سوژه نباید داده شود.');
        }

        $instance = ProcessInstance::create([
            'owner_company_id' => $definition->owner_company_id,
            'process_definition_id' => $definition->id,
            'subject_type' => $subject !== null ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'request_data' => $requestData,
            'current_step_id' => $startStep->id,
            'status' => ProcessStatus::InProgress,
            'started_by_user_id' => $actor->id,
            'started_at' => now(),
        ]);

        $this->log($instance, $startStep, $actor, LogAction::Started, null);

        // مرحله‌ی start هیچ اقدام انسانی لازم ندارد — بلافاصله با تنها انتقال
        // خروجی‌اش (بدون فیلتر بر اساس on_result) به مرحله‌ی بعد می‌رود.
        $this->moveFrom($instance, $startStep, null, [$startStep->id => true], 0, $actor, null);

        return $instance->fresh();
    }

    /**
     * اگر برای همان subject_type و شرکت مالک سوژه یک process_definition فعال
     * وجود داشته باشد، یک instance تازه می‌سازد و آن را استارت می‌کند؛ در غیر
     * این صورت null برمی‌گرداند و caller باید رفتار قبلی (بدون فرایند) را ادامه
     * دهد. این تنها نقطه‌ی اتصال یک ماژول دیگر (مثلاً HR) به موتور فرایند است —
     * ماژول دیگر هرگز مستقیم مدل ProcessDefinition را کوئری نمی‌کند (بند ۴
     * CLAUDE.md)، فقط همین متد سرویس را صدا می‌زند.
     */
    public function startForSubjectIfActive(Model $subject, User $actor): ?ProcessInstance
    {
        $definition = ProcessDefinition::withoutGlobalScope('owner_company')
            ->where('owner_company_id', $subject->owner_company_id)
            ->where('subject_type', $subject::class)
            ->where('is_active', true)
            ->first();

        if ($definition === null) {
            return null;
        }

        return $this->startInstance($definition, $actor, $subject);
    }

    /**
     * آیا این سوژه‌ی مشخص یک process_instance «در جریان» دارد؟ ماژول‌های دیگر
     * (مثل ApproveLeave/RejectLeave در HR) این را قبل از هر تغییر مستقیم وضعیت
     * صدا می‌زنند تا وقتی سوژه در فرایند است، مسیر مستقیم/موازی مسدود شود —
     * تنها راه مجاز تغییر در آن حالت، پیشرفت خودِ فرایند است.
     */
    public function hasActiveInstance(Model $subject): bool
    {
        return ProcessInstance::withoutGlobalScope('owner_company')
            ->where('subject_type', $subject::class)
            ->where('subject_id', $subject->getKey())
            ->where('status', ProcessStatus::InProgress->value)
            ->exists();
    }

    /**
     * نسخه‌ی دسته‌ای hasActiveInstance() برای صفحات فهرست (مثل LeaveIndex) —
     * تا کامپوننت Livewire یک ماژول دیگر مجبور نباشد مدل ProcessInstance را
     * مستقیم کوئری کند (بند ۴ CLAUDE.md)، همه‌چیز از طریق همین سرویس.
     *
     * @param  array<int, string>  $subjectIds
     * @return array<int, string> زیرمجموعه‌ای از $subjectIds که واقعاً یک instance «در جریان» دارند
     */
    public function activeInstanceSubjectIds(string $subjectType, array $subjectIds): array
    {
        if ($subjectIds === []) {
            return [];
        }

        return ProcessInstance::withoutGlobalScope('owner_company')
            ->where('subject_type', $subjectType)
            ->whereIn('subject_id', $subjectIds)
            ->where('status', ProcessStatus::InProgress->value)
            ->pluck('subject_id')
            ->all();
    }

    /**
     * نتیجه‌ی مرحله‌ی فعلی instance (approved/rejected از یک تأیید انسانی، یا
     * condition_true/condition_false از یک ارزیابی خودکار دستی‌فراخوانی‌شده) را
     * می‌گیرد، انتقال منطبق را پیدا و اعمال می‌کند.
     */
    public function advance(ProcessInstance $instance, string $result, ?User $actor = null, ?string $comment = null): void
    {
        if ($instance->status !== ProcessStatus::InProgress) {
            throw new RuntimeException('این فرایند دیگر در جریان نیست — قابل انتقال نیست.');
        }

        $currentStep = $instance->currentStep;

        if ($currentStep === null || $currentStep->step_type === StepType::End) {
            throw new RuntimeException('مرحله‌ی فعلی این فرایند نامعتبر یا پایانی است.');
        }

        $resultEnum = TransitionResult::from($result);

        $this->log($instance, $currentStep, $actor, $this->logActionForResult($resultEnum), $comment);

        $this->moveFrom($instance, $currentStep, $resultEnum, [$currentStep->id => true], 0, $actor, $comment);
    }

    /**
     * بررسی می‌کند آیا $actor واقعاً مجاز تصمیم‌گیری روی مرحله‌ی approval فعلی
     * است — یا نقش assigned_role را در همان شرکت instance دارد، یا خودِ
     * assigned_user_id است. طبق بند ۹ CLAUDE.md، این چک مستقل از caller است؛
     * Action های ApproveProcessStep/RejectProcessStep همیشه آن را صدا می‌زنند.
     */
    public function assertActorAuthorizedForStep(ProcessInstance $instance, ProcessStep $step, User $actor): void
    {
        $authorized = match ($step->assignment_type) {
            AssignmentType::Role => $step->assigned_role !== null
                && $actor->hasRoleInCompany($instance->owner_company_id, $step->assigned_role),
            AssignmentType::SpecificUser => $step->assigned_user_id !== null
                && $step->assigned_user_id === $actor->id,
            default => false,
        };

        if (! $authorized) {
            throw new AuthorizationException('شما مجاز به تأیید یا رد این مرحله نیستید.');
        }
    }

    /**
     * یادآوری holding_admin به مسئول مرحله‌ی فعلی — فقط یک لاگ جدید، هیچ تغییری
     * در current_step_id/status. authorize واقعی (ProcessInstancePolicy::remind)
     * در Action صدا زده می‌شود؛ این متد فقط رکورد را می‌نویسد.
     */
    public function remind(ProcessInstance $instance, User $actor, string $comment): void
    {
        $step = $instance->currentStep;

        if ($step === null) {
            throw new RuntimeException('این فرایند مرحله‌ی فعلی معتبری ندارد.');
        }

        $this->log($instance, $step, $actor, LogAction::Reminder, $comment);
    }

    /**
     * آخرین لاگ instance اگر و فقط اگر یک تصمیم انسانی (تأیید/رد) بازگردانی‌نشده
     * باشد — یعنی از آن لحظه هیچ اتفاق دیگری (ارزیابی شرط خودکار، تصمیم بعدی، یا
     * تکمیل فرایند) رخ نداده. چون moveFrom/advance هر رویداد را همان لحظه لاگ
     * می‌کند، «آخرین لاگ instance دقیقاً همین تصمیم است» به‌تنهایی یعنی «هیچ‌چیز
     * بعد از آن اتفاق نیفتاده» — نیازی به بررسی جداگانه‌ی مرحله‌ی بعدی نیست.
     */
    public function lastReversibleDecisionLog(ProcessInstance $instance): ?ProcessInstanceLog
    {
        $lastLog = $this->lastLog($instance);

        if ($lastLog === null || $lastLog->reversed_at !== null) {
            return null;
        }

        if (! in_array($lastLog->action, [LogAction::Approved, LogAction::Rejected], true)) {
            return null;
        }

        return $lastLog;
    }

    public function canReverseLastDecision(ProcessInstance $instance, User $actor): bool
    {
        $log = $this->lastReversibleDecisionLog($instance);

        return $log !== null && $log->actor_user_id === $actor->id;
    }

    /**
     * تصمیم اخیر خودِ $actor را بازمی‌گرداند: instance به همان مرحله‌ای که تصمیم
     * رویش گرفته شده بود برمی‌گردد (دوباره منتظر تصمیم انسانی)، و رکورد لاگ اصلی
     * فقط با یک مهر زمانی (reversed_at) علامت می‌خورد — محتوایش هرگز تغییر
     * نمی‌کند. یک لاگ جدید (action=reversed) هم برای تاریخچه ثبت می‌شود.
     */
    public function reverseLastDecision(ProcessInstance $instance, User $actor): void
    {
        if (! $this->canReverseLastDecision($instance, $actor)) {
            throw new AuthorizationException('این تصمیم قابل بازگردانی نیست — یا مال شما نیست، یا مرحله‌ی بعدی از قبل اقدامی داشته.');
        }

        $decisionLog = $this->lastReversibleDecisionLog($instance);

        $decisionLog->update(['reversed_at' => now()]);

        $instance->current_step_id = $decisionLog->step_id;
        $instance->status = ProcessStatus::InProgress;
        $instance->completed_at = null;
        $instance->save();

        $this->log($instance, $decisionLog->step, $actor, LogAction::Reversed, null);
    }

    /**
     * @param  array<string, true>  $visitedStepIds  کلید = step id، فقط در طول همین زنجیره‌ی خودکار
     *
     * $actor/$comment آخرین اقدام انسانی‌ای هستند که این زنجیره‌ی خودکار را
     * راه انداخته (یا الان راه می‌اندازد) — تا انتهای زنجیره (حتی از میان چند
     * مرحله‌ی condition خودکار) با خودشان حمل می‌شوند تا اگر به یک end رسیدند،
     * completeInstance بداند این تکمیل را «به نمایندگی از» کدام کاربر واقعی به
     * Action نهایی HR گزارش کند.
     */
    private function moveFrom(
        ProcessInstance $instance,
        ProcessStep $fromStep,
        ?TransitionResult $result,
        array $visitedStepIds,
        int $depth,
        ?User $actor,
        ?string $comment,
    ): void {
        if ($depth > self::MAX_AUTO_ADVANCE_STEPS) {
            throw new ProcessCycleDetectedException('حد مجاز انتقال خودکار بین مراحل رد شد — احتمال چرخه در گراف فرایند.');
        }

        $transitionQuery = ProcessTransition::query()->where('from_step_id', $fromStep->id);

        if ($result !== null) {
            $transitionQuery->where('on_result', $result->value);
        }

        $transition = $transitionQuery->first();

        if ($transition === null) {
            throw new RuntimeException("هیچ انتقالی از مرحله‌ی «{$fromStep->name}» برای این نتیجه تعریف نشده است.");
        }

        $nextStep = $transition->toStep;

        if (isset($visitedStepIds[$nextStep->id])) {
            throw new ProcessCycleDetectedException('چرخه در گراف فرایند تشخیص داده شد — انتقال متوقف شد.');
        }

        $visitedStepIds[$nextStep->id] = true;

        $instance->current_step_id = $nextStep->id;
        $instance->save();

        if ($nextStep->step_type === StepType::End) {
            $this->completeInstance($instance, $nextStep, $result, $actor, $comment);

            return;
        }

        if ($nextStep->step_type === StepType::Condition) {
            $conditionResult = $this->evaluateCondition($instance, $nextStep);
            $this->log($instance, $nextStep, null, LogAction::ConditionEvaluated, $conditionResult->label());
            $this->moveFrom($instance, $nextStep, $conditionResult, $visitedStepIds, $depth + 1, $actor, $comment);

            return;
        }

        // مرحله‌ی approval (یا start در یک گراف غیرعادی): اینجا متوقف می‌شود،
        // منتظر اقدام انسانی بعدی (advance جداگانه) می‌ماند.
    }

    private function completeInstance(
        ProcessInstance $instance,
        ProcessStep $endStep,
        ?TransitionResult $arrivedVia,
        ?User $actor,
        ?string $comment,
    ): void {
        $instance->status = $this->resolveEndStatus($arrivedVia);
        $instance->completed_at = now();
        $instance->save();

        $this->log($instance, $endStep, null, LogAction::Completed, null);

        // مهم: وضعیت instance همین الان به approved/rejected تغییر کرد (دیگر
        // in_progress نیست)، پس اگر Action زیر خودش دوباره hasActiveInstance()
        // را چک کند (که ApproveLeave/RejectLeave واقعاً می‌کنند)، این instance
        // دیگر «فعال» شناخته نمی‌شود و مسیر مسدودکننده‌ی مسیر مستقیم/دستی روی
        // این فراخوانی خودکار اثر نمی‌گذارد.
        $this->applyResultAction($instance, $actor, $comment);
    }

    /**
     * تنها منبع واحد حقیقت برای اعمال نتیجه‌ی نهایی روی سوژه: هرگز ستون وضعیت
     * سوژه مستقیم دستکاری نمی‌شود، همیشه از طریق whitelist کلاس Action در
     * config('processes.result_actions') — همان الگوی امنیتی whitelist دامنه‌ی
     * map/video در SiteBuilder، اینجا برای instantiate کلاس Action.
     */
    private function applyResultAction(ProcessInstance $instance, ?User $actor, ?string $comment): void
    {
        if ($instance->subject_type === null || $actor === null) {
            return;
        }

        $subject = $this->resolveSubject($instance);

        if ($subject === null) {
            return;
        }

        $outcome = match ($instance->status) {
            ProcessStatus::Approved => 'approved',
            ProcessStatus::Rejected => 'rejected',
            default => null,
        };

        if ($outcome === null) {
            return;
        }

        $actionClass = config("processes.result_actions.{$instance->subject_type}.{$outcome}");

        if ($actionClass === null) {
            Log::warning('Process: هیچ result_action ای برای این subject_type/outcome در config/processes.php ثبت نشده است.', [
                'subject_type' => $instance->subject_type,
                'outcome' => $outcome,
                'process_instance_id' => $instance->id,
            ]);

            return;
        }

        // امضای دقیق Action ها متفاوت است (مثلاً ApproveLeave دو پارامتر می‌گیرد،
        // RejectLeave سه‌تا با یک آرگومان اختیاری) — PHP آرگومان اضافه‌ی بدون
        // پارامتر معادل را نادیده می‌گیرد (خطا نمی‌دهد)، پس این فراخوانی عمومی
        // برای هر دو امن است.
        app($actionClass)->handle($subject, $actor, $comment);
    }

    private function resolveEndStatus(?TransitionResult $arrivedVia): ProcessStatus
    {
        return match ($arrivedVia) {
            TransitionResult::Approved, TransitionResult::ConditionTrue => ProcessStatus::Approved,
            TransitionResult::Rejected, TransitionResult::ConditionFalse => ProcessStatus::Rejected,
            // انتقال بدون نتیجه (مثلاً مستقیم از start) یک تکمیل بدون شاخه است.
            default => ProcessStatus::Approved,
        };
    }

    private function evaluateCondition(ProcessInstance $instance, ProcessStep $step): TransitionResult
    {
        $fieldValue = $this->resolveConditionFieldValue($instance, $step);
        $targetValue = $step->condition_value;

        $bothNumeric = is_numeric($fieldValue) && is_numeric($targetValue);
        $left = $bothNumeric ? (float) $fieldValue : (string) $fieldValue;
        $right = $bothNumeric ? (float) $targetValue : (string) $targetValue;

        $passes = match ($step->condition_operator) {
            ConditionOperator::GreaterThan => $left > $right,
            ConditionOperator::LessThan => $left < $right,
            ConditionOperator::Equal => $left == $right,
            ConditionOperator::GreaterThanOrEqual => $left >= $right,
            ConditionOperator::LessThanOrEqual => $left <= $right,
            ConditionOperator::NotEqual => $left != $right,
            null => throw new RuntimeException("مرحله‌ی شرط «{$step->name}» عملگر شرط ندارد."),
        };

        return $passes ? TransitionResult::ConditionTrue : TransitionResult::ConditionFalse;
    }

    /**
     * فقط از فیلدهای whitelist‌شده در config/processes.php (برای فرایند وصل‌شده
     * به یک subject_type) یا از request_data خودِ فرایند آزاد (که کلیدهایش از
     * قبل توسط request_form_fields همان تعریف، ساخته‌شده توسط holding_admin،
     * محدود شده) خوانده می‌شود — هرگز دسترسی آزاد به هر پراپرتی مدل.
     */
    private function resolveConditionFieldValue(ProcessInstance $instance, ProcessStep $step): mixed
    {
        $field = $step->condition_field;

        if ($field === null) {
            throw new RuntimeException("مرحله‌ی شرط «{$step->name}» فیلد شرط ندارد.");
        }

        if ($instance->subject_type !== null) {
            $allowedFields = config("processes.condition_fields.{$instance->subject_type}", []);

            if (! in_array($field, $allowedFields, true)) {
                throw new RuntimeException("فیلد «{$field}» برای این نوع سوژه در whitelist شرط مجاز نیست.");
            }

            return $this->resolveSubject($instance)?->{$field};
        }

        return data_get($instance->request_data, $field);
    }

    /**
     * $instance->subject (morphTo) از global scope خودِ مدل سوژه (مثلاً
     * BelongsToCompany روی Leave) عبور می‌کند، که بر پایه‌ی CompanyContext
     * فعالِ session فیلتر می‌کند — این متد داخلی موتور اصلاً به یک session/شرکت
     * فعال وابسته نیست (ممکن است از یک job/کنسول هم بیاید)، پس همیشه باید صریح
     * بدون global scope واکشی شود؛ وگرنه بی‌صدا null برمی‌گردد — هم ارزیابی شرط
     * هم اعمال نتیجه‌ی نهایی را خراب می‌کند.
     */
    private function resolveSubject(ProcessInstance $instance): ?Model
    {
        if ($instance->subject_type === null || $instance->subject_id === null) {
            return null;
        }

        $subjectClass = $instance->subject_type;

        return $subjectClass::withoutGlobalScopes()->find($instance->subject_id);
    }

    /**
     * آخرین لاگ واقعی instance — created_at با دقت ثانیه است و در اجرای سریع
     * (تست‌ها، یا دو رویداد در یک لحظه) می‌تواند تساوی بخورد؛ HasUuids پیش‌فرض
     * پروژه از Str::orderedUuid() استفاده می‌کند (UUID زمان‌محور)، پس id
     * به‌عنوان تای‌برک دوم واقعاً هم‌ترتیب زمانی است، نه یک ترتیب دلخواه. عمومی
     * است تا MyProcessTasks (بنر یادآوری)/ProcessOversight (مدت‌زمان مرحله)
     * همین منبع واحد را به‌جای کوئری تکراری استفاده کنند.
     */
    public function lastLog(ProcessInstance $instance): ?ProcessInstanceLog
    {
        return $instance->logs()->orderByDesc('created_at')->orderByDesc('id')->first();
    }

    private function logActionForResult(TransitionResult $result): LogAction
    {
        return match ($result) {
            TransitionResult::Approved => LogAction::Approved,
            TransitionResult::Rejected => LogAction::Rejected,
            TransitionResult::ConditionTrue, TransitionResult::ConditionFalse => LogAction::ConditionEvaluated,
        };
    }

    private function log(ProcessInstance $instance, ProcessStep $step, ?User $actor, LogAction $action, ?string $comment): void
    {
        ProcessInstanceLog::create([
            'owner_company_id' => $instance->owner_company_id,
            'process_instance_id' => $instance->id,
            'step_id' => $step->id,
            'actor_user_id' => $actor?->id,
            'action' => $action,
            'comment' => $comment,
        ]);
    }
}
