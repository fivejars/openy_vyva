<?php

namespace Drupal\vyva\Entity;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines the VYVA Conversion Status entity.
 *
 * @ingroup vyva
 *
 * @ContentEntityType(
 *   id = "vyva_conversion_status",
 *   label = @Translation("Conversion Status"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "views_data" = "Drupal\vyva\VyvaConversionStatusViewsData",
 *   },
 *   base_table = "vyva_conversion_status",
 *   translatable = FALSE,
 *   fieldable = FALSE,
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *   },
 * )
 */
class VyvaConversionStatus extends ContentEntityBase {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the status changed.'));

    $fields['eventinstance'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Event Instance'))
      ->setDescription(new TranslatableMarkup('Event Instance to convert.'))
      ->setSetting('target_type', 'eventinstance')
      ->setRequired(TRUE);

    $fields['conversion_status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Conversion Status'))
      ->setDescription(new TranslatableMarkup('Current status of conversion.'))
      ->setDefaultValue('requested')
      ->setSettings([
        'allowed_values' => [
          'requested' => 'Requested',
          'started' => 'Started',
          'progress' => 'Progress',
          'completed' => 'Completed',
          'failure' => 'Failure',
        ],
      ]);

    $fields['gc_video'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Virtual Y Video'))
      ->setDescription(new TranslatableMarkup('Created Virtual Y Video.'))
      ->setSetting('target_type', 'node')
      ->setSetting('handler', 'default:node')
      ->setSetting('handler_settings', [
        'target_bundles' => ['gc_video' => 'gc_video'],
        'auto_create' => FALSE,
      ]);

    $fields['details'] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('Details'))
      ->setDescription(new TranslatableMarkup('Status details.'));

    return $fields;
  }

}
