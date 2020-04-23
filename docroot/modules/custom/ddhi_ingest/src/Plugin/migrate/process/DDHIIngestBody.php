<?php

namespace Drupal\ddhi_ingest\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * Provides a 'DDHIIngestBody' migrate process plugin.
 *
 * @MigrateProcessPlugin(
 *  id = "ddhi_ingest_body"
 * )
 */
class DDHIIngestBody extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    
    // Strip paragraphs
    
    $output = $value->asXML();
    $output = preg_replace('~[[:cntrl:]]~', '',$output);
    
    $utterance_pattern = '/<u who=\"([^\"]*)\">(.*?)<\/u>/i';
    $utterance_replacement = "<h3>$1:</h3><p>$2</p>";
    return preg_replace($utterance_pattern,$utterance_replacement,$output);
  }

}