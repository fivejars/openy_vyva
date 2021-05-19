<?php

/**
 * @file
 * Contains vyva post update functions.
 */

/**
 * Set the initial changed field value.
 */
function vyva_post_update_init_changed() {
  $storage = \Drupal::entityTypeManager()->getStorage('vyva_conversion_status');
  $statuses = $storage->loadMultiple([]);
  foreach ($statuses as $status) {
    if (!$node = $status->gc_video->entity) {
      continue;
    }

    $status->set('changed', $node->changed->value);
    $status->save();
  }
}
