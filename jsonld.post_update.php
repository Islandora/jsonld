<?php

/**
 * @file
 * Post-update hook implementations.
 */

/**
 * Ensure `rdf_namespaces` exists.
 */
function jsonld_post_update_ensure_rdf_namespaces_exists() : void {
  $config = \Drupal::configFactory()->getEditable('jsonld.settings');

  if ($config->get('rdf_namespaces') === NULL) {
    $config->set('rdf_namespaces', [])->save();
  }
}
