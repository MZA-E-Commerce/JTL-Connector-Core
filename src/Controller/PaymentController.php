<?php

namespace Jtl\Connector\Core\Controller;

use Jtl\Connector\Core\Model\Identity;
use Jtl\Connector\Core\Model\Payment;
use Jtl\Connector\Core\Model\Product;
use Jtl\Connector\Core\Model\QueryFilter;

class PaymentController extends AbstractController implements PullInterface, StatisticInterface
{
    protected function updateModel(Product $model): void
    {
        // not needed for payments
    }

    public function statistic(QueryFilter $queryFilter): int
    {
        $endpointUrl = $this->getEndpointUrl('getPayments');
        $client = $this->getHttpClient();

        try {
            $response = $client->request('GET', $endpointUrl);

            $statusCode = $response->getStatusCode();
            $data = $response->toArray();

            if ($statusCode !== 200 || !isset($data['success']) || $data['success'] !== true) {
                $this->logger->error('Pimcore getPayments error (statistic)!');
                return 0;
            }

            $count = count($data['payments'] ?? []);
            $this->logger->info('paymentStatistic: ' . $count . ' payment(s) available');

            return $count;

        } catch (\Throwable $e) {
            $this->loggerService->get('paymentStatistic')->error('HTTP request failed: ' . $e->getMessage());
            return 0;
        }
    }

    public function pull(QueryFilter $queryFilter): array
    {
        $endpointUrl = $this->getEndpointUrl('getPayments');
        $client = $this->getHttpClient();

        $payments = [];

        try {
            $response = $client->request('GET', $endpointUrl);

            $statusCode = $response->getStatusCode();
            $data = $response->toArray();

            if ($statusCode !== 200 || !isset($data['success']) || $data['success'] !== true) {
                $this->logger->error('Pimcore getOrders error (paymentPull)!');
                return [];
            }

            foreach ($data['payments'] as $paymentData) {
                $transactionId = $paymentData['paymentInfo']['transactionId'] ?? '';

                if (empty($transactionId)) {
                    $this->logger->debug('No transactionId for order: ' . ($paymentData['orderNumber'] ?? 'unknown'));
                    continue;
                }

                $paymentCode = $this->getPaymentCode($paymentData['paymentInfo'] ?? []);

                $payment = new Payment();
                $payment->setId(new Identity((string)$paymentData['pimId'], 0));
                $payment->setCustomerOrderId(new Identity((string)$paymentData['pimId'], 0));
                $payment->setTransactionId($transactionId);
                $payment->setPaymentModuleCode($paymentCode);
                $payment->setTotalSum((float)($paymentData['totalSumGross'] ?? 0.0));
                $payment->setCreationDate(
                    \DateTime::createFromFormat('U', (string)$paymentData['orderDateUnix']) ?: null
                );

                $payments[] = $payment;

                $this->loggerService->get('paymentPull')->info('Payment pulled', [
                    'orderNumber' => $paymentData['orderNumber'] ?? '',
                    'pimId' => $paymentData['pimId'] ?? '',
                    'transactionId' => $transactionId,
                    'paymentModuleCode' => $paymentCode,
                    'totalSum' => (float)($paymentData['totalSumGross'] ?? 0.0),
                ]);
            }

        } catch (\Throwable $e) {
            $this->loggerService->get('paymentPull')->error('HTTP request failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            throw new \RuntimeException('HTTP request failed: ' . $e->getMessage(), 0, $e);
        }

        return $payments;
    }

    private function getPaymentCode(array $paymentInfo): string
    {
        $paymentMethod = $paymentInfo['paymentMethod'] ?? 'pimcore';
        $mapping = $this->config->get('mapping.paymentMethods');
        if (array_key_exists($paymentMethod, $mapping)) {
            return $mapping[$paymentMethod];
        }

        return 'payment_method_' . $paymentMethod . '_unknown';
    }
}