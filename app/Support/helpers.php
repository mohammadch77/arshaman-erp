<?php

if (! function_exists('theme_icon')) {
    /**
     * نام آیکون واقعی را از نگاشت معنایی config/theme.php برمی‌گرداند.
     */
    function theme_icon(string $key): string
    {
        return config("theme.icons.{$key}", 'o-question-mark-circle');
    }
}
