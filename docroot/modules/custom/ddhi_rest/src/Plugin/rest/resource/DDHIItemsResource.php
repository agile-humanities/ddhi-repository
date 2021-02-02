<?php

namespace Drupal\ddhi_rest\Plugin\rest\resource;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\ddhi_rest\Items\DDHIItemHandlerFactory;
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
use Drupal\ddhi_ingest\Handlers\DDHIIngestHandler;
use Drupal\ddhi_rest\Items\DDHIItemHandler;
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
  protected $itemHandler;
  protected $ingestHandler;


  function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, EntityTypeManagerInterface $entity_type_manager, AccountProxyInterface $current_user, Request $current_request, DDHIItemHandlerFactory $item_handler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);

    $this->currentUser = $current_user;
    $this->currentRequest = $current_request;
    $this->entityTypeManager = $entity_type_manager;
    $this->itemHandler = $item_handler;
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
      $container->get('ddhi_rest.item.handler')
    );
  }

  public function get($id=null) {
    if (!$id) {
      throw new NotFoundHttpException();
    }

    $itemHandler = $this->itemHandler->createInstance($id);

    if (!$itemHandler->isValid()) {
      throw new BadRequestHttpException("Requested resource is not valid.");
    }

    return $itemHandler->getResource($this->getFormat());
  }

  public function post($id) {

  }




  protected function getFormat() {
    $request_format = $this->currentRequest->query->get('_format');
    return $request_format ? $request_format : 'json';
  }

}
