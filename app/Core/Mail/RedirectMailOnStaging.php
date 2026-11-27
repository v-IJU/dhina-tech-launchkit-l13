<?php

declare(strict_types=1);

namespace App\Core\Mail;

use Illuminate\Mail\Events\MessageSending;
use Symfony\Component\Mime\Address;

/**
 * MAIL_ALWAYS_TO=qa@yourcompany.com → every email (notifications, password resets, invoices…) goes to
 * that inbox instead of the real recipients. For staging with a copy of the production database:
 * customers never get a test email. The original recipients are kept in the X-Original-To header.
 */
class RedirectMailOnStaging
{
    public function handle(MessageSending $event): void
    {
        $to = (string) config('mail.always_to');
        if ($to === '') {
            return;
        }

        $message = $event->message;
        $original = array_map(fn (Address $a) => $a->getAddress(), [...$message->getTo(), ...$message->getCc(), ...$message->getBcc()]);
        $message->getHeaders()->addTextHeader('X-Original-To', implode(', ', $original));
        $message->getHeaders()->remove('Cc');
        $message->getHeaders()->remove('Bcc');
        $message->to(...array_map('trim', explode(',', $to)));
        $message->subject('[STAGING] '.$message->getSubject());
    }
}
