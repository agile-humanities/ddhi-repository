<?php
/**
 * @file
 * Contains \Drupal\ddhi_ingest\Handler\DDHIIngestHandler.
 *
 * Handler for managing the ingestion of TEI interviews into Drupal.
 */

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Extension\ModuleHandler;

abstract class DDHIIngestHandler {

  protected $sourceType='file';

  public function __construct($sourceType) {

  }

  public function retrieveSource() {

  }

  public function stageSource() {

  }

  public function aggregate() {

  }

  public function ingest() {

  }

  public function rollBack() {

  }
}
