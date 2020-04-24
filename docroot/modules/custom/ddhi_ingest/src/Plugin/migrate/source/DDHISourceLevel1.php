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
   * URI of the temporary output record.
   *
   * @var string
   */
   
  protected $sourceOutputFile = '';
  
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
    
    if (!key_exists('source_output_file',$configuration)) {
      $configuration['source_output_file'] = 'ddhi_ingest/records.xml'; // Default source output
    }
    
    if (!key_exists('data_source_dir',$configuration)) {
      $configuration['data_source_dir'] = '../data/transcripts'; // Default source output
    }
    
    $this->dataSourceDir = $configuration['data_source_dir']; // Set source output
    $this->sourceOutputFile = $configuration['source_output_file']; // Set source output
    
    $configuration['urls'][] = 'public://ddhi_ingest/records.xml'; // Concatenated record.
    
    $this->prepareRecords();
    
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);

  }
  
  protected function prepareRecords() {
    
    
    $file_scheme_root = \Drupal::service('file_system')->realpath(file_default_scheme() . "://");
    $outFilePath =  $file_scheme_root . '/' . $this->sourceOutputFile;
        
    // @todo: Drupalize this concatenation function. Consider using a managed file approach.
    
    //Set source directory and output file location
    
    $dirPath = DRUPAL_ROOT . "/" . $this->dataSourceDir;
    
    // Create directories if necessary
    
    $pathArray = explode('/',$this->sourceOutputFile);
    
    if (count($pathArray) > 1) {
      $dirpath = $file_scheme_root;
      array_pop($pathArray); // Remove the filename, leaving only directories
      foreach ($pathArray as $dir) {
        $dirpath .= "/{$dir}";
        if (!is_dir($dirpath)) {
          mkdir($dirpath, 0755, true);
        }
      }
    }
    
    // Prepare outfile for writing. 
    
    $outFile = fopen($outFilePath, "w+");
    
    // Add a doctype declaration 
    
    fwrite($outFile,'<?xml-model type="application/relax-ng-compact-syntax"?>' . "\n");
    fwrite($outFile,"<DDHIMessage>\n");
        
    //Then cycle through the files reading and writing.

    foreach(scandir($dirPath) as $file){
      
        // Skip non XML files. @todo this will become a more sophisticated file integrity validator
                
        if (stripos($file, '.xml') == false) {
          continue;
        }
      
            
        $teiFilePath = $dirPath . "/" . $file;
        $inFile = fopen($teiFilePath, "r");
                        
        //Package the TEI. This will be replaced with values from the File Specification.
        
        fwrite($outFile,"<DDHIPackage>\n");
        fwrite($outFile,"<DDHIPackageHeader>\n");
        fwrite($outFile,"  <head>v001</head>\n");
        fwrite($outFile,"  <id>{$file}</id>\n"); // Use filename for now
        fwrite($outFile,"  <message></message>\n");
        fwrite($outFile,"  <TEIFilePath>{$teiFilePath}</TEIFilePath>\n");
        fwrite($outFile,"</DDHIPackageHeader>\n");
        
        // Copy lines
        
        while ($line = fgets($inFile)){
          // Filter out XML declaration
          if (!preg_match('/^<\?xml.+?>.*\n$/',$line)) {
            
            /** Strips namespace (and all attributes) from TEI tag.
              * See note in ddhi_ingest_level_1.yml file about namespaces. */
            
            if (preg_match('/<TEI.+?>/',$line)) {
              fwrite($outFile,"<TEI>\n"); //non-namespaced TEI tag
            } else {
              fwrite($outFile, $line); // append line
            }
          }
        }
        
        fclose($inFile);
        
        fwrite($outFile,"</DDHIPackage>\n");
    }
    
    fwrite($outFile,'</DDHIMessage>');

    //Then clean up
    fclose($outFile);

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
