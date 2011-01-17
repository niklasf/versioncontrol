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
      // @TODO The following is a hack until we can join both author_uid and committer_uid to users.
      // Only fix the username for author which is the only column joined to {users}.
      if ($this->field_alias == $this->aliases['author']) {
        $account->name = $values->{$this->aliases['username']};
      }
      else {
        // @TODO Remove this query
        // This is a temporary hack that we should very rarely need.
        $account->name = db_result(db_query("SELECT name FROM {users} WHERE uid = %d", $account->uid));
      }
      return theme('username', $account);
    }
  }

}
