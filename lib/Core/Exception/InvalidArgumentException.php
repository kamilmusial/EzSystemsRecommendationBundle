<?php
/**
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace EzSystems\Recommendation\Core\Exception;

use Exception;
use InvalidArgumentException as PHP_InvalidArgumentException;

/**
 * Generates InvalidArgumentException with customised message.
 */
class InvalidArgumentException extends PHP_InvalidArgumentException
{
    /**
     * InvalidArgumentException constructor.
     * @param string $message
     * @param \Exception|null $previous
     */
    public function __construct($message, Exception $previous = null)
    {
        parent::__construct(
            $message, 0, $previous
        );
    }
}
