<?php

declare(strict_types=1);

namespace Patylook\Services;

use Patylook\Contracts\CustomerRepositoryInterface;
use Patylook\Contracts\MainSellerRepositoryInterface;
use Patylook\Contracts\OrderBannerRendererInterface;
use Patylook\Contracts\OrderBannerServiceInterface;
use Patylook\Contracts\OrderRepositoryInterface;
use Patylook\Dto\OrderShare;
use Patylook\Exceptions\HttpException;
use Patylook\Models\Order;
use Patylook\Models\OrderStatus;
use Patylook\Support\Whatsapp;

final class OrderBannerService implements OrderBannerServiceInterface
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
        private readonly CustomerRepositoryInterface $customers,
        private readonly MainSellerRepositoryInterface $sellers,
        private readonly OrderBannerRendererInterface $renderer,
    ) {
    }

    public function png(int $orderId): string
    {
        $order = $this->completed($orderId);
        return $this->renderer->render($order, $this->customers->findById($order->customerId), $this->sellers->find());
    }

    public function share(int $orderId): OrderShare
    {
        $order = $this->completed($orderId);
        $customer = $this->customers->findById($order->customerId);
        $seller = $this->sellers->find();
        $digits = Whatsapp::digits($customer->phone);
        return new OrderShare(
            orderId: $order->id,
            customerName: $customer->name,
            customerFirstName: $customer->firstName(),
            customerPhone: $customer->phone,
            chatUrl: $digits === null ? null : Whatsapp::chatUrl($digits),
            sellerName: $seller?->name,
            sellerPhone: $seller?->whatsapp,
        );
    }

    /** The summary is only for orders already completed. */
    private function completed(int $orderId): Order
    {
        $order = $this->orders->findById($orderId);
        if ($order->status !== OrderStatus::Completed) {
            throw new HttpException(409, 'Pedido não concluído', 'O comprovante só pode ser enviado para pedidos concluídos.');
        }
        return $order;
    }
}
