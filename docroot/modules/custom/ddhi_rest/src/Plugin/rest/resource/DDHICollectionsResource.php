<?php

namespace Drupal\ddhi_rest\Plugin\rest\resource;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\ddhi_rest\Collections\DDHICollectionHandlerFactory;
use Drupal\ddhi_rest\Items\DDHIItemHandlerFactory;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpFoundation\Request;
use Drupal\ddhi_ingest\Handlers\DDHIIngestHandler;
/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "ddhi_collections_resource",
 *   label = @Translation("DDHI: Collections Resource"),
 *   uri_paths = {
 *     "canonical" = "/ddhi-api/collections/{collection}"
 *   }
 * )
 */

class DDHICollectionsResource extends ResourceBase {

  protected $currentUser;
  protected $currentRequest;
  protected $entityTypeManager;
  protected $collectionHandler;
  protected $ingestHandler;


  function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, EntityTypeManagerInterface $entity_type_manager, AccountProxyInterface $current_user, Request $current_request, DDHICollectionHandlerFactory $collection_handler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);

    $this->currentUser = $current_user;
    $this->currentRequest = $current_request;
    $this->entityTypeManager = $entity_type_manager;
    $this->collectionHandler = $collection_handler;
    $this->ingestHandler = new DDHIIngestHandler();

    if (empty($this->currentRequest->query->get('_format'))) {
      $this->currentRequest->attributes->set('_format','json');
    }
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
      $container->get('ddhi_rest.collection.handler')
    );
  }

  public function get($collection=null) {
    return $this->retrieve($collection);
  }

  public function post($type) {
    return $this->retrieve($collection);
  }

  protected function retrieve($collection=null) {
    if (!$collection) {
      throw new BadRequestHttpException("Requested collection is not valid.");
    }

    $collectionHandler = $this->collectionHandler->createInstance($collection);

    return $collectionHandler->getResource();

  }

}
