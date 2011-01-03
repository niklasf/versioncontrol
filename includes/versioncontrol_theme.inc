<?php
// $Id$
/**
 * @file
 * Theme functions for Version Control API.
 */

/**
 * Return a rendered visual diffstat.
 */
function theme_versioncontrol_diffstat($places, $result) {
  $output = '';
  for ($i = 0; $i++, $i <= $places;) {
    if ($result['add'] != 0) {
      $output .= theme('versioncontrol_diffstat_addition');
      $result['add']--;
      continue;
    }
    if ($result['remove'] != 0) {
      $output .= theme('versioncontrol_diffstat_removal');
      $result['remove']--;
      continue;
    }
    $output .= theme('versioncontrol_diffstat_no_op');
  }
  return '<span class="versioncontrol-diffstat">'. $output .'</span>';
}

function theme_versioncontrol_diffstat_addition() {
  return '<span class="plus">+</span>';
}

function theme_versioncontrol_diffstat_removal() {
  return '<span class="minus">-</span>';
}

function theme_versioncontrol_diffstat_no_op() {
  return '<span class="no-op"> </span>';
}
