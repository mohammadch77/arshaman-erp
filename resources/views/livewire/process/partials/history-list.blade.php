{{--
    فهرست تاریخچه‌ی instance — با هر دو پنل «کارهای من»/«درخواست‌های من»
    استفاده می‌شود. توقع دارد $events از قبل با ['step', 'actor',
    'fieldValues.formField'] eager-load شده باشد.
--}}
@if($events->isEmpty())
    <p class="text-base-content/60">هنوز هیچ رویدادی ثبت نشده است.</p>
@else
    <ul class="flex flex-col gap-3">
        @foreach($events as $event)
            <li class="border-b border-base-300 pb-3 last:border-b-0 last:pb-0">
                <div class="flex items-center gap-2">
                    <x-icon :name="theme_icon('history')" class="w-4 h-4 text-base-content/60" />
                    <span class="font-medium">{{ $event->step->name }}</span>
                    <span class="badge badge-ghost badge-sm">{{ $event->action->label() }}</span>
                    @if($event->reversed_at)
                        <span class="badge badge-warning badge-sm">بازگردانی‌شده</span>
                    @endif
                </div>

                <div class="text-sm mt-1 text-base-content/70">
                    {{ $event->actor?->full_name ?? 'خودکار (سیستم)' }}
                </div>

                @if($event->comment)
                    <p class="text-sm text-base-content/70 mt-1 whitespace-pre-line">{{ $event->comment }}</p>
                @endif

                @if($event->fieldValues->isNotEmpty())
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1 mt-2 text-sm">
                        @foreach($event->fieldValues as $fieldValue)
                            <div class="flex gap-2">
                                <dt class="text-base-content/60">{{ $fieldValue->formField->label ?? '—' }}:</dt>
                                @if(($fieldValue->formField->field_type ?? null) === 'file' && $fieldValue->value)
                                    <dd>
                                        <a href="{{ \App\Modules\Process\Support\ProcessFileUploader::url($fieldValue->value) }}" target="_blank" class="link link-primary inline-flex items-center gap-1">
                                            <x-icon :name="theme_icon('download')" class="w-4 h-4" />
                                            {{ \App\Modules\Process\Support\ProcessFileUploader::originalNameFromPath($fieldValue->value) }}
                                        </a>
                                    </dd>
                                @else
                                    <dd class="font-medium">{{ $fieldValue->value === '1' && ($fieldValue->formField->field_type ?? null) === 'boolean' ? 'بله' : ($fieldValue->value === '' ? 'خیر' : $fieldValue->value) }}</dd>
                                @endif
                            </div>
                        @endforeach
                    </dl>
                @endif

                <div class="text-xs text-base-content/60 mt-1">
                    {{ \App\Support\Jalali::toDisplayDateTime($event->created_at) }}
                </div>
            </li>
        @endforeach
    </ul>
@endif
