<?php

declare(strict_types=1);

namespace App\Modules\Chat\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Chat\Events\ChatEvent;
use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Models\Message;
use App\Modules\Chat\Models\Participant;
use App\Modules\Chat\Support\ChatFiles;
use App\Modules\Chat\Support\ChatSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Team chat: one-to-one conversations between staff users.
 *
 *  chat.view  = use chat: see your conversations, reply, send images / files / voice notes
 *  chat.start = also search all staff and start a new conversation with anyone
 *
 * You can only message active staff who have chat.view themselves (otherwise they could never read it).
 */
class ChatController extends Controller
{
    private const PAGE = 40;

    public function index(Request $request): View
    {
        $user = $this->user($request);

        return view('chat::admin.index', [
            'conversations' => $this->conversationList($user),
            'open' => $request->integer('c') ?: null,
            'canStart' => $user->can('chat.start'),
            'accept' => ChatSettings::accept(),
            'maxMb' => (int) (ChatSettings::maxKb() / 1024),
            'allowImages' => ChatSettings::images(),
            'allowFiles' => ChatSettings::files(),
            'allowVoice' => ChatSettings::voice(),
            'realtime' => ! in_array(config('broadcasting.default'), [null, 'null', 'log'], true),
        ]);
    }

    /** The left column, refreshed by the page (JSON). */
    public function conversations(Request $request): JsonResponse
    {
        return response()->json(['conversations' => $this->conversationList($this->user($request))]);
    }

    /** "New chat" search: active staff with chat access, by name or email. */
    public function users(Request $request): JsonResponse
    {
        $me = $this->user($request);
        abort_unless($me->can('chat.start'), 403);
        $term = trim((string) $request->query('q', ''));

        $users = User::query()
            ->where('status', 'active')->whereKeyNot($me->id)
            ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")))
            ->with(['roles', 'permissions'])->orderBy('name')->limit(50)->get()
            ->filter(fn (User $u) => $u->can('chat.view'))->take(15)
            ->map(fn (User $u) => $this->person($u) + ['role' => Str::headline((string) $u->getRoleNames()->first())])
            ->values();

        return response()->json(['users' => $users]);
    }

    /** Open (or create) the direct conversation with this user. */
    public function start(Request $request): JsonResponse
    {
        $me = $this->user($request);
        $data = $request->validate(['user_id' => ['required', 'integer']]);
        $other = User::query()->where('status', 'active')->whereKeyNot($me->id)->findOrFail($data['user_id']);

        $conversation = Conversation::query()->where('direct_key', Conversation::directKey($me->id, $other->id))->first();

        if ($conversation === null) {
            // Only people with chat.start may open a NEW conversation; anyone may reopen an existing one.
            abort_unless($me->can('chat.start'), 403, 'You can reply to chats but not start new ones.');
            if (! $other->can('chat.view')) {
                throw ValidationException::withMessages(['user_id' => "{$other->name} doesn't have access to team chat."]);
            }

            $conversation = DB::transaction(function () use ($me, $other) {
                $c = Conversation::query()->create(['type' => 'direct', 'direct_key' => Conversation::directKey($me->id, $other->id), 'created_by' => $me->id]);
                $c->users()->attach([$me->id, $other->id]);

                return $c;
            });
        }

        return response()->json(['conversation' => $this->summary($conversation->load(['users', 'participants']), $me)]);
    }

    /** Messages of one conversation: newest page, older with ?before=id, newer with ?after=id. */
    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $me = $this->user($request);
        $this->authorizeParticipant($conversation, $me);

        $query = $conversation->messages()->withTrashed()->with('user:id,name');
        if ($after = $request->integer('after')) {
            $messages = $query->where('id', '>', $after)->oldest('id')->limit(200)->get();
        } else {
            $messages = $query->when($request->integer('before'), fn ($q, $before) => $q->where('id', '<', $before))
                ->latest('id')->limit(self::PAGE)->get()->reverse()->values();
        }

        $conversation->load(['users', 'participants']);
        $other = $conversation->otherUser($me);

        return response()->json([
            'conversation' => $this->summary($conversation, $me),
            'messages' => $messages->map(fn (Message $m) => $m->toChat())->all(),
            'has_more' => ! $request->integer('after') && $messages->count() === self::PAGE,
            'other_read' => $other ? $conversation->lastReadBy($other) : 0,
        ]);
    }

    public function send(Request $request, Conversation $conversation): JsonResponse
    {
        $me = $this->user($request);
        $this->authorizeParticipant($conversation, $me);

        $request->validate([
            'body' => ['nullable', 'string', 'max:5000', 'required_without:attachment'],
            'attachment' => ['nullable', 'file', 'max:'.ChatSettings::maxKb()],
            'voice' => ['nullable', 'boolean'],
            'duration' => ['nullable', 'integer', 'min:0', 'max:3600'],
        ]);

        $data = ['conversation_id' => $conversation->id, 'user_id' => $me->id, 'type' => 'text', 'body' => trim((string) $request->input('body')) ?: null];

        if ($file = $request->file('attachment')) {
            $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension());
            $type = ChatSettings::typeFor($extension, $request->boolean('voice'));
            if ($type === null) {
                throw ValidationException::withMessages(['attachment' => 'This kind of file can\'t be sent in chat.']);
            }
            $data = array_merge($data, [
                'type' => $type,
                'attachment_path' => ChatFiles::store($file),
                'attachment_name' => mb_substr($type === 'audio' ? 'Voice note.'.$extension : $file->getClientOriginalName(), 0, 200),
                'attachment_mime' => $file->getMimeType(),
                'attachment_size' => $file->getSize(),
                'duration' => $type === 'audio' ? $request->integer('duration') : null,
            ]);
        } elseif ($data['body'] === null) {
            throw ValidationException::withMessages(['body' => 'Type a message.']);
        }

        $message = Message::query()->create($data);
        $conversation->update(['last_message_at' => $message->created_at]);
        Participant::query()->where('conversation_id', $conversation->id)->where('user_id', $me->id)->update(['last_read_message_id' => $message->id]);

        $payload = $message->load('user:id,name')->toChat();
        ChatEvent::send($conversation->users()->get(), 'message', ['message' => $payload, 'from' => $this->person($me)]);

        return response()->json(['message' => $payload]);
    }

    /** "Seen": the other person's ticks turn blue. */
    public function read(Request $request, Conversation $conversation): JsonResponse
    {
        $me = $this->user($request);
        $this->authorizeParticipant($conversation, $me);

        $last = (int) $conversation->messages()->withTrashed()->max('id');
        Participant::query()->where('conversation_id', $conversation->id)->where('user_id', $me->id)->update(['last_read_message_id' => $last]);
        ChatEvent::send($conversation->users()->whereKeyNot($me->id)->get(), 'read', ['conversation_id' => $conversation->id, 'user_id' => $me->id, 'message_id' => $last]);

        return response()->json(['ok' => true, 'unread_total' => $this->unreadTotal($me)]);
    }

    public function typing(Request $request, Conversation $conversation): JsonResponse
    {
        $me = $this->user($request);
        $this->authorizeParticipant($conversation, $me);
        ChatEvent::send($conversation->users()->whereKeyNot($me->id)->get(), 'typing', ['conversation_id' => $conversation->id, 'user' => $me->name]);

        return response()->json(['ok' => true]);
    }

    /** Delete your own message for everyone ("This message was deleted"). */
    public function destroy(Request $request, Message $message): JsonResponse
    {
        $me = $this->user($request);
        abort_unless($message->user_id === $me->id, 403);

        ChatFiles::delete($message->attachment_path);
        $message->forceFill(['body' => null, 'attachment_path' => null])->save();
        $message->delete();

        $payload = $message->load('user:id,name')->toChat();
        ChatEvent::send(User::query()->whereIn('id', Participant::query()->where('conversation_id', $message->conversation_id)->select('user_id'))->get(), 'deleted', ['message' => $payload]);

        return response()->json(['message' => $payload]);
    }

    /** Images, files and voice notes: only for the people in the conversation. */
    public function attachment(Request $request, Message $message): StreamedResponse
    {
        $me = $this->user($request);
        $this->authorizeParticipant($message->load('conversation')->conversation, $me);
        abort_if($message->attachment_path === null || ! ChatFiles::disk()->exists($message->attachment_path), 404);

        $headers = ['Cache-Control' => 'private, max-age=86400', 'X-Content-Type-Options' => 'nosniff'];
        $name = (string) $message->attachment_name;

        // Only images and audio open inline; everything else downloads (no HTML/SVG/PDF rendered on our origin).
        return $request->boolean('download') || ! in_array($message->type, ['image', 'audio'], true)
            ? ChatFiles::disk()->download($message->attachment_path, $name, $headers)
            : ChatFiles::disk()->response($message->attachment_path, $name, $headers + ['Content-Type' => (string) $message->attachment_mime]);
    }

    /** Badge in the top bar. */
    public static function unreadTotal(User $user): int
    {
        return (int) Message::query()
            ->join('chat_participants as p', fn ($j) => $j->on('p.conversation_id', '=', 'chat_messages.conversation_id')->where('p.user_id', $user->id))
            ->where('chat_messages.user_id', '!=', $user->id)
            ->whereRaw('chat_messages.id > COALESCE(p.last_read_message_id, 0)')
            ->count();
    }

    /** @return list<array<string, mixed>> */
    private function conversationList(User $me): array
    {
        return Conversation::query()->for($me)
            ->with(['users', 'participants', 'lastMessage' => fn ($q) => $q->withTrashed()])
            ->orderByDesc('last_message_at')->orderByDesc('id')->limit(100)->get()
            ->map(fn (Conversation $c) => $this->summary($c, $me))->values()->all();
    }

    /** @return array<string, mixed> */
    private function summary(Conversation $conversation, User $me): array
    {
        $other = $conversation->otherUser($me);
        $last = $conversation->relationLoaded('lastMessage') ? $conversation->lastMessage : $conversation->lastMessage()->withTrashed()->first();

        return [
            'id' => $conversation->id,
            'user' => $other ? $this->person($other) : ['id' => null, 'name' => 'Deleted user', 'initials' => '?', 'avatar' => null, 'color' => '#94a3b8', 'active' => false],
            'last' => $last ? [
                'text' => match (true) {
                    $last->deleted_at !== null => 'Message deleted',
                    $last->type === 'image' => '📷 Photo',
                    $last->type === 'audio' => '🎤 Voice note',
                    $last->type === 'file' => '📎 '.$last->attachment_name,
                    default => (string) $last->body,
                },
                'mine' => $last->user_id === $me->id,
                'time' => $last->created_at->isToday() ? $last->created_at->format('H:i') : $last->created_at->format('d M'),
            ] : null,
            'unread' => $conversation->unreadFor($me),
            'url' => route('admin.chat.index', ['c' => $conversation->id]),
        ];
    }

    /** @return array<string, mixed> */
    private function person(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'initials' => initials($user->name),
            'avatar' => $user->getAttribute('avatar_url'),
            'color' => avatar_color($user->name),
            'active' => $user->status === 'active',
        ];
    }

    private function authorizeParticipant(Conversation $conversation, User $user): void
    {
        abort_unless($conversation->hasParticipant($user), 404);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
