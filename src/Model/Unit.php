<?php

/**
 * @copyright  2010-2015 JTL-Software GmbH
 * @package    Jtl\Connector\Core\Model
 * @subpackage Product
 */

namespace Jtl\Connector\Core\Model;

use JMS\Serializer\Annotation as Serializer;

/**
 * Specifies product units like "piece", "bottle", "package".
 *
 * @access     public
 * @package    Jtl\Connector\Core\Model
 * @subpackage Product
 * @Serializer\AccessType("public_method")
 */
class Unit extends AbstractIdentity
{
    /**
     * @var UnitI18n[]
     * @Serializer\Type("array<Jtl\Connector\Core\Model\UnitI18n>")
     * @Serializer\SerializedName("i18ns")
     * @Serializer\AccessType("reflection")
     */
    protected $i18ns = [];

    /**
     * @param UnitI18n $i18n
     *
     * @return Unit
     */
    public function addI18n(UnitI18n $i18n): Unit
    {
        $this->i18ns[] = $i18n;

        return $this;
    }

    /**
     * @return UnitI18n[]
     */
    public function getI18ns(): array
    {
        return $this->i18ns;
    }

    /**
     * @param UnitI18n ...$i18ns
     *
     * @return Unit
     */
    public function setI18ns(UnitI18n ...$i18ns): Unit
    {
        $this->i18ns = $i18ns;

        return $this;
    }

    /**
     * @return Unit
     */
    public function clearI18ns(): Unit
    {
        $this->i18ns = [];

        return $this;
    }
}
