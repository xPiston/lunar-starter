<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Application\Account\ListMyOrders;
use App\Application\Account\OrderNotFoundException;
use App\Application\Account\ShowMyOrder;
use App\Domain\Account\OrderSummary;
use App\Http\Controllers\Controller;
use App\Http\Seo\PageMeta;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class AccountController extends Controller
{
    public function orders(ListMyOrders $listMyOrders): Response
    {
        return Inertia::render('storefront/account/orders/index', [
            'meta' => (new PageMeta(title: 'My orders', description: 'Your past orders.', noindex: true))->toArray(),
            'orders' => array_map(
                static fn (OrderSummary $order): array => $order->toArray(),
                $listMyOrders->handle(),
            ),
        ]);
    }

    public function showOrder(string $reference, ShowMyOrder $showMyOrder): Response
    {
        try {
            $order = $showMyOrder->handle($reference);
        } catch (OrderNotFoundException $exception) {
            throw new NotFoundHttpException($exception->getMessage(), $exception);
        }

        return Inertia::render('storefront/account/orders/show', [
            'meta' => (new PageMeta(title: 'Order details', description: 'Your order details.', noindex: true))->toArray(),
            'order' => $order->toArray(),
        ]);
    }
}
