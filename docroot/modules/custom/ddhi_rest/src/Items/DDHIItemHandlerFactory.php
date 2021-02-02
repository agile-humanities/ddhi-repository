<?php


namespace Drupal\ddhi_rest\Items;

use Drupal\ddhi_rest\Resolver\DDHIIDResolver;
use Drupal\node\NodeInterface;
use Drupal\ddhi_rest\Items\DDHIItemHandler;

class DDHIItemHandlerFactory {

  /**
   * Constructs a DDHI Item handler class.
   *
   * @param mixed $nodeObject. Must be a DDHI ID, node ID, or fully loaded $node object.
   *
   * @return Void
   */

  public function __construct($nodeObject)
  {

  }

  public function createInstance($nodeObject) {

    $entity_manager = \Drupal::service('entity_type.manager');
    $module_handler = \Drupal::service('module_handler');

    // Check if argument is a valid node object. If not, presume it's an identifier.


    if (!$nodeObject instanceof NodeInterface) {

      // The DDHIIDResolver disambiguates DDHI IDs and Node IDs, returning
      // a valid Node ID or false.

      $idResolver = \Drupal::service('ddhi_rest.id.resolver');
      $id = $idResolver->resolveIds($nodeObject);

      if ($id === false) {
        throw new \Exception("Cannot establish a valid node for ID “{$nodeObject}”.");
      }

      $nodeObject = $entity_manager->getStorage('node')->load($id);

    }

    $type = ucfirst($nodeObject->getType());

    $handler = "Drupal\ddhi_rest\Items\DDHIItemHandler" . $type;

    if (!class_exists($handler)) {
      throw new \Exception("Cannot create DDHI Item Handler. Class {$handler} does not exist.");
    }

    return new $handler($nodeObject,$entity_manager,$module_handler);
  }
}
