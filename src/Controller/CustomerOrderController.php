<?php

namespace Jtl\Connector\Core\Controller;

use Jtl\Connector\Core\Model\CustomerOrder;
use Jtl\Connector\Core\Model\CustomerOrderBillingAddress;
use Jtl\Connector\Core\Model\CustomerOrderItem;
use Jtl\Connector\Core\Model\CustomerOrderShippingAddress;
use Jtl\Connector\Core\Model\Identity;
use Jtl\Connector\Core\Model\KeyValueAttribute;
use Jtl\Connector\Core\Model\Product;
use Jtl\Connector\Core\Model\QueryFilter;

class CustomerOrderController extends AbstractController implements PullInterface
{
    public function pull(QueryFilter $queryFilter): array
    {
        $endpointUrl = $this->getEndpointUrl('getOrders');
        $client = $this->getHttpClient();

        $orders = [];

        try {
            $response = $client->request('GET', $endpointUrl);

            $statusCode = $response->getStatusCode();
            $data = $response->toArray();

            if ($statusCode !== 200 || !isset($data['success']) || $data['success'] !== true) {
                $this->logger->error('Pimcore getOrders error!');
                return [];
            }

            foreach ($data['orders'] as $orderData) {

                $items = $orderData['items'] ?? [];
                if (empty($items)) {
                    $this->logger->error('No items found for order: ' . $orderData['orderNumber']);
                    continue;
                }

                $isClickAndCollect = false;
                if (isset($orderData['isClickAndCollect']) && $orderData['isClickAndCollect'] === true) {
                    $isClickAndCollect = true;
                }

                $identity = new Identity((string)$orderData['pimId'], 0);
                $order = new CustomerOrder();
                $order->setId($identity);

                $order->setLanguageIso('de');

                $email = $orderData['customer']['email']??'';

                $order->setOrderNumber($orderData['orderNumber']);
                $setOrderCustomerNumber = $this->config->get('setOrderCustomerNumber');
                if ($setOrderCustomerNumber) {
                    $order->setCustomerId(new Identity($orderData['customer']['id']??'', 0));
                }

                $attribute = new KeyValueAttribute();
                $attribute->setKey('externeAuftragsnummer'); // oder 'order_number', 'order_id'
                $attribute->setValue($orderData['orderNumber']);
                $order->addAttribute($attribute);

                $order->setLanguageIso('de');
                $order->setCurrencyIso($orderData['currencyIso']?? 'EUR');
                $order->setCreationDate(\DateTime::createFromFormat('U', $orderData['orderDateUnix']));

                $order->setCustomerNote($orderData['customerComment'] ?? '');

                $attributeCustomerGroup = new KeyValueAttribute();
                $attributeCustomerGroup->setKey('customerGroup');
                $attributeCustomerGroup->setValue($orderData['customer']['tenantJtl']);
                $order->addAttribute($attributeCustomerGroup);

                if (!empty($orderData['idClickCollect'])) {
                    $attributeIdClickCollect = new KeyValueAttribute();
                    $attributeIdClickCollect->setKey('idClickCollect');
                    $attributeIdClickCollect->setValue($orderData['idClickCollect']);
                    $order->addAttribute($attributeIdClickCollect);
                }
                if (!empty($orderData['idRegionalprovision'])) {
                    $attributeIdRegionalprovision = new KeyValueAttribute();
                    $attributeIdRegionalprovision->setKey('idRegionalprovision');
                    $attributeIdRegionalprovision->setValue($orderData['idRegionalprovision']);
                    $order->addAttribute($attributeIdRegionalprovision);
                }

                if (!empty($orderData['versandart'])) {
                    $attributeShippingMethod = new KeyValueAttribute();
                    $attributeShippingMethod->setKey('Versandart');
                    $attributeShippingMethod->setValue($orderData['versandart']);
                    $order->addAttribute($attributeShippingMethod);
                }

                // Shipping address
                $isPackstation = !empty($orderData['delivery']['locationName']) && !empty($orderData['delivery']['postNumber']);
                $shippingAddress = new CustomerOrderShippingAddress();

                if ($isPackstation) {
                    $shippingAddress->setFirstName(!empty($orderData['customer']['firstName']) ? $orderData['customer']['firstName'] : '');
                    $shippingAddress->setLastName(!empty($orderData['customer']['lastName']) ? $orderData['customer']['lastName'] : '');
                    $shippingAddress->setCompany(!empty($orderData['customer']['company']) ? $orderData['customer']['company'] : '');
                    $shippingAddress->setStreet(!empty($orderData['delivery']['streetForLabel']) ? $orderData['delivery']['streetNumberForLabel'] : '');
                    $shippingAddress->setExtraAddressLine(!empty($orderData['delivery']['postNumber']) ? $orderData['delivery']['postNumber'] : '');
                    $shippingAddress->setZipCode(!empty($orderData['delivery']['zip']) ? $orderData['delivery']['zip'] : '');
                    $shippingAddress->setCity(!empty($orderData['delivery']['city']) ? $orderData['delivery']['city'] : '');
                    $shippingAddress->setCountryIso(!empty($orderData['delivery']['country']) ? $orderData['delivery']['country'] : 'DE');
                } else {
                    $shippingStreet = !empty($orderData['delivery']['street']) ? $orderData['delivery']['street'] : '';
                    $shippingHouseNumber = !empty($orderData['delivery']['houseNumber']) ? ' ' . $orderData['delivery']['houseNumber'] : '';
                    $shippingStreetWithHouseNumber = $shippingStreet . $shippingHouseNumber;
                    $shippingAddress->setFirstName(!empty($orderData['delivery']['firstName']) ? $orderData['delivery']['firstName'] : '');
                    $shippingAddress->setLastName(!empty($orderData['delivery']['lastName']) ? $orderData['delivery']['lastName'] : '');
                    $shippingAddress->setCompany(!empty($orderData['delivery']['company']) ? $orderData['delivery']['company'] : '');
                    $shippingAddress->setStreet($shippingStreetWithHouseNumber);
                    $shippingAddress->setZipCode(!empty($orderData['delivery']['zip']) ? $orderData['delivery']['zip'] : '');
                    $shippingAddress->setCity(!empty($orderData['delivery']['city']) ? $orderData['delivery']['city'] : '');
                    $shippingAddress->setCountryIso(!empty($orderData['delivery']['country']) ? $orderData['delivery']['country'] : 'DE');
                }
                $shippingAddress->setEMail($email);
                $shippingAddress->setCustomerId(new Identity($orderData['customer']['id']??'', 0));
                $order->setShippingAddress($shippingAddress);

                // Billing address
                $billingAddress = new CustomerOrderBillingAddress();
                $billingAddress->setCountryIso(!empty($orderData['customer']['country']) ? $orderData['customer']['country'] : 'DE');
                $billingAddress->setFirstName(!empty($orderData['customer']['firstName']) ? $orderData['customer']['firstName'] : 'n.a.');
                $billingAddress->setLastName(!empty($orderData['customer']['lastName']) ? $orderData['customer']['lastName'] : 'n.a.');
                $billingAddress->setCompany(!empty($orderData['customer']['company']) ? $orderData['customer']['company'] : '');
                $billingAddress->setCity(!empty($orderData['customer']['city']) ? $orderData['customer']['city'] : 'n.a.');
                $billingAddress->setStreet(!empty($orderData['customer']['street']) ? ($orderData['customer']['street'] . ' ' . $orderData['customer']['houseNumber']) : 'n.a.');
                $billingAddress->setExtraAddressLine(!empty($orderData['customer']['extraAddressLine']) ? $orderData['customer']['extraAddressLine'] : '');
                $billingAddress->setZipCode(!empty($orderData['customer']['zip']) ? $orderData['customer']['zip'] : '00000');
                $billingAddress->setEMail($email);

                $order->setBillingAddress($billingAddress);

                $setShippingMethod = $this->config->get('shipping.methods.setShippingMethod', false);
                if ($setShippingMethod === true) {
                    $shippingMethodId = $this->determineShippingMethodId($orderData['delivery']['shippingMethod'], $isClickAndCollect);
                    if (!empty($shippingMethodId)) {
                        $shippingMethodIdentity = new Identity('', (int)$shippingMethodId);
                        $order->setShippingMethodId($shippingMethodIdentity);
                        $order->setShippingMethodName($orderData['delivery']['shippingMethod']);
                    }
                }

                if (!empty($orderData['batteryDepositCosts'])) {
                    $batteryDepositCostsSinglePriceGross = (float)$this->config->get('batteryDepositCostsItems.singlePrice', 7.50);
                    $batteryDepositCostsSingleSku = $this->config->get('batteryDepositCostsItems.sku', 'BATTERIEPFAND01');
                    $batteryDepositCostsSingleName = $this->config->get('batteryDepositCostsItems.name', 'Batteriepfand');
                    $batteryDepositCostsSingleVat = (float)$this->config->get('batteryDepositCostsItems.vat', 19);
                    $batteryDepositAmount = round($orderData['batteryDepositCosts'] / $batteryDepositCostsSinglePriceGross, 2);

                    $batteryDepositCostsOrderItem = new CustomerOrderItem();
                    $batteryDepositCostsOrderItem->setSku($batteryDepositCostsSingleSku);
                    $batteryDepositCostsOrderItem->setName($batteryDepositCostsSingleName);
                    $batteryDepositCostsOrderItem->setType(CustomerOrderItem::TYPE_PRODUCT);
                    $batteryDepositCostsOrderItem->setQuantity($batteryDepositAmount);
                    $batteryDepositCostsOrderItem->setPriceGross($batteryDepositCostsSinglePriceGross);
                    $batteryDepositCostsSinglePriceNet = $batteryDepositCostsSinglePriceGross / (1 + $batteryDepositCostsSingleVat / 100);
                    $batteryDepositCostsOrderItem->setPrice($batteryDepositCostsSinglePriceNet);
                    $batteryDepositCostsOrderItem->setVat($batteryDepositCostsSingleVat);
                    $order->addItem($batteryDepositCostsOrderItem);
                }

                // Items
                foreach ($orderData['items'] as $item) {

                    if (empty($item['jtlId'])) {
                        $this->logger->error('Pimcore getOrders error! Order item without JTL-ID! (' . $item['name'] . ' SKU: '.$item['sku'] . ' Order#: ' . $orderData['orderNumber']);
                        continue;
                    }

                    $customerOrderItem = new CustomerOrderItem();

                    // Please check Dropshipping Connector! We need JTL-ID and set it to
                    #$customerOrderItem->setProductId(new Identity($item['jtlId'], 0));

                    $customerOrderItem->setSku($item['sku']);
                    $customerOrderItem->setName($item['name']);
                    $customerOrderItem->setType(CustomerOrderItem::TYPE_PRODUCT);
                    $customerOrderItem->setQuantity((float)$item['quantity']);
                    $customerOrderItem->setPriceGross((float)$item['singlePrice']);
                    $customerOrderItem->setPrice((float)$item['singlePriceNet']);
                    $customerOrderItem->setVat((float)$item['vat']);
                    $order->addItem($customerOrderItem);
                }

                $order = $this->addShippingCostItem($order, $orderData);
                $order = $this->addCouponItems($order, $orderData);

                $order->setTotalSum($orderData['totalSum']);
                $order->setTotalSumGross($orderData['totalSumGross']);

                $paymentCode = $this->getPaymentCode($orderData['paymentInfo'] ?? []);
                $order->setPaymentModuleCode($paymentCode);

                $orders[] = $order;
            }

        } catch (\Throwable $e) {
            $this->loggerService->get('getPimcoreCustomerOrders')->error('HTTP request failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'code' => $e->getCode(),
                'trace' => $e->getTraceAsString(),
                'previous' => $e->getPrevious() ? [
                    'message' => $e->getPrevious()->getMessage(),
                    'trace' => $e->getPrevious()->getTraceAsString(),
                ] : null,
            ]);

            throw new \RuntimeException('HTTP request failed: ' . $e->getMessage(), 0, $e);
        }
        return $orders;
    }

    /**
     * Add shipping cost as a separate order item (FRACHT01)
     *
     * @param CustomerOrder $order
     * @param array $orderData
     * @return CustomerOrder
     */
    private function addShippingCostItem(CustomerOrder $order, array $orderData): CustomerOrder
    {
        $shippingCostGross = !empty($orderData['delivery']['costs']) ? (float)$orderData['delivery']['costs'] : 0.0;
        $shippingVatRate = 19.0;
        $shippingCostNet = $shippingCostGross / (1 + $shippingVatRate / 100);

        // Only add a shipping item if cost > 0
        if ($shippingCostGross <= 0) {
            $this->logger->debug(
                'Skipping shipping cost item - amount is 0',
                ['orderNumber' => $order->getOrderNumber()]
            );
            return $order;
        }

        // Create a shipping cost item
        $shippingItem = new CustomerOrderItem();
        $shippingItem->setProductId(new Identity('', 0)); // No product ID for shipping
        $shippingItem->setSku('FRACHT01'); // Freight SKU
        $shippingItem->setName('Frachtkosten'); // Shipping costs
        $shippingItem->setType(CustomerOrderItem::TYPE_PRODUCT); // Do not use type_shipping!
        $shippingItem->setQuantity(1.0);
        $shippingItem->setPriceGross($shippingCostGross);
        $shippingItem->setPrice($shippingCostNet);
        $shippingItem->setVat($shippingVatRate);
        $shippingItem->setNote('Frachtkosten');

        $order->addItem($shippingItem);

        $this->logger->info(
            'Added shipping cost item to order',
            [
                'orderNumber' => $order->getOrderNumber(),
                'sku' => 'FRACHT01',
                'gross' => $shippingCostGross,
                'net' => $shippingCostNet,
                'vat' => $shippingVatRate
            ]
        );
        return $order;
    }

    /**
     * Add applied discount coupons as negative coupon order items.
     *
     * Each coupon is split per VAT rate so the net tax base of every rate is
     * reduced correctly (required for mixed-VAT baskets, e.g. 19% + 7%).
     *
     * Expected Pimcore payload per order:
     *   "coupons": [
     *     {
     *       "code": "SOMMER10",
     *       "breakdown": [                       // preferred: pre-split by Pimcore
     *         { "vat": 19.0, "discountNet": -10.00, "discountGross": -11.90 },
     *         { "vat": 7.0,  "discountNet": -10.00, "discountGross": -10.70 }
     *       ]
     *       // fallback if no breakdown is given:
     *       // "discountGross": -22.60
     *     }
     *   ]
     *
     * @param CustomerOrder $order
     * @param array $orderData
     * @return CustomerOrder
     */
    private function addCouponItems(CustomerOrder $order, array $orderData): CustomerOrder
    {
        if ($this->config->get('coupons.enabled', false) !== true) {
            return $order;
        }

        $coupons = $orderData['coupons'] ?? [];
        if (empty($coupons)) {
            return $order;
        }

        $namePrefix = $this->config->get('coupons.namePrefix', 'Gutschein');
        $sku        = $this->config->get('coupons.sku', '');

        foreach ($coupons as $coupon) {
            $code = $coupon['code'] ?? '';

            // 1) Preferred: Pimcore already delivers the per-VAT breakdown.
            $breakdown = $coupon['breakdown'] ?? null;

            // 2) Fallback: only a total gross discount -> split it across the
            //    product items' gross sums per VAT rate.
            if (empty($breakdown)) {
                $totalGross = isset($coupon['discountGross']) ? -abs((float)$coupon['discountGross']) : 0.0;
                if ($totalGross === 0.0) {
                    $this->logger->warning('Coupon without usable amount skipped', [
                        'orderNumber' => $order->getOrderNumber(),
                        'code' => $code,
                    ]);
                    continue;
                }
                $breakdown = $this->splitDiscountByVat($totalGross, $order);
            }

            foreach ($breakdown as $part) {
                $vat   = (float)($part['vat'] ?? 0.0);
                $gross = -abs((float)($part['discountGross'] ?? 0.0));
                if ($gross === 0.0) {
                    continue;
                }
                // Prefer Pimcore's net; otherwise derive it from gross + vat.
                $net = isset($part['discountNet'])
                    ? -abs((float)$part['discountNet'])
                    : round($gross / (1 + $vat / 100), 4);

                $couponItem = new CustomerOrderItem();
                $couponItem->setType(CustomerOrderItem::TYPE_COUPON);
                $couponItem->setName(trim($namePrefix . ' ' . $code));
                $couponItem->setNote($code);
                if ($sku !== '') {
                    $couponItem->setSku($sku);
                }
                $couponItem->setQuantity(1.0);
                $couponItem->setPriceGross($gross);
                $couponItem->setPrice($net);
                $couponItem->setVat($vat);
                $order->addItem($couponItem);

                $this->logger->info('Added coupon item to order', [
                    'orderNumber' => $order->getOrderNumber(),
                    'code' => $code,
                    'vat' => $vat,
                    'gross' => $gross,
                    'net' => $net,
                ]);
            }
        }

        return $order;
    }

    /**
     * Distribute a total gross discount proportionally across the VAT rates of
     * the order's product items. The last bucket absorbs the rounding remainder
     * so the sum stays exact.
     *
     * @param float $totalGross Negative total gross discount
     * @param CustomerOrder $order
     * @return array<int, array{vat: float, discountGross: float}>
     */
    private function splitDiscountByVat(float $totalGross, CustomerOrder $order): array
    {
        $grossPerVat = [];
        foreach ($order->getItems() as $item) {
            if ($item->getType() !== CustomerOrderItem::TYPE_PRODUCT) {
                continue;
            }
            $vatKey = (string)$item->getVat();
            $grossPerVat[$vatKey] = ($grossPerVat[$vatKey] ?? 0.0)
                + $item->getPriceGross() * $item->getQuantity();
        }

        $base = array_sum($grossPerVat);
        if ($base <= 0.0) {
            $this->logger->warning('Cannot split coupon - no positive product gross base', [
                'orderNumber' => $order->getOrderNumber(),
            ]);
            return [];
        }

        $parts     = [];
        $allocated = 0.0;
        $rates     = array_keys($grossPerVat);
        $lastRate  = end($rates);

        foreach ($grossPerVat as $vat => $vatGross) {
            if ($vat === $lastRate) {
                // last bucket gets the remainder so the total stays exact
                $share = round($totalGross - $allocated, 4);
            } else {
                $share = round($totalGross * ($vatGross / $base), 4);
                $allocated += $share;
            }
            $parts[] = ['vat' => (float)$vat, 'discountGross' => $share];
        }

        return $parts;
    }

    /**
     * Determine JTL-WaWi shipping method ID based on country and delivery type
     *
     * @param string $shippingMethodIdentifier
     * @param bool $isClickAndCollect Is this a Click & Collect order?
     * @return string JTL-WaWi shipping method ID
     */
    private function determineShippingMethodId(string $shippingMethodIdentifier, bool $isClickAndCollect): string
    {
        $default = $this->config->get('shipping.methods.default');
        return $this->config->get('shipping.methods.'.$shippingMethodIdentifier, $default);
    }

    /**
     * Check if a country code is an EU member state
     *
     * @param string $isoCountryCode ISO 3166-1 alpha-2 country code
     * @return bool
     */
    private function isEuropeanCountry(string $isoCountryCode): bool
    {
        $isoCountryCode = strtoupper(trim($isoCountryCode));

        // EU member states (27 countries as of 2025)
        $euCountries = [
            'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
            'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
            'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
        ];

        return in_array($isoCountryCode, $euCountries, true);
    }

    protected function updateModel(Product $model): void
    {
        // nothing to-do here
    }

    private function getPaymentCode(array $paymentInfo): string
    {
        $paymentMethod = $paymentInfo['paymentMethod'] ?? 'pimcore';
        $mapping= $this->config->get('mapping.paymentMethods');
        if (array_key_exists($paymentMethod, $mapping)) {
            return $mapping[$paymentMethod];
        }

        return 'payment_method_' . $paymentMethod . '_unknown';
    }
}