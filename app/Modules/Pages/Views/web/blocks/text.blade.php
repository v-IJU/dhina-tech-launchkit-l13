<section class="section">
    <div class="container-site max-w-3xl">
        @if (! empty($data['heading']))
            <h2 class="section-title">{{ $data['heading'] }}</h2>
        @endif
        @if (! empty($data['body']))
            <div class="prose-site mt-6">{!! $data['body'] !!}</div>
        @endif
    </div>
</section>
