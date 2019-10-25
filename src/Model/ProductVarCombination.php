<?php
/**
 * @copyright 2010-2015 JTL-Software GmbH
 * @package Jtl\Connector\Core\Model
 * @subpackage Product
 */

namespace Jtl\Connector\Core\Model;

use InvalidArgumentException;
use JMS\Serializer\Annotation as Serializer;

/**
 * Product to productVariationValue Allocation.
 *
 * @access public
 * @package Jtl\Connector\Core\Model
 * @subpackage Product
 * @Serializer\AccessType("public_method")
 */
class ProductVarCombination extends DataModel
{
    /**
     * @var Identity Reference to productVariation
     * @Serializer\Type("Jtl\Connector\Core\Model\Identity")
     * @Serializer\SerializedName("productVariationId")
     * @Serializer\Accessor(getter="getProductVariationId",setter="setProductVariationId")
     */
    protected $productVariationId = null;
    
    /**
     * @var Identity Reference to productVariationValue
     * @Serializer\Type("Jtl\Connector\Core\Model\Identity")
     * @Serializer\SerializedName("productVariationValueId")
     * @Serializer\Accessor(getter="getProductVariationValueId",setter="setProductVariationValueId")
     */
    protected $productVariationValueId = null;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->productVariationId = new Identity();
        $this->productVariationValueId = new Identity();
    }

    /**
     * @param Identity $productVariationId Reference to productVariation
     * @return ProductVarCombination
     * @throws InvalidArgumentException if the provided argument is not of type 'Identity'.
     */
    public function setProductVariationId(Identity $productVariationId): ProductVarCombination
    {
        $this->productVariationId = $productVariationId;
        
        return $this;
    }
    
    /**
     * @return Identity Reference to productVariation
     */
    public function getProductVariationId(): Identity
    {
        return $this->productVariationId;
    }
    
    /**
     * @param Identity $productVariationValueId Reference to productVariationValue
     * @return ProductVarCombination
     * @throws InvalidArgumentException if the provided argument is not of type 'Identity'.
     */
    public function setProductVariationValueId(Identity $productVariationValueId): ProductVarCombination
    {
        $this->productVariationValueId = $productVariationValueId;
        
        return $this;
    }
    
    /**
     * @return Identity Reference to productVariationValue
     */
    public function getProductVariationValueId(): Identity
    {
        return $this->productVariationValueId;
    }
}
