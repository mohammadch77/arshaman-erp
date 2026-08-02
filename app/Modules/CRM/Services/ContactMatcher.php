<?php

namespace App\Modules\CRM\Services;

use App\Modules\CRM\Models\Contact;
use App\Modules\Core\Models\User;

/**
 * تشخیص Golden Record: آیا این موبایل/ایمیل قبلاً به یک مخاطب هلدینگی تعلق
 * دارد؟ Contact بین‌شرکتی است (بدون owner_company_id)، پس جست‌وجو روی کل
 * جدول contacts انجام می‌شود، نه فقط شرکت جاری — همین «قابل‌تشخیص در چند
 * شرکت» بودن دلیل وجود این سرویس است.
 */
class ContactMatcher
{
    public function findOrCreateContact(string $fullName, string $phone, ?string $email, User $actor): Contact
    {
        $existing = Contact::query()
            ->where('phone', $phone)
            ->when($email, fn ($query) => $query->orWhere('email', $email))
            ->first();

        if ($existing) {
            return $existing;
        }

        return Contact::create([
            'full_name' => $fullName,
            'phone' => $phone,
            'email' => $email,
            'created_by_user_id' => $actor->id,
            'updated_by_user_id' => $actor->id,
        ]);
    }
}
