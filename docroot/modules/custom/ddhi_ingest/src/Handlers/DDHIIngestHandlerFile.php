<?php

/**
 * @file
 * Contains \Drupal\ddhi_ingest\Handler\DDHIIngestHandlerFile.
 *
 * Handler for managing the ingestion of TEI interviews from an uploaded, managed Drupal file.
 */

abstract class DDHIIngestHandlerGHub extends DDHIIngestHandler {

  public function __construct($sourceType)
  {
    parent::__construct($sourceType);
  }
}
