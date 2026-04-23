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
     * Only the GLOBAL connector is allowed to push stock levels to Pimcore.
     */
    public function push(AbstractModel ...$models): array
    {
        // Only the GLOBAL connector is allowed to push stock levels to Pimcore.
        return $models;
    }

    protected function updateModel(Product $model): void
    {
        // Only the GLOBAL connector is allowed to push stock levels to Pimcore.
    }

    protected function getUpdateType(): string
    {
        return self::UPDATE_TYPE_PRODUCT_STOCK_LEVEL;
    }
}