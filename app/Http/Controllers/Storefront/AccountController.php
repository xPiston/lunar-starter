<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Application\Account\ListMyOrders;
use App\Application\Account\OrderNotFoundException;
use App\Application\Account\ShowMyOrder;
use App\Domain\Account\OrderSummary;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class AccountController extends Controller
{
    public function orders(ListMyOrders $listMyOrders): Response
    {
        return Inertia::render('storefront/account/orders/index', [
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
            'order' => $order->toArray(),
        ]);
    }
}
