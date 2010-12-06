<?php
// $Id$
/**
 * @file
 * Account class
 */

/**
 * Account class
 *
 * This class provides the way to manage users accounts.
 */
abstract class VersioncontrolAccount extends VersioncontrolEntity {
  /**
   * The username, as it is represented by the VCS.
   *
   * @var string
   */
  public $vcs_username = '';

  /**
   * The uid of the associated Drupal user.
   *
   * If 0, indicates that there is no known associated Drupal user. Code using
   * this class should construct their behaviors to respect this magic value.
   *
   * @var int
   */
  public $uid = 0;

  public $repo_id;

  /**
   * Update a VCS user account in the database, and call the necessary
   * module hooks. The account repository and uid must stay the same values as
   * the one given on account creation, whereas vcs_username and
   * data data members can change.
   *
   * FIXME use same logic as in other classes, this probably would
   * be changed only when we get account_id PK schema change in.
   * TODO review performance impact of updating the vcs_username since
   * original jpetso work try to avoid to update if not needed by passing
   * it as parameter.
   */
  public function update($options = array()) {
    // Append default options.
    $options += $this->defaultCrudOptions['update'];

    $repo_id = $this->repository->repo_id;

    db_query("UPDATE {versioncontrol_accounts}
      SET vcs_username = '%s'
      WHERE uid = %d AND repo_id = %d",
      $this->vcs_username, $this->uid, $repo_id
    );

    // Update operations table.
    // this is assuming 1-1 relation between accounts and uid's
    //   1. unassign all uid related operations
    db_query('update {versioncontrol_operations}
              set author_uid = 0
              where author_uid = %d and repo_id = %d',
              $this->uid, $this->repository->repo_id);
    db_query('update {versioncontrol_operations}
              set committer_uid = 0
              where committer_uid = %d and repo_id = %d',
              $this->uid, $this->repository->repo_id);
    //   2. assingn all operations related to repo and vcs username
    db_query("UPDATE {versioncontrol_operations}
              SET author_uid = %d
              WHERE author = '%s' AND repo_id = %d",
              $this->uid, $this->vcs_username, $this->repository->repo_id);
    db_query("UPDATE {versioncontrol_operations}
              SET committer_uid = %d
              WHERE committer = '%s' AND repo_id = %d",
              $this->uid, $this->vcs_username, $this->repository->repo_id);
    // not using data field for now, but backends can

    // Let the backend take action.
    $this->backendUpdate($options);

    // Everything's done, invoke the hook.
    module_invoke_all('versioncontrol_entity_account_update', $this);
    return $this;
  }

  /**
   * Insert a VCS user account into the database,
   * and call the necessary module hooks.
   *
   * FIXME use same logic as in other classes, this probably would
   * be changed only when we get account_id PK schema change in.
   */
  public function insert($options = array()) {
    // Append default options.
    $options += $this->defaultCrudOptions['insert'];

    // not using data field for now, but backends can
    db_query(
      "INSERT INTO {versioncontrol_accounts} (uid, repo_id, vcs_username)
       VALUES (%d, %d, '%s')", $this->uid, $this->repository->repo_id, $this->vcs_username
    );

    // Provide an opportunity for the backend to add its own stuff.
    $this->backendInsert($options);

    // Update the operations table.
    db_query("UPDATE {versioncontrol_operations}
              SET author_uid = %d
              WHERE author = '%s' AND repo_id = %d",
              $this->uid, $this->vcs_username, $this->repository->repo_id);
    db_query("UPDATE {versioncontrol_operations}
              SET committer_uid = %d
              WHERE committer = '%s' AND repo_id = %d",
              $this->uid, $this->vcs_username, $this->repository->repo_id);

    // Everything's done, invoke the hook.
    module_invoke_all('versioncontrol_entity_account_insert', $this);
    return $this;
  }

  /**
   * Delete a VCS user account from the database, set all commits with this
   * account as author to user 0 (anonymous), and call the necessary hooks.
   *
   * FIXME use same logic as in other classes, this probably would
   * be changed only when we get account_id PK schema change in.
   */
  public function delete($options = array()) {
    // Append default options.
    $options += $this->defaultCrudOptions['delete'];

    // Update the operations table.
    db_query('update {versioncontrol_operations}
              set author_uid = 0
              where author_uid = %d and repo_id = %d',
              $this->uid, $this->repository->repo_id);
    db_query('update {versioncontrol_operations}
              set committer_uid = 0
              where committer_uid = %d and repo_id = %d',
              $this->uid, $this->repository->repo_id);

    db_delete('versioncontrol_accounts')
      ->condition('uid', $this->uid)
      ->condition('repo_id', $this->repo_id)
      ->execute();

    // Provide an opportunity for the backend to delete its own stuff.
    $this->backendDelete($options);

    module_invoke_all('versioncontrol_entity_account_delete', $this);
  }

}
