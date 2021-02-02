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
   * @return array Retrieves an array of content data. Provides a base dataset, then allows child classes (representing various content types) to modify it by overriding this method.
   */

  public function getData(): array {

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
        return $this->$method();
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
   * @param $qid The Wikidata indentifier.
   *
   * @return false|string Returns a fully formed URI to the Wikidata site, or false if the $qid does not exist.
   */
  protected function getWikiDataLink($qid) {
    return $qid ? 'https://www.wikidata.org/wiki/'. \Drupal\Component\Utility\UrlHelper::stripDangerousProtocols($qid) : false;
  }

  /**
   * @param $field \Drupal\Core\Field\EntityReferenceFieldItemList.  an Entity Reference field (or any array of objects with a target_id parameter).
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

}
