<?php

namespace App\Modules\Process\Exceptions;

use RuntimeException;

/**
 * فقط برای چرخه/حلقه‌ی بی‌نهایت واقعی در گراف فرایند (نه خطاهای دیگر موتور)،
 * تا تست بتواند دقیقاً همین حالت را از سایر RuntimeException ها تفکیک کند.
 */
class ProcessCycleDetectedException extends RuntimeException {}
