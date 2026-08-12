<?php

namespace App\Services\Schedule;

class ScheduleMailConfig
{
    /**
     * True when outbound mail looks ready for schedule distribution.
     * Allows smtp/sendmail/ses/etc. Rejects empty mailer, array driver, or placeholder from-address.
     * The log driver is treated as configured for local queue testing when from-address is real.
     */
    public static function isConfigured(): bool
    {
        $mailer = (string) config('mail.default');
        if ($mailer === '' || $mailer === 'array') {
            return false;
        }

        $from = (string) config('mail.from.address');
        if ($from === '' || strcasecmp($from, 'hello@example.com') === 0) {
            return false;
        }

        if ($mailer === 'smtp') {
            $host = (string) config('mail.mailers.smtp.host');
            if ($host === '' || $host === '127.0.0.1') {
                // Local smtp without a real relay — still OK if MAIL_MAILER=log elsewhere;
                // for smtp, require non-loopback unless explicitly allowed.
                if (! (bool) config('schedule.distribution.allow_local_smtp', false)) {
                    return false;
                }
            }
        }

        return true;
    }

    public static function notConfiguredMessage(): string
    {
        return 'Mail is not configured for schedule distribution. Update MAIL_* in .env (real MAIL_FROM_ADDRESS and a non-array mailer), then retry. PDF download still works.';
    }
}
