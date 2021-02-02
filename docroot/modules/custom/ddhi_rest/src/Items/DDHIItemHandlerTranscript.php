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
        'organizations' => $this->getRelatedEntityData($this->node->field_events),
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
    $tei = file_get_contents(\Drupal::service('file_system')->realpath($tei_uri));
    return new ResourceResponse($tei);
  }
}
