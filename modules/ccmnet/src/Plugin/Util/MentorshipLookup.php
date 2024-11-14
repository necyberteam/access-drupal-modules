<?php

namespace Drupal\ccmnet\Plugin\Util;

/**
 * Lookup connected Match+ nodes.
 *
 * @MatchLookup(
 *   id = "mentorship_lookup",
 *   title = @Translation("Mentorship Lookup"),
 *   description = @Translation("Lookup Users with mentorship engagements."),
 * )
 */
class MentorshipLookup {
  /**
   * Store matching nodes.
   * $var array
   */
  private $matches;

  /**
   * Array of sorted matches.
   * $var array
   */
  private $mentorships_sorted;

  /**
   * Function to return matching nodes.
   */
  public function __construct($mentorships_fields, $mentor_user_id, $public = FALSE) {
    // If not public, add engagements authored by User.
    if (!$public) {
      $query = \Drupal::database()->select('node_field_data', 'nfd');
      $query->fields('nfd', ['nid']);
      $query->condition('nfd.type', 'mentorship_engagement');
      $query->condition('nfd.uid', $mentor_user_id);
      $result = $query->execute()->fetchAll();
      $nids = array_column($result, 'nid');
      $this->matches['author'] = [
        'name' => 'Author',
        'nodes' => \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($nids),
      ];
    }
    foreach ($mentorships_fields as $match_field_key => $match_field) {
      $this->runQuery($match_field, $match_field_key, $mentor_user_id);
    }
    $this->gatherMatches($public);
  }

  /**
   * Function to Run entity query by type.
   */
  public function runQuery($match_field_name, $match_field, $mentor_user_id) {
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'mentorship_engagement')
      ->condition($match_field, $mentor_user_id)
      ->accessCheck(FALSE)
      ->execute();
    if ($query != NULL) {
      $this->matches[$match_field] = [
        'name' => $match_field_name,
        'nodes' => \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($query),
      ];
    }
  }

  /**
   * Function to lookup nodes and sort array.
   */
  public function gatherMatches($public) {
    $matches = $this->matches;
    $match_array = [];
    if ($matches == NULL) {
      return;
    }
    foreach ($matches as $key => $match) {
      foreach ($match['nodes'] as $node) {
        $title = $node->getTitle();
        $nid = $node->id();
        $match_name = $match['name'];
        $field_status = $node->get('field_me_state')->getValue();
        $field_status = !empty($field_status) ? $field_status : '';

        // Don't display engagement with a non-public status on public profile.
        if ($public == TRUE) {
          $non_public = ['Reviewing', 'On Hold', 'Halted'];

          $field_status = $field_status[0]['target_id'];
          $field_status = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($field_status);
          $field_status = $field_status->getName();

          if (in_array($field_status, $non_public)) {
            unset($matches[$key]);
            break;
          }
        }
        $match_array[$nid] = [
          'status' => $field_status,
          'name' => $match_name,
          'title' => $title,
          'nid' => $nid,
        ];
      }
    }
    $this->mentorships_sorted = $match_array;
  }

  /**
   * Function to sort by status - needs update if used.
   */
  public function sortStatusMatches() {
    $matches = $this->mentorships_sorted;
    $draft = $this->arrayPickSort($matches, 'draft');
    $in_review = $this->arrayPickSort($matches, 'in_review');
    $accepted = $this->arrayPickSort($matches, 'accepted');
    $recruiting = $this->arrayPickSort($matches, 'recruiting');
    $reviewing = $this->arrayPickSort($matches, 'reviewing_applicants');
    $in_progress = $this->arrayPickSort($matches, 'in_progress');
    $finishing = $this->arrayPickSort($matches, 'finishing_up');
    $completed = $this->arrayPickSort($matches, 'complete');
    $on_hold = $this->arrayPickSort($matches, 'on_hold');
    $halted = $this->arrayPickSort($matches, 'halted');
    // Combine all of the arrays.
    $mentorships_sorted  = $draft + $in_review + $accepted + $recruiting + $reviewing + $in_progress + $finishing + $completed + $on_hold + $halted;
    $this->mentorships_sorted = $mentorships_sorted;
  }

  /**
   * Function to pick out a status into an array and sort by title.
   */
  public function arrayPickSort($array, $sortby) {
    $sorted = [];
    if ($array == NULL) {
      return;
    }
    foreach ($array as $key => $value) {
      if ($value['status'] && $value['status'][0]['value'] == $sortby) {
        $sorted[$key] = $value;
      }
    }
    uasort($sorted, function ($a, $b) {
      return strnatcmp($a['title'], $b['title']);
    });
    return $sorted;
  }

  /**
   * Function to return styled list.
   */
  public function getMentorshipList() {
    $n = 1;
    $match_link = '';
    if ($this->mentorships_sorted == NULL) {
      return;
    }
    foreach ($this->mentorships_sorted as $match) {
      $stripe_class = $n % 2 == 0 ? 'bg-light bg-light-teal' : '';
      $title = $match['title'];
      $nid = $match['nid'];
      $match_status = $match['status'];
      $mentorship_status_list = [
        'Recruiting',
        'Reviewing',
        'In Progress',
        'Finishing Up',
        'In Progress and Recruiting',
        'Complete',
        'On Hold',
        'Halted',
      ];

      $mentorship_translated_status = [];

      foreach ($mentorship_status_list as $mentorship_status) {
        $mentorship_status_tid_lookup = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['name' => $mentorship_status]);
        $mentorship_tid = array_keys($mentorship_status_tid_lookup);
        if (empty($mentorship_tid)) {
          continue;
        }
        $mentorship_tid = $mentorship_tid[0];
        $mentorship_translated_status[$mentorship_tid] = $mentorship_status;
      }

      if ($match_status) {
        $set_status = isset($match_status[0]) ? $match_status[0]['target_id'] : $match_status;
        $match_status = $mentorship_translated_status[$set_status];
      }
      $match_name = $match['name'];
      if (($match_status == 'Recruiting' && $match_status == 'In Progress and Recruiting' && $match_name == 'Interested') || $match_name != 'Interested') {
        $lowercase = lcfirst($match_name);
        $first_letter = substr($lowercase, 0, 1);
        $match_name = "<div data-tippy-content='$match_name'>
          <i class='text-dark text-dark-teal text-2xl fa-solid fa-circle-$first_letter h2'></i>
        </div>";
        $match_link .= "<li class='d-flex flex p-3 $stripe_class'>
          <div class='text-truncate' style='width: 400px;'>
            <a href='/node/$nid' class='font-bold underline hover--no-underline hover--text-dark-teal'>$title</a>
          </div>
          <div class='font-weight-bold ms-5'>
            $match_name
          </div>
          <div class='ms-2'>
            $match_status
          </div>
        </li>";
        $n++;
      }
    }
    return $match_link;
  }

  /**
   * Function to return matching nodes.
   */
  public function getMatches() {
    return $this->matches;
  }

}
