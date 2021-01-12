<?php
/**
 * @file
 * Contains \Drupal\ddhi_ingest\Handler\DDHIIngestHandler.
 *
 * Handler for managing the ingestion of TEI interviews into Drupal.
 */

namespace Drupal\ddhi_ingest\Handlers;

use Drupal\Core\Controller\ControllerBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate_plus\Entity\MigrationGroup;
use Drupal\migrate\Plugin\RequirementsInterface;
use Drupal\migrate\Exception\RequirementsException;
use Exception;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate_plus\Entity\Migration;
use Drupal\migrate_tools\MigrateExecutable;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Archiver\Zip;

class DDHIIngestHandler extends ControllerBase {

  protected $sourceType;

  protected $sourceFile;

  protected $staging_dir;

  protected $staging_dir_interviews;

  protected $parameters = [];

  protected $messenger;

  public function __construct($sourceType = DDHI_SOURCE_OPTION_FILE) {
    $this->sourceType = $sourceType;
    $this->staging_dir = DRUPAL_ROOT . '/' . DDHI_STAGING_DIRECTORY;
    $this->staging_dir_interviews = DRUPAL_ROOT . '/' . DDHI_STAGING_DIRECTORY_INTERVIEWS;
    $this->messenger = \Drupal::messenger();
  }

  /**
   *  Retrieve a set of TEI interviews from source.
   *
   * @returns mixed. A Drupal File Object with archived files (typically .zip)
   *   if successful, false on failure.
   */
  public function retrieveSource() {

  }

  /**
   *  Place TEI files in the staging folder for aggregation.
   *
   * @returns Boolean. True indicates successful staging.
   */

  public function stageSource() {

    $this->messenger->addStatus('Aggregating TEI Files (to come)');

    return true;

  }

  /**
   * @param false $display_msg Boolean. Set to true to display the count as a status message
   *
   * @return int|void . Returns the number of interviews in the interview directory.
   */

  public function stagedInterviewCount($display_msg=false)  {
    $interview_files = glob($this->staging_dir_interviews . '/*.xml'); // get all file names
    $count = count($interview_files);

    if ($display_msg) {
      $this->messenger->addStatus("{$count} interviews are staged for import.");
    }

    return $count;
  }

  /**
   *  Remove existing files from the staging directory.
   *
   * @returns VOID.
   */

  protected function cleanStagingDirectory(): void {
    // Clean staged files

    $files = glob($this->staging_dir . '/*'); // get all file names
    foreach ($files as $file) { // iterate files
      if (is_file($file)) {
        unlink($file); // delete file
      }
    }
  }

  /**
   *  Checks to see if staging directory conforms to the File Layout
   * specifications.
   *
   * @param $file_layout_level : DDHI File Layout Specification Level as
   *   integer.
   *
   * @returns mixed. Returns the absolute path to the transcript directory, or
   *   false if directory is non-conforming/empty.
   */

  public function auditStagingDirectory($file_layout_level = 1) {
    if (!is_readable($this->staging_dir)) {
      return NULL;
    }
    return count(scandir($this->staging_dir)) > 2; // Scandir lists files in a directory, but always includes '.' and '..'. A count above 2 indicates files other than those pointers.
  }

  /**
   *  Runs the python-based DDHI aggregator. Requires a conforming Staging
   * directory.
   *
   * @returns mixed. Returns the absolute path to the aggregation directory, or
   *   false on failure.
   */

  public function aggregate() {

  }

  /**
   *  Runs the Drupal migration, ingesting aggregated TEI files into Drupal.
   * @params $ddhi_ingest_level: The DDHI Ingest API Level.
   *
   * @returns mixed. Returns the absolute path to the aggregation directory, or
   *   false on failure.
   */

  public function ingest($ddhi_ingest_level = 2) {

    $migrations = ($this->migrationList()['DDHI']);

    $this->executeMigration($migrations['ddhi_named_people_level_2']);
    $this->executeMigration($migrations['ddhi_transcripts_level_2']);

  }

  protected function executeMigration(MigrationInterface $migration): void {
    $executable = new MigrateExecutable($migration, new MigrateMessage());
    $executable->import();

    foreach($migration->getIdMap()->getMessages() as $row) {
      switch($row->level) {
        case 1:
          $this->messenger->addWarning($row->message);
          break;
        case 2:
          $this->messenger->addError($row->message);
          break;
        case 0:
        default:
          $this->messenger->addMessage($row->message);
      }
    }

    $this->messenger->addMessage($migration->label() . ' import complete. '
      . $migration->getIdMap()->processedCount() . ' record(s) processed, '
      . $migration->getIdMap()->importedCount() . ' record(s) imported, including '
      . $migration->getIdMap()->updateCount() . ' update(s) to existing content');
  }

  /**
   *  Rolls back the previous ingestion migration.
   *
   * @returns bool. Returns true on success, false otherwise.
   */

  public function rollback() {
    $migrations = $this->migrationList()['DDHI'];

    if (empty($migrations)) {
      $this->messenger->addWarning('No migrations found');
      return false;
    }

    $this->rollbackMigration($migrations['ddhi_transcripts_level_2']);
    $this->rollbackMigration($migrations['ddhi_named_people_level_2']);

  }

  protected function rollbackMigration(MigrationInterface $migration): void {
    $executable = new MigrateExecutable($migration, new MigrateMessage());
    $executable->rollback();

    foreach($migration->getIdMap()->getMessages() as $row) {
      switch($row->level) {
        case 1:
          $this->messenger->addWarning($row->message);
          break;
        case 2:
          $this->messenger->addError($row->message);
          break;
        case 0:
        default:
          $this->messenger->addMessage($row->message);
      }
    }

    $this->messenger->addMessage($migration->label() . ' rolled back. ');
  }


  /**
   *  Resets the ingest/Migration in case of problems.
   *
   * @returns bool. Returns true on success, false otherwise.
   */


  public function ingestReset() {

  }

  /**
   *  Sets handler parameters.
   *
   * @param $values : An associative array of parameters.
   *
   * @returns void.
   */

  public function setParameters($values = []) {
    $this->parameters = $values;
  }

  /**
   * Stolen shamelessly from migrate_tools module, with thanks!
   *
   * @param $migrations
   * @param false $names_only
   */

  public function migrationStatus($migrations, $names_only = FALSE) {
    $table = [];
    // Take it one group at a time, listing the migrations within each group.
    foreach ($migrations as $group_id => $migration_list) {
      $group = MigrationGroup::load($group_id);
      $group_name = !empty($group) ? "{$group->label()} ({$group->id()})" : $group_id;
      if ($names_only) {
        $table[] = [
          $this->t('Group: @name', ['@name' => $group_name]),
        ];
      }
      else {
        $table[] = [
          $this->t('Group: @name', ['@name' => $group_name]),
          $this->t('Status'),
          $this->t('Total'),
          $this->t('Imported'),
          $this->t('Unprocessed'),
          $this->t('Last imported'),
        ];
      }
      foreach ($migration_list as $migration_id => $migration) {
        try {
          $map = $migration->getIdMap();
          $imported = $map->importedCount();
          $source_plugin = $migration->getSourcePlugin();
        } catch (Exception $e) {
          $this->messenger->addWarning($this->t('Failure retrieving information on @migration: @message',
            ['@migration' => $migration_id, '@message' => $e->getMessage()]));
          continue;
        }
        if ($names_only) {
          $table[] = [$migration_id];
        }
        else {
          try {
            $source_rows = $source_plugin->count();
            // -1 indicates uncountable sources.
            if ($source_rows == -1) {
              $source_rows = $this->t('N/A');
              $unprocessed = $this->t('N/A');
            }
            else {
              $unprocessed = $source_rows - $map->processedCount();
            }
          } catch (Exception $e) {
            $this->messenger->addWarning($e->getMessage());
            $this->messenger->addWarning($this->t('Could not retrieve source count from @migration: @message',
              [
                '@migration' => $migration_id,
                '@message' => $e->getMessage(),
              ]));
            $source_rows = $this->t('N/A');
            $unprocessed = $this->t('N/A');
          }

          $status = $migration->getStatusLabel();
          $migrate_last_imported_store = \Drupal::keyValue('migrate_last_imported');
          $last_imported = $migrate_last_imported_store->get($migration->id(), FALSE);
          if ($last_imported) {
            /** @var \Drupal\Core\Datetime\DateFormatter $date_formatter */
            $date_formatter = \Drupal::service('date.formatter');
            $last_imported = $date_formatter->format($last_imported / 1000,
              'custom', 'Y-m-d H:i:s');
          }
          else {
            $last_imported = '';
          }
          $table[] = [
            $migration_id,
            $status,
            $source_rows,
            $imported,
            $unprocessed,
            $last_imported,
          ];
        }
      }
    }
    return($table);
  }

  public function migrationList($migration_group = ['DDHI'], $migration_ids = '', $migration_tags = []) {
    // Filter keys must match the migration configuration property name.
    $filter['migration_group'] = !empty($migration_group) ? $migration_group : [];
    $filter['migration_tags'] = !empty($migration_tags) ? $migration_tags : [];

    $manager = \Drupal::service('plugin.manager.migration');
    $plugins = $manager->createInstances([]);
    $matched_migrations = [];

    // Get the set of migrations that may be filtered.
    if (empty($migration_ids)) {
      $matched_migrations = $plugins;
    }
    else {
      // Get the requested migrations.
      $migration_ids = explode(',', mb_strtolower($migration_ids));
      foreach ($plugins as $id => $migration) {
        if (in_array(mb_strtolower($id), $migration_ids)) {
          $matched_migrations[$id] = $migration;
        }
      }
    }

    // Do not return any migrations which fail to meet requirements.
    /** @var \Drupal\migrate\Plugin\Migration $migration */
    foreach ($matched_migrations as $id => $migration) {
      if ($migration->getSourcePlugin() instanceof RequirementsInterface) {
        try {
          $migration->getSourcePlugin()->checkRequirements();
        } catch (RequirementsException $e) {
          unset($matched_migrations[$id]);
        }
      }
    }


    // Filters the matched migrations if a group or a tag has been input.
    if (!empty($filter['migration_group']) || !empty($filter['migration_tags'])) {
      // Get migrations in any of the specified groups and with any of the
      // specified tags.
      foreach ($filter as $property => $values) {
        if (!empty($values)) {
          $filtered_migrations = [];
          foreach ($values as $search_value) {
            foreach ($matched_migrations as $id => $migration) {
              // Cast to array because migration_tags can be an array.
              $configured_values = (array) $migration->get($property);
              $configured_id = (in_array($search_value, $configured_values)) ? $search_value : 'default';
              if (empty($search_value) || $search_value == $configured_id) {
                if (empty($migration_ids) || in_array(mb_strtolower($id), $migration_ids)) {
                  $filtered_migrations[$id] = $migration;
                }
              }
            }
          }
          $matched_migrations = $filtered_migrations;
        }
      }
    }

    // Sort the matched migrations by group.
    if (!empty($matched_migrations)) {
      foreach ($matched_migrations as $id => $migration) {
        $configured_group_id = empty($migration->migration_group) ? 'default' : $migration->migration_group;
        $migrations[$configured_group_id][$id] = $migration;
      }
    }
    return isset($migrations) ? $migrations : [];
  }

}

