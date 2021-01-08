<?php

/**
 * @file
 * Contains \Drupal\ddhi_ingest\Handler\DDHIIngestHandlerFile.
 *
 * Handler for managing the ingestion of TEI interviews from an uploaded, managed Drupal file.
 */

namespace Drupal\ddhi_ingest\Handlers;

use Drupal\Core\Archiver\Zip;
use Drupal\ddhi_ingest\Handlers\DDHIIngestHandler;

class DDHIIngestHandlerFile extends DDHIIngestHandler
{

  protected $file_directory;
  protected $steamWrapperURI;

  public function __construct($sourceType)
  {

    $this->file_directory = 'tei-interviews/source/' . date('Ymd');
    $this->steamWrapperURI = "public://";

    parent::__construct($sourceType);
  }

  public function retrieveSource($uri = null, $filename = 'tei_interviews_')
  {

    if ($uri === null) {
      return false;
    }

    $filename = $filename . '_' . date('Ymd') . '.zip';

    $file_system = \Drupal::service('file_system');
    if (!is_dir(\Drupal::service('file_system')->realpath($this->steamWrapperURI . $this->file_directory))) {
      $file_system->mkdir($this->steamWrapperURI . $this->file_directory, null, true);
    }
    $destination = $this->steamWrapperURI . $this->file_directory . "/" . $filename;
    $this->sourceFile = system_retrieve_file($uri, $destination, true);
    if ($this->sourceFile === false) {
      $this->messenger->addWarning('The system could not retrieve the interview file from ' . $uri . '. The ingestion process has stopped.');
    } else {
      $this->messenger->addMessage("Interviews successfully retrieved from {$uri}.");
    }

    return $this->sourceFile;
  }

  public function stageSource():bool
  {
    // Ensure that the staging directory exists

    if (!is_dir($this->staging_dir)) {
      mkdir($this->staging_dir, 0755, true);
    }

    if (!$this->sourceFile) {
      throw new \Exception("TEI zip file has not been successfully registered.");
    }

    $this->cleanStagingDirectory();

    $zip = new Zip(\Drupal::service('file_system')->realpath($this->sourceFile->getFileUri()));

    $zip->extract($this->staging_dir);

    if ($this->auditStagingDirectory()) {
      $this->messenger->addMessage('Interview files have been extracted to ' . $this->staging_dir . ' and are ready for aggregation.');
      return true;
    } else {
      $this->messenger->addWarning('Interview files could not be extracted to ' . $this->staging_dir);
      return false;
    }

  }
}
