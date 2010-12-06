<?php
// $Id$
/**
 * @file
 * Backend class
 */

/**
 * Backend base class
 *
 * @abstract
 */
abstract class VersioncontrolBackend {
  /**
   * The user-visible name of the VCS.
   *
   * @var string
   */
  public $name;

  /**
   * A short description of the backend, if possible not longer than
   * one or two sentences.
   *
   * @var string
   */
  public $description;

  /**
   * An array listing optional capabilities, in addition
   * to the required functionality like retrieval of detailed
   * commit information. Array values can be an arbitrary combination
   * of VERSIONCONTROL_CAPABILITY_* values. If no additional capabilities
   * are supported by the backend, this array will be empty.
   *
   * @var array
   */
  public $capabilities;

  /**
   * Classes which this backend will instantiate when acting as a factory.
   */
  public $classesEntities = array();

  public $classesControllers = array();

  /**
   * An array of the default views for use in the Versioncontorl API interfaces.
   */
  public $views = array();

  /**
   * An array of the update methods available for this backend.
   *
   * Currently the keys must pertain to the value of the constants used by the backend.
   * This undermines the whole purpose of using constants so this should be reworked.
   *
   * TODO: Make this not use constants at all?
   */
  public $update_methods = array();

  public function __construct() {
    // Add defaults to $this->classes
    $this->classesControllers += array(
      'repo'      => 'VersioncontrolRepositoryController',
      'account'   => 'VersioncontrolAccountController',
      'operation' => 'VersioncontrolOperationController',
      'item'      => 'VersioncontrolItemController',
      'branch'    => 'VersioncontrolBranchController',
      'tag'       => 'VersioncontrolTagController',
    );
    // FIXME currently all these classes are abstract, so this won't work. Decide
    // if this should be removed, or if they should be made concrete classes
    $this->classesEntities += array(
      'repo'      => 'VersioncontrolRepository',
      'account'   => 'VersioncontrolAccount',
      'operation' => 'VersioncontrolOperation',
      'item'      => 'VersioncontrolItem',
      'branch'    => 'VersioncontrolBranch',
      'tag'       => 'VersioncontrolTag',
    );
    $this->views += array(
      'repositories' => 'versioncontrol_repositories',
      'global commit view' => 'versioncontrol_global_commits',
      'repository commits' => 'versioncontrol_repository_commits',
      'user commit view' => 'versioncontrol_user_commits',
    );
    $this->update_methods += array(
      0 => t('Automatic log retrieval.'),
      1 => t('Use external script to insert data.'),
    );
  }

  /**
   * Instantiate and build a VersioncontrolEntity object using provided data.
   *
   * This is the central factory method that should ultimately be used to
   * produce any VersioncontrolEntity-descended object for any backend. It does
   * two important things:
   *   - Provides a central point of control over what classes are used to
   *     instanciate what string 'type', as dictated by $this->classesEntities.
   *   - Ensure the backend can handle the type requested, and that the class
   *     it wants to instantiate descends from VersioncontrolEntity.
   *
   * @param string $type
   *   A string indicating the type of entity to be created. Should match with a
   *   key in $this->classesEntities.
   * @param mixed $data
   *   Either a stdClass object or an associative array of data to build the
   *   object with.
   * @return VersioncontrolEntity
   *   The instantiated and built object.
   */
  public function buildEntity($type, $data) {
    // Ensure this backend knows how to handle the entity type requested
    if (empty($this->classesEntities[$type])) {
      throw new Exception("Invalid entity type '$type' requested; not supported by current backend.");
    }

    // Ensure the class to create descends from VersioncontrolEntity.
    $class = $this->classesEntities[$type];
    // FIXME temporary hack to accommodate the introduction of the VersioncontrolEntityInterface interface; have to use reflection to check interfaces on a classname string, and
    // it's too annoying to refactor all this to accommodate that right now.
    if (!is_subclass_of($class, 'VersioncontrolEntity') && !is_subclass_of($class, 'VersioncontrolRepository')) {
      throw new Exception('Invalid Versioncontrol entity class specified for building; all entity classes must implement VersioncontrolEntityInterface.');
    }

    // If we're not building a repo object, snag the repo object and pass it to
    // the object builder.
    if ($type !== 'repo' && !empty($data->repository) && !empty($data->repo_id)) {
      $repo = $this->loadEntities('repo', array($data->repo_id));
      $data->repository = reset($repo);
    }

    $obj = new $this->classesEntities[$type]($this);
    $obj->build($data);
    return $obj;
  }

  public function loadEntities($controller, $ids = array(), $conditions = array(), $options = array()) {
    if (!isset($this->controllers[$controller])) {
      $class = $this->classesControllers[$controller];
      $this->controllers[$controller] = new $class();
      $this->controllers[$controller]->setBackend($this);
    }
    return $this->controllers[$controller]->load($ids, $conditions, $options);
  }

  /**
   * Perform a load operation that is expected to produce a single
   * VersioncontrolEntity object.
   *
   * Convenience function that helps simplify other code which is only
   * interested in loading a single object. All parameters pass straight through
   * to @see VersioncontrolBackend::loadEntities().
   *
   * @return VersioncontrolEntity
   */
  public function loadEntity($controller, $ids = array(), $conditions = array(), $options = array()) {
    $results = $this->loadEntities($controller, $ids, $conditions, $options);
    // Pop the first item off the result set and return it.
    return reset($results);
  }

  /**
   * Augment a select query with options specific to this backend.
   *
   * This method is fired by entity controllers whenever the backend type is
   * known prior to the issuing of the query.
   *
   * @param SelectQuery $query
   *   The query object being built.
   * @param string $entity_type
   *   The type of entity being loaded.
   */
  public function augmentEntitySelectQuery($query, $entity_type) {}

  /**
   * Retrieves the appropriate views module view for this backend.
   *
   * Versioncontrol allows backends to customize the views used for the admin
   * interface.  This method gets the appopriate one for this backend.
   *
   * @param $type
   *   The type of view we are returning (eg. Repositories) 
   *
   * @return
   *   The name of the view to be used.
   */
  public function getViewName($type) {
    if (isset($this->views[$type])) {
      return $this->views[$type];
    }
    return FALSE;
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
   * @param $revision
   *   The unformatted revision, as given in $operation->revision
   *   or $item->revision (or the respective table columns for those values).
   *
   * @param $format
   *   Either 'full' for the original version, or 'short' for a more compact form.
   *   If the commit identifier doesn't need to be shortened, the results can
   *   be the same for both versions.
   */
  public function formatRevisionIdentifier($revision, $format = 'full') {
    return $revision;
  }

  /**
   * Return the most accurate guess on what the VCS username for a Drupal user
   * might look like in the repository's account.
   *
   * @param $user
   *  The Drupal user who wants to register an account.
   */
  public function usernameSuggestion($user) {
    return strtr(drupal_strtolower($user->name),
      array(' ' => '', '@' => '', '.' => '', '-' => '', '_' => '', '.' => '')
    );
  }

  /**
   * Determine if the vcs_username is valid for this backend.
   *
   * @param $vcs_username
   *  The vcs_username to check. It is passed by reference so if the
   *  vcs_username is valid but needs minor adaptions (such as cutting
   *  away unneeded parts) then it the backend can modify it before
   *  returning the result.
   *
   * @return
   *   TRUE if the username is valid, FALSE if not.
   */
  public function isUsernameValid(&$username) {
    if (!preg_match('/^[a-zA-Z0-9]+$/', $username)) {
      return FALSE;
    }
    return TRUE;
  }

}
