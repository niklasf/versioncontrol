<?php
/**
 * @file
 * Version Control API - An interface to version control systems
 * whose functionality is provided by pluggable back-end modules.
 *
 * This file contains module hooks for users of Version Control API,
 * with API documentation and a bit of example code.
 * Hooks that are intended for VCS backends are not to be found in this file
 * as they are already documented in versioncontrol_fakevcs.module.
 *
 * Copyright 2007, 2008, 2009 by Jakob Petsovits ("jpetso", http://drupal.org/user/56020)
 */

/**
 * Act just after a versioncontrol operation has been inserted.
 *
 * @param VersioncontrolOperation $operation
 *   The operation object.
 *
 * @ingroup Operations
 */
function hook_versioncontrol_entity_commit_insert(VersioncontrolOperation $operation) {
}

/**
 * Act just after a versioncontrol operation has been updated.
 *
 * @param VersioncontrolOperation $operation
 *   The operation object.
 *
 * @ingroup Operations
 */
function hook_versioncontrol_entity_commit_update(VersioncontrolOperation $operation) {
}

/**
 * Act just after a versioncontrol operation has been deleted.
 *
 * @param VersioncontrolOperation $operation
 *   The operation object.
 *
 * @ingroup Operations
 */
function hook_versioncontrol_entity_commit_delete(VersioncontrolOperation $operation) {
}

/**
 * Extract repository data from the repository edit/add form's submitted
 * values, and add it to the @p $repository array. Later, that array will be
 * passed to hook_versioncontrol_repository() as part of the repository
 * insert/update procedure.
 *
 * Elements written to $repository['data'][$module] will be automatically
 * serialized and stored with the repository, you can write to that array
 * in order to store module-specific repository settings. If there are settings
 * that require a lot of memory or need to be accessible for SQL queries, you
 * might be better off storing these in your own module's table with
 * hook_versioncontrol_repository().
 *
 * @param $repository
 *   The repository array which is being passed by reference so that it can be
 *   written to.
 * @param $form
 *   The form array of the submitted repository edit/add form, with
 *   $form['#id'] == 'versioncontrol-repository-form' (amongst others).
 * @param $form_state
 *   The form state of the submitted repository edit/add form.
 *   If you altered this form and added an additional form element then
 *   $form_state['values'] will also contain the value of this form element.
 *
 * @ingroup Repositories
 * @ingroup Form handling
 * @ingroup Target audience: All modules with repository specific settings
 */
function hook_versioncontrol_repository_submit(&$repository, $form, $form_state) {
  // The user can specify multiple repository ponies, separated by whitespace.
  // So, split the string up into an array of ponies.
  $ponies = trim($form_state['values']['mymodule_ponies']);
  $ponies = empty($ponies) ? array() : explode(' ', $ponies);
  $repository['mymodule']['ponies'] = $ponies;
}

/**
 * Act on database changes when VCS repositories are inserted,
 * updated or deleted.
 *
 * @param $op
 *   Either 'insert' when the repository has just been created, or 'update'
 *   when repository name, root, URL backend or module specific data change,
 *   or 'delete' if it will be deleted after this function has been called.
 *
 * @param $repository
 *   The repository array containing the repository. It's a single
 *   repository array like the one returned by versioncontrol_get_repository(),
 *   so it consists of the following elements:
 *
 *   - 'repo_id': The unique repository id.
 *   - 'name': The user-visible name of the repository.
 *   - 'vcs': The unique string identifier of the version control system
 *        that powers this repository.
 *   - 'root': The root directory of the repository. In most cases,
 *        this will be a local directory (e.g. '/var/repos/drupal'),
 *        but it may also be some specialized string for remote repository
 *        access. How this string may look like depends on the backend.
 *   - 'authorization_method': The string identifier of the repository's
 *        authorization method, that is, how users may register accounts
 *        in this repository. Modules can provide their own methods
 *        by implementing hook_versioncontrol_authorization_methods().
 *   - 'data': An array where modules can store additional information about
 *        the repository, for settings or other data.
 *   - '[xxx]_specific': An array of VCS specific additional repository
 *        information. How this array looks like is defined by the
 *        corresponding backend module (versioncontrol_[xxx]).
 *        (Deprecated, to be replaced by the more general 'data' property.)
 *   - '???': Any other additions that modules added by implementing
 *        hook_versioncontrol_repository_submit().
 *
 * @ingroup Repositories
 * @ingroup Database change notification
 * @ingroup Form handling
 * @ingroup Target audience: All modules with repository specific settings
 */
function hook_versioncontrol_repository($op, $repository) {
  $ponies = $repository['mymodule']['ponies'];

  switch ($op) {
    case 'update':
      db_query("DELETE FROM {mymodule_ponies}
                WHERE repo_id = %d", $repository['repo_id']);
      // fall through
    case 'insert':
      foreach ($ponies as $pony) {
        db_query("INSERT INTO {mymodule_ponies} (repo_id, pony)
                  VALUES (%d, %s)", $repository['repo_id'], $pony);
      }
      break;

    case 'delete':
      db_query("DELETE FROM {mymodule_ponies}
                WHERE repo_id = %d", $repository['repo_id']);
      break;
  }
}


/**
 * Register new authorization methods that can be selected for a repository.
 * A module may restrict access and alter forms depending on the selected
 * authorization method which is a property of every repository array
 * ($repository['authorization_method']).
 *
 * A list of all authorization methods can be retrieved
 * by calling versioncontrol_get_authorization_methods().
 *
 * @return
 *   A structured array containing information about authorization methods
 *   provided by this module, wrapped in a structured array. Array keys are
 *   the unique string identifiers of each authorization method, and
 *   array values are the user-visible method descriptions (wrapped in t()).
 *
 * @ingroup Accounts
 * @ingroup Authorization
 * @ingroup Target audience: Authorization control modules
 */
function hook_versioncontrol_authorization_methods() {
  return array(
    'mymodule_code_ninja' => t('Code ninja skills required'),
  );
}

/**
 * Alter the list of repositories that are available for user registration
 * and editing.
 *
 * @param $repository_names
 *   The list of repository names as it is shown in the select box
 *   at 'versioncontrol/register'. Array keys are the repository ids,
 *   and array elements are the captions in the select box.
 *   There's two things that can be done with this array:
 *   - Change (amend) the caption, in order to provide more information
 *     for the user. (E.g. note that an application is necessary.)
 *   - Unset any number of array elements. If you do so, the user will not
 *     be able to register a new account for this repository.
 * @param $repositories
 *   A list of repositories (with the repository ids as array keys) that
 *   includes at least all of the repositories that correspond to the
 *   repository ids of the @p $repository_names array.
 *
 * @ingroup Accounts
 * @ingroup Authorization
 * @ingroup Repositories
 * @ingroup Form handling
 * @ingroup Target audience: Authorization control modules
 */
function hook_versioncontrol_alter_repository_selection(&$repository_names, $repositories) {
  global $user;

  foreach ($repository_names as $repo_id => $caption) {
    if ($repositories[$repo_id]['authorization_method'] == 'mymodule_code_ninja') {
      if (!in_array('code ninja', $user->roles)) {
        unset($repository_names[$repo_id]);
      }
    }
  }
}

/**
 * Let the Version Control API know whether the given VCS account
 * is authorized or not.
 *
 * @param $repository
 *   The repository where the status should be checked. (Note that the user's
 *   authorization status may differ for each repository.)
 * @param $uid
 *   The user id of the checked account.
 *
 * @return
 *   TRUE if the account is authorized, or FALSE if it's not.
 *
 * @ingroup Accounts
 * @ingroup Authorization
 * @ingroup Target audience: Authorization control modules
 */
function hook_versioncontrol_is_account_authorized($repository, $uid) {
  if ($repository['authorization_method'] != 'mymodule_dojo_status') {
    return TRUE;
  }
  $result = db_query("SELECT status
                      FROM {mymodule_dojo_status}
                      WHERE uid = %d AND repo_id = %d",
                      $uid, $repository['repo_id']);

  while ($account = db_fetch_object($result)) {
    return ($account->status == MYMODULE_SENSEI);
  }
  return FALSE;
}
