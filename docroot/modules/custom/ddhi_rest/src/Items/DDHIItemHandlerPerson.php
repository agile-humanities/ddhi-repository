<?php


namespace Drupal\ddhi_rest\Items;


use Drupal\rest\ResourceResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DDHIItemHandlerPerson extends DDHIItemHandler {

  public function getData(): array {
    $data = parent::getData();

    $data = $data + [
      'description' => !empty($this->node->body->value) ? check_markup($this->node->body->value,$this->node->body->format)->__toString() : '',
    ];
    return $data;
  }
}
