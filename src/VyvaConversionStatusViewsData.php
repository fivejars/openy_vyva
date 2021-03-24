<?php

namespace Drupal\vyva;

use Drupal\views\EntityViewsData;

/**
 * Provides the views data for the VYVA Conversion status entity type.
 */
class VyvaConversionStatusViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();
    $data['eventinstance_field_data']['id']['relationship'] = [
      'title' => $this->t('VYVA: Conversion status'),
      'help' => $this->t('Status of event instance video conversion.'),
      'id' => 'standard',
      'base' => 'vyva_conversion_status',
      'base field' => 'eventinstance',
      'label' => $this->t('Conversion status'),
    ];
    return $data;
  }

}
