<?php

namespace Drupal\ddhi_ingest\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\migrate\Plugin\migrate\process\MigrationLookup;

/**
 * Provides a 'MigrationLookupUniqueValues' migrate process plugin.
 * Ensures that the results of migrate_lookup do not include duplicate entries.
 *
 * With thanks to Cottser: https://drupal.stackexchange.com/a/255726
 *
 * @MigrateProcessPlugin(
 *  id = "migration_lookup_unique_references",
 *  handle_multiples = TRUE
 * )
 *
 * Ensures that entity reference values are unique in instances where the
 * source has redundancies. This prevents a destination entity reference
 * field from listing the same entity more than once.
 *
 * Output from this plugin is formatted for use with the sub_process plugin.
 *
 * Example:
 *   people_unique:
 *     plugin: migration_lookup_unique_references
 *     source: people
 *     keyname: id
 *   field_people:
 *     plugin: sub_process
 *     source: '@people_unique'
 *     process:
 *       target_id:
 *       plugin: migration_lookup
 *       migration: ddhi_named_people_level_2
 *       source: id
 *
 */

class MigrationLookupUniqueValues extends ProcessPluginBase {

  protected $existing_ids = [];

  //public function multiple() {
  //  return true;
  //}

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $keyname = (is_string($this->configuration['keyname']) && $this->configuration['keyname'] != '') ? $this->configuration['keyname'] : 'value';
    $unique_values[] = [];

    if (is_array($value) || $value instanceof \Traversable) {
      $result = [];
      foreach ($value as $sub_value) {
        if (!in_array($sub_value,$unique_values)) {
          $result[] = [$keyname => $sub_value];
          $unique_values[] = $sub_value;
        }
      }
      return $result;
    }
    else {
      throw new MigrateException(sprintf('%s is not traversable', var_export($value, TRUE)));
    }
  }
}
