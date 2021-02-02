<?php


namespace Drupal\ddhi_rest\Items;


use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\rest\ResourceResponse;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

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

  /**
   * @return bool Returns true if the item is a valid Drupal node, false otherwise.
   */

  public function isValid(): bool {
    return $this->node instanceof NodeInterface;
  }

  /**
   * @return array Retrieves an array of content data. Returns a common dataset
   * (supplied by the getListingData() method),then allows child classes
   * (representing various content types) to supplement it by overriding this
   * method.
   */

  public function getData(): array {
    return $this->getListingData();
  }

  /**
   * @return array Returns a minimal set of item information as a keyed array.
   * This information is common to all DDHI Item types and should be used
   * when representing the item in collection lists.
   */

  public function getListingData() {
    $qid =  $this->node->hasField('field_qid') && !empty($this->node->field_qid->getString())  ?  $this->node->field_qid->getString() : null;

    // Uses field_id if available as the ID. Then uses Wikidata QID if available in keeping with current DDHI practice. Then generates a repository-specific ID as a fallback.

    $ddhi_id = $this->node->hasField('field_id') && !empty($this->node->field_id->getString()) ? $this->node->field_id->getString() : ($qid ? $qid : 'repository-' . $this->node->id());

    $uri_root = \Drupal::request()->getSchemeAndHttpHost() .'/'.  DDHI_API_ROOT_PATH .'/items/'. (!empty($ddhi_id) ? $ddhi_id : $this->node->id());

    $data = [
      'title' => $this->node->title->getString(),
      'resource_type' => $this->node->getType(),
      'id' => $ddhi_id,
      'repository_id' => $this->node->id(),
      'uri' => [
        'canonical' => $uri_root . "?_format=json",
        'json' => $uri_root . "?_format=json",
        'xml' => $uri_root . "?_format=xml"
      ],
    ];

    // Add Wikidata keys if available.

    if ($qid) {
      $data = $data + [
          'qid' => $qid,
          'wikidata_uri' => $this->getWikiDataLink($qid),
        ];
    }

    return $data;
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
        return new ResourceResponse($this->$method());
      }
    }

    $data = $this->getData();

    // Return a subresource data key if it exists, or the whole dataset otherwise. @TODO: Consider returning an http exception if the subresource doesn't exist.

    return new ResourceResponse(array_key_exists($subresource,$data) ? $data[$subresource] : $data);
  }

  /**
   *
   * @param $key
   *
   * @return \Drupal\rest\ResourceResponse
   *
   *DEPRECATED. @TODO: Remove when safe.
   */

  public function getSubResource($key): ResourceResponse {
    $data = $this->getData();
    $method = 'getSubResource'.ucfirst($key);
    if (method_exists($this,$method)) {
      return $this->$method();
    } else if (array_key_exists($key,$data)) {
      return new ResourceResponse($data[$key]);
    }

    throw new BadRequestHttpException("Resource {$key} not found");
  }

  /**
   * STUB FUNCTION. Item type handlers should override and reference the
   * name their referencing entity reference field.
   *
   *
   * @param null $field
   *
   * @return array
   */

  public function getSubResourceReference() {
    $field = null;
    return $this->getReferencingEntities($field);
  }

  /**
   * @param $qid string The Wikidata identifier.
   *
   * @return false|string Returns a fully formed URI to the Wikidata site, or false if the $qid does not exist.
   */
  protected function getWikiDataLink($qid) {
    return $qid ? 'https://www.wikidata.org/wiki/'. \Drupal\Component\Utility\UrlHelper::stripDangerousProtocols($qid) : false;
  }

  /**
   * @param $field \Drupal\Core\Field\EntityReferenceFieldItemList. An Entity Reference field (or any array of objects with a target_id parameter).
   *
   * @return array  Returns related entity data in an array.
   */

  protected function getRelatedEntityData($field) {
    $entities = [];

    foreach ($field as $row) {
      if (!isset($row->target_id)) {
        continue;
      }

      $entityHandler = \Drupal::service('ddhi_rest.item.handler')->createInstance($row->target_id);

      if (!$entityHandler) {
        continue;
      }

      $entities[] = $entityHandler->getData();
    }

    return $entities;
  }

  protected function getReferencingEntities($field) {
    $entities = [];

    if (!$field) {
      return $entities;
    }

    $database = \Drupal::database();
    $query = $database->select("node__{$field}",'f');
    $query->condition("f.{$field}_target_id",$this->node->id());

    // @todo add condition based on query string _filter

    $query->fields('f',['entity_id','bundle']);

    $references = $query->execute();

    if (!$references) {
      return $entities;
    }

    foreach ($references as $row) {
      $entityHandler = \Drupal::service('ddhi_rest.item.handler')->createInstance($row->entity_id);

      if (!$entityHandler) {
        continue;
      }

      $entities[$row->entity_id] = $entityHandler->getListingData();
    }

    return array_values($entities);
  }


}
