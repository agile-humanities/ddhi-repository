<?php

namespace Drupal\ddhi_ingest\Plugin\migrate\source;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate_plus\Plugin\migrate\source\Url;
use Drupal\migrate_source_directory\Plugin\migrate\source\Directory;
use Drupal\ddhi_ingest\Handlers\DDHIIngestHandler;
use Drupal\migrate\MigrateException;

/**
 * Source plugin for retrieving data via URLs.
 *
 * @MigrateSource(
 *   id = "ddhi_tei_files_level_2"
 * )
 */
class DDHITEIFilesLevel2 extends Directory {

  protected $ingestHandler;
  protected $messenger;

  function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration) {
    $this->ingestHandler = new DDHIIngestHandler();
    $this->messenger = \Drupal::messenger();

    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);

    // Update both the Plugin Config and the urls property with the staged interview directory path.

    $this->configuration['urls'] = [];
    array_push($this->configuration['urls'],$this->ingestHandler->getInterviewsDirectory(true));

    $this->urls = [];
    array_push($this->urls,$this->ingestHandler->getInterviewsDirectory(true));

    $this->configuration['file_extensions'] = [['xml']];
  }

  function initializeIterator() {
    parent::initializeIterator();

    foreach ($this->filesList as &$item) {
      if (empty($item['url'])) {
        throw new MigrateException("TEI Item does not have a valid URL");
      }

      // Get the ID number from the the TEI file.

      $tei = simplexml_load_file($item['url']);

      if ($tei === false) {
        throw new MigrateException("This is an invalid TEI-XML file: {$item['url']}. File migration has stopped.");
      }

      $item['id'] = $tei->teiHeader->fileDesc->publicationStmt->idno->__toString();

      $this->messenger->addMessage("ID: " . $item['id']);

    }

    return new \ArrayIterator($this->filesList);
  }

  /**
   * @return \string[][]
   *
   * Use the ID from the TEI file as the migration identifier.
   */

  function getIds() {
    return ['id' => ['type' => 'string']];
  }

}
