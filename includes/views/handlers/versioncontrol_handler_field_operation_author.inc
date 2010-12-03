<?php
// $Id$

/**
 * Views Field handler to map the author of a commit to their drupal user.
 */
class versioncontrol_handler_field_operation_author extends views_handler_field {

  /**
   * Called to add the field to a query.
   */
  function query() {
    $this->ensure_my_table();
    $this->field_alias = $this->query->add_field($this->table_alias, $this->real_field);
    // Force views to load the repo_id field when this field is added so that
    // we can appropriately build our links.
    $this->author_uid_alias = $this->query->add_field($this->table_alias, 'author_uid');
    $this->add_additional_fields();
  }

  function render($values) {
    $sanitized_author = $values->{$this->field_alias};
    // Strip out a email addresses wrapped in brackets
    $sanitized_author = preg_replace('/\<.*@.*\>/', '', $sanitized_author);
    // Protect against having only the email address not wrapped in <>
    $sanitized_author = preg_replace('/@.*\..*/', '', $sanitized_author);
    if ($values->{$this->author_uid_alias} != 0) {
      $output = l($sanitized_author, 'user/' . $author_uid);
    }
    else {
      $output = $sanitized_author; 
    }
    return $output;
  }
}
