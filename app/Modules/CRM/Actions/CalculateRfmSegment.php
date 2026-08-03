<?php

namespace App\Modules\CRM\Actions;

use App\Modules\Core\Models\User;
use App\Modules\CRM\Models\ContactSiteProfile;
use App\Modules\CRM\Models\Interaction;
use App\Modules\CRM\Models\RfmSegment;
use Illuminate\Support\Facades\Gate;

class CalculateRfmSegment
{
    /**
     * بخش‌بندی RFM یک پروفایل را بر پایه تعاملات نوع 'purchase' (فعلاً فقط
     * دستی‌ثبت‌شده) بازمحاسبه می‌کند. اگر هیچ تعامل خریدی نبود، segment='new'
     * و بقیه فیلدها null می‌مانند — چیزی برای محاسبه وجود ندارد.
     */
    public function handle(ContactSiteProfile $profile, User $actor): RfmSegment
    {
        Gate::forUser($actor)->authorize('calculate', [RfmSegment::class, $profile]);

        $purchases = Interaction::withoutGlobalScopes()
            ->where('contact_site_profile_id', $profile->id)
            ->where('interaction_type', Interaction::TYPE_PURCHASE)
            ->get();

        if ($purchases->isEmpty()) {
            $attributes = [
                'recency_days' => null,
                'frequency_count' => null,
                'monetary_amount' => null,
                'segment' => RfmSegment::SEGMENT_NEW,
            ];
        } else {
            $lastPurchaseAt = $purchases->max('occurred_at');
            $recencyDays = $lastPurchaseAt->diffInDays(now());
            $frequencyCount = $purchases->count();

            /*
             * total_purchase_amount هنوز هیچ‌جا به‌روزرسانی نمی‌شود — طبق طراحی
             * Session ۱ CRM، مقدارش تا اتصال سفارش واقعی (فاز ۳) همیشه همان
             * DEFAULT 0 پایگاه‌داده می‌ماند. وقتی تعامل خرید دستی داریم ولی این
             * ستون هنوز صفر است، یعنی مبلغ واقعاً «ثبت نشده»، نه این‌که مشتری صفر
             * خرج کرده — پس این‌جا null ذخیره می‌شود تا با صفر واقعی اشتباه
             * گرفته نشود. بعد از فاز ۳ که این ستون واقعاً پر می‌شود، این شرط
             * دیگر لازم نیست و می‌تواند حذف شود.
             */
            $monetaryAmount = $profile->total_purchase_amount > 0 ? $profile->total_purchase_amount : null;

            $attributes = [
                'recency_days' => $recencyDays,
                'frequency_count' => $frequencyCount,
                'monetary_amount' => $monetaryAmount,
                'segment' => RfmSegment::classify($recencyDays, $frequencyCount),
            ];
        }

        return RfmSegment::withoutGlobalScopes()->updateOrCreate(
            ['contact_site_profile_id' => $profile->id],
            array_merge($attributes, [
                'owner_company_id' => $profile->owner_company_id,
                'calculated_at' => now(),
            ])
        );
    }
}
