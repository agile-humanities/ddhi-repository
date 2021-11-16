<?php


namespace Drupal\ddhi_rest\Items;


use Drupal\rest\ResourceResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DDHIItemHandlerTranscript extends DDHIItemHandler {

  public function getData(): array {
    $data = parent::getData();
    $uri_root = \Drupal::request()->getSchemeAndHttpHost() .'/'.  DDHI_API_ROOT_PATH .'/items/'. $data['id'];
    $transcript = check_markup($this->node->body->value,$this->node->body->format);

    $data = $data + [
        'tei_uri' => $uri_root .'/tei?_format=xml',
        'persons' => $this->getRelatedEntityData($this->node->field_people),
        'places' => $this->getRelatedEntityData($this->node->field_places),
        'events' => $this->getRelatedEntityData($this->node->field_events),
        'organizations' => $this->getRelatedEntityData($this->node->field_organizations),
        'dates' => $this->getSubResourceDates(),
        'transcript' => !empty($this->node->body->value) ? $transcript->__toString() : '',
    ];

    return $data;
  }

  public function getSubResourceTei() {
    $tei_uri = $this->node->field_tei_transcription->entity->getFileUri();

    if (!$tei_uri) {
      throw new NotFoundHttpException("TEI not available");
    }

    $path = \Drupal::service('file_system')->realpath($tei_uri);
    // $tei = file_get_contents(\Drupal::service('file_system')->realpath($path));
    return [
      'filepath' => file_create_url($tei_uri)
    ];
    return $tei;
  }

  /**
   * Returns listed dates as a series of arrays. Conforms to the date array
   * structure of the DDHI Viewer web component. Currently does not support
   * date ranges or time of day, as per the current DDHI TEI standards.
   *
   * @return array
   */

  public function getSubResourceDates() {
    $transcript = check_markup($this->node->body->value,$this->node->body->format);
    $datefield = 'data-date';
    $xml = simplexml_load_string($transcript);
    $dates = [];

    foreach($xml->xpath('//date') as $item) {

      [$parent] = $item->xpath("parent::*");

      $point_in_time = $item->attributes()->$datefield ? ddhi_ingest_make_wikidata_date($item->attributes()->$datefield->__toString()) : '';

      $dates[] = [
        'reference' => $this->node->field_id->value,
        'id' => $item->attributes()->id ? $item->attributes()->id->__toString() : '',
        'when' => $item->attributes()->when ? $item->attributes()->when->__toString() : '', // From “when” attribute
        'expanded' => $item->attributes()->$datefield ? $item->attributes()->$datefield->__toString() : '',
        'pointInTime' =>$point_in_time, // Converted to a YYYY-MM-DD point in time
        'startDate' => $point_in_time, // Currently unsupported. Same as point in time.
        'endDate' => $point_in_time, // Currently unsupported. Same as point in time
        'sortDateStart' => $point_in_time,
        'sortDateEnd' => $point_in_time,
        'utterance' => $parent ? $parent->asXML() : ''
        ];
    }

    return $dates;

  }
}
