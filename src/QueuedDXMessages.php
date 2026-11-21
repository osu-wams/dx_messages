<?php

declare(strict_types=1);

namespace Drupal\dx_messages;

/**
 * Creates an Object to be used in the Queue System.
 */
class QueuedDXMessages {

  /**
   * QueuedDXMessages constructor.
   *
   * @param string $messageId
   *   The Message ID to check against.
   * @param int $entityId
   *   The Entity ID of the node.
   */
  public function __construct(
    protected readonly string $messageId,
    protected readonly int $entityId,
  ) {}

  /**
   * Get the entity ID.
   *
   * @return int
   *   The Entity ID.
   */
  public function getEntityId() {
    return $this->entityId;
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

}
