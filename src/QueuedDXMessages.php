<?php

namespace Drupal\dx_messages;

/**
 * Creates an Object to be used in the Queue System.
 */
class QueuedDXMessages {

  /**
   * The Message ID.
   *
   * @var string
   */
  private string $messageId;

  /**
   * The Entity ID.
   *
   * @var int
   */
  private int $entityId;

  /**
   * QueuedDXMessages constructor.
   *
   * @param string $messageId
   *   The Message ID to check against.
   * @param int $entityId
   *   The Entity ID of the node.
   */
  public function __construct(string $messageId, int $entityId) {

    $this->messageId = $messageId;
    $this->entityId = $entityId;
  }

  /**
   * Gets the Message ID string.
   *
   * @return string
   *   The Message ID.
   */
  public function getMessageId() {
    return $this->messageId;
  }

  /**
   * Get the entity ID.
   *
   * @return int
   *   The Entity ID.
   */
  public function getEntityId() {
    return $this->entityId;
  }

}
