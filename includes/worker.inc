<?php

/**
 * @file worker.inc
 *
 * Queue-processing worker-related functions.
 */

/**
 * Process a versioncontrol_repomgr queue job.
 *
 * @param type $data
 *   The array of job data to be processed.
 */
function _versioncontrol_repomgr_run_worker($data) {
  // Lots and LOTS of input validation; this is a really bad place to fail silently.
  if (empty($data['operation'])) {
    watchdog('versioncontrol_repomgr', "Repository management job was enqueued without any operations to run.", array(), WATCHDOG_ERROR);
    throw new Exception("Repository management job was enqueued without any operations to run.", E_ERROR);
  }

  $ops = is_string($data['operation']) ? array($data['operation'] => array()) : $data['operation'];

  if (empty($data['repository']) || !$data['repository'] instanceof VersioncontrolRepository) {
    watchdog('versioncontrol_repomgr', "Repository management job was enqueued without a valid VersioncontrolRepository object to act on.", array(), WATCHDOG_ERROR);
    throw new Exception("Repository management job was enqueued without a valid VersioncontrolRepository object to act on.", E_ERROR);
  }

  $repo = $data['repository'];
  $manager = $repo->getRepositoryManager();

  // Loop through all ops and ensure all are valid before running any. This
  // helps preserve known state by preventing jobs are guaranteed to terminate
  // partway through from starting at all.
  foreach (array_keys($ops) as $op) {
    if (!method_exists($manager, $op)) {
      $vars = array(
        '%op' => $op,
        '%repo_id' => empty($repo->repo_id) ? '[NEW]' : $repo->repo_id,
        '%plugin' => get_class($manager),
      );
      watchdog('versioncontrol_repomgr', "Operation '%op' was requested on repo_id %repo_id using plugin class %plugin, but no such method exists.", $vars, WATCHDOG_ERROR);
      throw new Exception(strtr("Operation '%op' was requested on repo_id %repo_id using plugin class %plugin, but no such method exists.", $vars), E_ERROR);
    }
  }

  // Finally ready to actually run the operations.
  foreach ($ops as $op => $args) {
    call_user_func_array(array($manager, $op), $args);
  }
}