<?php

namespace App\Modules\CRM\Actions;

use App\Modules\Core\Models\User;
use App\Modules\CRM\Models\ContactSiteProfile;
use App\Modules\CRM\Services\ContactMatcher;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreateContactSiteProfile
{
    private const DUPLICATE_PROFILE_MESSAGE = 'این مخاطب از قبل در این شرکت پروفایل دارد.';

    /**
     * @param  array{full_name: string, phone: string, email: ?string, site_full_name: ?string, owner_company_id: string}  $data
     */
    public function handle(array $data, User $actor, ContactMatcher $matcher): ContactSiteProfile
    {
        Gate::forUser($actor)->authorize('create', [ContactSiteProfile::class, $data['owner_company_id']]);

        return DB::transaction(function () use ($data, $actor, $matcher) {
            $contact = $matcher->findOrCreateContact($data['full_name'], $data['phone'], $data['email'], $actor);

            // چک قبل از insert، نه تکیه به گرفتن QueryException خام از دیتابیس —
            // Action تنها caller نیست (بند ۹ CLAUDE.md)، پس این گارد باید همین‌جا
            // باشد، نه فقط پیش‌بینی سمت Livewire.
            if ($this->hasDuplicateProfile($contact->id, $data['owner_company_id'])) {
                $this->throwDuplicateProfileException();
            }

            try {
                return ContactSiteProfile::create([
                    'owner_company_id' => $data['owner_company_id'],
                    'contact_id' => $contact->id,
                    'site_full_name' => $data['site_full_name'],
                    'first_seen_at' => now(),
                    'created_by_user_id' => $actor->id,
                    'updated_by_user_id' => $actor->id,
                ]);
            } catch (QueryException $e) {
                // پنجره race واقعی: بین چک بالا و این insert، یک request موازی
                // دیگر همین (contact_id, owner_company_id) را ساخت و قید یکتای
                // uq_contact_site_profile را نقض کرد. فقط دقیقاً همین قید گرفته
                // می‌شود — هر QueryException دیگری خام بالا می‌رود تا خطای واقعی
                // دیگری پنهان نماند.
                if ($this->isDuplicateProfileConstraintViolation($e)) {
                    $this->throwDuplicateProfileException();
                }

                throw $e;
            }
        });
    }

    protected function hasDuplicateProfile(string $contactId, string $companyId): bool
    {
        return ContactSiteProfile::withoutGlobalScopes()
            ->where('contact_id', $contactId)
            ->where('owner_company_id', $companyId)
            ->exists();
    }

    /**
     * پیام خطای دقیق دیتابیس‌محرک است: MySQL نام قید (uq_contact_site_profile)
     * را در پیام می‌آورد، SQLite/Postgres به‌جایش هر دو ستون ترکیب یکتا را کنار
     * هم می‌گذارند. هر دو الگو را چک می‌کنیم تا هم تست‌ها (sqlite) هم دیتابیس
     * واقعی (MySQL) درست تشخیص داده شوند — و نه بیشتر، تا خطای نامرتبط دیگری
     * اشتباهاً «مخاطب تکراری» تشخیص داده نشود.
     */
    protected function isDuplicateProfileConstraintViolation(QueryException $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'uq_contact_site_profile')
            || (str_contains($message, 'contact_site_profiles.contact_id') && str_contains($message, 'contact_site_profiles.owner_company_id'));
    }

    private function throwDuplicateProfileException(): never
    {
        throw ValidationException::withMessages([
            'phone' => self::DUPLICATE_PROFILE_MESSAGE,
        ]);
    }
}
