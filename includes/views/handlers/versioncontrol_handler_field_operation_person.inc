<?php
// $Id$

/**
 * Views Field handler to show an author or committer, mapped to a
 * drupal user if possible.
 */
class versioncontrol_handler_field_operation_person extends views_handler_field {

  function render($values) {
    $account->uid = $values->{$this->aliases['person_uid']};
    if ($account->uid == 0) {
      return $values->{$this->field_alias};
    }
    else {
      $account->name = $values->{$this->field_alias};
      return theme('username', $account);
    }
  }

}
