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
    
    
    if (empty($value) || !is_array($value)) {
      return '';
    }
    
    $element = $value[0];
    $xml = $value[1];   
    $xml->registerXPathNamespace("tei","http://www.tei-c.org/ns/1.0");
    $items = $xml->xpath('//tei:' . $element);
    $values = [];
    
        
    if ($items !== false) {
    
      foreach ($items as $item) {
        
        switch ($element) {
          case 'date':
            $item->registerXPathNamespace("tei","http://www.tei-c.org/ns/1.0");
            $date = $item->attributes()->when;
            $text = $date ? $date->__toString() . " (" . $item->__toString() . ")" : $item->__toString();
            break;
          default:
            $text = $item->__toString();
        }
        
        if ($element == 'date') {
          $key = $date ? strtotime($date->__toString()) : strtotime($item->__toString());
        }
        
        $key = strtolower($text); // natural sort
        $key = preg_replace('/^the/','',$key); // remove articles
        $key = preg_replace('/^a/','',$key);
        
        if (!empty(trim($text))) {
          $values[trim($key)] = $text; // create unique values
        }
      }
      
      ksort($values);
          
      return join("\r",array_values($values));
    
    } 
  }

}
