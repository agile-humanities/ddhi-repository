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
    $messenger = \Drupal::messenger();

    if (is_object($value)) {
      // Strip paragraphs

      $standoff = [];
      $standoff_keys = ['person' => 'people_local','event' => 'events_local','place' => 'places_local'];

      $source = $row->getSource();

      // Cross references canonical entity ids with local references.
      // @todo: This is a very useful table for a document. We should consider storing this permanently.

      foreach($standoff_keys as $type => $key) {
        foreach ($source[$key] as $item) {
          $local_key = (string) $item->attributes()->id;
          $standoff[$local_key] = [
            'local_key' => $local_key,
            'id' => (string) $item->id,
            'name' => (string) $item->name,
            'type' => $type
          ];
        }
      }

      $i=1;

      $entityCount = [];

      // Process inline entities via span tags.
      // Canonical entity ids and ordinal information are merged.
      // A named anchor tag is also added to allow for inbound focus

      foreach($value->xpath('//span') as $item) {
        $id = (string) $item->attributes()->{'data-id'};

        // Inline entities must have an id attribute.
        if (empty($id) || !array_key_exists($id,$standoff)) {
          continue;
        }

        $local = $standoff[$id];
        $item->addAttribute('entity-type',$local['type']);
        $item->addAttribute('entity-id',$local['id']);
        $item->addAttribute('entity-ordinal',$i);

        // Track unique entities. This will facilitate cycling for instances
        // of a particular entity on display

        if (array_key_exists($local['id'],$entityCount)) {
          $entityCount[$local['id']] ++;
        } else {
          $entityCount[$local['id']] = 1;
        }

        $named_anchor = $item->addChild('a');
        $named_anchor->addAttribute('name',DDHI_NAMED_ANCHOR_PREFIX . $local['id']. '-' .$entityCount[$local['id']]);
        $i++;
      }

      return $value->asXML();
      //$output = preg_replace('~[[:cntrl:]]~', '',$output);


      //$utterance_pattern = '/<u who=\"([^\"]*)\">(.*?)<\/u>/i';
     // $utterance_replacement = "<h3>$1:</h3><p>$2</p>";
      //return preg_replace($utterance_pattern,$utterance_replacement,$output);
    } else if (is_string($value)) {
      return $value;
    }

    return '';
  }

}
