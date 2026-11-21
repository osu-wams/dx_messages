<?php

declare(strict_types=1);

namespace Drupal\dx_messages\EventSubscriber;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\dx_messages\DashboardMessages;
use Drupal\dx_messages\QueuedDXMessages;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Content Moderation Event Subscriber.
 */
class DxMessagesWorkflowSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * DxMessagesWorkflowSubscriber constructor.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly MessengerInterface $messenger,
    private readonly DashboardMessages $dashboardMessages,
    private readonly QueueFactory $queue,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      'content_moderation.state_changed' => 'onContentModerationTransition',
    ];
  }

  /**
   * DX Messages event action.
   *
   * @param \Drupal\content_moderation\Event\ContentModerationStateChangedEvent|\Drupal\workbench_email\EventSubscriber\ContentModerationStateChangedEvent $event
   *   The event listened to.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function onContentModerationTransition($event) {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $event->getModeratedEntity();
    /** @var \Drupal\workflows\WorkflowInterface $workflow */
    $workflow = $this->entityTypeManager->getStorage('workflow')
      ->load($event->getWorkflow());

    if ($entity->bundle() !== 'messages' || $workflow->id() !== 'message_publication') {
      return;
    }
    /** @var \Drupal\content_moderation\Plugin\WorkflowType\ContentModerationInterface $typePlugin */
    $typePlugin = $workflow->getTypePlugin();

    if (!$event->getOriginalState()) {
      $from = $typePlugin->getInitialState($entity)->id();
    }
    else {
      $from = $event->getOriginalState();
    }
    $to = $event->getNewState();

    try {
      $transition = $typePlugin->getTransitionFromStateToState($from, $to);
    }
    catch (\InvalidArgumentException $e) {
      // Do nothing in case of invalid transition.
      return;
    }
    $transitionId = $transition->id();

    $messagePublishId = $entity->get('field_messages_response_id')->getString();

    switch ($transitionId) {
      case 'published':
        $messagesResponseId = $this->dashboardMessages->sendMessage($entity);

        if ($messagesResponseId && \is_string($messagesResponseId)) {
          $entity->set('field_messages_response_id', $messagesResponseId);
          $entity->save();
          $currentDateTime = new DrupalDateTime('now', 'UTC');
          $messagePublishDateTime = new DrupalDateTime($entity->get('field_message_publish_day')
            ->getString(), 'UTC');

          if ($currentDateTime > $messagePublishDateTime) {
            $this->messenger->addMessage('Your Message has been successfully queued for sending. Please check the message Dashboard for status.');
          }
          else {
            $cleanMessageResponse = $messagePublishDateTime->format('l, F d, Y - h:i:s A', ['timezone' => date_default_timezone_get()]);
            $this->messenger->addMessage($this->t('Your Message has been successfully queued for sending. Please check the Message Dashboard after @messageDateTime.', ['@messageDateTime' => $cleanMessageResponse]));
          }
          // Add the item to the queue to be process on next run.
          $this->queue->get('dx_messages')
            ->createItem(new QueuedDXMessages($messagesResponseId, $entity->id()));
        }
        else {
          // If the message had a problem, set it back to review to allow
          // sending again.
          $entity->set('moderation_state', 'review');
          $entity->save();
        }

        break;

      case 'cancelled':
        $currentMessageStatus = $this->dashboardMessages->getMessageStatus($messagePublishId);

        if (isset($currentMessageStatus) && $currentMessageStatus === 'PROCESSING') {
          $this->messenger->addWarning('Message is being processed, cannot Cancel.');
          $entity->set('moderation_state', 'sent');
          $entity->setNewRevision();
          $entity->setRevisionLogMessage('Sent');
          $entity->save();
        }
        else {
          $cancelStatus = $this->dashboardMessages->sendCancelMessage($messagePublishId);

          if ($cancelStatus === 'CANCELLED') {
            $this->messenger->addMessage('Message has been successfully cancelled.');
          }
          elseif ($cancelStatus === 'SENT') {
            $this->messenger->addWarning('Message has already been sent.');
            $entity->set('moderation_state', 'sent');
            $entity->setNewRevision();
            $entity->setRevisionLogMessage('Sent');
            $entity->save();
          }
          else {
            $this->messenger->addError('Something went terribly wrong with your request. Please contact the site owners.');
          }
        }

        break;
    }
  }

}
