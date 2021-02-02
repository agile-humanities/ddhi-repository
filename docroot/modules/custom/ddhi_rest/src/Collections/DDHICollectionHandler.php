<?php


namespace Drupal\ddhi_rest\Collections;


use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\rest\ResourceResponse;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class DDHICollectionHandler {

  protected $collection;
  protected $entityTypeManager;
  protected $moduleHandler;

  public function __construct(string $collection,EntityTypeManager $entity_type_manager, ModuleHandler $module_handler) {
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
    $this->collection = $this->getSupportedCollection($collection);

    if (!$this->isValid()) {
      throw new BadRequestHttpException("This collection is not supported.");
    }
  }

  /**
   * @return bool Returns true if the collection is supported, false otherwise.
   */

  public function isValid(): bool {
    return $this->getSupportedCollection($this->collection) !== false;
  }

  /**
   * @param $key string A string representing the collection. This function
   * accepts a collection key or its pluralized form to be useful when
   * handling more readable paths like ddhi-api/collections/events .
   *
   * @return false|string Returns a valid collection key, or false otherwise.
   */

  public function getSupportedCollection($key) {
    $supported_collections = [
      'events' => 'event',
      'transcripts' => 'transcript',
      'persons' => 'person',
      'people' => 'person',
      'places' => 'place',
      'organizations' => 'organization',
      'list' => 'list',
    ];

    if (array_key_exists($key,$supported_collections)) {
      return $supported_collections[$key];
    } else if (in_array($key,$supported_collections)) {
      return $key;
    } else {
      return false;
    }
  }

  /**
   * @return array Retrieves an array collection data as a list of items
   * with the base (Listing) dataset.
   */

  public function getData(): array {
    if (method_exists($this,$this->collection)) {
      return $this->{$this->collection};
    }
    $nids = \Drupal::entityQuery('node')->condition('type',$this->collection)->execute();

    $collectionArray = [];

    if ($nids) {
      foreach ($nids as $nid) {
        $entityHandler = \Drupal::service('ddhi_rest.item.handler')->createInstance($nid);
        $collectionArray[] = $entityHandler->getListingData();
      }
    }

    return $collectionArray;
  }



  /**
   * @param null $subresource Takes an optional $subresource parameter.
   * A subresource is a subset of an item's data or a derivative view.
   * This function checks to see if a named subresource method exists and
   * execute it. It then checks to see if the subresource is a valid data
   * key and returns its value. It falls back to presenting the entire resource,
   * effectively ignoring the subresource request.
   *
   * @return \Drupal\rest\ResourceResponse Returns a resource response in
   * the provided _format.
   */
  public function getResource($subresource=null): ResourceResponse {

    // Check if child classes implement a subresource method.

    if ($subresource) {
      $method = 'getSubResource' . ucfirst($subresource);
      if (method_exists($this,$method)) {
        return $this->$method();
      }
    }

    $data = $this->getData();

    // Return a subresource data key if it exists, or the whole dataset otherwise. @TODO: Consider returning an http exception if the subresource doesn't exist.

    return new ResourceResponse(array_key_exists($subresource,$data) ? $data[$subresource] : $data);
  }

}
