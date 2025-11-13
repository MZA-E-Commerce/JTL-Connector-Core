<?php
namespace Jtl\Connector\Core\Controller;

use Jtl\Connector\Core\Config\CoreConfigInterface;
use Jtl\Connector\Core\Model\Product;
use Psr\Log\LoggerInterface;

class ProductPriceController extends AbstractController
{
    public function __construct(CoreConfigInterface $config, LoggerInterface $logger)
    {
        parent::__construct($config, $logger);
    }

    protected function updateModel(Product $model): void
    {
        $this->updateProductPimcore($model, self::UPDATE_TYPE_PRODUCT_PRICE);
    }
}