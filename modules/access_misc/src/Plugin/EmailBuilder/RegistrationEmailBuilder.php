<?php

namespace Drupal\access_misc\Plugin\EmailBuilder;

use Drupal\symfony_mailer\Processor\EmailBuilderBase;
use Drupal\symfony_mailer\EmailInterface;

/**
 * Email Builder plug-in for the access_misc module.
 *
 * @EmailBuilder(
 *   id = "access_misc",
 *   sub_types = {
 *     "register" = @Translation("User Registers for an Event"),
 *     "registration_approved" = @Translation("User Registration Approved"),
 *   },
 *   common_adjusters = {"email_subject", "email_body"},
 * )
 */
class RegistrationEmailBuilder extends EmailBuilderBase {
  public function build(EmailInterface $email) {
    $email->setFrom('noreply@support.access-ci.org');
  }
}
