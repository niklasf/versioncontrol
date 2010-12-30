<?php

/**
 * @file
 *
 * Drush integration for VCAPI.
 */

function versioncontrol_drush_command() {
  $items = array();

  $items['vcapi-parse-logs'] = array(
    'description' => "Parse the logs from a repository known to Version Control API, fully updating the database with any changes made.",
    'arguments' => array(
      'repositories' => 'A comma-delimited list of repository ids or shortnames. Mixing and matching repo',
    ),
    'examples' => array(
      'drush vcapi-parse-logs 1,2,repo_name,17' => 'Sequentially fetch repository logs for the the four indicated repositories.',
    ),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_FULL,
  );

  return $items;
}

/**
 * Implementation of hook_drush_help().
 *
 * @param
 *   A string with the help section (prepend with 'drush:')
 *
 * @return
 *   A string with the help text for your command.
 */
function versioncontrol_drush_help($section) {
  switch ($section) {
    case 'drush:vcapi-parse-logs':
      return dt("This command will fetch all new activity in the repositories listed on the command line. If no repositories are given, log fetching will be triggered on all repositories.");
  }
}

function drush_versioncontrol_vcapi_parse_logs() {
  $repo_ids = _convert_csv_to_array(func_get_args());

  $repos = array();
  if (!empty($repo_ids)) {
    $ids = $names = array();
    foreach ($repo_ids as $repo) {
      if (is_numeric($repo)) {
        $ids[] = $repo;
      }
      else {
        $names[] = $repo;
      }
    }

    if (!empty($ids)) {
      $repos = versioncontrol_repository_load_multiple($ids);
    }
    if (!empty($names)) {
      $repos = array_merge(versioncontrol_repository_load_multiple(array(), array('name' => $names)), $repos);
    }
  }
  else {
    $repos = versioncontrol_repository_load_multiple(FALSE);
  }

  // With the repository list assembled, begin log fetching.
  foreach ($repos as $repo) {
    try {
      $repo->fetchLogs();
      drush_log(dt('Successfully parsed all logs from repository !name', array('!name' => $repo->name)), 'ok');
    }
    catch (Exception $e) {
      drush_set_error('Invalid generic repo class', dt('For repository "!name", a generic VersioncontrolRepository object was loaded instead of a backend-specific object; the generic class cannot be used for log fetching', array('!name' => $repo->name)));
    }
  }
}