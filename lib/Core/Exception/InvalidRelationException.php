<?php
/**
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace EzSystems\Recommendation\Core\Exception;

use Exception;

/**
 * Generates InvalidRelationException with customised message.
 */
class InvalidRelationException extends InvalidArgumentException
{
    public function __construct($message, Exception $previous = null)
    {
        parent::__construct(
            $message, 0, $previous
        );
    }
}
