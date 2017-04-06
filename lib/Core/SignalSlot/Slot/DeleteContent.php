<?php
/**
 * This file is part of the EzSystemsRecommendationBundle package.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 * @version //autogentag//
 */
namespace EzSystems\Recommendation\Core\SignalSlot\Slot;

use eZ\Publish\Core\SignalSlot\Signal;

class DeleteContent extends Base
{
    public function receive(Signal $signal)
    {
        if (!$signal instanceof Signal\ContentService\DeleteContentSignal) {
            return;
        }

        $this->client->deleteContent($signal->contentId);
    }
}
