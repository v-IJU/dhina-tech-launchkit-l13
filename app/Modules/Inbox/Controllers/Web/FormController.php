<?php

declare(strict_types=1);

namespace App\Modules\Inbox\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Inbox\Models\ContactMessage;
use App\Modules\Inbox\Models\Subscriber;
use App\Notifications\AdminAlert;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

use function Illuminate\Support\defer;

/**
 * Public contact and newsletter forms. Bots are filtered by a hidden
 * "website" field (honeypot) and the throttle on the routes.
 */
class FormController extends Controller
{
    public function contact(Request $request): RedirectResponse
    {
        if ($request->filled('website')) {
            return back()->with('contact_sent', true); // silently drop bots
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'subject' => ['nullable', 'string', 'max:150'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'page' => ['nullable', 'string', 'max:255'],
        ]);

        $message = ContactMessage::query()->create($data + [
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
        ]);

        AdminAlert::toAdmins("New enquiry from {$message->name}", route('admin.inbox.show', $message), 'inbox.view', 'bi-inbox');

        // Email the team after the response is sent, so the visitor never waits on SMTP.
        if ($to = setting('contact_email')) {
            defer(fn () => Mail::raw(
                "New enquiry from {$message->name} <{$message->email}>\n\n{$message->message}\n\nOpen in admin: ".route('admin.inbox.show', $message),
                fn ($m) => $m->to($to)->replyTo($message->email, $message->name)->subject('Website enquiry: '.($message->subject ?: $message->name))
            ));
        }

        return back()->with('contact_sent', true)->withFragment('contact');
    }

    public function subscribe(Request $request): RedirectResponse
    {
        if ($request->filled('website')) {
            return back();
        }

        $data = $request->validateWithBag('newsletter', ['email' => ['required', 'email', 'max:255']]);

        $subscriber = Subscriber::query()->firstOrCreate(['email' => Str::lower($data['email'])], ['ip_address' => $request->ip()]);

        if ($subscriber->wasRecentlyCreated) {
            AdminAlert::toAdmins("New newsletter subscriber {$subscriber->email}", route('admin.subscribers.index'), 'subscribers.view', 'bi-envelope-paper');
        }

        return back()->with('success', __('Thanks for subscribing!'));
    }
}
