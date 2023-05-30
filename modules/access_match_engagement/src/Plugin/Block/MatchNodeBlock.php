<?php

namespace Drupal\access_match_engagement\Plugin\Block;

use Drupal\node\NodeInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\Entity\File;
use Drupal\Core\Url;
use Drupal\image\Entity\ImageStyle;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Cache\Cache;

/**
 * Access Match Node Block.
 *
 * @Block(
 *   id = "match_node_block",
 *   admin_label = @Translation("Access Match Node Block")
 * )
 */
class MatchNodeBlock extends BlockBase implements
  ContainerFactoryPluginInterface {

  /**
   * Invoke renderer.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityInterface;

  /**
   * Invoke renderer.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routMatchInterface;

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   Container pulled in.
   * @param array $configuration
   *   Configuration added.
   * @param string $plugin_id
   *   Plugin_id added.
   * @param mixed $plugin_definition
   *   Plugin_definition added.
   *
   * @return static
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('current_route_match'),
    );
  }

  /**
   * {@inheritdoc}
   *
   * @param array $configuration
   *   Configuration array.
   * @param string $plugin_id
   *   Plugin id string.
   * @param mixed $plugin_definition
   *   Plugin Definition mixed.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_interface
   *   Invokes renderer.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match_interface
   *   Invokes routeMatch.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_interface, RouteMatchInterface $route_match_interface) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityInterface = $entity_interface;
    $this->routMatchInterface = $route_match_interface;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $thisNode = $this->routMatchInterface->getParameter('node');
    if ($thisNode instanceof NodeInterface) {
      $nid = $thisNode->id();
      $node = $this->entityInterface->getStorage('node')->load($nid);
      $fields = [
        'field_consultant',
        'field_students',
        'field_mentor',
      ];
      $msc_loaded = [];
      foreach ($fields as $field) {
        $field_value = $node->get($field)->getValue();
        if ($field_value) {
          $field_value = $this->entityInterface->getStorage('user')->load($field_value[0]['target_id']);
          $msc_loaded[$field] = $field_value->get('field_user_first_name')->value . ' ' . $field_value->get('field_user_last_name')->value;
        }
        else {
          $msc_loaded[$field] = '';
        }
      }
      $image = $node->get('field_project_image')->getValue();
      $image_loaded = '';
      if ($image) {
        $img_file = File::load($image[0]['target_id']);
        $uri = $img_file->getFileUri();
        $image_full = Url::fromUri(file_create_url($uri))->toString();
        $alt = $image[0]['alt'];
        $width = $image[0]['width'];
        $height = $image[0]['height'];
        $image_styled = ImageStyle::load('access_match_sidebar')->buildUrl($uri);
        $image_loaded = "<img src='$image_styled' alt='$alt' width='$width' height='$height' style='height: auto;' />";
      }
      $tags = $node->get('field_tags')->getValue();
      $tag_list = '';
      if ($tags) {
        $tag_count = count($tags);
        $tag_iterate = 0;
        foreach ($tags as $tag) {
          $tag_iterate++;
          $tag_id = $tag['target_id'];
          $term = $this->entityInterface->getStorage('taxonomy_term')->load($tag_id);
          if ($term) {
            $tag_load = $term->get('name')->value;
            $tag_list .= "<a class='tag' href='/taxonomy/term/$tag_id'>$tag_load</a>";
          }
        }
      }
      $status = $node->get('field_status')->getValue();
      $skill_label = '';
      $skill_level = '';
      $qualifications = '';
      $qualifications_label = '';
      if ($status) {
        $status = $status[0]['value'];
        $is_recruiting = $status == 'recruiting' ? TRUE : FALSE;
        $skill = $node->get('field_programming_skill_level')->getValue();
        if ($skill) {
          $skill_label = $is_recruiting ? $this->t('Programming Skill Level:') : '';
          $skill_level = $is_recruiting ? $skill[0]['value'] : '';
        }
        $qualifications = $node->get('field_qualifications')->getValue();
        if ($qualifications) {
          $qualifications_label = $is_recruiting ? $this->t('Preferred Qualifications:') : '';
          $qualifications_value = $is_recruiting ? $qualifications[0]['value'] : '';
        }
      }
      $type = $node->get('field_node_type')->getValue();
      $type = $type[0]['value'];
      $nid = $node->id();
      $interested_users = $node->get('field_match_interested_users')->getValue();
      // Lookup user names from uid.
      $interested_users = $this->getInterestedUsers($interested_users);
      $status = $node->get('field_status')->getValue() ?? $node->get('field_status')->getValue()[0]['value'];
      $interested_text = $this->t("I'm Interested");
      $interested_button = $is_recruiting ? "<a class='btn btn-primary' href='/node/$nid/interested'>$interested_text</a>" : '';
      return [
        '#type' => 'inline_template',
        '#template' => '<div class="p-3">
          <div class="pb-3">{{ image | raw }}</div>
          {% if ( type == "plus" ) %}
            {% if ( student ) %}
              <div><span class="fw-bold">{{ student_label }}:</span> {{ student }}</div>
            {% endif %}
            {% if ( mentor ) %}
              <div><span class="fw-bold">{{ mentor_label }}:</span> {{ mentor }}</div>
            {% endif %}
          {% endif %}
          {% if (consultant and ( type == "premier" )) %}
            <div><span class="fw-bold">{{ consultant_label }}:</span> {{ consultant }}</div>
          {% endif %}
          {% if tags %}
            <div>{{ tags | raw }}</div>
          {% endif %}
          <div><span class="fw-bold">{{ skill_label }}</span> {{ skill_level | raw }}</div>
          <div><span class="fw-bold">{{ qualifications_label }}</span> {{ qualifications_value | raw }}</div>
        </div>
       {{ interested_button | raw }}
        {% if interested_users %}
          <div class="p-3">
            <h3>Interested People</h3>
              <ul>
              {% for interested_user in interested_users %}
                <li>{{ interested_user }}</li>
              {% endfor %}
              </ul>
          </div>
        {% endif %}',
        '#context' => [
          'consultant_label' => $this->t('Consultant'),
          'consultant' => $msc_loaded['field_consultant'],
          'student_label' => $this->t('Student'),
          'student' => $msc_loaded['field_students'],
          'mentor_label' => $this->t('Mentor'),
          'mentor' => $msc_loaded['field_mentor'],
          'tags' => $tag_list,
          'image' => $image_loaded,
          'skill_label' => $skill_label,
          'skill_level' => $skill_level,
          'qualifications_label' => $qualifications_label,
          'qualifications_value' => $qualifications_value,
          'type' => $type,
          'interested_button' => $interested_button,
          'interested_users' => $interested_users,
        ],
      ];
    }
  }

  /**
   * Get interested users.
   */
  public function getInterestedUsers($interested_users) {
    $interested_users = array_column($interested_users, 'target_id');
    $users = $this->entityInterface->getStorage('user')->loadMultiple($interested_users);
    $user_names = [];
    foreach ($users as $user) {
      $user_names[] = $user->get('field_user_first_name')->value . ' ' . $user->get('field_user_last_name')->value;
    }
    return $user_names;
  }

  /**
   * Set cache tag by node id.
   */
  public function getCacheTags() {
    // With this when your node change your block will rebuild.
    if ($node = $this->routMatchInterface->getParameter('node')) {
      // If there is node add its cachetag.
      return Cache::mergeTags(parent::getCacheTags(), ['node:' . $node->id()]);
    }
    else {
      // Return default tags instead.
      return parent::getCacheTags();
    }
  }

  /**
   * Return cache contexts.
   */
  public function getCacheContexts() {
    // If you depends on \Drupal::routeMatch()
    // you must set context of this block with 'route' context tag.
    // Every new route this block will rebuild.
    return Cache::mergeContexts(parent::getCacheContexts(), ['route']);
  }

}
