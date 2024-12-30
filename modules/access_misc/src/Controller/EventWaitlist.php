<?php

namespace Drupal\access_misc\Controller;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Controller for Event Waitlist.
 */
class EventWaitlist extends ControllerBase {

  /**
   * Perform redirect.
   *
   * @var \Drupal\Core\Routing\RedirectDestinationInterface
   */
  protected $redirectDestination;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The event instance id from uri.
   *
   * @var int
   */
  protected $eventInstanceId;

  /**
   * The event original url.
   *
   * @var string
   */
  protected $eventRegistrationUrl;

  /**
   * Constructs request stuff.
   *
   * @param \Drupal\Core\Routing\RedirectDestinationInterface $redirect_destination
   *   The redirect destination service.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    RedirectDestinationInterface $redirect_destination,
    Connection $database,
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    $this->redirectDestination = $redirect_destination;
    $this->database = $database;
    $this->entityTypeManager = $entity_type_manager;

    // Get uri.
    $uri = $this->redirectDestination->get();
    $uri = explode('/', $uri);
    $this->eventInstanceId = $uri[2];
    $this->eventRegistrationUrl = '/' . $uri[1] . '/' . $uri[2] . '/registrations';
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('redirect.destination'),
      $container->get('database'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Route to approve user.
   */
  public function approve() {
    $this->status(1);

    return new RedirectResponse($this->eventRegistrationUrl);
  }

  /**
   * Route to unapprove user.
   */
  public function unapprove() {
    $this->status(0);

    return new RedirectResponse($this->eventRegistrationUrl);
  }

  /**
   * Set status.
   */
  private function status($status) {
    $eventinstance_id = is_numeric($this->eventInstanceId) ? $this->eventInstanceId : 0;

    $url = $this->redirectDestination->get();
    if (strpos($url, '?')) {
      $query = explode('?', $url);
      $query = explode('=', $query[1]);
      $query = [
        'reg_id' => $query[1],
      ];
    }

    $reg_id = 0;

    if (strpos($url, '?')) {
      if (array_key_exists('reg_id', $query)) {
        $reg_id = is_numeric($query['reg_id']) ? $query['reg_id'] : 0;
      }
    }

    $opposite_status = $status === 1 ? 0 : 1;

    // Entity query get all registrant id with 'eventseries_id' that equals
    // to $eventinstance_id.
    $registrant_entity = $this->entityTypeManager->getStorage('registrant');
    $entity_query = $registrant_entity->getQuery()
      ->condition('eventinstance_id', $eventinstance_id)
      ->condition('status', $opposite_status)
      ->accessCheck(FALSE);
    if ($reg_id) {
      $entity_query->condition('id', $reg_id);
    }
    $registrant_ids = $entity_query->execute();

    foreach ($registrant_ids as $registrant_id) {
      $registrant = $this->entityTypeManager->getStorage('registrant')->load($registrant_id);
      $registrant->set('status', $status);
      $registrant->save();
    }

    // Invalidate cache on Events Facet view.
    $cache_tags = ['config:views.view.events_facet'];
    Cache::invalidateTags($cache_tags);
  }

}
