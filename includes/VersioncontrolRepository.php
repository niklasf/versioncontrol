<?php
// $Id$
/**
 * @file
 * Repo class
 */

/**
 * Contain fundamental information about the repository.
 */
abstract class VersioncontrolRepository implements VersioncontrolEntityInterface {
  protected $_id = 'repo_id';

  /**
   * db identifier
   *
   * @var    int
   */
  public $repo_id;

  /**
   * repository name inside drupal
   *
   * @var    string
   */
  public $name;

  /**
   * VCS string identifier
   *
   * @var    string
   */
  public $vcs;

  /**
   * where it is
   *
   * @var    string
   */
  public $root;

  /**
   * how ot authenticate
   *
   * @var    string
   */
  public $authorization_method = 'versioncontrol_admin';

  /**
   * The method that this repository will use to update the operations table.
   *
   * This should correspond to constants provided by the backend provider.
   *
   * @var    integer
   */
  public $update_method = 0;

  /**
   * The Unix timestamp when this repository was last updated.
   *
   * @var    integer
   */
  public $updated = 0;

  /**
   * Indicates if the repository is being updated now.
   *
   * @var    integer
   */
  public $locked = 0;

  /**
   * An array of additional per-repository settings, mostly populated by
   * third-party modules. It is serialized on DB.
   */
  public $data = array();

  /**
   * The backend associated with this repository
   *
   * @var VersioncontrolBackend
   */
  protected $backend;

  /**
   * An array of VersioncontrolEntityController objects used to spawn more
   * entities from this repository, if needed. These objects are lazy-
   * instanciated to avoid unnecessary object creation.
   *
   * @var array
   */
  protected $controllers = array();

  protected $built = FALSE;

  /**
   * An array describing the plugins that will be used for this repository.
   *
   * The current plugin types(array keys) are:
   * - author_mapper
   * - committer_mapper
   *
   * @var array
   */
  public $plugins = array();

  /**
   * An array of plugin instances (instanciated plugin objects).
   *
   * @var array
   */
  protected $pluginInstances = array();

  protected $defaultCrudOptions = array(
    'update' => array('nested' => TRUE),
    'insert' => array('nested' => TRUE),
    'delete' => array('nested' => TRUE),
  );

  public function __construct($backend = NULL) {
    if ($backend instanceof VersioncontrolBackend) {
      $this->backend = $backend;
    }
    else if (variable_get('versioncontrol_single_backend_mode', FALSE)) {
      $backends = versioncontrol_get_backends();
      $this->backend = reset($backends);
    }
  }

  public function getBackend() {
    return $this->backend;
  }

  /**
   * Pseudo-constructor method; call this method with an associative array or
   * stdClass object containing properties to be assigned to this object.
   *
   * @param array $args
   */
  public function build($args = array()) {
    // If this object has already been built, bail out.
    if ($this->built == TRUE) {
      return FALSE;
    }

    foreach ($args as $prop => $value) {
      $this->$prop = $value;
    }
    if (!empty($this->data) && is_string($this->data)) {
      $this->data = unserialize($this->data);
    }
    if (!empty($this->plugins) && is_string($this->plugins)) {
      $this->plugins = unserialize($this->plugins);
    }
    $this->built = TRUE;
  }


  /**
   * Perform a log fetch, synchronizing VCAPI database data with the current
   * state of the repository.
   *
   * FIXME - this should be an abstract method, but until we can make VersioncontrolRepository abstract again, we'll have to live with the fatal error.
   *
   */
  public function fetchLogs() {
    throw new Exception('Cannot perform a log fetch using base VersioncontrolRepository; your loaded repository object must be backend-specific.', E_ERROR);
  }

  /**
   * Title callback for repository arrays.
   */
  public function titleCallback() {
    return check_plain($repository->name);
  }

  /**
   * Load known branches in a repository from the database as an array of
   * VersioncontrolBranch-descended objects.
   *
   * @param array $ids
   *   An array of branch ids. If given, only branches matching these ids will
   *   be returned.
   * @param array $conditions
   *   An associative array of additional conditions. These will be passed to
   *   the entity controller and composed into the query. The array should be
   *   key/value pairs with the field name as key, and desired field value as
   *   value. The value may also be an array, in which case the IN operator is
   *   used. For more complex requirements, FIXME finish!
   *   @see VersioncontrolEntityController::buildQuery() .
   *
   * @return
   *   An associative array of label objects, keyed on their
   */
  public function loadBranches($ids = array(), $conditions = array(), $options = array()) {
    $conditions['repo_id'] = $this->repo_id;
    return $this->backend->loadEntities('branch', $ids, $conditions, $options);
  }

  /**
   * Load known tags in a repository from the database as an array of
   * VersioncontrolTag-descended objects.
   *
   * @param array $ids
   *   An array of tag ids. If given, only tags matching these ids will be
   *   returned.
   * @param array $conditions
   *   An associative array of additional conditions. These will be passed to
   *   the entity controller and composed into the query. The array should be
   *   key/value pairs with the field name as key, and desired field value as
   *   value. The value may also be an array, in which case the IN operator is
   *   used. For more complex requirements, FIXME finish!
   *   @see VersioncontrolEntityController::buildQuery() .
   *
   * @return
   *   An associative array of label objects, keyed on their
   */
  public function loadTags($ids = array(), $conditions = array(), $options = array()) {
    $conditions['repo_id'] = $this->repo_id;
    return $this->backend->loadEntities('tag', $ids, $conditions, $options);
  }

  public function loadCommits($ids = array(), $conditions = array(), $options = array()) {
    $conditions['type'] = VERSIONCONTROL_OPERATION_COMMIT;
    $conditions['repo_id'] = $this->repo_id;
    return $this->backend->loadEntities('operation', $ids, $conditions, $options);
  }

  public function loadAccounts($ids = array(), $conditions = array(), $options = array()) {
    $conditions['repo_id'] = $this->repo_id;
    return $this->backend->loadEntities('account', $ids, $conditions, $options);
  }

  /**
   * Return TRUE if the account is authorized to commit in the actual
   * repository, or FALSE otherwise. Only call this function on existing
   * accounts or uid 0, the return value for all other
   * uid/repository combinations is undefined.
   *
   * @param $uid
   *   The user id of the checked account.
   */
  public function isAccountAuthorized($uid) {
    if (!$uid) {
      return FALSE;
    }
    $approved = array();

    foreach (module_implements('versioncontrol_is_account_authorized') as $module) {
      $function = $module .'_versioncontrol_is_account_authorized';

      // If at least one hook_versioncontrol_is_account_authorized()
      // returns FALSE, the account is assumed not to be approved.
      if ($function($this, $uid) === FALSE) {
        return FALSE;
      }
    }
    return TRUE;
  }

  public function save($options = array()) {
    return isset($this->repo_id) ? $this->update($options) : $this->insert($options);
  }

  /**
   * Update a repository in the database, and invoke the necessary hooks.
   *
   * The 'repo_id' and 'vcs' properties of the repository object must stay
   * the same as the ones given on repository creation,
   * whereas all other values may change.
   */
  public function update($options = array()) {
    if (empty($this->repo_id)) {
      // This is supposed to be an existing repository, but has no repo_id.
      throw new Exception('Attempted to update a Versioncontrol repository which has not yet been inserted in the database.', E_ERROR);
    }

    // Append default options.
    $options += $this->defaultCrudOptions['update'];

    drupal_write_record('versioncontrol_repositories', $this, 'repo_id');

    $this->backendUpdate($options);

    // Everything's done, let the world know about it!
    module_invoke_all('versioncontrol_repository_entity_update', $this);
    return $this;
  }

  protected function backendUpdate($options) {}

  /**
   * Insert a repository into the database, and call the necessary hooks.
   *
   * @return
   *   The finalized repository array, including the 'repo_id' element.
   */
  public function insert($options = array()) {
    if (!empty($this->repo_id)) {
      // This is supposed to be a new repository, but has a repo_id already.
      throw new Exception('Attempted to insert a Versioncontrol repository which is already present in the database.', E_ERROR);
    }

    // Append default options.
    $options += $this->defaultCrudOptions['insert'];

    // drupal_write_record() will fill the $repo_id property on $this.
    drupal_write_record('versioncontrol_repositories', $this);

    $this->backendInsert($options);

    // Everything's done, let the world know about it!
    module_invoke_all('versioncontrol_repository_entity_insert', $this);
    return $this;
  }

  protected function backendInsert($options) {}

  /**
   * Delete a repository from the database, and call the necessary hooks.
   * Together with the repository, all associated commits and accounts are
   * deleted as well.
   */
  public function delete($options = array()) {
    // Append default options.
    $options += $this->defaultCrudOptions['delete'];

    if ($options['nested']) {
      // Delete operations.
      foreach ($this->loadBranches() as $branch) {
        $branch->delete();
      }
      foreach ($this->loadTags() as $tag) {
        $tag->delete();
      }
      foreach ($this->loadCommits() as $commit) {
        $commit->delete();
      }
      // FIXME accounts are changing significantly, this will need to, too
      foreach ($this->loadAccounts() as $account) {
        $account->delete();
      }
    }

    db_delete('versioncontrol_repositories')
      ->condition('repo_id', $this->repo_id)
      ->execute();

    $this->backendDelete($options);

    module_invoke_all('versioncontrol_entity_repository_delete', $this);
  }

  protected function backendDelete($options) {}

  /**
   * Export a repository's authenticated accounts to the version control system's
   * password file format.
   *
   * FIXME this is TOTALLY broken, it calls itself.
   * @param $repository
   *   The repository array of the repository whose accounts should be exported.
   *
   * @return
   *   The plaintext result data which could be written into the password file
   *   as is.
   *
   */
  public function exportAccounts() {
    $accounts = $this->loadAccounts();
    return $repository->exportAccounts($accounts);
  }

  /**
   * Convinience method to call backend analogue.
   *
   * @param $revision
   *   The unformatted revision, as given in $operation->revision
   *   or $item->revision (or the respective table columns for those values).
   * @param $format
   *   Either 'full' for the original version, or 'short' for a more compact form.
   *   If the revision identifier doesn't need to be shortened, the results can
   *   be the same for both versions.
   */
  public function formatRevisionIdentifier($revision, $format = 'full') {
    return $this->backend->formatRevisionIdentifier($revision, $format);
  }

  /**
   * Convinience method to retrieve url handler.
   */
  public function getUrlHandler() {
    if (!isset($this->data['versioncontrol']['url_handler'])) {
      $this->data['versioncontrol']['url_handler'] =
        new VersioncontrolRepositoryUrlHandler(
          $this, VersioncontrolRepositoryUrlHandler::getEmpty()
        );
    }
    return $this->data['versioncontrol']['url_handler'];
  }

  /**
   * Retrieve the Drupal user id for a given VCS username.
   *
   * @param $username
   *   The VCS specific username (a string) corresponding to the Drupal
   *   user.
   * @param $include_unauthorized
   *   If FALSE (which is the default), this function does not return
   *   accounts that are pending, queued, disabled, blocked, or otherwise
   *   non-approved. If TRUE, all accounts are returned, regardless of
   *   their status.
   *
   * @return
   *   The Drupal user id that corresponds to the given username and
   *   repository, or NULL if no Drupal user could be associated to
   *   those.
   */
  public function getAccountUidForUsername($username, $include_unauthorized = FALSE) {
    $result = db_query("SELECT uid, repo_id
      FROM {versioncontrol_accounts}
      WHERE vcs_username = '%s' AND repo_id = %d",
      $username, $this->repo_id);

    while ($account = db_fetch_object($result)) {
      // Only include approved accounts, except in case the caller said otherwise.
      if ($include_unauthorized || $this->isAccountAuthorized($account->uid)) {
        return $account->uid;
      }
    }
    return NULL;
  }

  /**
   * Retrieve the VCS username for the Drupal user id.
   *
   * @param $uid
   *   The Drupal user id corresponding to the VCS account.
   * @param $include_unauthorized
   *   If FALSE (which is the default), this function does not return
   *   accounts that are pending, queued, disabled, blocked, or otherwise
   *   non-approved. If TRUE, all accounts are returned, regardless of
   *   their status.
   *
   * @return
   *   The VCS username (a string) that corresponds to the given Drupal
   *   user and repository, or NULL if no VCS account could be associated
   *   to those.
   */
  function getAccountUsernameForUid($uid, $include_unauthorized = FALSE) {
    $result = db_query('SELECT uid, username, repo_id
      FROM {versioncontrol_accounts}
      WHERE uid = %d AND repo_id = %d',
      $uid, $this->repo_id);

    while ($account = db_fetch_object($result)) {
      // Only include approved accounts, except in case the caller said otherwise.
      if ($include_unauthorized || $this->isAccountAuthorized($account->uid)) {
        return $account->vcs_username;
      }
    }
    return NULL;
  }

  /**
   * Get an instantiated plugin object based on a requested plugin slot, and the
   * plugin this repository object has assigned to that slot.
   *
   * Internal function - other methods should provide a nicer public-facing
   * interface. This method exists primarily to reduce code duplication involved
   * in ensuring error handling and sound loading of the plugin.
   */
  protected function getPluginClass($plugin_slot, $plugin_type, $class_type) {
    ctools_include('plugins');

    if (empty($this->plugins[$plugin_slot])) {
      throw new Exception("Attempted to get plugin in slot '$plugin_slot', but no plugin has been assigned to that slot on this repository.", E_STRICT);
      return FALSE;
    }
    $plugin_name = $this->plugins[$plugin_slot];

    $plugin = ctools_get_plugins('versioncontrol', $plugin_type, $plugin_name);
    if (!is_array($plugin)) {
      throw new Exception("Attempted to get a plugin of type '$plugin_type' named '$plugin_name', but no such plugin could be found.", E_WARNING);
      return FALSE;
    }

    $class_name = ctools_plugin_get_class($plugin, $class_type);
    if (!class_exists($class_name)) {
      throw new Exception("Plugin '$plugin_name' of type '$plugin_type' does not contain a valid class name in handler slot '$class_type'", E_WARNING);
      return FALSE;
    }

    return new $class_name();
  }

  public function getAuthorMapper() {
    if (!isset($this->pluginInstances['author_mapper'])) {
      // if no plugin is set, just directly register FALSE for the instance
      if (empty($this->plugins['author_mapper'])) {
        $this->pluginInstances['author_mapper'] = FALSE;
      }
      else {
        $this->pluginInstances['author_mapper'] = $this->getPluginClass('author_mapper', 'user_mapping_methods', 'mapper');
      }
    }
    return $this->pluginInstances['author_mapper'];
  }

  public function getCommitterMapper() {
    if (!isset($this->pluginInstances['committer_mapper'])) {
      // If nothing is set for the committer mapper plugin, reuse the author one
      if (empty($this->plugins['committer_mapper'])) {
        $this->pluginInstances['committer_mapper'] = $this->getAuthorMapper();
      }
      else {
        $this->pluginInstances['committer_mapper'] = $this->getPluginClass('committer_mapper', 'user_mapping_methods', 'mapper');
      }
    }

    return $this->pluginInstances['committer_mapper'];
  }


  //ArrayAccess interface implementation FIXME soooooooo deprecated
  public function offsetExists($offset) {
    return isset($this->$offset);
  }
  public function offsetGet($offset) {
    return $this->$offset;
  }
  public function offsetSet($offset, $value) {
    $this->$offset = $value;
  }
  public function offsetUnset($offset) {
    unset($this->$offset);
  }
}

/**
 * Contains the urls mainly for displaying.
 */
class VersioncontrolRepositoryUrlHandler {

  /**
   * Repository where this urls belongs.
   *
   * @var    VersioncontrolRepository
   */
  public $repository;

  /**
   * An array of repository viewer URLs.
   *
   * @var    array
   */
  public $urls;

  public function __construct($repository, $urls) {
    $this->repository = $repository;
    $this->urls = $urls;
  }

  /**
   * Explain and return and empty array of urls data member.
   */
  public static function getEmpty() {
    return array(
      /**
       * The URL of the repository viewer that displays a given commit in the
       * repository. "%revision" is used as placeholder for the
       * revision/commit/changeset identifier.
       */
      'commit_view'    => '',
      /**
       * The URL of the repository viewer that displays the commit log of a
       * given file in the repository. "%path" is used as placeholder for the
       * file path, "%revision" will be replaced by the file-level revision
       * (the one in {versioncontrol_item_revisions}.revision), and "%branch"
       * will be replaced by the branch name that the file is on.
       */
      'file_log_view'  => '',
      /**
       * The URL of the repository viewer that displays the contents of a given
       * file in the repository. "%path" is used as placeholder for the file
       * path, "%revision" will be replaced by the file-level revision (the one
       * in {versioncontrol_item_revisions}.revision), and "%branch" will be
       * replaced by the branch name that the file is on.
       */
      'file_view'      => '',
      /**
       * The URL of the repository viewer that displays the contents of a given
       * directory in the repository. "%path" is used as placeholder for the
       * directory path, "%revision" will be replaced by the file-level revision
       * (the one in {versioncontrol_item_revisions}.revision - only makes sense
       * if directories are versioned, of course), and "%branch" will be
       * replaced by the branch name that the directory is on.
       */
      'directory_view' => '',
      /**
       * The URL of the repository viewer that displays the diff between two
       * given files in the repository. "%path" and "%old-path" are used as
       * placeholders for the new and old paths (for some version control
       * systems, like CVS, those paths will always be the same).
       * "%new-revision" and "%old-revision" will be replaced by the
       * respective file-level revisions (from
       * {versioncontrol_item_revisions}.revision), and "%branch" will be
       * replaced by the branch name that the file is on.
       */
      'diff'           => '',
      /**
       * The URL of the issue tracker that displays the issue/case/bug page of
       * an issue id which presumably has been mentioned in a commit message.
       * As issue tracker URLs are likely specific to each repository, this is
       * also a per-repository setting. (Although... maybe it would make sense
       * to have per-project rather than per-repository. Oh well.)
       */
      'tracker'        => ''
    );
  }

  /**
   * Retrieve the URL of the repository viewer that displays the given commit
   * in the corresponding repository.
   *
   * @param $revision
   *   The revision on the commit operation whose view URL should be retrieved.
   *
   * @return
   *   The commit view URL corresponding to the given arguments.
   *   An empty string is returned if no commit view URL has been defined,
   *   or if the commit cannot be viewed for any reason.
   */
  public function getCommitViewUrl($revision) {
    if (empty($revision)) {
      return '';
    }
    return strtr($this->urls['commit_view'], array(
      '%revision' => $revision,
    ));
  }

  /**
   * Retrieve the URL of the repository viewer that displays the commit log
   * of the given item in the corresponding repository. If no such URL has been
   * specified by the user, the appropriate URL from the Commit Log module is
   * used as a fallback (if that module is enabled).
   *
   * @param $item
   *   The item whose log view URL should be retrieved.
   *
   * @return
   *   The item log view URL corresponding to the given arguments.
   *   An empty string is returned if no item log view URL has been defined
   *   (and if not even Commit Log is enabled), or if the item cannot be viewed
   *   for any reason.
   */
  public function getItemLogViewUrl(&$item) {
    $label = $item->getSelectedLabel();

    if (isset($label->type) && $label->type == VERSIONCONTROL_LABEL_BRANCH) {
      $current_branch = $label['name'];
    }

    if (!empty($this->urls['file_log_view'])) {
      if ($item->isFile()) {
        return strtr($this->urls['file_log_view'], array(
          '%path'     => $item->path,
          '%revision' => $item->revision,
          '%branch'   => isset($current_branch) ? $current_branch : '',
        ));
      }
      // The default URL backend doesn't do log view URLs for directory items:
      return '';
    }
    elseif (module_exists('commitlog')) { // fallback, as 'file_log_view' is empty
      $query = array(
        'repos' => $item->repository->repo_id,
        'paths' => drupal_urlencode($item->path),
      );
      if (isset($current_branch)) {
        $query['branches'] = $current_branch;
      }
      return url('commitlog', array(
        'query' => $query,
        'absolute' => TRUE,
      ));
    }
    return ''; // in case we really can't retrieve any sensible URL
  }

  /**
   * Retrieve the URL of the repository viewer that displays the contents of the
   * given item in the corresponding repository.
   *
   * @param $item
   *   The item whose view URL should be retrieved.
   *
   * @return
   *   The item view URL corresponding to the given arguments.
   *   An empty string is returned if no item view URL has been defined,
   *   or if the item cannot be viewed for any reason.
   */
  public function getItemViewUrl(&$item) {
    $label = $item->getSelectedLabel();

    if (isset($label->type) && $label->type == VERSIONCONTROL_LABEL_BRANCH) {
      $current_branch = $label->name;
    }
    $view_url = $item->isFile()
      ? $this->urls['file_view']
      : $this->urls['directory_view'];

    return strtr($view_url, array(
      '%path'     => $item['path'],
      '%revision' => $item['revision'],
      '%branch'   => isset($current_branch) ? $current_branch : '',
    ));
  }

  /**
   * Retrieve the URL of the repository viewer that displays the diff between
   * two given files in the corresponding repository.
   *
   * @param $file_item_new
   *   The new version of the file that should be diffed.
   * @param $file_item_old
   *   The old version of the file that should be diffed.
   *
   * @return
   *   The diff URL corresponding to the given arguments.
   *   An empty string is returned if no diff URL has been defined,
   *   or if the two items cannot be diffed for any reason.
   */
  public function getDiffUrl(&$file_item_new, $file_item_old) {
    $label = $file_item_new->getSelectedLabel();

    if (isset($label['type']) && $label['type'] == VERSIONCONTROL_LABEL_BRANCH) {
      $current_branch = $label['name'];
    }
    return strtr($this->urls['diff'], array(
      '%path'         => $file_item_new['path'],
      '%new-revision' => $file_item_new['revision'],
      '%old-path'     => $file_item_old['path'],
      '%old-revision' => $file_item_old['revision'],
      '%branch'       => isset($current_branch) ? $current_branch : '',
    ));
  }

  /**
   * Retrieve the URL of the issue tracker that displays the issue/case/bug page
   * of an issue id which presumably has been mentioned in a commit message.
   * As issue tracker URLs are specific to each repository, this also needs
   * to be given as argument.
   *
   * @param $issue_id
   *   A number that uniquely identifies the mentioned issue/case/bug.
   *
   * @return
   *   The issue tracker URL corresponding to the given arguments.
   *   An empty string is returned if no issue tracker URL has been defined.
   */
  public function getTrackerUrl($issue_id) {
    return strtr($this->urls['tracker'], array('%d' => $issue_id));
  }
}
