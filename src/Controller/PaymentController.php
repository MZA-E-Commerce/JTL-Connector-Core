<?php

namespace Jtl\Connector\Core\Controller;

use Jtl\Connector\Core\Model\Product;
use Jtl\Connector\Core\Model\QueryFilter;

class PaymentController extends AbstractController implements PullInterface
{
    protected function updateModel(Product $model): void
    {
        // not needed for payments
    }

    public function pull(QueryFilter $queryFilter): array
    {
        $payments = CustomerOrderController::getPulledPayments();

        $this->logger->info('paymentPull: ' . count($payments) . ' payment(s) from CustomerOrderController');

        CustomerOrderController::clearPulledPayments();

        return $payments;
    }
}
