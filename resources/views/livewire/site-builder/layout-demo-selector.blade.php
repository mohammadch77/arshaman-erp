<div>
    <x-header title="تنظیمات سایت" subtitle="لوگو، فاوآیکون، و دموی هدر/فوتر فعال" separator />

    <x-form wire:submit="save" class="gap-6 max-w-2xl">
        <x-card shadow title="هویت سایت">
            <x-input label="عنوان سایت" wire:model="site_title" :icon="theme_icon('site')" />
            <x-input label="شعار سایت" wire:model="site_tagline" />

            <x-file label="لوگو" wire:model="logo" accept="image/*" hint="حداکثر ۱ مگابایت">
                @if($existingLogoUrl && ! $logo)
                    <img src="{{ $existingLogoUrl }}" class="mt-2 h-12" />
                @endif
            </x-file>

            <x-file label="فاوآیکون" wire:model="favicon" accept="image/*" hint="حداکثر ۲۵۶ کیلوبایت">
                @if($existingFaviconUrl && ! $favicon)
                    <img src="{{ $existingFaviconUrl }}" class="mt-2 h-8" />
                @endif
            </x-file>
        </x-card>

        <x-card shadow title="هدر فعال">
            <div class="flex flex-col gap-2.5">
                @forelse($this->headerDemos as $demo)
                    <label class="flex cursor-pointer items-center gap-3 rounded-box border p-3.5 transition hover:border-primary/50 {{ $active_header_demo_id === $demo->id ? 'border-primary ring-2 ring-primary bg-primary/5' : 'border-base-300' }}">
                        <input type="radio" class="radio radio-primary" wire:model="active_header_demo_id" value="{{ $demo->id }}" />
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-box bg-primary/10 text-primary">
                            <x-icon :name="theme_icon('header')" class="w-4 h-4" />
                        </span>
                        <span class="font-medium">{{ $demo->name }}</span>
                    </label>
                @empty
                    <x-alert title="هیچ دموی هدری ساخته نشده است." :icon="theme_icon('warning')" class="alert-warning" />
                @endforelse
            </div>
        </x-card>

        <x-card shadow title="فوتر فعال">
            <div class="flex flex-col gap-2.5">
                @forelse($this->footerDemos as $demo)
                    <label class="flex cursor-pointer items-center gap-3 rounded-box border p-3.5 transition hover:border-primary/50 {{ $active_footer_demo_id === $demo->id ? 'border-primary ring-2 ring-primary bg-primary/5' : 'border-base-300' }}">
                        <input type="radio" class="radio radio-primary" wire:model="active_footer_demo_id" value="{{ $demo->id }}" />
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-box bg-primary/10 text-primary">
                            <x-icon :name="theme_icon('footer')" class="w-4 h-4" />
                        </span>
                        <span class="font-medium">{{ $demo->name }}</span>
                    </label>
                @empty
                    <x-alert title="هیچ دموی فوتری ساخته نشده است." :icon="theme_icon('warning')" class="alert-warning" />
                @endforelse
            </div>
        </x-card>

        <x-slot:actions>
            <x-button label="ذخیره" type="submit" class="btn-primary" :icon="theme_icon('save')" spinner="save" />
        </x-slot:actions>
    </x-form>
</div>
