<?php

namespace Drupal\dx_messages\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\dx_messages\DashboardMessages;
use Drupal\dx_messages\QueuedDXMessages;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines 'dx_messages' queue worker.
 *
 * @QueueWorker(
 *   id = "dx_messages",
 *   title = @Translation("DxMessagesQueue"),
 *   cron = {"time" = 60}
 * )
 */
class DxMessagesStatusWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Entity type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * Messages Interface.
   *
   * @var \Drupal\dx_messages\DashboardMessages
   */
  private DashboardMessages $dashboardMessages;

  /**
   * Constructs a Queue Worker.
   *
   * @param array $configuration
   *   Configuration.
   * @param string $plugin_id
   *   Plugin Id.
   * @param mixed $plugin_definition
   *   Plugin Definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The Entity Type Manager.
   * @param \Drupal\dx_messages\DashboardMessages $dashboardMessages
   *   Messages.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entityTypeManager, DashboardMessages $dashboardMessages) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
    $this->dashboardMessages = $dashboardMessages;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('dx_messages.dashboard_messages')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    if ($data instanceof QueuedDXMessages) {
      $message_status = $this->dashboardMessages->getMessageStatus($data->getMessageId());
      if ($message_status && $message_status === 'SENT') {
        $node = $this->entityTypeManager->getStorage('node')
          ->load($data->getEntityId());
        $node->set('moderation_state', 'sent');
        $node->setNewRevision(TRUE);
        $node->setRevisionLogMessage('Sent');
        $node->save();
      }
    }
  }

}
