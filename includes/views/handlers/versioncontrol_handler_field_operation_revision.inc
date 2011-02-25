<?php

/**
 * Version Control API field handler to output the right value of the
 * VersioncontrolOperation::revision.
 */
class versioncontrol_handler_field_operation_revision extends views_handler_field {
  public $backends = NULL;
  public $repos = array();

  function construct() {
    parent::construct();
    $this->backends = versioncontrol_get_backends();
  }

  /**
   * Get a repository of the @param $vcs type.
   */
  function getRepository($vcs, $repo_id) {
    if (!isset($this->repos[$repo_id])) {
      $this->repos[$repo_id] = $this->backends[$vcs]->loadEntity('repo', array($repo_id));
    }
    return $this->repos[$repo_id];
  }

  function render($values) {
    $revision = $values->{$this->field_alias};
    $vcs = $values->{$this->aliases['vcs']};
    $repo_id = $values->{$this->aliases['repo_id']};
    $revision = $this->backends[$vcs]->formatRevisionIdentifier($revision, 'short');
    $repo = $this->getRepository($vcs, $repo_id);
    $url_handler = $repo->getUrlHandler();
    $link = $url_handler->getCommitViewUrl($revision);
    if (!empty($link)) {
      return l(check_plain($revision), $link);
    }
    return check_plain($revision);
  }
}
