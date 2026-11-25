<x-mail::message>
Hello {{ $contact->name }},

{{ $body }}

Best regards,<br>
{{ $senderName }}<br>
{{ setting('site_name', config('app.name')) }}

<x-mail::panel>
**Your message on {{ $contact->created_at->format('d M Y') }}:**<br>
{{ \Illuminate\Support\Str::limit($contact->message, 600) }}
</x-mail::panel>
</x-mail::message>
