<?php
namespace Jtl\Connector\Core\Exception;

use Jtl\Connector\Core\Definition\ErrorCode;

class DefinitionException extends \Exception
{
    public const MODEL_MAPPING_NOT_EXISTS            = 10;
    public const UNKNOWN_IDENTITY_PROPERTY           = 20;
    public const UNKNOWN_MODEL                       = 30;
    public const IDENTITY_TYPE_MAPPING_NOT_EXISTS    = 40;
    public const UNKNOWN_IDENTITY_TYPE               = 50;
    public const UNKNOWN_CONFIG_OPTION               = 70;
    public const DEFAULT_VALUE_NOT_EXISTS            = 80;
    public const UNKNOWN_MOMENT                      = 100;
    public const UNKNOWN_RELATION_TYPE               = 110;
    public const UNKNOWN_IMAGE_IDENTITY_TYPE_MAPPING = 120;
    public const UNKNOWN_RPC_METHOD                  = 130;
    public const RELATION_TYPE_CANNOT_BE_EMPTY       = 140;

    /**
     * @param string $modelName
     * @return DefinitionException
     */
    public static function modelMappingNotExists(string $modelName): self
    {
        $msg = \sprintf('Identity type for model (%s) not found', $modelName);
        return new self($msg, self::MODEL_MAPPING_NOT_EXISTS);
    }

    /**
     * @param int $identityType
     * @return DefinitionException
     */
    public static function identityTypeMappingNotExists(int $identityType): self
    {
        $msg = \sprintf('Model for identity type (%s) not found', $identityType);
        return new self($msg, self::IDENTITY_TYPE_MAPPING_NOT_EXISTS);
    }

    /**
     * @param integer $identityType
     * @return DefinitionException
     */
    public static function unknownIdentityType(int $identityType): self
    {
        $msg = \sprintf('Identity type (%s) does not exist', $identityType);
        return new self($msg, self::UNKNOWN_IDENTITY_TYPE);
    }

    /**
     * @param string $modelName
     * @param string $propertyName
     * @return DefinitionException
     */
    public static function unknownIdentityProperty(string $modelName, string $propertyName): self
    {
        $msg = \sprintf('Unknown identity property (%s) in model (%s)', $propertyName, $modelName);
        return new self($msg, self::UNKNOWN_IDENTITY_PROPERTY);
    }

    /**
     * @param string $modelName
     * @return DefinitionException
     */
    public static function unknownModel(string $modelName): self
    {
        $msg = \sprintf('Model (%s) does not exist', $modelName);
        return new self($msg, self::UNKNOWN_MODEL);
    }

    /**
     * @param string $controllerName
     * @return DefinitionException
     */
    public static function unknownController(string $controllerName): self
    {
        $msg = \sprintf('Unknown controller (%s)', $controllerName);
        return new self($msg, ErrorCode::UNKNOWN_CONTROLLER);
    }

    /**
     * @param string $action
     * @return DefinitionException
     */
    public static function unknownAction(string $action): self
    {
        $msg = \sprintf('Unknown action (%s)', $action);
        return new self($msg, ErrorCode::UNKNOWN_ACTION);
    }

    /**
     * @param string $moment
     * @return DefinitionException
     */
    public static function unknownMoment(string $moment): self
    {
        $msg = \sprintf('Unknown moment (%s)', $moment);
        return new self($msg, self::UNKNOWN_MOMENT);
    }

    /**
     * @param string $relationType
     * @return DefinitionException
     */
    public static function unknownRelationType(string $relationType): self
    {
        $msg = \sprintf('Unknown relation type (%s)', $relationType);
        return new self($msg, self::UNKNOWN_RELATION_TYPE);
    }

    /**
     * @return static
     */
    public static function relationTypeCannotBeEmpty(): self
    {
        return new self("Relation type cannot be empty.", self::RELATION_TYPE_CANNOT_BE_EMPTY);
    }

    /**
     * @param string $rpcMethod
     * @return DefinitionException
     */
    public static function unknownRpcMethod(string $rpcMethod): self
    {
        $msg = \sprintf('Unknown rpc method (%s)', $rpcMethod);
        return new self($msg, self::UNKNOWN_RPC_METHOD);
    }
}
