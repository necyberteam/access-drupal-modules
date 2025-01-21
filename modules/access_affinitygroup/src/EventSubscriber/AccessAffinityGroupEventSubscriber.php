<?php

namespace Drupal\access_affinitygroup\EventSubscriber;

use Drupal\Component\Utility\Html;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\search_api\Event\QueryPreExecuteEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Drupal\search_api\Query\QueryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for the Access Affinity Group module.
 */
class AccessAffinityGroupEventSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  private $token;

  /**
   * Constructs a new AccessAffinityGroupEventSubscriber object.
   */
  public function __construct() {
    $this->token = \Drupal::token();
  }

  /**
   * Reacts to the QUERY_PRE_EXECUTE event to sort the affinity groups.
   */
  public function onQueryPreExecute(QueryPreExecuteEvent $event) {
    $query = $event->getQuery();
    if ($query->getIndex()->id() === 'affinity_groups') {
      $domain_name = Html::getClass($this->token->replace($this->t('[domain:name]')));
      if ($domain_name == "access-support") {
        $query->sort('field_affinity_group_category', QueryInterface::SORT_ASC);
      } else {
        $query->sort('field_affinity_group_category', QueryInterface::SORT_DESC);
      }
      $query->sort('title', QueryInterface::SORT_ASC);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      SearchApiEvents::QUERY_PRE_EXECUTE => 'onQueryPreExecute',
    ];
  }

}
