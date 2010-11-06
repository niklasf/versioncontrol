<?php
// $Id$

/**
 * @file
 *
 * Implement views hooks to provide views integration for Version Control API's
 * tables and define the requisite plugins for doing so.
 */

/**
 * Implementation of hook_views_data().
 *
 * Describes schema for versioncontrol tables.
 *
 * @return array
 */
function versioncontrol_views_data() {
  $data = array();

  // VersionControl Repositories
  $data['versioncontrol_repositories']['table']['group'] = t("VersionControl Repository");
  $data['versioncontrol_repositories']['table']['base'] = array(
    'field' => 'repo_id',
    'title' => t('VersionControl Repository'),
    'help'  => t('The repositories tracked by VersionControl.'),
  );
  $data['versioncontrol_repositories']['repo_id'] = array(
    'title' => t("Repository ID"),
    'help' => t("The unique identifier of the repository"),
    'field' => array(
      'handler' => 'views_handler_field_numeric',
      'click sortable' => TRUE,
    ),
    'sort' => array(
      'handler' => 'views_handler_sort',
    ),
    'filter' => array(
      'handler' => 'views_handler_filter_numeric',
    ),
    'argument' => array(
      'handler' => 'views_handler_argument_numeric',
    ),
  );
  $data['versioncontrol_repositories']['name'] = array(
    'title' => t("Repository Name"),
    'help' => t("The name of the repository"),
    'field' => array(
      'handler' => 'views_handler_field',
      'click sortable' => TRUE,
    ),
    'sort' => array(
      'handler' => 'views_handler_sort',
    ),
    'filter' => array(
      'handler' => 'views_handler_filter_string',
    ),
    'argument' => array(
      'handler' => 'views_handler_argument_string',
    ),
  );
  $data['versioncontrol_repositories']['vcs'] = array(
    'title' => t("Backend Type"),
    'help' => t("The backend type of VCS that this repository uses"),
    'field' => array(
      'handler' => 'views_handler_field',
      'click sortable' => TRUE,
    ),
    'sort' => array(
      'handler' => 'views_handler_sort',
    ),
    'filter' => array(
      'handler' => 'versioncontrol_handler_filter_repo_backend',
    ),
    'argument' => array(
      'handler' => 'views_handler_argument_string',
    ),
  );
  $data['versioncontrol_repositories']['update_method'] = array(
    'title' => t("Update method"),
    'help' => t("The method by which this repository will be updated."),
    'field' => array(
      'handler' => 'versioncontrol_handler_field_repository_update_method',
      'click sortable' => TRUE,
    ),
  );

  $data['versioncontrol_repositories']['actions'] = array(
    'title' => t("Actions"),
    'help' => t("Actions that can be performed on a repository."),
    'field' => array(
      'handler' => 'versioncontrol_handler_field_repository_actions',
      'click sortable' => TRUE,
    ),
  );
  $data['versioncontrol_repositories']['root'] = array(
    'title' => t("Root Path"),
    'help' => t("The root path to the repository"),
    'field' => array(
      'handler' => 'views_handler_field',
      'click sortable' => TRUE,
    ),
    'sort' => array(
      'handler' => 'views_handler_sort',
    ),
    'filter' => array(
      'handler' => 'views_handler_filter',
    ),
  );
  $data['versioncontrol_repositories']['updated'] = array(
    'title' => t("Last Updated"),
    'help' => t("The last time the repository was updated."),
    'field' => array(
      'handler' => 'views_handler_field_date',
      'click sortable' => TRUE,
    ),
    'sort' => array(
      'handler' => 'views_handler_sort_date',
    ),
    'filter' => array(
      'handler' => 'views_handler_filter_date',
    ),
  );
  $data['versioncontrol_repositories']['table']['join'] = array(
    'versioncontrol_operations' => array(
      'left_field' => 'repo_id',
      'field' => 'repo_id',
    ),
    'versioncontrol_item_revisions' => array(
      'left_field' => 'repo_id',
      'field' => 'repo_id',
    ),
    'versioncontrol_accounts' => array(
      'left_field' => 'repo_id',
      'field' => 'repo_id',
    ),
  );

  // VersionControl Operations
  $data['versioncontrol_operations']['table']['group'] = t('VersionControl Operations');
  $data['versioncontrol_operations']['table']['base'] = array(
    'field' => 'vc_op_id',
    'title' => t('VersionControl Operation'),
    'help'  => t('Operations are the actions that can be performed on a repository.  Examples are commit, tag, merge, and branch.'),
  );
  $data['versioncontrol_operations']['vc_op_id'] = array(
    'title'    => t('Operation ID'),
    'help'     => t('The unique ID for a operation'),
    'field'    => array(
      'handler' => 'views_handler_field_numeric',
      'click sortable' => TRUE,
    ),
    'sort' => array(
      'handler' => 'views_handler_sort',
    ),
    'filter' => array(
      'handler' => 'views_handler_filter_numeric',
    ),
    'argument' => array(
      'handler' => 'views_handler_argument_numeric',
    ),
  );
  $data['versioncontrol_operations']['type'] = array(
    'title'    => t('Type'),
    'help'     => t('The type of operation. Tag, Commit or Branch'),
    'field'    => array(
      'handler' => 'versioncontrol_handler_field_operation_type',
       'click sortable' => TRUE,
    ),
    'sort' => array(
      'handler' => 'views_handler_sort',
    ),
    'filter' => array(
      'handler' => 'versioncontrol_handler_filter_operation_type',
    ),
  );
  $data['versioncontrol_operations']['date'] = array(
    'title'    => t('Date'),
    'help'     => t('The date of the operation'),
    'field'    => array(
      'handler' => 'views_handler_field_date',
      'click sortable' => TRUE,
    ),
    'sort' => array(
      'handler' => 'views_handler_sort_date',
    ),
    'filter' => array(
      'handler' => 'views_handler_filter_date',
    ),
    'argument' => array(
      'handler' => 'views_handler_argument_date',
    ),
  );
  $data['versioncontrol_operations']['uid'] = array(
    'title' => t('User'),
    'help' => t('The user that did the operation'),
    'relationship' => array(
      'base'  => 'users',
      'base field' => 'uid',
      'handler' => 'views_handler_relationship',
      'label' => t('User'),
    ),
  );
  $data['versioncontrol_operations']['author'] = array(
    'title'    => t('Author'),
    'help'     => t('The Operation Author'),
    'field'    => array(
      //TODO: Display handler to filter GIT usernames?
      'handler' => 'views_handler_field',
    ),
    'sort' => array(
      'handler' => 'views_handler_sort',
    ),
    'filter' => array(
      'handler' => 'views_handler_filter_string',
    ),
    'argument' => array(
      'handler' => 'views_handler_argument_string',
    ),
  );
  $data['versioncontrol_operations']['committer'] = array(
    'title'    => t('Commiter'),
    'help'     => t('The Operation Commiter'),
    'field'    => array(
      'handler' => 'views_handler_field',
    ),
    'sort' => array(
      'handler' => 'views_handler_sort',
    ),
    'filter' => array(
      'handler' => 'views_handler_filter_string',
    ),
    'argument' => array(
      'handler' => 'views_handler_argument_string',
    ),
  );
  $data['versioncontrol_operations']['revision'] = array(
    'title'    => t('Revision'),
    'help'     => t('The Operation Revision Identifier'),
    'field'    => array(
      'handler' => 'views_handler_field',
      'click sortable' => TRUE,
    ),
    'sort' => array(
      'handler' => 'views_handler_sort',
    ),
    'filter' => array(
      'handler' => 'views_handler_filter_string',
    ),
    'argument' => array(
      'handler' => 'views_handler_argument_string',
    ),
  );
  $data['versioncontrol_operations']['message'] = array(
    'title'    => t('Message'),
    'help'     => t('The Operation Message'),
    'field'    => array(
      'handler' => 'views_handler_field',
    ),
  );

  $data['versioncontrol_operations']['table']['join'] = array(
    'users' => array(
      'left_field' => 'uid',
      'field'      => 'uid',
    ),

    'versioncontrol_repositories' => array(
      'left_field' => 'repo_id',
      'field' => 'repo_id',
    ),

    'versioncontrol_operation_labels' => array(
      'left_field' => 'vc_op_id',
      'field' => 'vc_op_id',
    ),
    'versioncontrol_labels' => array(
      'left_table' => 'versioncontrol_operation_labels',
      'left_field' => 'vc_op_id',
      'field' => 'vc_op_id',
    ),

    'versioncontrol_operation_items' => array(
      'left_field' => 'vc_op_id',
      'field' => 'vc_op_id',
    ),
    'versioncontrol_item_revisions' => array(
      'left_table' => 'versioncontrol_operation_items',
      'left_field' => 'vc_op_id',
      'field' => 'vc_op_id',
    ),
  );
  $data['users']['table']['join']['versioncontrol_operations'] = array(
    'left_field' => 'uid',
      'field'      => 'uid',
      'type'       => 'inner',
  );
  
  // VersionControl Accounts
  $data['versioncontrol_accounts']['table']['group'] = t("VersionControl Accounts");
  $data['versioncontrol_accounts']['table']['base'] = array(
    'field' => 'uid',
    'title' => t('VersionControl Accounts'),
    'help'  => t('The VersionControl accounts held by users.'),
  );
  $data['versioncontrol_accounts']['table']['join'] = array(
    'user' => array(
      'left_field' => 'uid',
      'field' => 'uid',
    ),
    'versioncontrol_repositories' => array(
      'left_field' => 'repo_id',
      'field' => 'repo_id',
    ),
  );
  $data['versioncontrol_accounts']['vcs_username'] = array(
    'title' => t('Versioncontrol Name'),
    'help' => t('The username of the committer, as known by the VCS.'),
    'field' => array(
      'handler' => 'views_handler_field',
      'click sortable' => TRUE,
    ),
    'sort' => array(
      'handler' => 'views_handler_sort',
    ),
    'argument' => array(
      'handler' => 'views_handler_argument_string',
    ),
  );
  $data['users']['table']['join']['versioncontrol_accounts'] = array(
    'left_field' => 'uid',
    'field' => 'uid',
    'type' => 'inner',
  );

  // VersionControl Labels
  $data['versioncontrol_labels']['table']['group'] = t("VersionControl Labels");
  $data['versioncontrol_labels']['table']['base'] = array(
    'field' => 'label_id',
    'title' => t('VersionControl Branches/Tags'),
    'help'  => t('Branches & tags in repositories tracked by the Versioncontrol system.'),
  );
  $data['versioncontrol_labels']['table']['join'] = array(
    'versioncontrol_operation_labels' => array(
      'left_field' => 'label_id',
      'field' => 'label_id',
    ),
    'versioncontrol_operations' => array(
      'left_table' => 'versioncontrol_operation_labels',
      'left_field' => 'label_id',
      'field' => 'label_id',
    ),
  );
  $data['versioncontrol_labels']['label_id'] = array(
      'title' => t("Label ID"),
      'help' => t("The unique label identifier"),
      'field' => array(
        'handler' => 'views_handler_field_numeric',
      ),
      'sort' => array(
        'handler' => 'views_handler_sort',
      ),
      'filter' => array(
        'handler' => 'views_handler_filter_numeric',
      ),
      'argument' => array(
        'handler' => 'views_handler_argument_numeric',
      ),
  );
  $data['versioncontrol_labels']['name'] = array(
    'title' => t("Label Name"),
    'help' => t("The name of this label"),
    'field' => array(
      'handler' => 'views_handler_field',
    ),
    'sort' => array(
      'handler' => 'views_handler_sort',
    ),
    'filter' => array(
      'handler' => 'versioncontrol_handler_filter_operation_type_label',
    ),
    'argument' => array(
      'handler' => 'views_handler_argument_string',
    ),
  );
  $data['versioncontrol_labels']['type'] = array(
      'title' => t('Label Type'),
      'help' => t('The label type - either a tag or a branch.'),
      //TODO: Handler 'field' and 'filter'
      'field' => array(
        'handler' => 'views_handler_field_numeric',
      ),
  );
  // VersionControl Operations Labels
  $data['versioncontrol_operation_labels']['table']['group'] = t("VersionControl Labels");
  $data['versioncontrol_operation_labels']['table']['join'] = array(
    'versioncontrol_labels' => array(
      'left_field' => 'label_id',
      'field' => 'label_id',
    ),
    'versioncontrol_operations' => array(
      'left_field' => 'vc_op_id',
      'field' => 'vc_op_id',
    ),
  );
  $data['versioncontrol_operation_labels']['label_id'] = array(
    'title' => t('Label List'),
    'help' => t('A list of labels this operation affected'),
    'field' => array(
      'handler' => 'versioncontrol_handler_field_operation_labels',
    ),
  );
  $data['versioncontrol_operation_labels']['action'] = array(
    //TODO: What is this again?
    'title' => t("Op Label Action"),
    'help' => t("The operations action on this label"),

    'field' => array(
      'handler' => 'views_handler_field_numeric',
    ),
  );

  
  // VersionControl Item Revisions
  $data['versioncontrol_item_revisions']['table']['group'] = t("VersionControl Item Revisions");
  $data['versioncontrol_item_revisions']['table']['base'] = array(
    'field' => 'item_revision_id',
    'title' => t('VersionControl Item Revisions'),
    'help'  => t('File-by-file revision histories.'),
  );
  $data['versioncontrol_item_revisions']['table']['join'] = array(
    'versioncontrol_operations' => array(
      'left_field' => 'vc_op_id',
      'field' => 'vc_op_id',
    ),

    'versioncontrol_repositories' => array(
      'left_field' => 'repo_id',
      'field' => 'repo_id',
    ),
    'versioncontrol_source_items' => array(
      'left_field' => 'item_revision_id',
      'field' => 'item_revision_id',
    ),
  );
  $data['versioncontrol_item_revisions']['path'] = array(
    'title' => t("File path"),
    'help' => t("The file path within the repository"),
    'field' => array(
      'handler' => 'views_handler_field',
    ),
    'sort' => array(
      'handler' => 'views_handler_sort',
    ),
    'filter' => array(
      'handler' => 'views_handler_filter_string',
    ),
    'argument' => array(
      'handler' => 'views_handler_argument_string',
    ),
  );
  $data['versioncontrol_item_revisions']['revision'] = array(
    'title' => t("Revision ID"),
    'help' => t("The revision identifier for this item"),
    'field' => array(
      'handler' => 'views_handler_field',
      'click sortable' => TRUE,
    ),
    'sort' => array(
      'handler' => 'views_handler_sort',
    ),
    'argument' => array(
      'handler' => 'views_handler_argument_string',
    ),
  );
  $data['versioncontrol_item_revisions']['type'] = array(
    'title' => t("Op Item Type"),
    'help' => t("The type of item - file or directory & deleted or current."),
    'field' => array(
      'handler' => 'views_handler_field_numeric',
    ),
  );

  // VersionControl Operation Items 
  $data['versioncontrol_operation_items']['table']['group'] = t("VersionControl Item Revisions");
  $data['versioncontrol_operation_items']['table']['join'] = array(
    'versioncontrol_item_revisions' => array(
      'left_field' => 'item_revision_id',
      'field' => 'item_revision_id',
    ),
    'versioncontrol_operations' => array(
      'left_field' => 'vc_op_id',
      'field' => 'vc_op_id',
    ),
  );
  $data['versioncontrol_operation_items']['item_revision_id'] = array(
    'title' => t('Item Revision ID'),
    'help' => t('The unique ID of this item revision.'),
    'field' => array(
      'handler' => 'versioncontrol_handler_field_operation_items',
    ),
  );
  //TODO: Confirm what this is.
  $data['versioncontrol_operation_items']['type'] = array(
    'title' => t("Op Item Type"),
    'help' => t("The type of items' operation"),
    'field' => array(
      'handler' => 'views_handler_field_numeric',
    ),
  );

  // VersionControl Source Items
  $data['versioncontrol_source_items']['table']['group'] = t("VersionControl Source Items");
  // TODO: item_revision_id
  $data['versioncontrol_source_items']['item_revision_id'] = array();
  // TODO: source_item_revision_id
  $data['versioncontrol_source_items']['source_item_revision_id'] = array();
  $data['versioncontrol_source_items']['action'] = array(
    'title' => t('Action'),
    'help' => t('The action performed on this item'),
    'field' => array(
      //TODO: Action field
      'handler' => 'views_handler_field_generic',
    ),
  );
  // TODO: line_changes_recorded
  $data['versioncontrol_source_items']['line_changes_recorded'] = array();
  $data['versioncontrol_source_items']['line_changes_added'] = array(
    'title' => t("Lines Added"),
    'help' => t("The number of lines added to this item"),
    'field' => array(
      'handler' => 'views_handler_field_numeric',
      'click sortable' => TRUE,
    ),
    'sort' => array(
      'handler' => 'views_handler_sort',
    ),
  );
  $data['versioncontrol_source_items']['line_changes_removed'] = array(
    'title' => t("Lines Removed"),
    'help' => t("The number of lines removed from this item"),
    'field' => array(
      'handler' => 'views_handler_field_numeric',
      'click sortable' => TRUE,
    ),
    'sort' => array(
      'handler' => 'views_handler_sort',
    ), 
  );
  return $data;
}



/**
 * Implement hook_views_handler().
 *
 * @return array
 */
function versioncontrol_views_handlers() {
  $ret = array(
    'info' => array(
      'path'       => drupal_get_path('module', 'versioncontrol') . '/includes/views/handlers',
    ),
    'handlers' => array(
      // field handlers
      'versioncontrol_handler_field_repository_update_method' => array(
        'parent' => 'views_handler_field',
      ),
      'versioncontrol_handler_field_repository_actions' => array(
        'parent' => 'views_handler_field',
      ),
      'versioncontrol_handler_field_operation_type' => array(
        'parent' => 'views_handler_field',
      ),
      'versioncontrol_handler_field_operation_items' => array(
        'parent' => 'views_handler_field_prerender_list',
      ),
      'versioncontrol_handler_field_operation_labels' => array(
        'parent' => 'views_handler_field_prerender_list',
      ),
      'versioncontrol_handler_filter_operation_type' => array(
        'parent' => 'views_handler_filter_in_operator',
      ),
      'versioncontrol_handler_filter_operation_label' => array(
        'parent' => 'views_handler_filter_in_operator',
      ),
      'versioncontrol_handler_filter_repo_backend' => array(
        'parent' => 'views_handler_filter_in_operator',
      ),
      'versioncontrol_handler_filter_operation_type_label' => array(
        'parent' => 'views_handler_filter_string',
      ),
    ),
  );

  return $ret;
}

/**
 * Implementation of hook_views_plugins()
 *
 * @return array
 */
function versioncontrol_views_plugins() {
  //TODO: This is in progress...
  /*
  return array(
    'module' => 'versioncontrol',
    'row' => array(
      'versioncontrol_operations' => array(
        'title' => t('Operation'),
        'help' => t('Display a rendered operation'),
        'handler' => 'versioncontrol_plugin_row_operation',
        'path' => drupal_get_path('module', 'versioncontrol') . '/includes/views/handlers',
        'base' => array(
          'versioncontrol_operations',
        ),
        'type' => 'normal',
        'uses fields' => FALSE,
      ),
    ),
    'style' => array(
      'versioncontrol_operations' => array(
        'title' => t('Versioncontrol Operations'),
        'help' => t('Displays a grouped list of operations'),
        'handler' => 'versioncontrol_plugin_style_operations',
        'path' => drupal_get_path('module', 'versioncontrol') . '/handlers',
        'base' => array(
          'versioncontrol_operations',
        ),
        'type' => 'normal',
        'uses grouping' => TRUE,
        'uses row plugin' => TRUE,
        'theme' => 'versioncontrol_views_style_operations',
      ),
    ),
  );
  */
}

