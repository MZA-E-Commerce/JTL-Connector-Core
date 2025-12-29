<?php

namespace Jtl\Connector\Core\Controller;

use JMS\Serializer\SerializerInterface;
use Jtl\Connector\Core\Application\Application;
use Jtl\Connector\Core\Config\CoreConfigInterface;
use Jtl\Connector\Core\Logger\LoggerService;
use Jtl\Connector\Core\Model\AbstractModel;
use Jtl\Connector\Core\Model\Identity;
use Jtl\Connector\Core\Model\Product;
use Jtl\Connector\Core\Model\ProductPrice;
use Jtl\Connector\Core\Model\QueryFilter;
use Jtl\Connector\Core\Serializer\SerializerBuilder;
use Jtl\Connector\Core\Utilities\Validator\Validate;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

abstract class AbstractController
{
    /**
     * @var string
     */
    public const CUSTOMER_TYPE_B2C = 'c22d4b2da85e5c6154f1ec805b3405c9';

    public const PIMCORE_CUSTOMER_TYPE_B2C = 'B2C';

    /**
     * @var array
     */
    public const CUSTOMER_TYPE_MAPPINGS_REVERSE = [
        self::PIMCORE_CUSTOMER_TYPE_B2C => self::CUSTOMER_TYPE_B2C
    ];

    /**
     * @var string
     */
    protected const UPDATE_TYPE_PRODUCT = 'setProductData';

    /**
     * @var string
     */
    protected const UPDATE_TYPE_PRODUCT_STOCK_LEVEL = 'setProductStockLevel';

    /**
     * @var string
     */
    protected const UPDATE_TYPE_PRODUCT_PRICE = 'setProductPrice';

    /**
     * @var CoreConfigInterface
     */
    protected CoreConfigInterface $config;

    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * @var LoggerService
     */
    protected LoggerService $loggerService;

    /**
     * @var SerializerInterface
     */
    private SerializerInterface $serializer;

    /**
     * Using direct dependencies for better testing and easier use with a DI container.
     *
     * AbstractController constructor.
     * @param CoreConfigInterface $config
     * @param LoggerInterface $logger
     * @param LoggerService $loggerService
     */
    public function __construct(CoreConfigInterface $config, LoggerInterface $logger, LoggerService $loggerService)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->loggerService = $loggerService;

        $this->serializer = SerializerBuilder::create()->build();
    }

    /**
     * Template‑Method for all Controllers
     *
     * @param AbstractModel ...$models
     * @return AbstractModel[]
     */
    public function push(AbstractModel ...$models): array
    {
        $errors = [];

        foreach ($models as $i => $model) {
            // Check type
            if (!$model instanceof Product) {
                $this->logger->error('Invalid model type. Expected Product, got ' . get_class($model));
                continue;
            }

            $identity = $model->getId();
            // Check existing mapping
            if ($identity->getEndpoint()) {
                $this->logger->info(\sprintf(
                    'Product already has identity (host=%d endpoint=%d)',
                    $identity->getHost(),
                    $identity->getEndpoint()
                ));
            } else {
                // Get Pimcore ID
                try {
                    $pimcoreId = $this->getPimcoreId($model->getSku());
                } catch (\Throwable $e) {
                    $this->loggerService->get('pimcore')->error('Error fetching Pimcore ID for SKU ' . $model->getSku() . ': ' . $e->getMessage() . '. Try to create a new product in Pimcore.');
                    try {
                        $pimcoreId = $this->createPimcoreProduct($model);
                    } catch (\Throwable $e) {
                        $this->loggerService->get('pimcore')->error('Error creating Pimcore product: ' . $e->getMessage());
                        continue;
                    }
                }

                $identity = new Identity($pimcoreId, $identity->getHost());
                $model->setId($identity);
            }

            // Hook for the update
            try {
                $this->updateModel($model);
                $models[$i] = $model;
            } catch (\Throwable $e) {
                $this->logger->error('Error in updateModel(): ' . $e->getMessage());
                $errors[] = [
                    'sku' => $model->getSku(),
                    'error' => $e->getMessage()
                ];
                continue;
            }
        }

        if (!empty($errors)) {
            $errorMessage = 'Errors occurred while processing models: ' . json_encode($errors);
            throw new \RuntimeException($errorMessage);
        }

        return $models;
    }

    /**
     * @param string $endpointKey
     * @return string
     */
    protected function getEndpointUrl(string $endpointKey): string
    {
        $apiKey = $this->config->get('pimcore.api.key');
        if (empty($apiKey)) {
            throw new \RuntimeException('Pimcore API key is not set');
        }

        $url = $this->config->get('pimcore.api.url');
        return $url . $this->config->get('pimcore.api.endpoints.' . $endpointKey . '.url');
    }

    /**
     * @return HttpClientInterface
     */
    protected function getHttpClient(): HttpClientInterface
    {
        $client = HttpClient::create();
        return $client->withOptions([
            'headers' => [
                'X-API-KEY' => $this->config->get('pimcore.api.key'),
                'Accept' => 'application/json',
            ],
            'auth_basic' => [$this->config->get('pimcore.api.auth.username'), $this->config->get('pimcore.api.auth.password')]
        ]);
    }

    /**
     * @param string $sku
     * @return int
     */
    protected function getPimcoreId(string $sku): int
    {
        if (empty($sku)) {
            throw new \RuntimeException('SKU is empty');
        }

        $url = $this->getEndpointUrl('getId');
        $fullApiUrl = str_replace('{sku}', $sku, $url);
        $client = $this->getHttpClient();

        try {
            $response = $client->request($this->config->get('pimcore.api.endpoints.getId.method'), $fullApiUrl);

            $statusCode = $response->getStatusCode();
            $data = $response->toArray();

            if ($statusCode === 200 && isset($data['success']) && $data['success'] === true) {
                return (int)$data['id'];
            }
            $this->loggerService->get('getPimcoreId')->error('Pimcore API error: ' . ($data['error'] ?? 'Unknown error'));
            throw new \RuntimeException('Pimcore API error: ' . ($data['error'] ?? 'Unknown error'));

        } catch (TransportExceptionInterface|HttpExceptionInterface|DecodingExceptionInterface $e) {
            $this->loggerService->get('getPimcoreId')->error('HTTP request failed: ' . $e->getMessage());
            throw new \RuntimeException('HTTP request failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @param Product $product
     * @param string $type
     * @return void
     * @throws TransportExceptionInterface
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    protected function updateProductPimcore(Product $product, string $type = self::UPDATE_TYPE_PRODUCT): void
    {
        $httpMethod = $this->config->get('pimcore.api.endpoints.' . $type . '.method');
        $client = $this->getHttpClient();
        $fullApiUrl = $this->getEndpointUrl($type);

        // Set id of Pimcore product
        $postData = [
            'id' => $product->getId()->getEndpoint(),
            'jtlId' => (string)$product->getId()->getHost(),
            'uvp' => null,
            'netPrice' => null,
            'stockLevel' => null,
            'customerGroup' => self::PIMCORE_CUSTOMER_TYPE_B2C
        ];

        switch ($type) {
            case self::UPDATE_TYPE_PRODUCT_STOCK_LEVEL:
                $this->loggerService->get('updateProductPimcore')->info('Updating product stock level (SKU: ' . $product->getSku() . ')');
                $postData['stockLevel'] = $product->getStockLevel();
                break;
            case self::UPDATE_TYPE_PRODUCT_PRICE:
                $this->loggerService->get('updateProductPimcore')->info('Updating product price (SKU: ' . $product->getSku() . ')');
                $postData['netPrice'] = $this->getNetPrice($product, self::CUSTOMER_TYPE_B2C);
                $postData['specialPrice'] = $this->getSpecialPrice($product, self::CUSTOMER_TYPE_B2C);
                break;
            case self::UPDATE_TYPE_PRODUCT: // Check JTL WaWi setting "Artikel komplett senden"!!

                $this->loggerService->get('updateProductPimcore')->info('Updating product in Pimcore (SKU: ' . $product->getSku() . ')');

                $postData['netPrice'] = $this->getNetPrice($product, self::CUSTOMER_TYPE_B2C);
                $postData['specialPrice'] = $this->getSpecialPrice($product, self::CUSTOMER_TYPE_B2C);

                $useGrossPrices = $this->config->get('useGrossPrices');
                if ($useGrossPrices) {
                    $uvp = $product->getRecommendedRetailPrice();
                    if (!is_null($uvp)) {
                        $vat = $product->getVat();
                        $uvp = round($uvp * (1 + $vat / 100), 4);
                    }
                } else {
                    $uvp = $product->getRecommendedRetailPrice();
                }
                // Set UVP price
                $postData['uvp'] = $uvp;
                break;
        }

        // Tax rate
        $postData['taxRate'] = $product->getVat();

        try {
            $response = $client->request($httpMethod, $fullApiUrl, ['json' => $postData]);

            $statusCode = $response->getStatusCode();
            $responseData = $response->toArray();

            if ($statusCode !== 200) {
                $this->loggerService->get('updateProductPimcore')->error('Product updated failed in Pimcore (SKU: ' . $product->getSku() . ')' . ', Error: ' . ($responseData['error'] ?? 'Unknown error'));
            }

            if ($statusCode === 200 && isset($responseData['success']) && $responseData['success'] === true) {
                $this->loggerService->get('updateProductPimcore')->info('Product updated successfully in Pimcore (SKU: ' . $product->getSku() . ')');
                return;
            }
            $this->loggerService->get('updateProductPimcore')->error('Pimcore API error: ' . ($responseData['error'] ?? 'Unknown error'));
            throw new \RuntimeException('Pimcore API error: ' . ($responseData['error'] ?? 'Unknown error'));

        } catch (TransportExceptionInterface|HttpExceptionInterface|DecodingExceptionInterface $e) {
            if (method_exists($e, 'getResponse') && $e->getResponse() instanceof \Symfony\Contracts\HttpClient\ResponseInterface) {
                $errorMessage = $e->getResponse()?->getContent(false);
            } else {
                $errorMessage = $e->getMessage();
            }
            $this->loggerService->get('updateProductPimcore')->error($errorMessage);
            throw new \RuntimeException($errorMessage, $e->getCode(), $e);
        }
    }

    /**
     * @param Product $model
     * @return void
     */
    abstract protected function updateModel(Product $model): void;

    private function getSpecialPrice(Product $product, string $type = self::CUSTOMER_TYPE_B2C): array
    {
        $specialPrice = [];

        if (empty($product->getSpecialPrices())) {
            return $specialPrice;
        }

        foreach ($product->getSpecialPrices() as $priceModel) {
            if ($priceModel->getItems()) {
                foreach ($priceModel->getItems() as $item) {
                    if ($item->getCustomerGroupId()->getEndpoint() == $type) {
                        $specialPrice = [
                            'priceNet' => $item->getPriceNet(),
                            'activeFromDate' => $priceModel->getActiveFromDate(),
                            'activeUntilDate' => $priceModel->getActiveUntilDate(),
                            'considerDateLimit' => $priceModel->getConsiderDateLimit()
                        ];
                    }
                }
            }
        }

        return $specialPrice;
    }

    /**
     * @param Product $product
     * @param string $type
     * @return float|null
     */
    private function getNetPrice(Product $product, string $type = self::CUSTOMER_TYPE_B2C): ?float
    {
        $netPrice = null;

        foreach ($product->getPrices() as $priceModel) {
            if ($priceModel->getCustomerGroupId()->getEndpoint() == $type) {
                foreach ($priceModel->getItems() as $item) {
                    $netPrice = $item->getNetPrice();
                    break 2;
                }
            }
        }

        return $netPrice;
    }

    /**
     * @param Product $product
     * @return int
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    private function createPimcoreProduct(Product $product): int
    {
        $httpMethod = $this->config->get('pimcore.api.endpoints.createProduct.method');
        $client = $this->getHttpClient();
        $fullApiUrl = $this->getEndpointUrl('createProduct');

        $postData['published'] = false;
        $postData['sku'] = $product->getSku();

        $jtlId = $product->getId()?->getHost();
        $postData['jtlId'] = $jtlId;

        $postData['gtin'] = $product->getEan();
        $postData['stockLevel'] = $product->getStockLevel();
        $postData['vat'] = $product->getVat();
        $postData['isActive'] = $product->getIsActive();

        $name = $product->getSku() . '_NAME_NOT_SET';
        $i18n = $product->getI18ns();
        foreach ($i18n as $i18nModel) {
            if ($i18nModel->getLanguageIso() === 'de' && !empty($i18nModel->getName())) {
                $name = $i18nModel->getName();
            }
        }
        $postData['name'] = $name;

        try {
            $response = $client->request($httpMethod, $fullApiUrl, ['json' => $postData]);

            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                $this->loggerService->get('createPimcoreProduct')->error('Product creation failed in Pimcore (SKU: ' . $product->getSku() . ')');
            }

            $responseData = $response->toArray();

            if ($statusCode === 200 && isset($responseData['success'])
                && $responseData['success'] === true
                && !empty($responseData['id'])) {
                $this->loggerService->get('createPimcoreProduct')->info('Product created successfully in Pimcore (SKU: ' . $product->getSku() . ')');
                return (int)$responseData['id'];
            }

            $this->loggerService->get('createPimcoreProduct')->error('Pimcore API error: ' . ($responseData['error'] ?? 'Unknown error'));
            throw new \RuntimeException('Pimcore API error: ' . ($responseData['error'] ?? 'Unknown error'));

        } catch (TransportExceptionInterface|HttpExceptionInterface|DecodingExceptionInterface $e) {
            if (method_exists($e, 'getResponse') && $e->getResponse() instanceof \Symfony\Contracts\HttpClient\ResponseInterface) {
                $errorMessage = $e->getResponse()?->getContent(false);
            } else {
                $errorMessage = $e->getMessage();
            }
            $this->loggerService->get('createPimcoreProduct')->error($errorMessage);
            throw new \RuntimeException($errorMessage, $e->getCode(), $e);
        }
    }

    /**
     * @param Product $product
     * @return void
     * @throws \Exception
     */
    protected function deleteProduct(Product $product): void
    {
        $postData['jtlId'] = $product->getId()->getHost();

        $skuOrEndpoint = $product->getSku() ?: $product->getId()->getEndpoint();
        $postData['sku'] = $skuOrEndpoint;

        $client = $this->getHttpClient();
        $fullApiUrl = $this->getEndpointUrl('deleteProduct');
        $httpMethod = $this->config->get('pimcore.api.endpoints.deleteProduct.method');

        $ignoreProductNotFound = $this->config->get('pimcore.api.endpoints.deleteProduct.ignoreProductNotFound');

        $this->loggerService->get('deleteProduct')->info($httpMethod . ' -> ' . $fullApiUrl . ' -> ' . json_encode($postData));

        try {
            $response = $client->request($httpMethod, $fullApiUrl, ['json' => $postData]);
            $statusCode = $response->getStatusCode();
            $responseData = $response->toArray();

            if ($statusCode === 200 && isset($responseData['success'])
                && $responseData['success'] === true
                && !empty($responseData['id'])) {
                $this->loggerService->get('deleteProduct')->info('Product deleted successfully in Pimcore (PIM-ID: ' . $responseData['id'] . ')');
                return;
            }

            if ($statusCode === 404 && $ignoreProductNotFound === true) {
                $this->loggerService->get('deleteProduct')->info('Product not found in Pimcore!');
                return;
            }

            throw new \RuntimeException('API error: ' . ($responseData['error'] ?? 'Unknown error'));
        } catch (TransportExceptionInterface|HttpExceptionInterface|DecodingExceptionInterface $e) {
            if ($e->getCode() === 404 && $ignoreProductNotFound === true) {
                $this->loggerService->get('deleteProduct')->info('Product not found in Pimcore!');
                return;
            }
            $this->loggerService->get('deleteProduct')->error('HTTP request failed: ' . $e->getMessage());
            throw new \RuntimeException('HTTP request failed: ' . $e->getMessage(), 0, $e);
        }
    }
}