<?php

namespace Drupal\access_misc\Plugin\Util;

/**
 * Convert Date for events.
 *
 * @EventDateConvert(
 *   id = "event_date_convert",
 *   title = @Translation("Event date convert"),
 *   description = @Translation("Convert Date for events.")
 * )
 */
class EventDateConvert {
  /**
   * Stores start date.
   *
   * @var string
   */
  private $start;

  /**
   * Stores end date.
   *
   * @var string
   */
  private $end;

  /**
   * True if start and end date are the same day.
   *
   * @var boolean
   */
  public $sameDay = 1;

  /**
   * Function to convert start and end date for events.
   */
  public function __construct($set_start, $set_end) {
    $start = 0;

    if ($set_start != NULL ) {
      $start_iso = strtotime($set_start);
      $start_day = date('d', $start_iso);
      $start = date('m/d/y - h:i A', $start_iso);
    }

    $end = 0;

    if ($set_end != NULL ) {
      $end_iso = strtotime($set_end);
      $end_day = date('d', $end_iso);
    }
    if ($set_end != NULL && $set_start != NULL) {
      if ($start_day != $end_day) {
        $this->sameDay = 0;

        $end = date('m/d/y - h:i A T', $end_iso);
      }
      else {
        $end = date('h:i A T', $end_iso);
      }
    }
    $this->start = $start;
    $this->end = $end;
  }

  /**
   * Function to get start date.
   */
  public function getStart() {
    return $this->start;
  }

  /**
   * Function to get end date.
   */
  public function getEnd() {
    return $this->end;
  }

}
