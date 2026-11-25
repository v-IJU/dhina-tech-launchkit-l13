<?php

declare(strict_types=1);

namespace App\Modules\Inbox\Mail;

use App\Modules\Inbox\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Reply to a website enquiry, sent from the queue.
 */
class ContactReplyMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public ContactMessage $contact, public string $body, public string $senderName) {}

    public function envelope(): Envelope
    {
        $replyTo = setting('contact_email');

        return new Envelope(
            subject: 'Re: '.($this->contact->subject ?: 'Your enquiry'),
            replyTo: $replyTo ? [new Address($replyTo, setting('site_name', config('app.name')))] : [],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'inbox::mail.reply');
    }
}
