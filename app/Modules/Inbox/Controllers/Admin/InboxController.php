<?php

declare(strict_types=1);

namespace App\Modules\Inbox\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Inbox\Mail\ContactReplyMail;
use App\Modules\Inbox\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class InboxController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:inbox.view', only: ['index', 'show']),
            new Middleware('permission:inbox.edit', only: ['unread', 'star']),
            new Middleware('permission:inbox.reply', only: ['reply']),
            new Middleware('permission:inbox.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View
    {
        return view('inbox::admin.index', $this->listData($request) + ['selected' => null]);
    }

    public function show(Request $request, ContactMessage $message): View
    {
        if (! $message->read_at) {
            $message->update(['read_at' => now()]);
        }

        return view('inbox::admin.index', $this->listData($request) + ['selected' => $message->load('replies.user')]);
    }

    public function unread(ContactMessage $message): RedirectResponse
    {
        $message->update(['read_at' => null]);

        return redirect()->route('admin.inbox.index')->with('success', 'Marked as unread.');
    }

    public function star(ContactMessage $message): RedirectResponse
    {
        $message->update(['is_starred' => ! $message->is_starred]);

        return back();
    }

    public function reply(Request $request, ContactMessage $message): RedirectResponse
    {
        $body = $request->validate(['body' => ['required', 'string', 'min:2', 'max:5000']])['body'];

        $message->replies()->create(['user_id' => $request->user()->id, 'body' => $body]);
        $message->update(['replied_at' => now()]);

        Mail::to($message->email, $message->name)->queue(new ContactReplyMail($message, $body, $request->user()->name));

        activity('inbox')->performedOn($message)->event('updated')->log("Replied to {$message->name}");

        return back()->with('success', "Reply queued to {$message->email}.");
    }

    public function destroy(ContactMessage $message): RedirectResponse
    {
        $message->delete();

        return redirect()->route('admin.inbox.index')->with('success', 'Message deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function listData(Request $request): array
    {
        $tab = in_array($request->query('tab'), ['unread', 'starred'], true) ? $request->query('tab') : 'all';

        $messages = ContactMessage::query()
            ->when($tab === 'unread', fn ($q) => $q->unread())
            ->when($tab === 'starred', fn ($q) => $q->where('is_starred', true))
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")
                ->orWhere('subject', 'like', "%{$term}%")->orWhere('message', 'like', "%{$term}%")))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return [
            'messages' => $messages,
            'tab' => $tab,
            'counts' => [
                'all' => ContactMessage::query()->count(),
                'unread' => ContactMessage::query()->unread()->count(),
                'starred' => ContactMessage::query()->where('is_starred', true)->count(),
            ],
        ];
    }
}
