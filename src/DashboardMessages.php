<?php

namespace Drupal\dx_messages;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;

/**
 * DashboardMessages service.
 *
 * Custom service to send requests off to the api.
 */
class DashboardMessages {

  use StringTranslationTrait;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Messenger interface.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  private $messenger;

  /**
   * The Translation interface.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface
   */
  private $string_translation;

  /**
   * Constructs a DashboardMessages object.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The Messenger.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   String Translations.
   */
  public function __construct(ClientInterface $http_client, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, MessengerInterface $messenger, TranslationInterface $string_translation) {
    $this->httpClient = $http_client;
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;
    $this->string_translation = $string_translation;
  }

  /**
   * Create a new Message in MCM.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The Drupal Node to use as data for the message.
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
    $longBody = $entity->get('field_message_body')->get(0)->value;

    $publishDayTime = $entity->get('field_message_publish_day')->getString();
    $isoDate = date('Y-m-d\TH:i:s.v\Z', strtotime($publishDayTime));

    $messageAudience = $entity->get('field_message_audience')->getValue();

    $mediaId = $entity->get('field_message_media')->getString();
    /** @var \Drupal\media\Entity\Media $media */
    $media = $this->entityTypeManager->getStorage('media')->load($mediaId);
    if (!is_null($media)) {
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
          'affiliations' => array_column($messageAudience, 'value'),
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
      $messagesResponse = $this->httpClient->post($messagesCreateApiEndpoint, [
        'headers' => [
          'x-api-key' => $messageApiKey,
        ],
        'json' => $payload,
      ]);
    }
    catch (ClientException $clientException) {
      $messagesResponse = $clientException->getResponse();
      $this->messenger->addError('There was a problem sending your message.');
      watchdog_exception('dx_messages', $clientException);
    }
    catch (RequestException $requestException) {
      $messagesResponse = $requestException->getResponse();
      $messageContents = JSON::decode($messagesResponse->getBody()
        ->getContents())['message'];
      $this->messenger->addError($this->t('There was a problem sending your message. @message', ['@message' => $messageContents]));
      watchdog_exception('dx_messages', $requestException);
    }
    catch (\Exception $exception) {
      // Catch all and log.
      $this->messenger->addError('An unknown error occurred, please contact the site owner.');
      watchdog_exception('dx_messages', $exception);
    }
    if (isset($messagesResponse) && $messagesResponse->getStatusCode() === 200) {
      $responseBody = Json::decode($messagesResponse->getBody()
        ->getContents());
      return $responseBody['object']['id'];
    }
    else {
      return FALSE;
    }
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
      $messageResponse = $this->httpClient->post($messageApiEndpoint . '/' . $messageId . '/cancel', [
        'headers' => [
          'x-api-key' => $messageApiKey,
        ],
      ]);

    }
    catch (ClientException $clientException) {
      $messageResponse = $clientException->getResponse();
      $this->messenger->addError('There was a problem.');
      watchdog_exception('dx_messages', $clientException);
    }
    catch (RequestException $requestException) {
      $messageResponse = $requestException->getResponse();
      $this->messenger->addError('There was a problem sending the cancel request.');
      watchdog_exception('dx_messages', $requestException);
    }
    catch (\Exception $exception) {
      // Catch all and log.
      $this->messenger->addError('An unknown error occurred, please contact the site owner.');
      watchdog_exception('dx_messages', $exception);
    }
    if (isset($messageResponse) && $messageResponse->getStatusCode() === 200) {
      return 'CANCELED';
    }
    elseif (isset($messageResponse) && $messageResponse->getStatusCode() === 400) {
      return 'SENT';
    }
    else {
      return FALSE;
    }
  }

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
      /** @var \Psr\Http\Message\ResponseInterface $response */
      $response = $this->httpClient->get($messageApiEndpoint . '/' . $messageId, [
        'headers' => [
          'x-api-key' => $messageApiKey,
        ],
      ]);
    }
    catch (ClientException $clientException) {
      watchdog_exception('dx_messages', $clientException);
    }
    catch (RequestException $requestException) {
      watchdog_exception('dx_messages', $requestException);
    }
    catch (\Exception $exception) {
      watchdog_exception('dx_messages', $exception);
    }
    if (isset($response) && $response->getStatusCode() === 200) {
      $response_body = JSON::decode($response->getBody()->getContents());
      return $response_body['message']['status'];
    }
    return FALSE;
  }

  /**
   * Get the count of the number of users the message will be sent to.
   */
  public function getAudienceCount() {
  }

}
