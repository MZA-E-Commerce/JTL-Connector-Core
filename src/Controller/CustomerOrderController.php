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
                // 2. Händlernummer als Auftragsmerkmal setzen

                // Items
                foreach ($orderData['items'] as $item) {

                    if (empty($item['jtlId'])) {
                        $this->logger->error('Pimcore getOrders error! Order item without JTL-ID! (' . $item['name'] . ' SKU: '.$item['sku'] . ' Order#: ' . $orderData['orderNumber']);
                        continue;
                    }

                    $customerOrderItem = new CustomerOrderItem();

                    // Please check Dropshipping Connector! We need JTL-ID and set it to
                    $customerOrderItem->setProductId(new Identity($item['jtlId'], 0));
                    #$customerOrderItem->setId(new Identity($item['productId'], 0));
                    $customerOrderItem->setSku($item['sku']);
                    $customerOrderItem->setName($item['name']);
                    $customerOrderItem->setType(CustomerOrderItem::TYPE_PRODUCT);
                    $customerOrderItem->setQuantity($item['quantity']);
                    $customerOrderItem->setPriceGross($item['totalPrice']);
                    $customerOrderItem->setPrice($item['totalPriceNet']);
                    $customerOrderItem->setVat($item['vat']);
                    $customerOrderItem->setNote('e.g. "ANP_SONDERPREIS=0|ANP_BEIGABE=0|ANP_KREDITKAUF=0"');
                    $order->addItem($customerOrderItem);
                }

                $order->setTotalSum($orderData['totalSum']);
                $order->setTotalSumGross($orderData['totalSumGross']);

                if (isset($orderData['salesRepresentative']) && !empty($orderData['salesRepresentative']['customerNumber'])) {
                    $order->setShippingInfo('ClickAndCollect|' .
                        $orderData['salesRepresentative']['customerNumber'] . '|' .
                        $orderData['salesRepresentative']['companyName'] . '|' .
                        $orderData['salesRepresentative']['id']
                    );
                }

                $orders[] = $order;
            }

        } catch (\Throwable $e) {
            throw new \RuntimeException('HTTP request failed: ' . $e->getMessage(), 0, $e);
        }
        return $orders;
    }

    protected function updateModel(Product $model): void
    {
        // nothing to-do here
    }
}