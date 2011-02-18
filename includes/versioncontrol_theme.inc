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
  drupal_add_css(drupal_get_path('module', 'versioncontrol') . '/versioncontrol.css', 'module');
  for ($i = 0; $i++, $i <= $places;) {
    if ($result['add'] != 0) {
      $output .= '<span class="plus">+</span>';
      $result['add']--;
      continue;
    }
    if ($result['remove'] != 0) {
      $output .= '<span class="minus">-</span>';
      $result['remove']--;
      continue;
    }
    $output .= '<span class="no-op"> </span>';
  }
  return '<span class="versioncontrol-diffstat clear-block">'. $output .'</span>';
}
