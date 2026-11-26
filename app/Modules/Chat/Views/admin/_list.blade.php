@forelse ($conversations as $c)
    <a href="{{ $c['url'] }}" class="chat-item" data-chat-open="{{ $c['id'] }}" data-chat-search="{{ \Illuminate\Support\Str::lower($c['user']['name'].' '.($c['user']['email'] ?? '')) }}">
        <span class="avatar" style="background: {{ $c['user']['color'] }}">
            @if ($c['user']['avatar'])<img src="{{ $c['user']['avatar'] }}" alt="">@else{{ $c['user']['initials'] }}@endif
        </span>
        <span class="chat-item-body">
            <span class="d-flex justify-content-between gap-2">
                <span class="fw-semibold text-truncate">{{ $c['user']['name'] }}</span>
                <small class="text-secondary flex-none">{{ $c['last']['time'] ?? '' }}</small>
            </span>
            <span class="d-flex justify-content-between gap-2">
                <small class="text-secondary text-truncate">@if ($c['last']['mine'] ?? false)You: @endif{{ $c['last']['text'] ?? 'No messages yet' }}</small>
                <span class="badge rounded-pill bg-primary {{ $c['unread'] ? '' : 'd-none' }}" data-chat-badge>{{ $c['unread'] }}</span>
            </span>
        </span>
    </a>
@empty
    <div class="p-4 text-center text-secondary small" data-chat-none>No chats yet.</div>
@endforelse
