<?php
namespace Jtl\Connector\Core\Controller;

use Jtl\Connector\Core\Config\CoreConfigInterface;
use Jtl\Connector\Core\Logger\LoggerService;
use Jtl\Connector\Core\Model\AbstractModel;
use Jtl\Connector\Core\Model\Product;
use Psr\Log\LoggerInterface;

class ProductStockLevelController extends AbstractController
{
    public function __construct(CoreConfigInterface $config, LoggerInterface $logger, LoggerService $loggerService)
    {
        parent::__construct($config, $logger, $loggerService);
    }

    /**
     * Only the GLOBAL domain is allowed to push stock levels to Pimcore.
     * All other domains skip this push silently.
     */
    public function push(AbstractModel ...$models): array
    {
        if (!$this->isGlobalDomain()) {
            $this->logger->info('ProductStockLevel push skipped: only the GLOBAL domain may push stock levels to Pimcore.');
            return $models;
        }

        return parent::push(...$models);
    }

    protected function updateModel(Product $model): void
    {
        $this->updateProductPimcore($model, self::UPDATE_TYPE_PRODUCT_STOCK_LEVEL);
    }

    protected function getUpdateType(): string
    {
        return self::UPDATE_TYPE_PRODUCT_STOCK_LEVEL;
    }
}

/**
 * Example JSON of product data:
[
	{
        "id":
		[
            "",
            4955
        ],
		"sku": "83152-A-L",
		"stockLevel": 86
	}
]
*/