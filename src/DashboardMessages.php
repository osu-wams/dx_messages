<?php

declare(strict_types=1);

namespace Drupal\dx_messages;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Utility\Error;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * DashboardMessages service.
 *
 * Custom service to send requests off to the api.
 */
class DashboardMessages implements LoggerAwareInterface {

  use LoggerAwareTrait;
  use StringTranslationTrait;

  /**
   * Constructs a DashboardMessages object.
   */
  public function __construct(
    protected readonly ClientInterface $httpClient,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly MessengerInterface $messenger,
  ) {}

  /**
   * Get the count of the number of users the message will be sent to.
   */
  public function getAudienceCount() {}

  /**
   * Get the status of an MCM Message.
   *
   * @param string $messageId
   *   The Dashboard Message ID to check against.
   *
   * @return string|bool
   *   The status or false.
   */
  public function getMessageStatus(string $messageId) {
    $config = $this->configFactory->get('dx_messages.settings');
    $messageApiKey = $config->get('dx_api_key');
    $messageApiEndpoint = $config->get('dx_api_messages_endpoint');

    try {
      $response = $this->httpClient->request('GET', "{$messageApiEndpoint}/{$messageId}", [
        'headers' => [
          'x-api-key' => $messageApiKey,
        ],
      ]);
    }
    catch (RequestException $requestException) {
      Error::logException($this->logger, $requestException);
    }
    catch (\Exception $exception) {
      Error::logException($this->logger, $exception);
    }

    if (isset($response) && $response->getStatusCode() === 200) {
      $response_body = Json::decode($response->getBody()->getContents());

      return $response_body['message']['status'];
    }

    return FALSE;
  }

  /**
   * Send a Cancel Request for the message ID.
   *
   * @param string $messageId
   *   The Message ID to cancel.
   *
   * @return string|bool
   *   A string with either CANCELED or SENT or FALSE.
   */
  public function sendCancelMessage(string $messageId) {
    $config = $this->configFactory->get('dx_messages.settings');
    $messageApiKey = $config->get('dx_api_key');
    $messageApiEndpoint = $config->get('dx_api_messages_endpoint');

    try {
      $messageResponse = $this->httpClient->request('POST', "{$messageApiEndpoint}/{$messageId}/cancel", [
        'headers' => [
          'x-api-key' => $messageApiKey,
        ],
      ]);
    }
    catch (RequestException $requestException) {
      $messageResponse = $requestException->getResponse();
      $this->messenger->addError('There was a problem sending the cancel request.');
      Error::logException($this->logger, $requestException);
    }
    catch (\Exception $exception) {
      // Catch all and log.
      $this->messenger->addError('An unknown error occurred, please contact the site owner.');
      Error::logException($this->logger, $exception);
    }

    if (isset($messageResponse) && $messageResponse->getStatusCode() === 200) {
      return 'CANCELLED';
    }

    if (isset($messageResponse) && $messageResponse->getStatusCode() === 400) {
      return 'SENT';
    }

    return FALSE;
  }

  /**
   * Create a new Message in MCM.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The Drupal Node to use as data for the message.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   *
   * @return string|bool
   *   The Message ID from the response or FALSE.
   */
  public function sendMessage(ContentEntityInterface $entity) {
    global $base_url;
    $config = $this->configFactory->get('dx_messages.settings');
    $messagesCreateApiEndpoint = $config->get('dx_api_create_endpoint');
    $messageApiKey = $config->get('dx_api_key');
    $title = $entity->get('title')->getString();

    $shortBody = $entity->get('field_message_short_body')->getString();
    $longBody = $entity->get('field_message_body')->getString();

    $publishDayTime = $entity->get('field_message_publish_day')->getString();
    $isoDate = date('Y-m-d\TH:i:s.v\Z', strtotime($publishDayTime));

    $messageAudience = [];

    /** @var \Drupal\Core\Field\EntityReferenceFieldItemList $audience */
    foreach ($entity->get('field_message_audience') as $audience) {
      /** @var \Drupal\Core\Entity\Plugin\DataType\EntityAdapter $audience_adapter */
      $audience_adapter = $audience->get('entity')->getTarget();

      if ($audience_adapter !== NULL) {
        $messageAudience[] = $audience_adapter->getValue()
          ->get('field_message_audience_key')
          ->getString();
      }
    }

    /** @var \Drupal\media\Entity\Media $media */
    $media = $entity->get('field_message_media')->entity;

    if ($media !== NULL) {
      $fileId = $media->getSource()->getSourceFieldValue($media);
      /** @var \Drupal\file\Entity\File $file */
      $file = $this->entityTypeManager->getStorage('file')
        ->load($fileId);
      $fileUri = $file->getFileUri();
      $fileUrl = $base_url . $file->createFileUrl($fileUri);
    }
    else {
      $fileUrl = '';
    }
    // Create a payload array.
    $payload = [
      'payload' => [
        'populationParams' => [
          'affiliations' => $messageAudience,
        ],
        'channelIds' => ['dashboard'],
        'content' => $longBody,
        'contentShort' => $shortBody,
        'imageUrl' => $fileUrl,
        'sendAt' => $isoDate,
        'title' => $title,
      ],
    ];

    try {
      $messagesResponse = $this->httpClient->request('POST', $messagesCreateApiEndpoint, [
        'headers' => [
          'x-api-key' => $messageApiKey,
        ],
        'json' => $payload,
      ]);
    }
    catch (RequestException $requestException) {
      $messagesResponse = $requestException->getResponse();
      $messageContents = Json::decode($messagesResponse->getBody()
        ->getContents())['message'];
      $this->messenger->addError($this->t('There was a problem sending your message. @message', ['@message' => $messageContents]));
      Error::logException($this->logger, $requestException);
    }
    catch (\Exception $exception) {
      // Catch all and log.
      $this->messenger->addError('An unknown error occurred, please contact the site owner.');
      Error::logException($this->logger, $exception);
    }

    if (isset($messagesResponse) && $messagesResponse->getStatusCode() === 200) {
      $responseBody = Json::decode($messagesResponse->getBody()
        ->getContents());

      return $responseBody['object']['id'];
    }

    return FALSE;
  }

}
