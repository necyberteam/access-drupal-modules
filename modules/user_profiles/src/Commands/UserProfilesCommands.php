<?php

namespace Drupal\user_profiles\Commands;

use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile for Affinity Groups.
 *
 * @package Drupal\user_profiles\Commands
 */
class UserProfilesCommands extends DrushCommands
{
    /**
     * Add existing Affinity Group members to Constant Contact lists.
     *
     * Save all Affinity Groups to trigger the creation of the
     * associated Constant Contact list. Then add all existing members of
     * the group to that Constant Contact list.
     *
     * @command user_profiles:mergeUser
     * @param   $from_user_id user id to merge from
     * @param   $to_user_id user id to merge to
     * @aliases mergeUser
     * @usage   user_profiles:mergeUser
     */
    public function mergeUser(string $from_user_id, string $to_user_id)
    {
        
        // $flagService = \Drupal::service('flag');
        // $this->output()->writeln(print_r($flagService->getAllFlags(), true));

        $this->output()->writeln("------------- Merge user $from_user_id into $to_user_id ---------------------------------");

        $user_from = User::load($from_user_id);
        $user_to = User::load($to_user_id);

        if (!$user_from) {
            $this->output()->writeln("  *** No user found with id $from_user_id");
            return;
        }
        if (!$user_to) {
            $this->output()->writeln("  *** No user found with id $to_user_id");
            return;
        }

        $first_name1 = $user_from->get('field_user_first_name')->getString();
        $last_name1 = $user_from->get('field_user_last_name')->getString();
        $first_name2 = $user_to->get('field_user_first_name')->getString();
        $last_name2 = $user_to->get('field_user_last_name')->getString();

        $this->output()->writeln("  Merging from '$first_name1 $last_name1' to '$first_name2 $last_name2'");

        $this->mergeAfffinityGroups($user_from, $user_to);
        $this->mergeFlag('interest', $user_from, $user_to);
        $this->mergeFlag('skill', $user_from, $user_to);
    }

    private function mergeFlag($flag_name, $user_from, $user_to)
    {
        $this->output()->writeln("Merging " . $flag_name . "s");
        
        $term = \Drupal::database()->select('flagging', 'fl');
        $term->condition('fl.uid', $user_from->id());
        $term->condition('fl.flag_id', $flag_name);
        $term->fields('fl', ['entity_id']);
        $flagged_items = $term->execute()->fetchCol();
        if ($flagged_items == NULL) {
            $this->output()->writeln("  From-user has no reported " . $flag_name . "s");
            return;
        }
        
        foreach ($flagged_items as $flagged_item) {
            $term = \Drupal\taxonomy\Entity\Term::load($flagged_item);
            $title = $term->get('name')->value;

            $this->output()->writeln("  from-user has $flag_name '$title'");

            // Check if already flagged. If not, set the flag.
            $flag_service = \Drupal::service('flag');
            $flag = $flag_service->getFlagById($flag_name);
            $flag_status = $flag_service->getFlagging($flag, $term, $user_to);
            if (!$flag_status) {
                $this->output()->writeln("    Add $flag_name '$title' to to-user");
                $flag_service->flag($flag, $term, $user_to);
            } else {
                $this->output()->writeln("    To-user already has $flag_name '$title'");
            }
        }

    }

    private function mergeAfffinityGroups($user_from, $user_to)
    {

        $this->output()->writeln("Merging affinity groups");

        // get user_to's blocked ag taxonomy ids
        $user_blocked_tid_array = $user_to->get('field_blocked_ag_tax')->getValue();
        $user_blocked_tids = [];
        foreach ($user_blocked_tid_array as $user_blocked_tid) {
            $user_blocked_tids[] = $user_blocked_tid['target_id'];
        }
        // $this->output()->writeln("  user-to blocked ag tids: " . implode(' ', $user_blocked_tids));

        // get all the affinity groups of $user_from
        $query = \Drupal::database()->select('flagging', 'fl');
        $query->condition('fl.uid', $user_from->id());
        $query->condition('fl.flag_id', 'affinity_group');
        $query->fields('fl', ['entity_id']);
        $ag_ids = $query->execute()->fetchCol();

        if ($ag_ids == NULL) {
            $this->output()->writeln("  from-user is not a member of any affinity groups");
            return;
        }
        // for each affinity group id, add user_to to that affinity group
        // $this->output()->writeln("  from-user ag ids: " . implode(' ', $ag_ids));
        foreach ($ag_ids as $ag_id) {
            $this->addUserToAG($user_to, $ag_id, $user_blocked_tids);
        }
    }

    /**
     * Add a user to an affinity group (unless on the users's block list)
     */
    private function addUserToAG(UserInterface $to_user, $ag_id, $user_blocked_tids)
    {
        // get the node id of the affinity group
        $query = \Drupal::database()->select('taxonomy_index', 'ti');
        $query->condition('ti.tid', $ag_id);
        $query->fields('ti', ['nid']);
        $affinity_group_nid = $query->execute()->fetchCol();

        if (!isset($affinity_group_nid[0])) {
            // not sure how or if this could happen, or what it would mean, but Miles' code in 
            // CommunityPersonaController.php line 36 also ignores this condition
            $this->output()->writeln("  *** Warning, from-user flagged as member of affinity group #$ag_id but no such affinity group found - skipping this affinity group");
            return;
        }

        // load that affinity group
        $ag_nid = $affinity_group_nid[0];
        // $affinity_group_loaded = Node::load($ag_nid);
        $affinity_group_loaded = \Drupal::entityTypeManager()
            ->getStorage('node')
            ->load($ag_nid);

        if (!$affinity_group_loaded) {
            $this->output()->writeln("  *** Warning, could not load affinity group with node_id #$ag_nid - skipping this affinity group");
            return;
        }

        $ag_title = $affinity_group_loaded->getTitle();

        $this->output()->writeln("  From-user member of AG '$ag_title' (id #$ag_id)");

        // get AG taxonomy id
        $ag_taxonomy = \Drupal::entityTypeManager()
            ->getStorage('taxonomy_term')
            ->loadByProperties(['name' => $ag_title]);
        $ag_taxonomy = reset($ag_taxonomy);

        if (!$ag_taxonomy) {
            $this->output()->writeln("    *** Warning, no taxonomy id found for AG title '$ag_title'");
            return;
        }

        $ag_tax_id =  $ag_taxonomy->id();

        // check if ag is on block list.
        if (in_array($ag_tax_id, $user_blocked_tids)) {
            $this->output()->writeln("    Not adding '$ag_title' (tid #$ag_tax_id) because on to-user's block list");
            return;
        }

        // Check if already flagged. If not, set the flag.
        $flag_service = \Drupal::service('flag');
        $ag_flag = $flag_service->getFlagById('affinity_group');
        $ag_flag_status = $flag_service->getFlagging($ag_flag, $ag_taxonomy, $to_user);
        if (!$ag_flag_status) {
            $this->output()->writeln("    Add to-user to affinity group '$ag_title' (#$ag_tax_id)");
            // TODO following sometimes giving "The flag does not apply to the bundle of the entity."
            if ($ag_taxonomy->bundle() !== 'affinity_groups') {
                $this->output()->writeln("    *** Error, affinity group '$ag_title' has unexpected bundle = '" . $ag_taxonomy->bundle() 
                    . "' (expected it to have bundle 'affinity_groups')");
            } else {
                $flag_service->flag($ag_flag, $ag_taxonomy, $to_user);
            }
        } else {
            $this->output()->writeln("    To-user already a member of affinity group '$ag_title' (#$ag_tax_id)");
        }
    }
}
