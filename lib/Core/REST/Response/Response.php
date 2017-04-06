<?php
/**
 * This file is part of the EzSystemRecommendationBundle package.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace EzSystems\Recommendation\Core\REST\Response;

use EzSystems\Recommendation\API\REST\Response\ResponseInterface;
use EzSystems\Recommendation\Core\REST\ValueObjectVisitor\ContentListElementGenerator;

abstract class Response implements ResponseInterface
{
    /** @var \EzSystems\Recommendation\Core\REST\ValueObjectVisitor\ContentListElementGenerator */
    public $contentListElementGenerator;

    /** @var bool */
    protected $authenticationMethod;

    /** @var string */
    protected $authenticationLogin;

    /** @var string */
    protected $authenticationPassword;

    /**
     * @param \EzSystems\Recommendation\Core\REST\ValueObjectVisitor\ContentListElementGenerator $contentListElementGenerator
     * @param bool $authenticationMethod
     * @param string $authenticationLogin
     * @param string $authenticationPassword
     */
    public function __construct(
        ContentListElementGenerator $contentListElementGenerator,
        $authenticationMethod,
        $authenticationLogin,
        $authenticationPassword
    ) {
        $this->contentListElementGenerator = $contentListElementGenerator;
        $this->authenticationMethod = $authenticationMethod;
        $this->authenticationLogin = $authenticationLogin;
        $this->authenticationPassword = $authenticationPassword;
    }
}
