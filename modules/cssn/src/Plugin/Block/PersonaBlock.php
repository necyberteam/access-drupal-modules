<?php

namespace Drupal\cssn\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\access_misc\Plugin\Util\RolesLabelLookup;
use Drupal\cssn\Plugin\Util\EndUrl;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\User;

/**
 * Provides a 'Community Persona' Block.
 *
 * @Block(
 *   id = "cssn_block",
 *   admin_label = "Community persona block",
 * )
 */
class PersonaBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    // Get last item in url.
    $end_url = new EndUrl();
    $url_end = $end_url->getUrlEnd();
    $public = TRUE;
    $should_user_load = FALSE;
    if ($url_end == 'community-persona') {
      $public = FALSE;
      $should_user_load = TRUE;
    }
    if (is_numeric($url_end)) {
      $user = User::load($url_end);
      if ($user !== NULL && count($user->field_region->getValue()) > 0) {
        $should_user_load = TRUE;
      }
    }
    if ($should_user_load) {
      $user = $public ? $user : \Drupal::currentUser();
      $user_entity = \Drupal::entityTypeManager()->getStorage('user')->load($user->id());
      $user_image = $user_entity->get('user_picture');
      if ($user_image->entity !== NULL) {
        $user_image = $user_image->entity->getFileUri();
        $user_image = \Drupal::service('file_url_generator')->generateAbsoluteString($user_image);
        $user_image = '<img src="' . $user_image . '" class="img-fluid mb-3 border border-black" />';
      }
      else {
        $user_image = '<svg version="1.1" class="mb-3" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"
           viewBox="0 0 448 448" style="enable-background:new 0 0 448 448;" xml:space="preserve">
            <style type="text/css">
              .st0{fill:#ECF9F8;}
              .st1{fill:#B7CDD1;}
            </style>
            <rect class="st0" width="448" height="448"/>
            <path class="st1" d="M291,158v14.5c0,40-32.4,72.5-72.5,72.5s-72.5-32.4-72.5-72.5V158c0-5,0.5-9.8,1.4-14.5h27.5
              c27,0,50.6-14.8,63-36.7c10.6,13.5,27.1,22.2,45.6,22.2h1.2C288.8,137.9,291,147.7,291,158z M102.6,158v14.5
              c0,64,51.9,115.9,115.9,115.9s115.9-51.9,115.9-115.9V158c0-64-51.9-115.9-115.9-115.9S102.6,94,102.6,158z"/>
            <path class="st1" d="M151.2,306.3c-71.9,7.8-128.2,68.1-130,141.7h405.6c-1.8-73.6-58.1-133.8-130.1-141.6
              c-4.8-0.5-9.5,1.7-12.4,5.6l-48.8,65c-5.8,7.7-17.4,7.7-23.2,0l-48.8-65l0.1-0.1C160.7,308,156,305.9,151.2,306.3z"/>
            </svg>
            ';
      }
      // Create Drupal 9 link to edit user profile with ?destination=community-persona.
      $edit_url = Url::fromUri('internal:/user/' . $user->id() . '/edit?destination=community-persona');
      $edit_link = Link::fromTextAndUrl('Edit Persona', $edit_url);
      $edit_link = $edit_link->toRenderable();
      $edit_link['#attributes'] = [
        'class' => [
          'btn',
          'btn-primary',
          'btn-sm',
          'w-100',
          'w-full',
        ],
      ];
      $edit_link = $public ? "" : $edit_link;
      $first_name = $user_entity->get('field_user_first_name')->value;
      $last_name = $user_entity->get('field_user_last_name')->value;
      $pronouns = $user_entity->get('field_user_preferred_pronouns')->value;

      // Show access organization if set; otherwise, use institution field.
      $orgArray = $user_entity->get('field_access_organization')->getValue();
      if (!empty($orgArray) && !empty($orgArray[0])) {
        $nodeId = $orgArray[0]['target_id'];
        if (!empty($nodeId)) {
          $orgNode = \Drupal::entityTypeManager()->getStorage('node')->load($nodeId);
        }
      }
      $institution = isset($orgNode) ? $orgNode->getTitle() : $user_entity->get('field_institution')->value;

      $roles = $user_entity->getRoles();
      $is_student = array_search('student', $roles) !== FALSE;
      $academic_status = $is_student
        ? $user_entity->get('field_academic_status')->value : '';

      $academic_terms_map = $user_entity->get('field_academic_status')->getSettings()['allowed_values'];
      // If $academic_status is not empty, map it to the label.
      if (!empty($academic_status)) {
        $academic_status = $academic_terms_map[$academic_status];
      }
      else {
        $academic_status = '';
      }
      // Don't display these roles.
      $roles_not_to_include = ['authenticated', 'administrator', 'Masquerade', 'exportpeople', 'site_developer', 'ccmnet'];
      foreach ($roles_not_to_include as $role) {
        $key = array_search($role, $roles);
        if ($key !== FALSE) {
          unset($roles[$key]);
        }
      }
      $role = new RolesLabelLookup($roles);
      $roles = $role->getRoleLabelsString();
      $regions = $user_entity->get('field_region')->getValue();
      $terms = [];
      foreach ($regions as $region) {
        $region_tid = $region['target_id'];
        $terms[$region_tid] = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($region_tid)->getName();
      }
      if (!$public) {
        $cssn_role_url = Url::fromUri('internal:/form/edit-your-cssn-roles?destination=community-persona');
        $cssn_role_link = Link::fromTextAndUrl('Edit Roles', $cssn_role_url);
        $cssn_role_renderable = $cssn_role_link->toRenderable();
        $cssn_role = $cssn_role_renderable;
        $cssn_role['#attributes']['class'] = ['text-dark'];
      }
      else {
        $cssn_role = "";
      }

      // Badges.
      $badges = $user_entity->get('field_user_badges')->getValue();
      $badge_name = [];
      $user_badges = '';
      foreach ($badges as $badge) {
        $term_id = $badge['target_id'];
        if (Term::load($term_id)->get('field_badge')->entity) {
          $name = Term::load($term_id)->get('name')->value;
          $image_alt = Term::load($term_id)->get('field_badge')->alt;
          $image_url = Term::load($term_id)->get('field_badge')->entity->getFileUri();
          $image = \Drupal::service('file_url_generator')->generateAbsoluteString($image_url);
          if ($image) {
            if ($name) {
              $user_badges .= "<div class='badge' data-placement='top' data-toggle='tooltip' title='$name'>";
            }
            else {
              $user_badges .= "<div>";
            }

            $user_badges .= "<img src='$image' alt='$image_alt' title='$name' class='me-2 mb-2' width='55' height='55' />";

            $user_badges .= "</div>";
          }
        }
      }

      // Programs.
      $program = implode(', ', $terms);
      // If $terms contains 'ACCESS CSSN', then the user is a CSSN member.
      $cssn_member = in_array('ACCESS CSSN', $terms) ? TRUE : FALSE;
      // $ws_query = \Drupal::entityQuery('webform_submission')
      //  ->condition('uid', $user->id())
      //  ->condition('uri', '/form/join-the-cssn-network');
      // $ws_results = $ws_query->execute();
      $cssn_indicator = "";
      if ($cssn_member) {
        $cssn_indicator = "<span class='text-primary'><i class='bi-square-fill text-orange'></i></span>";
        $cssn = "CSSN Member";
      }
      elseif ($public) {
        $cssn_indicator = "<span class='text-secondary'><i class='bi-square-fill'></i></span>";
        $cssn = "Not a CSSN Member";
      }
      else {
        $cssn_url = Url::fromUri('https://support.access-ci.org/community/cssn#join-cssn');
        $cssn_link = Link::fromTextAndUrl('Join the CSSN', $cssn_url);
        $cssn_renderable = $cssn_link->toRenderable();
        $cssn = $cssn_renderable;
        $cssn['#attributes']['class'] = ['btn', 'btn-primary', 'btn-sm', 'py-1', 'px-2'];
      }
      $cssn_more_url = Url::fromUri('https://support.access-ci.org/community/cssn ');
      $cssn_more_link = Link::fromTextAndUrl('info', $cssn_more_url);
      $cssn_more_renderable = $cssn_more_link->toRenderable();
      $cssn_more = $cssn_more_renderable;
      $cssn_more['#attributes']['class'] = [
        'text-dark',
        'text-md-teal',
        'no-underline',
      ];

      // Get the user's email address.
      $user_id = $user->id();
      // Show the email button on public profiles.
      $send_email = $public ? "<a href='/user/$user_id/contact?destination=community-persona/$user_id' class='w-100 btn btn-primary btn-sm py-1 px-2'><i class='bi-envelope'></i> Send Email</a>" : "";

      // Get Job title.
      $user_entity = \Drupal::entityTypeManager()->getStorage('user')->load($user_id);
      $job_title = $user_entity->get('field_current_occupation')->value;

      $persona_block['string'] = [
        '#type' => 'inline_template',
        '#template' => '<div class="persona">
                          {{ user_image | raw }}
                          <h2 {% if pronouns %}class="m-0" {% endif %}>
                            {{ first_name }} {{ last_name }}
                          </h2>
                          {% if pronouns %}
                            <div><strong>Pronouns:</strong> {{ pronouns }}</div>
                          {% endif %}
                          <h4 class="institution text-md-teal">{{ institution }}</h4>
                          {% if job_title %}
                            <div class="mb-3"><i>{{ job_title }}</i></div>
                          {% endif %}
                          {% if academic_status and academic_status != "I am not in an academic program but interested in shifting focus to research computing facilitation"  %}
                            <div class="academic-status mb-3">{{ academic_status }}</div>
                          {% endif %}
                          {% if cssn != "Not a CSSN Member" %}
                            <div class="d-flex justify-content-between flex justify-between">
                              <p>{{ cssn_indicator | raw }} <strong>{{ cssn }}</strong></p>
                              <div><i class="text-dark bi-info-circle text-md-teal"></i> {{ cssn_more }}</div>
                            </div>
                          {% endif %}
                          {% if user_badges %}
                            <div class="py-3 border-b border-black flex flex-wrap">{{ user_badges | raw }}</div>
                          {% endif %}
                          <div class="d-flex justify-content-between flex justify-between border-top border-bottom mb-3 py-3 border-secondary border-b border-black">
                            {% if roles %}
                              <div><b>{{ role_text }}:</b><br />{{ roles | raw }}</div>
                            {% endif %}
                            {% if cssn_role %}
                              <div><i class="text-dark bi-pencil-square"></i> {{ cssn_role }}</div>
                            {% endif %}
                          </div>
                          {% if program %}
                            <div class="mb-3"><b>{{ program_text }}:</b><br />{{ program }}</div>
                          {% endif %}
                          <div class="w-100">
                           {{ send_email | raw }}
                          {{ edit_link | raw }}
                          </div>
                        </div>',
        '#context' => [
          'user_image' => $user_image,
          'edit_link' => $edit_link,
          'first_name' => $first_name,
          'last_name' => $last_name,
          'pronouns' => $pronouns,
          'institution' => $institution,
          'job_title' => $job_title,
          'academic_status' => $academic_status,
          'cssn' => $cssn,
          'cssn_indicator' => $cssn_indicator,
          'cssn_more' => $cssn_more,
          'user_badges' => $user_badges,
          'roles' => $roles,
          'role_text' => t('Roles'),
          'cssn_role' => $cssn_role,
          'program' => $program,
          'program_text' => t('Programs'),
          'send_email' => $send_email,
        ],
      ];
      return $persona_block;
    }
    else {
      return [];
    }
  }

  /**
   * @return int
   */
  public function getCacheMaxAge() {
    return 0;
  }

}
