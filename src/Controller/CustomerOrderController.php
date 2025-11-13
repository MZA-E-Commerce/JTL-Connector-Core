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

                if (!empty($orderData['delivery']['shippingMethod'])) {
                    $attributeVersandart = new KeyValueAttribute();
                    $attributeVersandart->setKey('Versandart');
                    $attributeVersandart->setValue(strtoupper($orderData['delivery']['shippingMethod']));
                    $order->addAttribute($attributeVersandart);
                }

                // Shipping address
                $shippingStreet = !empty($orderData['delivery']['street']) ? $orderData['delivery']['street'] : '';
                $shippingHouseNumber = !empty($orderData['delivery']['houseNumber']) ? ' ' . $orderData['delivery']['houseNumber'] : '';
                $shippingStreetWithHouseNumber = $shippingStreet . $shippingHouseNumber;
                $shippingAddress = new CustomerOrderShippingAddress();
                $shippingAddress->setCountryIso(!empty($orderData['delivery']['country']) ? $orderData['delivery']['country'] : 'DE');
                $shippingAddress->setFirstName(!empty($orderData['delivery']['firstName']) ? $orderData['delivery']['firstName'] : '');
                $shippingAddress->setLastName(!empty($orderData['delivery']['lastName']) ? $orderData['delivery']['lastName'] : '');
                $shippingAddress->setCompany(!empty($orderData['delivery']['company']) ? $orderData['delivery']['company'] : '');
                //$shippingAddress->setExtraAddressLine(!empty($orderData['delivery']['extraAddressLine']) ? $orderData['delivery']['extraAddressLine'] : '');
                $shippingAddress->setCity(!empty($orderData['delivery']['city']) ? $orderData['delivery']['city'] : '');
                $shippingAddress->setStreet($shippingStreetWithHouseNumber);
                $shippingAddress->setZipCode(!empty($orderData['delivery']['zip']) ? $orderData['delivery']['zip'] : '');
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

                // ToDo:
                // 1. Versandart "Click & Collect" (Andreas fragen) -> Versandart in WaWi auf "Abholung" setzen

                $shippingMethodId = $this->determineShippingMethodId($orderData['delivery']['shippingMethod'], $isClickAndCollect);
                if (!empty($shippingMethodId)) {
                    $shippingMethodIdentity = new Identity('', (int)$shippingMethodId);
                    $order->setShippingMethodId($shippingMethodIdentity);
                    $order->setShippingMethodName($orderData['delivery']['shippingMethod']);
                }

                // Items
                foreach ($orderData['items'] as $item) {

                    if (empty($item['jtlId'])) {
                        $this->logger->error('Pimcore getOrders error! Order item without JTL-ID! (' . $item['name'] . ' SKU: '.$item['sku'] . ' Order#: ' . $orderData['orderNumber']);
                        continue;
                    }

                    $customerOrderItem = new CustomerOrderItem();

                    // Please check Dropshipping Connector! We need JTL-ID and set it to
                    $customerOrderItem->setProductId(new Identity($item['jtlId'], 0));
                    $customerOrderItem->setSku($item['sku']);
                    $customerOrderItem->setName($item['name']);
                    $customerOrderItem->setType(CustomerOrderItem::TYPE_PRODUCT);
                    $customerOrderItem->setQuantity($item['quantity']);
                    $customerOrderItem->setPriceGross($item['totalPrice']);
                    $customerOrderItem->setPrice($item['totalPriceNet']);
                    $customerOrderItem->setVat($item['vat']);
                    $order->addItem($customerOrderItem);
                }

                $order = $this->addShippingCostItem($order, $orderData);

                $order->setTotalSum($orderData['totalSum']);
                $order->setTotalSumGross($orderData['totalSumGross']);

                $orders[] = $order;
            }

        } catch (\Throwable $e) {
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
        $shippingCostGross = $orderData['delivery']['costs']['gross'] ?? 0.0;
        $shippingCostNet = $orderData['delivery']['costs']['net'] ?? 0.0;
        $shippingVatRate = $orderData['delivery']['costs']['vat'] ?? 19.0; // ToDo: ist es ok, hier immer 19% zu nehmen?

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
}