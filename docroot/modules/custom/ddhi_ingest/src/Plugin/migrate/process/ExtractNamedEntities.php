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
    
    if (empty($value) || !is_object($value)) {
      return '';
    }
    
   
    $value->registerXPathNamespace("tei","http://www.tei-c.org/ns/1.0");
    $items = $value->xpath('//tei:placeName');
    $values = [];
    
        
    if ($items !== false) {
    
      foreach ($items as $item) {
        $text = $item->__toString();
        $values[strtolower($text)] = $text; // Creates unique values and natural sorting order
      }
      
      ksort($values);
          
      return join("\r",array_values($values));
    
    } 
    
         /*   
    foreach((array)$value as $item) {
      $values[] = $item;
    }
    
    $values = array_unique($values);
    asort($values);
    $output = join("\r",$values);
    
    return $output;
    */
  }

}
