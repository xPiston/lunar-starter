<x-mail::message>
# {{ $copy['headline'] }}

{{ $change->firstName ? 'Hi ' . $change->firstName . ',' : 'Hello,' }}

{{ $copy['body'] }}

Order **{{ $change->reference }}** — {{ $change->status->label }}

<x-mail::button :url="$lookupUrl">
Track this order
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
