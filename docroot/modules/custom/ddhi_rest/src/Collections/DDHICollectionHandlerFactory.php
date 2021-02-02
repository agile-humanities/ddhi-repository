<?php


namespace Drupal\ddhi_rest\Collections;

use Drupal\ddhi_rest\Resolver\DDHIIDResolver;
use Drupal\node\NodeInterface;
use Drupal\ddhi_rest\Items\DDHIItemHandler;

class DDHICollectionHandlerFactory {

  /**
   * Constructs a DDHI Item handler class.
   *
   *
   * @return Void
   */

  public function __construct()
  {

  }

  /**
   * @param $collection string A collection resource to be instantiated.
   *
   * @return \Drupal\ddhi_rest\Collections\DDHICollectionHandler|mixed
   */

  public function createInstance($collection) {

    $entity_manager = \Drupal::service('entity_type.manager');
    $module_handler = \Drupal::service('module_handler');


    $handler = "Drupal\ddhi_rest\Collections\DDHICollectionHandler" . $collection;

    if (!class_exists($handler)) {
      $handler = "Drupal\ddhi_rest\Collections\DDHICollectionHandler";
    }

    return new $handler($collection,$entity_manager,$module_handler);
  }
}
