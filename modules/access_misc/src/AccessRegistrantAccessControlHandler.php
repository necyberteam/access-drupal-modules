<?php

namespace Drupal\access_misc;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\recurring_events_registration\RegistrantAccessControlHandler;

/**
 * Access controller for the Registrant entity.
 *
 * @see \Drupal\recurring_events_registration\Entity\Registrant.
 */
class AccessRegistrantAccessControlHandler extends RegistrantAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    // Get current uri.
    $current_uri = \Drupal::service('path.current')->getPath();
    $url_bits = explode('/', $current_uri);
    $event_id = is_numeric($url_bits[2]) ? $url_bits[2] : 0;

    $eventinstance = \Drupal::entityTypeManager()->getStorage('eventinstance')->load($event_id);
    $eventseries = $eventinstance->getEventSeries();
    // Get author of event series.
    $author = $eventseries->getOwner();

    /** @var \Drupal\recurring_events_registration\Entity\RegistrantInterface $entity */
    switch ($operation) {
      case 'delete':
        // Allow access
      if ($author->id() == $account->id()) {
        return AccessResult::allowed();
      }
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

}
