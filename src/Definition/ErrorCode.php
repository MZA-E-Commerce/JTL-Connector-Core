<?php
/**
 * @copyright 2010-2019 JTL-Software GmbH
 * @package Jtl\Connector\Core\Application
 */
namespace Jtl\Connector\Core\Definition;

final class ErrorCode
{
    const NO_SESSION = 789;
    const AUTHENTICATION_FAILED = 790;
    const INVALID_SESSION = -32000;
    const UNINITIALIZED_SESSION = -32001;
    const INVALID_REQUEST = -32600;
    const PARSE_ERROR = -32700;
}
