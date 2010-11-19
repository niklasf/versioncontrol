<?php
// $Id$
/**
 * @file
 * Operation class
 */

/**
 * Stuff that happened in a repository at a specific time
 */
abstract class VersioncontrolOperation extends VersioncontrolEntity {
  protected $_id = 'vc_op_id';
  /**
   * db identifier
   *
   * The Drupal-specific operation identifier (a simple integer)
   * which is unique among all operations (commits, branch ops, tag ops)
   * in all repositories.
   *
   * @var int
   */
  public $vc_op_id;

  /**
   * Who actually perform the change on the repository.
   *
   * @var string
   */
  public $committer;

  /**
   * The time when the operation was performed, given as
   * Unix timestamp. (For commits, this is the time when the revision
   * was committed, whereas for branch/tag operations it is the time
   * when the files were branched or tagged.)
   *
   * @var timestamp
   */
  public $date;

  /**
   * The VCS specific repository-wide revision identifier,
   * like '' in CVS, '27491' in Subversion or some SHA-1 key in various
   * distributed version control systems. If there is no such revision
   * (which may be the case for version control systems that don't support
   * atomic commits) then the 'revision' element is an empty string.
   * For branch and tag operations, this element indicates the
   * (repository-wide) revision of the files that were branched or tagged.
   *
   * @var string
   */
  public $revision;

  /**
   * The log message for the commit, tag or branch operation.
   * If a version control system doesn't support messages for the current
   * operation type, this element should be empty.
   *
   * @var string
   */
  public $message;

  /**
   * The system specific VCS username of the user who executed this
   * operation(aka who write the change)
   *
   * @var string
   */
  public $author;

  /**
   * The repository where this operation occurs.
   *
   * @var VersioncontrolRepository
   */
  public $repository;

  /**
   * The type of the operation - one of the
   * VERSIONCONTROL_OPERATION_{COMMIT,BRANCH,TAG} constants.
   *
   * @var string
   */
  public $type;

  /**
   * An array of branches or tags that were affected by this
   * operation. Branch and tag operations are known to only affect one
   * branch or tag, so for these there will be only one element (with 0
   * as key) in 'labels'. Commits might affect any number of branches,
   * including none. Commits that emulate branches and/or tags (like
   * in Subversion, where they're not a native concept) can also include
   * add/delete/move operations for labels, as detailed below.
   * Mind that the main development branch - e.g. 'HEAD', 'trunk'
   * or 'master' - is also considered a branch. Each element in 'labels'
   * is a VersioncontrolLabel(VersioncontrolBranch VersioncontrolTag)
   *
   * @var array
   */
  public $labels = array();

  /**
   * The Drupal user id of the operation author, or 0 if no Drupal user
   * could be associated to the author.
   *
   * @var int
   */
  public $uid;

  /**
   * An array of VersioncontrolItem objects affected by this commit.
   *
   * @var array
   */
  public $itemRevisions = array();

  protected $defaultCrudOptions = array(
    'update' => array('nested' => TRUE),
    'insert' => array('nested' => TRUE),
    'delete' => array('nested' => TRUE),
  );

  /**
   * Error messages used mainly to get descriptions of errors at
   * hasWriteAccess().
   */
  private static $error_messages = array();

  public function loadItemRevisions($ids = array(), $conditions = array(), $options = array()) {
    $conditions['repo_id'] = $this->repo_id;
    $conditions['vc_op_id'] = $this->vc_op_id;
    return $this->backend->loadEntities('item', $ids, $conditions, $options);
  }

  public function insert($options = array()) {
    if (!empty($this->vc_op_id)) {
      // This is supposed to be a new commit, but has a vc_op_id already.
      throw new Exception('Attempted to insert a Versioncontrol commit which is already present in the database.', E_ERROR);
    }

    // Append default options.
    $options += $this->defaultCrudOptions['insert'];

    drupal_write_record('versioncontrol_operations', $this);

    if (!empty($options['nested'])) {
      $this->insertNested($options);
    }

    $this->backendInsert($options);

    // Everything's done, invoke the hook.
    module_invoke_all('versioncontrol_entity_commit_insert', $this);
    return $this;
  }

  protected function insertNested($options) {
    foreach ($this->itemRevisions as $item) {
      if (!isset($item->vc_op_id)) {
        $item->vc_op_id = $this->vc_op_id;
      }
      $item->insert($options);
    }
  }

  public function update($options = array()) {
    if (empty($this->vc_op_id)) {
      // This is supposed to be an existing branch, but has no vc_op_id.
      throw new Exception('Attempted to update a Versioncontrol commit which has not yet been inserted in the database.', E_ERROR);
    }

    // Append default options.
    $options += $this->defaultCrudOptions['update'];

    drupal_write_record('versioncontrol_operations', $this, 'vc_op_id');

    if (!empty($options['nested'])) {
      $this->updateNested($options);
    }

    $this->backendUpdate($options);

    // Everything's done, invoke the hook.
    module_invoke_all('versioncontrol_entity_commit_update', $this);
    return $this;
  }

  protected function updateNested($options) {
    foreach ($this->itemRevisions as $item) {
      $item->save($options);
    }
  }

  public function updateLabels() {
    db_delete('versioncontrol_operation_labels')
      ->condition('vc_op_id', $this->vc_op_id)
      ->execute();

    $insert = db_insert('versioncontrol_operation_labels')
      ->fields(array('vc_op_id', 'label_id', 'action'));
    foreach ($this->labels as $label) {
      // first, ensure there's a record of the label already
      if (!isset($label->label_id)) {
        $label->insert();
      }
      $values = array(
        'vc_op_id' => $this->vc_op_id,
        'label_id' => $label->label_id,
        // FIXME temporary hack, sets a default action. _CHANGE_ this.
        'action' => !empty($label->action) ? $label->action : VERSIONCONTROL_ACTION_MODIFIED,
      );
      $insert->values($values);
    }
    $insert->execute();
  }

  /**
   * Delete a commit, a branch operation or a tag operation from the database,
   * and call the necessary hooks.
   *
   * @param $operation
   *   The commit, branch operation or tag operation array containing
   *   the operation that should be deleted.
   */
  public function delete($options = array()) {
    // Append default options.
    $options += $this->defaultCrudOptions['delete'];

    db_delete('versioncontrol_operations')
      ->condition('vc_op_id', $this->vc_op_id)
      ->execute();

    if (!empty($options['nested'])) {
      $this->deleteNested($options);
    }

    // Remove relevant entries from the versioncontrol_operation_labels table.
    db_delete('versioncontrol_operation_labels')
      ->condition('vc_op_id', $this->vc_op_id)
      ->execute();

    $this->backendDelete($options);

    module_invoke_all('versioncontrol_entity_commit_delete', $this);
  }

  protected function deleteNested($options) {
    $items = $this->loadItemRevisions();
    foreach ($items as $item) {
      $item->delete($options);
    }
  }

  /**
   * Retrieve the list of access errors.
   *
   * If versioncontrol_has_commit_access(), versioncontrol_has_branch_access()
   * or versioncontrol_has_tag_access() returned FALSE, you can use this function
   * to retrieve the list of error messages from the various access checks.
   * The error messages do not include trailing linebreaks, it is expected that
   * those are inserted by the caller.
   */
  private function getAccessErrors() {
    return self::$error_messages;
  }

  /**
   * Set the list of access errors.
   */
  private function setAccessErrors($new_messages) {
    if (isset($new_messages)) {
      self::$error_messages = $new_messages;
    }
  }

  /**
   * Determine if a commit, branch or tag operation may be executed or not.
   * Call this function inside a pre-commit hook.
   *
   * @param $operation
   *   A single operation array like the ones returned by
   *   versioncontrol_get_operations(), but leaving out on a few details that
   *   will instead be determined by this function. This array describes
   *   the operation that is about to happen. Here's the allowed elements:
   *
   *   - 'type': The type of the operation - one of the
   *        VERSIONCONTROL_OPERATION_{COMMIT,BRANCH,TAG} constants.
   *   - 'repository': The repository where this operation occurs,
   *        given as a structured array, like the return value
   *        of versioncontrol_get_repository().
   *        You can either pass this or 'repo_id'.
   *   - 'repo_id': The repository where this operation occurs, given as a simple
   *        integer id. You can either pass this or 'repository'.
   *   - 'uid': The Drupal user id of the committer. Passing this is optional -
   *        if it isn't set, this function will determine the uid.
   *   - 'username': The system specific VCS username of the committer.
   *   - 'message': The log message for the commit, tag or branch operation.
   *        If a version control system doesn't support messages for the current
   *        operation type, this element must not be set. Operations with
   *        log messages that are set but empty will be denied access.
   *
   *   - 'labels': An array of branches or tags that will be affected by this
   *        operation. Branch and tag operations are known to only affect one
   *        branch or tag, so for these there will be only one element (with 0
   *        as key) in 'labels'. Commits might affect any number of branches,
   *        including none. Commits that emulate branches and/or tags (like
   *        in Subversion, where they're not a native concept) can also include
   *        add/delete/move operations for labels, as detailed below.
   *        Mind that the main development branch - e.g. 'HEAD', 'trunk'
   *        or 'master' - is also considered a branch. Each element in 'labels'
   *        is a VersioncontrolLabel(VersioncontrolBranch VersioncontrolTag)
   *
   * @param $item_revisions
   *   A structured array containing the exact details of what is about to happen
   *   to each item in this commit. The structure of this array is the same as
   *   the return value of VersioncontrolOperation::getItems() - that is,
   *   elements for 'type', 'path', 'revision', 'action', 'source_items' and
   *   'replaced_item' - but doesn't include the 'item_revision_id' element as
   *   there's no relation to the database yet.
   *
   *   The 'action', 'source_items', 'replaced_item' and 'revision' elements
   *   of each item are optional and may be left unset.
   *
   * @return
   *   TRUE if the operation may happen, or FALSE if not.
   *   If FALSE is returned, you can retrieve the concerning error messages
   *   by calling versioncontrol_get_access_errors().
   */
  protected function hasWriteAccess($operation, $item_revisions) {
    $operation->fill();

    // If we can't determine this operation's repository,
    // we can't really allow the operation in the first place.
    if (!isset($operation['repository'])) {
      switch ($operation['type']) {
      case VERSIONCONTROL_OPERATION_COMMIT:
        $type = t('commit');
        break;
      case VERSIONCONTROL_OPERATION_BRANCH:
        $type = t('branch');
        break;
      case VERSIONCONTROL_OPERATION_TAG:
        $type = t('tag');
        break;
      }
      $this->setAccessErrors(array(t(
        '** ERROR: Version Control API cannot determine a repository
        ** for the !commit-branch-or-tag information given by the VCS backend.',
        array('!commit-branch-or-tag' => $type)
      )));
      return FALSE;
    }

    // If the user doesn't have commit access at all, we can't allow this as well.
    $repo_data = $operation->repository->data['versioncontrol'];

    if (!$repo_data['allow_unauthorized_access']) {

      if (!$operation->repository->isAccountAuthorized($operation->uid)) {
        $this->setAccessErrors(array(t(
          '** ERROR: !user does not have commit access to this repository.',
          array('!user' => $operation->committer)
        )));
        return FALSE;
      }
    }

    // Don't let people do empty log messages, that's as evil as it gets.
    if (isset($operation['message']) && empty($operation['message'])) {
      $this->setAccessErrors(array(
        t('** ERROR: You have to provide a log message.'),
      ));
      return FALSE;
    }

    // Also see if other modules have any objections.
    $error_messages = array();

    foreach (module_implements('versioncontrol_write_access') as $module) {
      $function = $module .'_versioncontrol_write_access';

      // If at least one hook_versioncontrol_write_access returns TRUE,
      // the commit goes through. (This is for admin or sandbox exceptions.)
      $outcome = $function($operation, $item_revisions);
      if ($outcome === TRUE) {
        return TRUE;
      }
      else { // if !TRUE, $outcome is required to be an array with error messages
        $error_messages = array_merge($error_messages, $outcome);
      }
    }

    // Let the operation fail if there's more than zero error messages.
    if (!empty($error_messages)) {
    $this->setAccessErrors($error_messages);
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Get the user-visible version of a commit identifier a.k.a.
   * 'revision', as plaintext. By default, this function returns the
   * operation's revision if that property exists, or its vc_op_id
   * identifier as fallback.
   *
   * Version control backends can, however, choose to implement their
   * own version of this function, which for example makes it possible
   * to cut the SHA-1 hash in distributed version control systems down
   * to a readable length.
   *
   * @param $format
   *   Either 'full' for the original version, or 'short' for a more compact form.
   *   If the commit identifier doesn't need to be shortened, the results can
   *   be the same for both versions.
   */
  public function formatRevisionIdentifier($format = 'full') {
    if (empty($this->revision)) {
      return '#'. $this->vc_op_id;
    }
    return $this->repository->formatRevisionIdentifier($this->revision, $format);
  }

  /**
   * Retrieve the tag or branch that applied to that item during the
   * given operation. The result of this function will be used for the
   * selected label property of the item, which is necessary to preserve
   * the item state throughout navigational API functions.
   *
   * @param $item
   *   The item revision for which the label should be retrieved.
   *
   * @return
   *   NULL if the given item does not belong to any label or if the
   *   appropriate label cannot be retrieved. Otherwise a
   *   VersioncontrolLabel array is returned
   *
   *   In case the label array also contains the 'label_id' element
   *   (which happens when it's copied from the $operation->labels
   *   array) there will be a small performance improvement as the label
   *   doesn't need to be compared to and loaded from the database
   *   anymore.
   */
  public abstract function getSelectedLabel($item);
}
