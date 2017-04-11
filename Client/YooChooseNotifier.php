<?php
/**
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace EzSystems\RecommendationBundle\Client;

use Exception;
use eZ\Publish\API\Repository\ContentService;
use eZ\Publish\API\Repository\ContentTypeService;
use eZ\Publish\API\Repository\LocationService;
use Guzzle\Http\Message\Response;
use GuzzleHttp\ClientInterface as GuzzleClient;
use GuzzleHttp\Exception\RequestException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use eZ\Publish\Core\SignalSlot\Repository;
use eZ\Publish\API\Repository\Exceptions\NotFoundException;

/**
 * A recommendation client that sends notifications to a YooChoose server.
 */
class YooChooseNotifier implements RecommendationClient
{
    /** @var array */
    protected $options;

    /** @var \GuzzleHttp\ClientInterface */
    private $guzzle;

    /** @var \Psr\Log\LoggerInterface|null */
    private $logger;

    /** @var \eZ\Publish\Core\SignalSlot\Repository */
    private $repository;

    /** @var \eZ\Publish\API\Repository\ContentService */
    private $contentService;

    /** @var \eZ\Publish\API\Repository\LocationService */
    private $locationService;

    /** @var \eZ\Publish\API\Repository\ContentTypeService */
    private $contentTypeService;

    /**
     * Constructs a YooChooseNotifier Recommendation Client.
     *
     * @param \GuzzleHttp\ClientInterface $guzzle
     * @param \eZ\Publish\Core\SignalSlot\Repository $repository
     * @param \eZ\Publish\API\Repository\ContentService $contentService
     * @param \eZ\Publish\API\Repository\LocationService $locationService
     * @param \eZ\Publish\API\Repository\ContentTypeService $contentTypeService
     * @param array $options
     *     Keys (all required):
     *     - customer-id: the YooChoose customer ID, e.g. 12345
     *     - license-key: YooChoose license key, e.g. 1234-5678-9012-3456-7890
     *     - api-endpoint: YooChoose http api endpoint
     *     - server-uri: the site's REST API base URI (without the prefix), e.g. http://api.example.com
     * @param \Psr\Log\LoggerInterface|null $logger
     */
    public function __construct(
        GuzzleClient $guzzle,
        Repository $repository,
        ContentService $contentService,
        LocationService $locationService,
        ContentTypeService $contentTypeService,
        array $options,
        LoggerInterface $logger = null
    ) {
        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);

        $this->options = $resolver->resolve($options);
        $this->guzzle = $guzzle;
        $this->repository = $repository;
        $this->contentService = $contentService;
        $this->locationService = $locationService;
        $this->contentTypeService = $contentTypeService;
        $this->logger = $logger;
    }

    /**
     * Sets `customer-id` option when service is created which allows to
     * inject parameter value according to siteaccess configuration.
     *
     * @param string $value
     */
    public function setCustomerId($value)
    {
        $this->options['customer-id'] = $value;
    }

    /**
     * Sets `license-key` option when service is created which allows to
     * inject parameter value according to siteaccess configuration.
     *
     * @param string $value
     */
    public function setLicenseKey($value)
    {
        $this->options['license-key'] = $value;
    }

    /**
     * Sets `server-uri` option when service is created which allows to
     * inject parameter value according to siteaccess configuration.
     *
     * @param string $value
     */
    public function setServerUri($value)
    {
        $this->options['server-uri'] = $value;
    }

    /**
     * Sets `included-content-types` option when service is created which allows to
     * inject parameter value according to siteaccess configuration.
     *
     * @param array $value
     */
    public function setIncludedContentTypes($value)
    {
        $this->options['included-content-types'] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function updateContent($contentId)
    {
        try {
            if (!in_array($this->getContentTypeIdentifier($contentId), $this->options['included-content-types'])) {
                // this Content is not intended to be submitted because ContentType was excluded
                return;
            }
        } catch (NotFoundException $e) {
            // this is most likely a internal draft, or otherwise invalid, ignoring
            return;
        }

        if (isset($this->logger)) {
            $this->logger->info("Notifying YooChoose: updateContent($contentId)");
        }
        try {
            $this->notify(array(array(
                'action' => 'update',
                'uri' => $this->getContentUri($contentId),
                'contentTypeId' => $this->getContentTypeId($contentId),
            )));
        } catch (RequestException $e) {
            if (isset($this->logger)) {
                $this->logger->error('YooChoose Post notification error: ' . $e->getMessage());
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteContent($contentId)
    {
        try {
            if (!in_array($this->getContentTypeIdentifier($contentId), $this->options['included-content-types'])) {
                // this Content is not intended to be submitted because ContentType was excluded
                return;
            }
        } catch (NotFoundException $e) {
            // this is most likely a internal draft, or otherwise invalid, ignoring
            return;
        }

        if (isset($this->logger)) {
            $this->logger->info("Notifying YooChoose: delete($contentId)");
        }
        try {
            $this->notify(array(array(
                'action' => 'delete',
                'uri' => $this->getContentUri($contentId),
                'contentTypeId' => $this->getContentTypeId($contentId),
            )));
        } catch (RequestException $e) {
            if (isset($this->logger)) {
                $this->logger->error('YooChoose Post notification error: ' . $e->getMessage());
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function hideLocation($locationId, $isChild = false)
    {
        $location = $this->locationService->loadLocation($locationId);
        $content = $this->contentService->loadContent($location->contentId);

        $children = $this->locationService->loadLocationChildren($location)->locations;
        foreach ($children as $child) {
            $this->hideLocation($child->id, true);
        }

        if (!in_array($this->getContentTypeIdentifier($content->id), $this->options['included-content-types'])) {
            return;
        }

        if (!$isChild) {
            $contentLocations = $this->locationService->loadLocations($content->contentInfo);
            foreach ($contentLocations as $contentLocation) {
                if (!$contentLocation->hidden) {
                    return;
                }
            }
        }

        if (isset($this->logger)) {
            $this->logger->info("Notifying YooChoose: hide($content->id)");
        }

        try {
            $this->notify(array(array(
                'action' => 'delete',
                'uri' => $this->getContentUri($content->id),
                'contentTypeId' => $this->getContentTypeId($content->id),
            )));
        } catch (RequestException $e) {
            if (isset($this->logger)) {
                $this->logger->error('YooChoose Post notification error: ' . $e->getMessage());
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function unhideLocation($locationId)
    {
        $location = $this->locationService->loadLocation($locationId);
        $content = $this->contentService->loadContent($location->contentId);

        $children = $this->locationService->loadLocationChildren($location)->locations;
        foreach ($children as $child) {
            $this->unhideLocation($child->id);
        }

        if (!in_array($this->getContentTypeIdentifier($content->id), $this->options['included-content-types'])) {
            return;
        }

        if (isset($this->logger)) {
            $this->logger->info("Notifying YooChoose: unhide($content->id)");
        }

        try {
            $this->notify(array(array(
                'action' => 'update',
                'uri' => $this->getContentUri($content->id),
                'contentTypeId' => $this->getContentTypeId($content->id),
            )));
        } catch (RequestException $e) {
            if (isset($this->logger)) {
                $this->logger->error('YooChoose Post notification error: ' . $e->getMessage());
            }
        }
    }

    /**
     * Returns ContentType identifier based on $contentId.
     *
     * @param int|mixed $contentId
     *
     * @return string
     */
    private function getContentTypeIdentifier($contentId)
    {
        $contentTypeService = $this->contentTypeService;
        $contentService = $this->contentService;

        $contentType = $this->repository->sudo(function () use ($contentId, $contentTypeService, $contentService) {
            $contentType = $this->contentTypeService->loadContentType(
                $this->contentService
                    ->loadContent($contentId)
                    ->contentInfo
                    ->contentTypeId
            );

            return $contentType;
        });

        return $contentType->identifier;
    }

    /**
     * Gets ContentType ID based on $contentId.
     *
     * @param mixed $contentId
     *
     * @return int|null
     */
    protected function getContentTypeId($contentId)
    {
        $contentTypeId = null;

        try {
            $contentTypeId = $this->contentService->loadContentInfo($contentId)->contentTypeId;
        } catch (Exception $e) {
        }

        return $contentTypeId;
    }

    /**
     * Generates the REST URI of content $contentId.
     *
     * @param $contentId
     *
     * @return string
     */
    protected function getContentUri($contentId)
    {
        return sprintf(
            '%s/api/ezp/v2/content/objects/%s',
            // @todo normalize in configuration
            $this->options['server-uri'],
            $contentId
        );
    }

    /**
     * Notifies the YooChoose API of one or more repository events.
     *
     * A repository event is defined as an array with three keys:
     * - action: the event name (update, delete)
     * - uri: the event's target, as an absolute HTTP URI to the REST resource
     * - contentTypeId: currently processed ContentType ID
     *
     * @param array $events
     *
     * @throws \InvalidArgumentException If provided $events seems to be of wrong type
     * @throws \GuzzleHttp\Exception\RequestException if a request error occurs
     */
    protected function notify(array $events)
    {
        foreach ($events as $event) {
            if (array_keys($event) != array('action', 'uri', 'contentTypeId')) {
                throw new InvalidArgumentException('Invalid action keys');
            }
        }

        if (isset($this->logger)) {
            $this->logger->debug('POST notification to YooChoose:' . json_encode($events, true));
        }

        if (method_exists($this->guzzle, 'post')) {
            $this->notifyGuzzle5($events);
        } else {
            $this->notifyGuzzle6($events);
        }
    }

    /**
     * Notifies the YooChoose API using Guzzle 5 (for PHP 5.4 support).
     *
     * @param array $events
     */
    private function notifyGuzzle5(array $events)
    {
        $response = $this->guzzle->post(
            $this->getNotificationEndpoint(),
            array(
                'json' => array(
                    'transaction' => null,
                    'events' => $events,
                ),
                'auth' => array(
                    $this->options['customer-id'],
                    $this->options['license-key'],
                ),
            )
        );

        if (isset($this->logger)) {
            $this->logger->debug('Got ' . $response->getStatusCode() . ' from YooChoose notification POST');
        }
    }

    /**
     * Notifies the YooChoose API using Guzzle 6 asynchronously.
     *
     * @param array $events
     */
    private function notifyGuzzle6(array $events)
    {
        $promise = $this->guzzle->requestAsync(
            'POST',
            $this->getNotificationEndpoint(),
            array(
                'json' => array(
                    'transaction' => null,
                    'events' => $events,
                ),
                'auth' => array(
                    $this->options['customer-id'],
                    $this->options['license-key'],
                ),
            )
        );

        $promise->wait();

        $promise->then(function (Response $response) {
            $this->log(sprintf('Got asynchronously %s from YooChoose notification POST', $response->getStatusCode()), 'debug');
        });
    }

    /**
     * @param OptionsResolver $resolver
     */
    protected function configureOptions(OptionsResolver $resolver)
    {
        $options = array('customer-id', 'license-key', 'api-endpoint', 'server-uri');
        $resolver->setDefined($options);
        $resolver->setDefaults(
            array(
                'customer-id' => null,
                'license-key' => null,
                'server-uri' => null,
                'api-endpoint' => null,
            )
        );
    }

    /**
     * Returns the YooChoose notification endpoint.
     *
     * @return string
     */
    private function getNotificationEndpoint()
    {
        return sprintf(
            '%s/api/v4/publisher/ez/%s/notifications',
            rtrim($this->options['api-endpoint'], '/'),
            $this->options['customer-id']
        );
    }
}
