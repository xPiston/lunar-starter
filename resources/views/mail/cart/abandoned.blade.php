<x-mail::message>
# {{ $cart->firstName ? 'Hi ' . $cart->firstName . ',' : 'Still thinking it over?' }}

You left {{ $cart->itemCount() === 1 ? 'something' : 'a few things' }} in your cart at {{ config('app.name') }}. It's still here.

<x-mail::table>
| Item | Qty | Total |
| :--- | :-: | ----: |
@foreach ($cart->lines as $line)
| {{ $line->name }} | {{ $line->quantity }} | {{ $line->lineTotal->formatted }} |
@endforeach
</x-mail::table>

**Total: {{ $cart->total->formatted }}**

<x-mail::button :url="$recoveryUrl">
Back to my cart
</x-mail::button>

Stock isn't reserved, so anything popular may sell out before you get back.

Thanks,<br>
{{ config('app.name') }}

<x-slot:subcopy>
This is the only reminder we'll send about this cart. If you'd rather not get
them at all, [unsubscribe here]({{ $unsubscribeUrl }}).
</x-slot:subcopy>
</x-mail::message>
