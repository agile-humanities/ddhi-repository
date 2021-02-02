<?php


namespace Drupal\ddhi_rest\Items;


use Drupal\rest\ResourceResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DDHIItemHandlerPlace extends DDHIItemHandler {

  public function getData(): array {
    $data = parent::getData();


    foreach($this->node->field_location as $row) {
      $location = $row->getValue();
      $map = \Drupal::service('renderer')->renderPlain($row->view(array('type' => 'geolocation_map')));
    }

    $data = $data + [
      'location' => $location,
      'map' => $map,
      'description' => !empty($this->node->body->value) ? check_markup($this->node->body->value,$this->node->body->format)->__toString() : '',
    ];

    return $data;
  }

  public function getSubResourceReference() {
    $field = null;
    return $this->getReferencingEntities('field_places');
  }

}
