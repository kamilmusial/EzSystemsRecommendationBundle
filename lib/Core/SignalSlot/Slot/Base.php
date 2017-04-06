<?php
/**
 * This file is part of the EzSystemsRecommendationBundle package.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 * @version //autogentag//
 */
namespace EzSystems\Recommendation\Core\SignalSlot\Slot;

use eZ\Publish\Core\SignalSlot\Slot as BaseSlot;
use EzSystems\Recommendation\API\Client\RecommendationClient;

abstract class Base extends BaseSlot
{
    /** @var \EzSystems\Recommendation\API\Client\RecommendationClient */
    protected $client;

    /**
     * @param \EzSystems\Recommendation\API\Client\RecommendationClient $client
     */
    public function __construct(RecommendationClient $client)
    {
        $this->client = $client;
    }
}
