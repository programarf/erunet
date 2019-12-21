<?php

namespace Drupal\eru_csv_importer\Plugin\Importer;

use Drupal\eru_csv_importer\Plugin\ImporterBase;

/**
 * Class NodeImporter.
 *
 * @Importer(
 *   id = "node_importer",
 *   entity_type = "node",
 *   label = @Translation("Node importer")
 * )
 */
class NodeImporter extends ImporterBase {}
