<x-mail::message>
# Thanks for your order!

Your order **{{ $order->reference }}** has been confirmed.

<x-mail::table>
| Item | Qty | Total |
| :--- | :-: | ----: |
@foreach ($order->lines as $line)
| {{ $line->name }} | {{ $line->quantity }} | {{ $line->lineTotal->formatted }} |
@endforeach
</x-mail::table>

Subtotal: {{ $order->subTotal->formatted }}
Shipping: {{ $order->shippingTotal->formatted }}
Tax: {{ $order->taxTotal->formatted }}

**Total: {{ $order->total->formatted }}**

@if ($order->shippingAddress)
## Shipping to

{{ $order->shippingAddress->firstName }} {{ $order->shippingAddress->lastName }}<br>
{{ $order->shippingAddress->lineOne }}<br>
@if ($order->shippingAddress->lineTwo)
{{ $order->shippingAddress->lineTwo }}<br>
@endif
{{ $order->shippingAddress->city }}, {{ $order->shippingAddress->postcode }}
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
