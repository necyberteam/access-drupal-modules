<?php

namespace Drupal\access_misc\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Component\Utility\Xss;

/**
 * Provides a 'Registrations' link Block.
 *
 * @Block(
 *   id = "registrations_listing_link",
 *   admin_label = "Registrations Listing Link",
 * )
 */
class RegistrationsListingLink extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    // Get current url.
    $current_path = Xss::filter(\Drupal::service('path.current')->getPath());

    $url = explode('/', $current_path);
    $event_instance_id = is_numeric($url[2]) ? $url[2] : '';
    // Entity load eventinctance by id.
    $event_instance = \Drupal::entityTypeManager()->getStorage('eventinstance')->load($event_instance_id);
    $series = $event_instance->getEventSeries();
    $author = $series->getOwner();
    $current_user = \Drupal::currentUser();

    // Check if the current user is the author of the event series or else an administrator.
    if ($author->id() != $current_user->id() && !$current_user->hasPermission('administer site configuration')) {
      return [
        '#markup' => "",
      ];
    }


    $link = "<a href='$current_path/registrations' class='btn btn-primary'>Registrations</a>";
    return [
      '#markup' => $link,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    if ($user = \Drupal::currentUser()) {
      return Cache::mergeTags(parent::getCacheTags(), ['user:' . $user->id()]);
    }
    else {
      return parent::getCacheTags();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), ['user']);
  }

}
