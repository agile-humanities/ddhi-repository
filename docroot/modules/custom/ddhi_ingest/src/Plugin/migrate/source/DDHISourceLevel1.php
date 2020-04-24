<?php

namespace Drupal\ddhi_ingest\Plugin\migrate\source;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate_plus\Plugin\migrate\source\Url;

/**
 * Source plugin for retrieving data via URLs.
 *
 * @MigrateSource(
 *   id = "ddhi_source_level_1"
 * )
 */
class DDHISourceLevel1 extends Url {

  /**
   * This implementation has a single pre-set source url, the concatenated XML record.
   *
   * @var array
   */
  protected $sourceUrls = [];
    
  /**
   * Filepath of the data directory.
   *
   * @var string
   */
   
  protected $dataSourceDir = '';


  /**
   * The data parser plugin.
   *
   * @var \Drupal\migrate_plus\DataParserPluginInterface
   */
  protected $dataParserPlugin;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration) {
    
    if (!key_exists('data_source_dir',$configuration)) {
      $configuration['data_source_dir'] = '../data/transcripts'; // Default source output
    }
    
    $this->dataSourceDir = $configuration['data_source_dir']; // Set source output
    
    $configuration['urls'][] = 'public://ddhi_ingest/records.xml'; // Concatenated record.
    
    $this->prepareRecords();
    
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);

  }
  
  protected function prepareRecords() {
    ddhi_ingest_prepare_data_records($this->dataSourceDir,true);
  }

  /**
   * Return a string representing the source URLs.
   *
   * @return string
   *   Comma-separated list of URLs being imported.
   */
   
  /*
  public function __toString() {
    // This could cause a problem when using a lot of urls, may need to hash.
    $urls = implode(', ', $this->sourceUrls);
    return $urls;
  } */

  /**
   * Returns the initialized data parser plugin.
   *
   * @return \Drupal\migrate_plus\DataParserPluginInterface
   *   The data parser plugin.
   */
   
  /*
  public function getDataParserPlugin() {
    if (!isset($this->dataParserPlugin)) {
      $this->dataParserPlugin = \Drupal::service('plugin.manager.migrate_plus.data_parser')->createInstance($this->configuration['data_parser_plugin'], $this->configuration);
    }
    return $this->dataParserPlugin;
  }
  
  */

  /**
   * Creates and returns a filtered Iterator over the documents.
   *
   * @return \Iterator
   *   An iterator over the documents providing source rows that match the
   *   configured item_selector.
   */
   
   /*
  protected function initializeIterator() {
    return $this->getDataParserPlugin();
  } */

}
