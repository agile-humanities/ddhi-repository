<?php
/**
 * @file
 * Contains \Drupal\ddhi_ingest\Factory\ITSBDataHandlerFactory.
 *
 * Generates a handler to managing ingestion of TEI-encoded interviews.
 */


namespace Drupal\ddhi_ingest\Factory;

class DDHIIngestHandlerFactory
{
  /**
   * Constructs a Drupal\ddhi_ingest\Factory object.
   *
   * @param null $sourceType. Optional. Currently supports “GHub” (GitHub) and “File”.
   */

  protected $sourceType;

  public function __construct()
  {

  }

  public function createInstance($sourceType='') {

    $handler = "Drupal\ddhi_ingest\Handlers\DDHIIngestHandler" . $sourceType;

    if (!class_exists($handler)) {
      throw new \Exception("Cannot create DDHI Ingest Factory Handler. Class {$handler} does not exist.");
    }
    return new $handler();

    //return new $handler($sourceType,\Drupal::service('module_handler'));
  }
}
