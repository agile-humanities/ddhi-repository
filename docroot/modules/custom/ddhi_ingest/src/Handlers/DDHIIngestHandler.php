<?php
/**
 * @file
 * Contains \Drupal\ddhi_ingest\Handler\DDHIIngestHandler.
 *
 * Handler for managing the ingestion of TEI interviews into Drupal.
 */
namespace Drupal\ddhi_ingest\Handlers;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Archiver\Zip;

class DDHIIngestHandler extends ControllerBase {

  protected $sourceType;
  protected $sourceFile;
  protected $staging_dir;
  protected $parameters = [];
  protected $messenger;

  public function __construct($sourceType=DDHI_SOURCE_OPTION_FILE) {
    $this->sourceType = $sourceType;
    $this->staging_dir = DRUPAL_ROOT . '/' . DDHI_STAGING_DIRECTORY;
    $this->messenger = \Drupal::messenger();
  }

  public function retrieveSource() {

  }

  public function stageSource() {
    // Ensure that the staging directory exists

    if (!is_dir($this->staging_dir)) {
      mkdir($this->staging_dir,0755,true);
    }

    if (!$this->sourceFile) {
      throw new \Exception("TEI zip file has not been successfully registered.");
    }

    $this->cleanStagingDirectory();

    $zip = new Zip(\Drupal::service('file_system')->realpath($this->sourceFile->getFileUri()));

    $zip->extract($this->staging_dir);

    if ($this->hasStagedFiles()){
      $this->messenger->addMessage('Interview files have been extracted to ' . $this->staging_dir . ' and are ready for aggregation.');
      return true;
    } else {
      $this->messenger->addWarning('Interview files could not be extracted to ' . $this->staging_dir);
      return false;
    }

  }

  protected function cleanStagingDirectory() {
    // Clean staged files

    $files = glob($this->staging_dir . '/*'); // get all file names
    foreach($files as $file){ // iterate files
      if(is_file($file)) {
        unlink($file); // delete file
      }
    }
  }

  public function hasStagedFiles() {
    if (!is_readable($this->staging_dir)) return null;
    return count(scandir($this->staging_dir)) > 2; // Scandir lists files in a directory, but always includes '.' and '..'. A count above 2 indicates files other than those pointers.
  }

  public function aggregate() {

  }

  public function ingest() {

  }

  public function rollBack() {

  }

  public function setParameters($values=[]) {
    $this->parameters = $values;
  }
}
