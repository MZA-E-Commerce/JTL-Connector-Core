<?php
namespace Jtl\Connector\Core\Controller;

use Jtl\Connector\Core\Config\CoreConfigInterface;
use Jtl\Connector\Core\Logger\LoggerService;
use Jtl\Connector\Core\Model\AbstractModel;
use Jtl\Connector\Core\Model\Product;
use Psr\Log\LoggerInterface;

class ProductPriceController extends AbstractController
{
    public function __construct(CoreConfigInterface $config, LoggerInterface $logger, LoggerService $loggerService)
    {
        parent::__construct($config, $logger, $loggerService);
    }

    /**
     * The GLOBAL domain must not push prices to Pimcore (only stock levels).
     */
    public function push(AbstractModel ...$models): array
    {
        return parent::push(...$models);
    }

    protected function updateModel(Product $model): void
    {
        $this->updateProductPimcore($model, self::UPDATE_TYPE_PRODUCT_PRICE);
    }

    protected function getUpdateType(): string
    {
        return self::UPDATE_TYPE_PRODUCT_PRICE;
    }
}