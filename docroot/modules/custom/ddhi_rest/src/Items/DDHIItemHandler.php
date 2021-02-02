<?php


namespace Drupal\ddhi_rest\Items;


use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\rest\ResourceResponse;
use Drupal\node\NodeInterface;

class DDHIItemHandler {

  protected $node;
  public $type;
  protected $entityTypeManager;
  protected $moduleHandler;

  public function __construct($nodeObject,EntityTypeManager $entity_type_manager, ModuleHandler $module_handler) {
    $this->node = $nodeObject;
    $this->type = $nodeObject->getType();
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
  }

  public function isValid() {
    return $this->node instanceof NodeInterface;
  }

  public function getData() {
    return [];
  }

  public function getResource($format="Json") {
    $format = 'getResource' . ucfirst($format);
    if (method_exists($this,$format)) {
      return $this->$format();
    } else {
      throw new \Exception("Cannot display this in {$format} format.");
    }
  }
  public function getResourceJson() {
    return new ResourceResponse($this->getData());
  }

}
