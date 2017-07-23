<?php

namespace Drupal\rate\Controller;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;

/**
 * Returns responses for Rate routes.
 */
class ResultsController extends ControllerBase {

  /**
   * Display rate voting results views.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node for which to display results.
   *
   * @return array
   *   The render array.
   */
  public function results(NodeInterface $node) {
    // First, make sure the data is fresh.
    $cache_bins = Cache::getBins();
    $cache_bins['data']->deleteAll();
    // Get and return the rate results views.
    $page[] = views_embed_view('rate_results', 'results_block', $node->id(), 'node');
    $page[] = views_embed_view('rate_results', 'summary_block', $node->id(), 'node');
    return $page;
  }

}
