<?php

namespace Drupal\rate\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\rate\RateEntityVoteWidget;
use Drupal\rate\RateVote;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Returns responses for Rate routes.
 */
class VoteController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The config factory wrapper to fetch settings.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The cache tags invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected $cacheTagsInvalidator;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The vote service.
   *
   * @var \Drupal\rate\RateVote
   */
  protected $rateVote;

  /**
   * RateEntityVoteWidget connection object.
   *
   * @var \Drupal\rate\RateEntityVoteWidget
   */
  protected $voteWidget;

  /**
   * Constructs a Vote Controller.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cache_tags_invalidator
   *   The cache tags invalidator.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\rate\RateVote $rate_vote
   *   The bot detector service.
   * @param \Drupal\rate\RateEntityVoteWidget $vote_widget
   *   The vote widget to display.
   */
  public function __construct(ConfigFactoryInterface $config_factory,
                              EntityTypeManagerInterface $entity_type_manager,
                              CacheTagsInvalidatorInterface $cache_tags_invalidator,
                              RendererInterface $renderer,
                              RateVote $rate_vote,
                              RateEntityVoteWidget $vote_widget) {
    $this->config = $config_factory->get('rate.settings');
    $this->entityTypeManager = $entity_type_manager;
    $this->cacheTagsInvalidator = $cache_tags_invalidator;
    $this->renderer = $renderer;
    $this->rateVote = $rate_vote;
    $this->voteWidget = $vote_widget;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('cache_tags.invalidator'),
      $container->get('renderer'),
      $container->get('rate.vote'),
      $container->get('rate.entity.vote_widget')
    );
  }

  /**
   * Invalidate cache tags to update vote display.
   *
   * @param string $entity_type_id
   *   The entity type.
   * @param int $entity_id
   *   The entity id.
   * @param string $bundle
   *   The bundle name.
   */
  protected function invalidateCacheTags($entity_type_id, $entity_id, $bundle) {
    $invalidate_tags = [
      $entity_type_id . ':' . $entity_id,
      'vote:' . $bundle . ':' . $entity_id,
    ];
    $this->cacheTagsInvalidator->invalidateTags($invalidate_tags);
  }

  /**
   * Prepare a response object.
   *
   * @param string $entity_type_id
   *   The entity type.
   * @param int $entity_id
   *   The entity id.
   * @param string $bundle
   *   The bundle name.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return AjaxResponse|RedirectResponse
   *   The response object.
   */
  protected function prepareResponse($entity_type_id, $entity_id, $bundle, Request $request) {
    $use_ajax = $this->config->get('use_ajax');
    // If Request was AJAX and voting on a node, send AJAX response.
    if ($use_ajax) {
      $response = new AjaxResponse();
      $vote_widget = $this->voteWidget->buildRateVotingWidget($entity_id, $entity_type_id, $bundle);
      $widget_id = '#rate-' . $entity_type_id . '-' . $entity_id;
      $html = $this->renderer->render($vote_widget);
      $response->addCommand(new ReplaceCommand($widget_id, $html));
      return $response;
    }
    // Otherwise, redirect back to destination.
    else {
      $url = $request->getUriForPath($request->getPathInfo());
      return new RedirectResponse($url);
    }
  }

  /**
   * Record a vote.
   *
   * @param string $entity_type_id
   *   Entity type ID such as node.
   * @param string $vote_type_id
   *   Vote type id.
   * @param int $entity_id
   *   Entity id of the entity type.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object that contains redirect path.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect to path provided in request.
   */
  public function vote($entity_type_id, $vote_type_id, $entity_id, Request $request) {
    $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($entity_id);
    $this->rateVote->vote($entity_type_id, $vote_type_id, $entity_id, $request);
    $this->invalidateCacheTags($entity_type_id, $entity_id, $entity->bundle());
    return $this->prepareResponse($entity_type_id, $entity_id, $entity->bundle(), $request);
  }

  /**
   * Undo a vote.
   *
   * @param string $entity_type_id
   *   Entity type ID such as node.
   * @param int $entity_id
   *   Entity id of the entity type.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object that contains redirect path.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect to path provided in request.
   */
  public function undoVote($entity_type_id, $entity_id, Request $request) {
    $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($entity_id);
    $this->rateVote->undoVote($entity_type_id, $entity_id, $request);
    $this->invalidateCacheTags($entity_type_id, $entity_id, $entity->bundle());
    return $this->prepareResponse($entity_type_id, $entity_id, $entity->bundle(), $request);
  }

}
