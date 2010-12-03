<?php
// $Id$

/**
 * Version Control API field handler to output the right value of the
 * VersioncontrolOperation::revision.
 */
class versioncontrol_handler_field_operation_revision extends views_handler_field {
  public $backends = NULL;

  function construct() {
    parent::construct();
    $this->backends = versioncontrol_get_backends();
  }

  function render($values) {
    $revision = $values->{$this->field_alias};
    $vcs = $values->{$this->aliases['vcs']};
    $revision = $this->backends[$vcs]->formatRevisionIdentifier($revision, 'short');
    return check_plain($revision);
  }
}
