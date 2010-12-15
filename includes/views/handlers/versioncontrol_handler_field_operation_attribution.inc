<?php
// $Id$

/**
 * Views Field handler to show an author mapped to a drupal user if
 * possible. It also shows the committer if it is different.
 */
class versioncontrol_handler_field_operation_attribution extends versioncontrol_handler_field_operation_person {

  function render($values) {
    $author_uid = $values->versioncontrol_operations_author_uid;
    $committer_uid = $values->{$this->aliases['committer_uid']};

    // Show only author if they are equal.
    if ($author_uid == $committer_uid) {
      $this->aliases['person_uid'] = 'versioncontrol_operations_author_uid';
      $this->field_alias = 'versioncontrol_operations_author';
      return parent::render($values);
    }

    // Show both if they are different.
    $this->aliases['person_uid'] = 'versioncontrol_operations_author_uid';
    $this->field_alias = 'versioncontrol_operations_author';
    $author = parent::render($values);
    $this->aliases['person_uid'] = 'versioncontrol_operations_committer_uid';
    $this->field_alias = 'versioncontrol_operations_committer';
    $committer = parent::render($values);
    return t('authored by !author, committed by !committer', array(
      '!author' => $author, '!committer' => $committer));
  }

}
