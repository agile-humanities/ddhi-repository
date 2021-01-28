<?php

namespace Drupal\ddhi_rest\Plugin\rest\resource;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\migrate\Plugin\migrate\process\MigrationLookup;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpFoundation\Request;
use Drupal\rest\ResourceResponse;
use Drupal\migrate\MigrateLookupInterface;
use Drupal\ddhi_ingest\Handlers\DDHIIngestHandler;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "ddhi_items_resource",
 *   label = @Translation("DDHI: Items Resource"),
 *   uri_paths = {
 *     "canonical" = "/ddhi-api/items/{id}"
 *   }
 * )
 */

class DDHIItemsResource extends ResourceBase {

  protected $currentUser;
  protected $currentRequest;
  protected $entityTypeManager;
  protected $migrateLookup;
  protected $ingestHandler;


  function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, EntityTypeManagerInterface $entity_type_manager, AccountProxyInterface $current_user, Request $current_request,MigrateLookupInterface $migrate_lookup) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);

    $this->currentUser = $current_user;
    $this->currentRequest = $current_request;
    $this->entityTypeManager = $entity_type_manager;
    $this->migrateLookup = $migrate_lookup;
    $this->ingestHandler = new DDHIIngestHandler();
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('ddhi'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('migrate.lookup')
    );
  }

  public function get($id=null) {
    if (!$id) {
      throw new NotFoundHttpException();
    }

    $id = $this->resolveIds($id);

    if ($id === false) {
      throw new BadRequestHttpException("Requested resource is not valid.");
    }

    $node = $this->getNode($id);

    return new ResourceResponse($node);
  }

  public function post($id) {

  }

  /**
   * Resolve an identifier into a node id (nid).
   * If passed a nid or array of nids it will pass the value back out.
   * Other identifiers are presumed to be DDHI identifiers and their corresponding node ids are returned.
   *
   * @param mixed $ids. Handles a single id or an array of ids.
   * @param bool $force_array. Return an array regardless of the type it's passed.
   *
   * @return array|mixed . Returns resolved nids as an array or single value depending on the type it's passed. Returns false if there are no valid ids.
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\migrate\MigrateException
   */

  protected function resolveIds($ids, $force_array=false) {
    $type = gettype($ids);
    $ids = $type == 'array' ? $ids : [$ids];
    $dest_ids = $this->migrateLookup->lookup($this->ingestHandler->getMigrationIDs(2,true),$ids);
    $resolved = [];

    foreach (!empty($dest_ids) ? $dest_ids : $ids as $nid) {
      if (!empty(\Drupal::entityQuery('node')->condition('nid', $nid)->execute())) {
        $resolved[] = $nid;
      }
    }

    if (empty($resolved)) {
      return false;
    }

    return $type == 'array' ? $resolved : array_shift($resolved);
  }

  protected function getFormat() {
    $request_format = $this->currentRequest->query->get('_format');
    return $request_format ? $request_format : 'json';
  }

  /**
   * @param $id
   * @return array|int
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */

  protected function getNodeQuery($id) {
    return $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('nid',$id)
      ->condition('status', 1)
      ->execute();
  }

  protected function getNode($id) {
    if (is_array($id)) {
      return $this->entityTypeManager->getStorage('node')->loadMultiple($id);
    } else {
      return $this->entityTypeManager->getStorage('node')->load($id);
    }
  }


}
