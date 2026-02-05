<?php

namespace Jtl\Connector\Core\Controller;

use Jtl\Connector\Core\Config\CoreConfigInterface;
use Jtl\Connector\Core\Logger\LoggerService;
use Jtl\Connector\Core\Model\AbstractModel;
use Jtl\Connector\Core\Model\Product;
use Psr\Log\LoggerInterface;

class ProductController extends AbstractController implements DeleteInterface
{
    public function __construct(CoreConfigInterface $config, LoggerInterface $logger, LoggerService $loggerService)
    {
        parent::__construct($config, $logger, $loggerService);
    }

    protected function updateModel(Product $model): void
    {
        $this->updateProductPimcore($model);
    }

    /**
     * @inheritDoc
     */
    public function delete(AbstractModel ...$models): array
    {
        $useBulk = $this->config->get('pimcore.api.useBulk', false);

        // Filter valid Product models
        $products = [];
        foreach ($models as $model) {
            if (!$model instanceof Product) {
                $this->logger->error('Invalid model type. Expected Product, got ' . get_class($model));
                continue;
            }
            $products[] = $model;
        }

        if (empty($products)) {
            return $models;
        }

        if ($useBulk) {
            $this->loggerService->get('bulk')->info(sprintf(
                'BULK Delete started: %d product(s)',
                count($products)
            ));

            try {
                $this->bulkDeleteProducts($products);
                $this->loggerService->get('bulk')->info('BULK Delete finished successfully');
            } catch (\Throwable $e) {
                $this->loggerService->get('bulk')->error('BULK Delete error: ' . $e->getMessage());
                throw $e;
            }
        } else {
            // Single delete fallback
            foreach ($products as $product) {
                $this->logger->info(\sprintf(
                    'Product delete requested (host=%d, sku/endpoint=%s)',
                    $product->getId()->getHost(),
                    $product->getId()->getEndpoint()
                ));

                $this->deleteProduct($product);
            }
        }

        return $models;
    }
}