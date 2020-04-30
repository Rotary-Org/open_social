<?php

namespace Drupal\social_follow_taxonomy\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\FilterPluginBase;

/**
 * Filters activity based on visibility settings.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("activity_follow_taxonomy_visibility_access")
 */
class ActivityFollowTaxonomyVisibilityAccess extends FilterPluginBase {

  /**
   * Not exposable.
   */
  public function canExpose() {
    return FALSE;
  }

  /**
   * Filters out activity items the user is not allowed to see.
   */
  public function query() {
    $account = $this->view->getUser();

    // Add queries.
    $and_wrapper = db_and();
    $or = db_or();

    // Adds a condition to the request to limit the visibility of personal
    // activities for nodes: if the activity is not personal, then make it
    // available to all users, otherwise only to the associated user.
    $node_access = db_and();
    $node_access->condition('activity__field_activity_entity.field_activity_entity_target_type', 'node', '=');
    $this->query->addTable('activity__field_activity_recipient_user');

    if ($account->isAuthenticated()) {
      $na_or = db_or();
      $node_access->condition($na_or
        ->isNull('activity__field_activity_recipient_user.field_activity_recipient_user_target_id')
        ->condition('activity__field_activity_recipient_user.field_activity_recipient_user_target_id', $account->id(), 'IN')
      );
    }
    else {
      $node_access->isNull('activity__field_activity_recipient_user.field_activity_recipient_user_target_id');
    }

    $or->condition($node_access);

    // Lets add all the or conditions to the Views query.
    $and_wrapper->condition($or);

    // Posts: all the posts the user has access to and posted to community.
    $post_access = db_and();
    $post_access->condition('activity__field_activity_entity.field_activity_entity_target_type', 'post', '=');
    $post_access->condition('post__field_visibility.field_visibility_value', '3', '!=');

    if (!$account->hasPermission('view public posts')) {
      $post_access->condition('post__field_visibility.field_visibility_value', '1', '!=');
    }
    if (!$account->hasPermission('view community posts')) {
      $post_access->condition('post__field_visibility.field_visibility_value', '2', '!=');
      // Also do not show recipient posts (e.g. on open groups).
      $post_access->condition('post__field_visibility.field_visibility_value', '0', '!=');
    }
    $post_access
      ->condition('activity__field_activity_recipient_user.field_activity_recipient_user_target_id', $account->id(), 'IN');

    $or->condition($post_access);

    $this->query->addWhere('visibility', $and_wrapper);
  }

}
