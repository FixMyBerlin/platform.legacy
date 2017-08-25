<?php

define('DRUPAL_DIR', /*dirname(__DIR__)*/"/Applications/MAMP/htdocs");
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

// check
if(empty($config['custom_importer'])) {
  die('Please set custom_imporer config.');
}

// fetch current rows from carto
$carto_api_key = $config['custom_importer']['carto_api_key'];
$carto_api_url = "https://bosh.carto.com/api/v2/sql?api_key=".$carto_api_key."&q=";
$sql = "SELECT uuid FROM radverkehrsprojekte";
$carto_json_uuids = @json_decode(file_get_contents($carto_api_url.rawurlencode($sql)));
if(empty($carto_json_uuids)){
  die("Couldn't fetch data from carto: ".$sql."\n");
}

// load data from google spreadsheet
require_once 'init.php';
$google_spread_sheet_id = $config['custom_importer']['google_spread_sheet_id'];
$spreadsheet = $service->spreadsheets_values->get($google_spread_sheet_id, 'Radverkehrsprojekte');
if(empty($spreadsheet->values)) {
  die("Couldn't fetch data from google.\n");
}
$values = $spreadsheet->values;
#$values = json_decode(file_get_contents('test.json'));

// identify column names
foreach($values as &$row) {
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
foreach($values as $row) {

  // check if do import is true and UUID is in valid format
  if(@$row[$index['do_import']] == 1 && preg_match('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/', @$row[$index['UUID']])) {
    $uuid = $row[$index['UUID']];
    $title = $row[$index['Adresse']];
    $body = $row[$index['Beschreibung der Maßnahme']];
    $project_number = $row[$index['Projektnummer']];
    $type_feedback = $row[$index['Art d. Feedback']];
    $the_geom = $row[$index['the_geom']];
    if(@json_decode($the_geom)) {

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
      print "Updated or inserted entity with UUID $uuid in drupal.\n";

      // update carto db
      $found_uuid = FALSE;
      foreach($carto_json_uuids->rows as $carto_row) {
        if($carto_row->uuid == $uuid) {
          $found_uuid = TRUE;
          break;
        }
      }
      if($found_uuid) {
        $sql = "UPDATE radverkehrsprojekte SET name='".$title."', type_feedback='".$type_feedback."', the_geom=ST_SetSRID(ST_GeomFromGeoJSON('".$the_geom."'), 4326), do_import=1 WHERE uuid='".$uuid."'";
        $carto_json = @json_decode(file_get_contents($carto_api_url.rawurlencode($sql)));
        if(empty($carto_json)){
          die("Couldn't insert data into carto: ".$sql."\n");
        }
        print "Updated carto row for UUID $uuid.\n";
      }
      else{
        $sql = "INSERT INTO radverkehrsprojekte (name, type_feedback, uuid, the_geom, do_import) VALUES ('".$title."', '".$type_feedback."', '".$uuid."', ST_SetSRID(ST_GeomFromGeoJSON('".$the_geom."'), 4326), 1)";
        $carto_json = @json_decode(file_get_contents($carto_api_url.rawurlencode($sql)));
        if(empty($carto_json)){
          die("Couldn't insert data into carto: ".$sql."\n");
        }
        print "Inserted carto row for UUID $uuid.\n";
      }
    }
    else {
      die("the_geom isn't valid json.");
    }
  }
}
