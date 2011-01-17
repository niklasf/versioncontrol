<?php
// $Id$

/**
 * Views Field handler to show an author mapped to a drupal user if
 * possible. It also shows the committer if it is different.
 */
class versioncontrol_handler_field_operation_attribution extends versioncontrol_handler_field_operation_person {

  function pre_render($values) {
    parent::pre_render($values);
    // Ensure the author alias is set.
    $this->aliases['author'] = $this->field_alias;
  }

  function render($values) {
    $author_uid = $values->{$this->aliases['author_uid']};
    $committer_uid = $values->{$this->aliases['committer_uid']};

    // Show only author if they are equal.
    if ($author_uid == $committer_uid) {
      $this->aliases['person_uid'] = $this->aliases['author_uid'];
      $this->field_alias = $this->aliases['author'];
      $author = parent::render($values);
      return t('by !author', array('!author' => $author));
    }

    // Show both if they are different.
    $this->aliases['person_uid'] = $this->aliases['author_uid'];
    $this->field_alias = $this->aliases['author'];
    $author = parent::render($values);
    $this->aliases['person_uid'] = $this->aliases['committer_uid'];
    $this->field_alias = $this->aliases['committer'];
    $committer = parent::render($values);
    return t('<span class="authored-by">authored by !author</span>, <span class="committed-by">committed by !committer</span>', array(
      '!author' => $author, '!committer' => $committer));
  }

}
