@extends('layouts.admin')

@section('content')
    <x-admin.page-header title="Contact inbox" :breadcrumbs="['Website CMS', 'Contact inbox']" />

    <div class="card overflow-hidden">
        <div class="row g-0" style="min-height: 560px">
            <div class="col-lg-5 col-xl-4 border-end d-flex flex-column">
                <div class="p-3 border-bottom d-flex flex-column gap-2">
                    <ul class="nav nav-tabs-soft">
                        @foreach (['all' => 'All', 'unread' => 'Unread', 'starred' => 'Starred'] as $key => $label)
                            <li class="nav-item"><a href="{{ route('admin.inbox.index', ['tab' => $key]) }}" class="nav-link py-1 {{ $tab === $key ? 'active' : '' }}">{{ $label }} <span class="badge badge-soft-secondary">{{ $counts[$key] }}</span></a></li>
                        @endforeach
                    </ul>
                    <form method="GET">
                        <input type="hidden" name="tab" value="{{ $tab }}">
                        <x-admin.search placeholder="Search messages…" />
                    </form>
                </div>
                <div class="list-group list-group-flush flex-fill">
                    @forelse ($messages as $message)
                        <a href="{{ route('admin.inbox.show', [$message, 'tab' => $tab]) }}" @class(['list-group-item list-group-item-action d-flex gap-3 py-3', 'active-soft bg-primary-subtle' => $selected?->is($message)])>
                            <x-admin.avatar :name="$message->name" size="sm" />
                            <div class="min-w-0 flex-fill">
                                <div class="d-flex justify-content-between gap-2">
                                    <span @class(['text-truncate', 'fw-bold' => ! $message->read_at])>{{ $message->name }}</span>
                                    <small class="text-secondary text-nowrap">{{ $message->created_at->diffForHumans(short: true) }}</small>
                                </div>
                                <div @class(['small text-truncate', 'fw-semibold' => ! $message->read_at])>
                                    @if ($message->is_starred)<i class="bi bi-star-fill text-warning"></i>@endif
                                    @if ($message->replied_at)<i class="bi bi-reply-fill text-success" title="Replied"></i>@endif
                                    {{ $message->subject ?: 'No subject' }}
                                </div>
                                <div class="small text-secondary text-truncate">{{ \Illuminate\Support\Str::limit($message->message, 80) }}</div>
                            </div>
                        </a>
                    @empty
                        <x-admin.empty icon="bi-inbox" message="No messages here." />
                    @endforelse
                </div>
                @if ($messages->hasPages())<div class="p-2 border-top">{{ $messages->links() }}</div>@endif
            </div>

            <div class="col-lg-7 col-xl-8 d-flex flex-column">
                @if ($selected)
                    <div class="p-4 border-bottom d-flex flex-wrap justify-content-between gap-3">
                        <div class="d-flex gap-3 align-items-center">
                            <x-admin.avatar :name="$selected->name" />
                            <div>
                                <h2 class="h5 fw-bold mb-0">{{ $selected->subject ?: 'No subject' }}</h2>
                                <div class="small text-secondary">
                                    {{ $selected->name }} · <a href="mailto:{{ $selected->email }}">{{ $selected->email }}</a>
                                    @if ($selected->phone) · <a href="tel:{{ $selected->phone }}">{{ $selected->phone }}</a>@endif
                                </div>
                            </div>
                        </div>
                        <div class="d-flex gap-2 align-items-start">
                            @can('inbox.edit')
                                <form method="POST" action="{{ route('admin.inbox.star', $selected) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-secondary"><i class="bi {{ $selected->is_starred ? 'bi-star-fill text-warning' : 'bi-star' }}"></i> {{ $selected->is_starred ? 'Starred' : 'Star' }}</button></form>
                                <form method="POST" action="{{ route('admin.inbox.unread', $selected) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-secondary">Mark unread</button></form>
                            @endcan
                            @can('inbox.delete')
                                <x-admin.delete-button :action="route('admin.inbox.destroy', $selected)" confirm="Delete this message?" label="Delete" />
                            @endcan
                        </div>
                    </div>
                    <div class="p-4 flex-fill d-flex flex-column gap-3">
                        <small class="text-secondary">Received {{ $selected->created_at->format('d M Y, H:i') }} from <code>{{ $selected->page ?: 'website' }}</code> · IP {{ $selected->ip_address }}</small>
                        <p class="mb-0" style="white-space: pre-line; line-height: 1.7">{{ $selected->message }}</p>
                        @foreach ($selected->replies as $reply)
                            <div class="border-start border-3 border-primary ps-3 mt-2">
                                <small class="text-secondary">{{ $reply->user?->name ?? 'Staff' }} replied {{ $reply->created_at->diffForHumans() }}</small>
                                <p class="mb-0" style="white-space: pre-line">{{ $reply->body }}</p>
                            </div>
                        @endforeach
                    </div>
                    @can('inbox.reply')
                        <form method="POST" action="{{ route('admin.inbox.reply', $selected) }}" class="p-4 border-top bg-body-tertiary d-flex flex-column gap-2">
                            @csrf
                            <x-admin.form.textarea name="body" :label="'Reply to '.$selected->name" rows="4" placeholder="Write a reply… it is emailed from the queue" required />
                            <div class="text-end"><button class="btn btn-primary"><i class="bi bi-send me-1"></i> Send reply</button></div>
                        </form>
                    @endcan
                @else
                    <div class="m-auto text-center text-secondary p-5">
                        <i class="bi bi-envelope-open fs-1 d-block mb-2 opacity-50"></i>
                        Select a message to read it.
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
