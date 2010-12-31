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
      'repositories' => 'A comma-delimited list of repository ids or shortnames. Mixing and matching names and ids is fine.',
    ),
    'examples' => array(
      'drush vcapi-parse-logs 1,2,repo_name,17' => 'Sequentially fetch repository logs for the the four indicated repositories.',
      'drush vcapi-parse-logs' => 'Sequentially fetch repository logs for all repositories tracked by the system.',
    ),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_FULL,
  );
  $items['vcapi-map-operations'] = array(
    'description' => "Attempt to map author & committer data in tracked commits/operations to Drupal users. By default, only unmapped operations will be attempted.",
    'arguments' => array(
      'repositories' => 'A comma-delimited list of repository ids or shortnames. Mixing and matching names and ids is fine.',
    ),
    'options' => array(
      '--discover' => 'Instead of using the repositories provided in the arguments, derive a list of repositories to update by checking to see which ones have unmapped commits.',
      '--all' => 'Attempt mapping on all operations, whether or not they have already been mapped. Respects --authors and --committers options.',
      '--authors' => 'Only attempt to map authors.',
      '--committers' => 'Only attempt to map committers.',
    ),
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

/**
 * Parse a comma-delimited list of repo arguments and return the corresponding
 * list of repositories.
 *
 * Helper function, since this is a common argument for VCAPI's drush commands.
 *
 * @param string $repo_args
 */
function _drush_versioncontrol_parse_repo_arg_list($repo_args) {
  $repo_ids = _convert_csv_to_array($repo_args);

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
}

function drush_versioncontrol_vcapi_parse_logs() {
  $repos = _drush_versioncontrol_parse_repo_arg_list(func_get_args());

  // With the repository list assembled, begin log fetching.
  foreach ($repos as $repo) {
    try {
      drush_log(dt('Beginning synchronization of repository !name', array('!name' => $repo->name)), 'ok');
      $repo->fetchLogs();
      drush_log(dt('Successfully synchronized repository !name', array('!name' => $repo->name)), 'success');
    }
    catch (Exception $e) {
      drush_set_error('Invalid generic repo class', dt('For repository "!name", a generic VersioncontrolRepository object was loaded instead of a backend-specific object; the generic class cannot be used for log fetching', array('!name' => $repo->name)));
    }
  }
}

function drush_versioncontrol_vcapi_map_operations() {
  if ($discover = drush_get_option('discover', FALSE)) {
    $ids = db_select('versioncontrol_operations', 'vco')
      ->fields(array('repo_id'))
      ->distinct()
      ->where('vco.author_uid = 0 OR vco.committer_uid = 0')
      ->execute()
      ->fetchAll(PDO::FETCH_COLUMN, 'repo_id');

    $repos = versioncontrol_repository_load_multiple($repos);
  }
  else {
    $repos = _drush_versioncontrol_parse_repo_arg_list(func_get_args());
  }

  $full_remap = drush_get_option('all', FALSE);
  $only_authors = drush_get_option('authors', FALSE);
  $only_committers = drush_get_option('committers', FALSE);

  foreach ($repos as $repo) {
    $conditions = array();
    if (!$full_remap) {
      if ($only_authors) {
        $conditions['author_uid'] = 0;
      }
      else if ($only_committers) {
        $conditions['committer_uid'] = 0;
      }
    }

    // FIXME with the current architecture, this skips tag objects
    $commits = $repo->loadCommits(array(), $conditions);
    foreach ($commits as $commit) {
      $succeeded = FALSE;
      // TODO this logic is really horribly inelegant and if-y, improve it
      if ($full_remap) {
        if ($only_committers || $only_authors) {
          if ($only_authors) {
            $succeeded = $commit->mapAuthor();
          }
          else {
            $succeeded = $commit->mapCommitter();
          }
        }
        else {
          $succeeded = $commit->mapUsers();
        }
      }
      else {
        if ($commit->author_uid == 0 && !$only_committers) {
          $succeeded = $commit->mapAuthor();
        }
        if ($commit->committer_uid == 0 && !$only_authors) {
          $succeeded = $commit->mapCommitter() ? TRUE : $succeeded;
        }
      }

      if ($succeeded) {
        // Save the updated commit.
        $commit->save();
      }
    }
  }
}