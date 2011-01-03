<?php
// $Id$

/**
 * Views Field handler to show an author or committer, mapped to a
 * drupal user if possible.
 */
class versioncontrol_handler_field_operation_person extends views_handler_field {

  function render($values) {
    if (empty($values->{$this->aliases['person_uid']})) {
      return $values->{$this->field_alias};
    }
    else {
      $account = new stdClass();
      $account->uid = $values->{$this->aliases['person_uid']};
      $account->name = $values->{$this->field_alias};
      return theme('username', $account);
    }
  }

}
