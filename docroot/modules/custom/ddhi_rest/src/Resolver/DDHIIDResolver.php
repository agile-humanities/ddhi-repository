<?php


namespace Drupal\ddhi_rest\Resolver;

use Drupal\migrate\MigrateLookupInterface;
use Drupal\ddhi_ingest\Handlers\DDHIIngestHandler;


class DDHIIDResolver {
  protected $migrateLookup;
  protected $ingestHandler;

  public function __construct() {
    $this->migrateLookup = \Drupal::service('migrate.lookup');
    $this->ingestHandler = new DDHIIngestHandler();
  }

  /**
   * Resolve an identifier into a node id (nid).
   * If passed a nid or array of nids it will pass the value back out.
   * Other identifiers are presumed to be DDHI identifiers and their corresponding node ids are returned.
   *
   * @param mixed $ids. Handles a single id or an array of ids.
   * @param bool $force_array. Return an array regardless of the type it's passed.
   *
   * @return array|mixed . Returns resolved nids as an array or single value depending on the type it's passed. Returns false if there are no valid ids.
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\migrate\MigrateException
   */

  public function resolveIds($ids, $force_array=false) {
    $type = gettype($ids);
    $ids = $type == 'array' ? $ids : [$ids];
    $dest_ids = $this->migrateLookup->lookup($this->ingestHandler->getMigrationIDs(2,true),$ids);
    $resolved = [];

    foreach (!empty($dest_ids) ? array_column($dest_ids,'nid') : $ids as $nid) {
      if (!empty(\Drupal::entityQuery('node')->condition('nid', $nid)->execute())) {
        $resolved[] = $nid;
      }
    }

    if (empty($resolved)) {
      return false;
    }

    return $type == 'array' ? $resolved : array_shift($resolved);
  }
}
