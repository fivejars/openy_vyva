<?php

namespace Drupal\vyva;

/**
 * Provides an interface for Virtual Y Video Automation data managers.
 */
interface VyvaManagerInterface {

  /**
   * Update conversion status.
   *
   * @param array $data
   *   Data to update the conversion status.
   *
   * @return bool
   *   Success or failure.
   */
  public function updateStatus(array $data);

  /**
   * Load conversion status entity / create one if doesn't exist.
   *
   * @param int $eventinstance_id
   *   Event instance ID.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface
   *   Conversion status entity.
   */
  public function getEntity($eventinstance_id);

  /**
   * Create Virtual Y Video node.
   *
   * @param array $data
   *   Data to create the content from.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface
   *   Created Virtual Y Video node.
   */
  public function createVideo(array $data);

  /**
   * Get video data from Vimeo.
   *
   * @param string $vimeo_url
   *   Vimeo URL.
   *
   * @return array
   *   Video data.
   */
  public function getVimeoVideoData($vimeo_url);

}
