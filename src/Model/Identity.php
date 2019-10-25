<?php
/**
 * @copyright 2010-2013 JTL-Software GmbH
 * @package Jtl\Connector\Core\Model
 * @subpackage Internal
 */

namespace Jtl\Connector\Core\Model;

use Exception;
use InvalidArgumentException;
use \Jtl\Connector\Core\Model\Model;
use JMS\Serializer\Annotation as Serializer;

/**
 * Identity
 *
 * @access public
 * @package Jtl\Connector\Core\Model
 * @subpackage Internal
 * @Serializer\AccessType("public_method")
 */
class Identity extends Model
{
    /**
     * @var string
     * @Serializer\Type("string")
     * @Serializer\SerializedName("endpoint")
     * @Serializer\Accessor(getter="getEndpoint",setter="setEndpoint")
     */
    protected $endpoint = '';
    
    /**
     * @var int
     * @Serializer\Type("integer")
     * @Serializer\SerializedName("host")
     * @Serializer\Accessor(getter="getHost",setter="setHost")
     */
    protected $host = 0;
    
    /**
     * Constructor
     *
     * @param string $endpoint
     * @param int $host
     */
    public function __construct(string $endpoint = '', int $host = 0)
    {
        $this->endpoint = $endpoint;
        $this->host     = $host;
    }
    
    /**
     * Gets the value of endpoint.
     *
     * @return string
     */
    public function getEndpoint(): string
    {
        return $this->endpoint;
    }
    
    /**
     * Sets the value of endpoint.
     *
     * @param string $endpoint the endpoint
     * @return Identity
     */
    public function setEndpoint(string $endpoint): Identity
    {
        $this->endpoint = $endpoint;
        
        return $this;
    }
    
    /**
     * Gets the value of host.
     *
     * @return int
     */
    public function getHost(): int
    {
        return $this->host;
    }
    
    /**
     * Sets the value of host.
     *
     * @param int $host the host
     * @return Identity
     */
    public function setHost(int $host): Identity
    {
        $this->host = $host;
        
        return $this;
    }
    
    /**
     * Convert to Array
     *
     * @return array
     */
    public function toArray(): array
    {
        return [$this->endpoint, $this->host];
    }

    /**
     * Convert the Model into array
     *
     * @param array $publics
     * @return array
     */
    public function getPublic(array $publics = []): \stdClass
    {
        return (object) $this->toArray();
    }
    
    /**
     * Convert from Array
     *
     * @param array $data
     * @return Identity
     */
    public static function fromArray(array $data)
    {
        if ($data === null || count($data) != 2 || !array_key_exists(0, $data) || !array_key_exists(1, $data)) {
            throw new InvalidArgumentException('The data parameter can not be null and must contain two values');
        }
        
        return new self($data[0], $data[1]);
    }
    
    /**
     * Dynamic Converter
     *
     * @param $data
     * @return Identity
     */
    public static function convert($data)
    {
        if ($data instanceof self) {
            return $data;
        }
        
        if (!is_array($data) && $data !== null) {
            return new self($data);
        }
        
        if ($data === null || (is_array($data) && (count($data) != 2 || !array_key_exists(0,
                        $data) || !array_key_exists(1, $data)))) {
            return new self;
        }
        
        try {
            return self::fromArray($data);
        } catch (Exception $exc) {
            return new self;
        }
    }
}
