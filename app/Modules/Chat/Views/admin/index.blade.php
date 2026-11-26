@extends('layouts.admin')

@section('title', 'Team chat')

@php
    $config = [
        'me' => auth()->id(),
        'open' => $open,
        'canStart' => $canStart,
        'realtime' => $realtime,
        'maxMb' => $maxMb,
        'voice' => $allowVoice,
        'routes' => [
            'conversations' => route('admin.chat.conversations'),
            'users' => route('admin.chat.users'),
            'start' => route('admin.chat.start'),
            'messages' => route('admin.chat.messages', '__ID__'),
            'send' => route('admin.chat.send', '__ID__'),
            'read' => route('admin.chat.read', '__ID__'),
            'typing' => route('admin.chat.typing', '__ID__'),
            'destroy' => route('admin.chat.destroy', '__ID__'),
        ],
    ];
@endphp

@section('content')
    <x-admin.page-header title="Team chat" :breadcrumbs="['Team chat']">
        @can('settings.view')
            <a href="{{ route('admin.settings.edit', ['tab' => 'chat']) }}" class="btn btn-outline-secondary"><i class="bi bi-gear me-1"></i> Chat settings</a>
        @endcan
    </x-admin.page-header>

    <div class="chat card" data-chat='@json($config)'>
        {{-- Left: conversations --}}
        <aside class="chat-side">
            <div class="chat-side-head">
                <div class="position-relative flex-fill">
                    <i class="bi bi-search chat-search-icon"></i>
                    <input type="search" class="form-control" data-chat-filter placeholder="{{ $canStart ? 'Search or start a chat…' : 'Search chats…' }}" autocomplete="off">
                </div>
            </div>
            <div class="chat-side-list" data-chat-list>
                @include('chat::admin._list', ['conversations' => $conversations])
            </div>
            @if ($canStart)
                <div class="chat-people d-none" data-chat-people>
                    <div class="chat-people-title">Start a chat with</div>
                    <div data-chat-people-list></div>
                </div>
            @endif
        </aside>

        {{-- Right: the open conversation --}}
        <section class="chat-main" data-chat-main>
            <div class="chat-empty" data-chat-empty>
                <i class="bi bi-chat-heart"></i>
                <h5 class="mt-3 mb-1">Team chat</h5>
                <p class="text-secondary mb-0">
                    @if ($canStart)
                        Pick a chat on the left, or type a colleague's name to start one.
                    @else
                        Pick a chat on the left. A colleague with permission to start chats can message you first.
                    @endif
                </p>
            </div>

            <div class="chat-thread d-none" data-chat-thread>
                <header class="chat-head">
                    <button type="button" class="btn btn-icon btn-link text-body d-md-none" data-chat-back aria-label="Back"><i class="bi bi-arrow-left"></i></button>
                    <span class="avatar" data-chat-avatar></span>
                    <div class="lh-sm min-w-0">
                        <div class="fw-semibold text-truncate" data-chat-name></div>
                        <small class="text-secondary" data-chat-status></small>
                    </div>
                </header>

                <div class="chat-messages" data-chat-messages>
                    <div class="text-center py-2 d-none" data-chat-more><button type="button" class="btn btn-sm btn-outline-secondary">Load earlier messages</button></div>
                    <div data-chat-items></div>
                </div>

                <form class="chat-compose" data-chat-form autocomplete="off">
                    <div class="chat-emoji-panel d-none" data-chat-emoji-panel></div>
                    <div class="chat-recording d-none" data-chat-recording>
                        <span class="chat-rec-dot"></span> Recording <span data-chat-rec-time>0:00</span>
                        <button type="button" class="btn btn-sm btn-link text-danger ms-auto" data-chat-rec-cancel>Cancel</button>
                        <button type="button" class="btn btn-sm btn-primary" data-chat-rec-send><i class="bi bi-send"></i> Send</button>
                    </div>
                    <div class="chat-compose-row" data-chat-compose-row>
                        <button type="button" class="btn btn-icon btn-link text-body" data-chat-emoji aria-label="Emoji" title="Emoji"><i class="bi bi-emoji-smile fs-5"></i></button>
                        @if ($allowImages || $allowFiles)
                            <label class="btn btn-icon btn-link text-body mb-0" title="Photo or file (max {{ $maxMb }} MB)" aria-label="Attach">
                                <i class="bi bi-paperclip fs-5"></i>
                                <input type="file" class="d-none" data-chat-file accept="{{ $accept }}">
                            </label>
                        @endif
                        <textarea class="form-control" rows="1" maxlength="5000" placeholder="Type a message" data-chat-input></textarea>
                        @if ($allowVoice)
                            <button type="button" class="btn btn-icon btn-link text-body" data-chat-mic aria-label="Record a voice note" title="Voice note"><i class="bi bi-mic fs-5"></i></button>
                        @endif
                        <button type="submit" class="btn btn-primary btn-icon" aria-label="Send" title="Send (Enter)"><i class="bi bi-send"></i></button>
                    </div>
                    <div class="small text-danger px-2 pt-1 d-none" data-chat-error></div>
                </form>
            </div>
        </section>
    </div>
@endsection
