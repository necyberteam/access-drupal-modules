<?php


namespace Drupal\access_misc\Plugin\views\access;

use Drupal\views\Plugin\views\access\AccessPluginBase;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Routing\Route;

/**
 * Class ViewsCustomAccess
 *
 * @ingroup views_access_plugins
 *
 * @ViewsAccess(
 *     id = "ViewsRegistrationCustomAccess",
 *     title = @Translation("Custom Registration Access"),
 *     help = @Translation("Custom Registration Access for view"),
 * )
 */
class ViewsRegistrationCustomAccess extends AccessPluginBase {
  /**
   * {@inheritdoc}
   */
  public function summaryTitle() {
    return $this->t('Custom Registration Access');
  }


  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account) {
    $access = FALSE;

    // Get current uri.
    $current_uri = \Drupal::service('path.current')->getPath();
    $url_bits = explode('/', $current_uri);
    $event_id = is_numeric($url_bits[2]) ? $url_bits[2] : 0;

    $eventinstance = \Drupal::entityTypeManager()->getStorage('eventinstance')->load($event_id);
    $eventseries = $eventinstance->getEventSeries();
    // Get author of event series.
    $author = $eventseries->getOwner();

    if ($author->id() == $account->id()) {
      $access = TRUE;
    }

    if ($account->hasPermission('administer site configuration')) {
      $access = TRUE;
    }

    return $access;
  }


  /**
   * {@inheritdoc}
   */
  public function alterRouteDefinition(Route $route) {
    $route->setRequirement('_access', 'TRUE');
  }
}
