<?php

namespace Drupal\ddhi_ingest\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * Provides a 'ExtractNamedEntities' migrate process plugin.
 *
 * @MigrateProcessPlugin(
 *  id = "extract_named_entities"
 * )
 */
class ExtractNamedEntities extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    
    $output = '';
    
    if (!empty($value)) {
      
      $values = array();
      foreach($value as $item) {
        $values[] = $item;
      }
      $values = array_unique($values);
      asort($values);
      $output = join("\r",$values);
    }
    
    return $output;
  }

}
