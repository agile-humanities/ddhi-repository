<?php

/**
 * @file
 * Contains \Drupal\ddhi_ingest\Handler\DDHIIngestHandlerFile.
 *
 * Handler for managing the ingestion of TEI interviews from an uploaded, managed Drupal file.
 */

namespace Drupal\ddhi_ingest\Handlers;

use Drupal\ddhi_ingest\Handlers\DDHIIngestHandler;

class DDHIIngestHandlerFile extends DDHIIngestHandler {

  protected $file_directory;
  protected $steamWrapperURI;

  public function __construct($sourceType)
  {

    $this->file_directory = 'tei-interviews/source/' . date('Ymd');
    $this->steamWrapperURI = "public://";

    parent::__construct($sourceType);
  }

  public function retrieveSource($uri=null,$filename='tei_interviews_') {

    if ($uri===null) {
      return false;
    }

    $filename = $filename .'_'. date('Ymd') . '.zip';

    $file_system = \Drupal::service('file_system');
    if (!is_dir(\Drupal::service('file_system')->realpath($this->steamWrapperURI . $this->file_directory))) {
      $file_system->mkdir($this->steamWrapperURI . $this->file_directory,null,true);
    }
    $destination = $this->steamWrapperURI . $this->file_directory . "/" . $filename;
    $this->sourceFile = system_retrieve_file($uri,$destination,true);
    if ($this->sourceFile === false) {
      $this->messenger->addWarning('The system could not retrieve the interview file from ' . $uri . '. The ingestion process has stopped.');
    } else {
      $this->messenger->addMessage("Interviews successfully retrieved from {$uri}.");
    }

    return $this->sourceFile;
  }
}
