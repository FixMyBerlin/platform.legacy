<?php

define('DRUPAL_DIR', dirname(__DIR__));
use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;
require_once DRUPAL_DIR . '/core/includes/database.inc';
require_once DRUPAL_DIR . '/core/includes/schema.inc';

// specify relative path to the drupal root.
$autoloader = require_once DRUPAL_DIR . '/autoload.php';
$request = Request::createFromGlobals();

// bootstrap drupal to different levels
$kernel = DrupalKernel::createFromRequest($request, $autoloader, 'prod');
$kernel->boot();
$kernel->prepareLegacyRequest($request);

use \Drupal\node\Entity\Node;
use \Drupal\node\Entity\TaxonomyTerm;

require_once 'init.php';
$spreadsheet = $service->spreadsheets_values->get($fileId, 'Radverkehrsprojekte');

// identify column names
foreach($spreadsheet->values as &$row) {
  foreach($row as &$cell) {
    $cell = trim($cell);
    if($cell == "UUID") {
      $index = array_flip($row);
    }
  }
}

if(!isset($index)) {
  die("Header column not found.");
}

// check and import content
foreach($spreadsheet->values as $row) {

  // check if do import is true and UUID is in valid format
  if(@$row[$index['do_import']] == 1 && preg_match('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/', @$row[$index['UUID']])) {
    $uuid = $row[$index['UUID']];
    $title = $row[$index['Adresse']];
    $body = $row[$index['Beschreibung der Maßnahme']];
    $project_number = $row[$index['Projektnummer']];

    // status
    $status = $row[$index['Status']];
    $terms = taxonomy_term_load_multiple_by_name($status, 'project_status');
    if(!empty($terms)) {
      $term_status = array_pop($terms);
    }
    else {
      $term_status = \Drupal\taxonomy\Entity\Term::create([
        'vid' => 'project_status',
        'name' => $status,
      ]);
      $term_status->save();
    }

    // create entity if empty
    if(!($entity = \Drupal::entityManager()->loadEntityByUuid('node', $uuid))) {
      $entity = Node::create([
        'type' => 'project',
        'uuid' => $uuid,
      ]);
    }
    $entity->title = $title;
    $entity->body = $body;
    $entity->field_project_status = $term_status->id();
    $entity->field_project_number = $project_number;
    $entity->save();
  }
}
