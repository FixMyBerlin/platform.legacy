<?php

namespace Drupal\fmb_importer\Controller;

use Drupal\Core\Controller\ControllerBase;
use \Drupal\node\Entity\Node;
use \Drupal\node\Entity\TaxonomyTerm;

class FMBController extends ControllerBase {

  /**
   * Display the markup.
   *
   * @return array
   */
  public function content() {

    // check
    global $config;
    if(empty($config['custom_importer'])) {
      return array(
        '#type' => 'markup',
        '#markup' => "Please set custom_imporer config.",
      );
    }

    // fetch current rows from carto
    $carto_api_key = $config['custom_importer']['carto_api_key'];
    $carto_api_url = "https://bosh.carto.com/api/v2/sql?api_key=".$carto_api_key."&q=";
    $sql = "SELECT uuid FROM radverkehrsprojekte";
    $carto_json_uuids = @json_decode(file_get_contents($carto_api_url.rawurlencode($sql)));
    if(empty($carto_json_uuids)){
      return array(
        '#type' => 'markup',
        '#markup' => "Couldn't fetch data from carto: ".$sql,
      );
    }

    // load data from google spreadsheet
    require_once DRUPAL_ROOT.'/libraries/importer/init.php';
    $google_spread_sheet_id = $config['custom_importer']['google_spread_sheet_id'];
    $spreadsheet = $service->spreadsheets_values->get($google_spread_sheet_id, 'Radverkehrsprojekte');

    if(empty($spreadsheet->values)) {
      return array(
        '#type' => 'markup',
        '#markup' => "Couldn't fetch data from google.",
      );
    }
    $values = $spreadsheet->values;

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
      return array(
        '#type' => 'markup',
        '#markup' => "Header column not found.",
      );
    }

    // check and import content
    foreach($values as $row) {

      // check if do import is true and UUID is in valid format
      if(@$row[$index['do_import']] == 1 && preg_match('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/', @$row[$index['UUID']])) {
        $uuid = $row[$index['UUID']];
        $title = $row[$index['Adresse']];
        $body = $row[$index['Beschreibung der Maßnahme']];
        $project_number = $row[$index['Projektnummer']];
        $project_costs = $row[$index['Kosten']];
        $project_status = $row[$index['Status']];
        $project_type = $row[$index['Art der Maßn.']];
        $type_feedback = $row[$index['Art d. Feedback']];

        // project period
        $project_period = array('value' => '', 'end_value' => '');
        if(!empty($row[$index['Beginn']])) {
          $project_period['value'] = trim($row[$index['Beginn']]);
          if(preg_match('/^\d{4}$/', $project_period['value'])) {
            $project_period['value'] .= '-01-01';
          }
          else{
            $project_period['value'] = date('Y-m-d', strtotime($project_period['value']));
          }
        }
        if(!empty($row[$index['Fertigstellung']])) {
          $project_period['end_value'] = trim($row[$index['Fertigstellung']]);
          if(preg_match('/^\d{4}$/', $project_period['end_value'])) {
            $project_period['end_value'] .= '-12-31';
          }
          else{
            $project_period['end_value'] = date('Y-m-d', strtotime($project_period['end_value']));
          }
        }

        $the_geom = $row[$index['the_geom']];
        if(@json_decode($the_geom)) {

          // term project status
          $terms = taxonomy_term_load_multiple_by_name($project_status, 'project_status');
          if(!empty($terms)) {
            $term_project_status = array_pop($terms);
          }
          else {
            $term_project_status = \Drupal\taxonomy\Entity\Term::create([
              'vid' => 'project_status',
              'name' => $project_status,
            ]);
            $term_project_status->save();
          }

          // term project type
          $terms = taxonomy_term_load_multiple_by_name($project_type, 'project_type');
          if(!empty($terms)) {
            $term_project_type = array_pop($terms);
          }
          else {
            $term_project_type = \Drupal\taxonomy\Entity\Term::create([
              'vid' => 'project_type',
              'name' => $project_type,
            ]);
            $term_project_type->save();
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
          $entity->save();
          $entity = \Drupal::entityManager()->loadEntityByUuid('node', $uuid);
          $entity->field_project_status = $term_project_status->id();
          $entity->field_project_type = $term_project_type->id();
          $entity->field_project_number = $project_number;
          $entity->field_project_costs = $project_costs;
          $entity->field_project_period->set(0)->setValue($project_period);
          $entity->save();
          #print "Updated or inserted entity with UUID $uuid in drupal.\n";

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
              return array(
                '#type' => 'markup',
                '#markup' => "Couldn't insert data into carto: ".$sql,
              );
            }
            #print "Updated carto row for UUID $uuid.\n";
          }
          else{
            $sql = "INSERT INTO radverkehrsprojekte (name, type_feedback, uuid, the_geom, do_import) VALUES ('".$title."', '".$type_feedback."', '".$uuid."', ST_SetSRID(ST_GeomFromGeoJSON('".$the_geom."'), 4326), 1)";
            $carto_json = @json_decode(file_get_contents($carto_api_url.rawurlencode($sql)));
            if(empty($carto_json)){
              return array(
                '#type' => 'markup',
                '#markup' => "Couldn't insert data into carto: ".$sql,
              );
            }
            #print "Inserted carto row for UUID $uuid.\n";
          }
        }
        else {
          return array(
            '#type' => 'markup',
            '#markup' => "the_geom isn't valid json.",
          );
        }
      }
    }
    return array(
      '#type' => 'markup',
      '#markup' => "All done",
    );
  }
}
