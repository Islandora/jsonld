<?php

namespace Drupal\jsonld\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Config form for the module.
 *
 * @package Drupal\jsonld\Form
 */
class JsonLdSettingsForm extends ConfigFormBase {

  const CONFIG_NAME = 'jsonld.settings';

  const REMOVE_JSONLD_FORMAT = 'remove_jsonld_format';

  const RDF_NAMESPACES = 'rdf_namespaces';

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      self::CONFIG_NAME,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'jsonld_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(self::CONFIG_NAME);
    $mappings_from_hook = rdf_get_namespaces();
    $form = [
      self::REMOVE_JSONLD_FORMAT => [
        '#type' => 'checkbox',
        '#title' => $this->t('Remove jsonld parameter from @ids'),
        '#description' => $this->t('This will alter any @id parameters to remove "?_format=jsonld"'),
        '#default_value' => $config->get(self::REMOVE_JSONLD_FORMAT) ? $config->get(self::REMOVE_JSONLD_FORMAT) : FALSE,
      ],
    ];

    $rdf_namespaces = '';
    foreach ($config->get('rdf_namespaces') as $namespace) {
      if (isset($mappings_from_hook[$namespace['prefix']])) {
        unset($mappings_from_hook[$namespace['prefix']]);
      }
      $rdf_namespaces .= $namespace['prefix'] . '|' . $namespace['namespace'] . "\n";
    }
    $mapping_string = '';
    foreach ($mappings_from_hook as $pref => $nspace) {
      $mapping_string .= "$pref|$nspace \n";
    }
    $form[self::RDF_NAMESPACES] = [
      '#type' => 'textarea',
      '#title' => $this->t('Additional RDF Namespaces'),
      '#rows' => 10,
      '#default_value' => $rdf_namespaces,
      '#description' => $this->t("Enter pipe-separated prefixes and namespaces e.g.<br /><strong>dcterms|http://purl.org/dc/terms/</strong>"),
    ];
    $form['existing'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Existing RDF Namespaces from modules'),
      '#rows' => count($mappings_from_hook),
      '#value' => $mapping_string,
      '#attributes' => ['readonly' => 'readonly'],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Validate RDF Namespaces.
    // First we stash a copy of the existing prefixes so we can find additions.
    $previous_prefixes = [];
    foreach ($this->config(self::CONFIG_NAME)->get('rdf_namespaces') as $namespace) {
      $previous_prefixes[] = $namespace['prefix'];
    }
    $new_prefixes = [];

    foreach (preg_split("/[\r\n]+/", $form_state->getValue(self::RDF_NAMESPACES)) as $line) {
      if (empty($line)) {
        continue;
      }
      $namespace = explode("|", trim($line));
      if (empty($namespace[0]) || empty($namespace[1])) {
        $form_state->setErrorByName(
          self::RDF_NAMESPACES,
          $this->t("RDF Namespace form is malformed on line '@line'",
            ['@line' => trim($line)]
          )
        );
        continue;
      }
      if (!in_array($namespace[0], $previous_prefixes)) {
        $new_prefixes[] = $namespace[0];
      }
    }

    // Check to make sure new prefixes aren't already in use.
    $all_namespaces = rdf_get_namespaces();
    foreach ($new_prefixes as $prefix) {
      if (in_array($prefix, array_keys($all_namespaces))) {
        $form_state->setErrorByName(
          self::RDF_NAMESPACES,
          $this->t("RDF namespace prefix '@prefix' is already mapped by a module to '@namespace'",
            ['@prefix' => $prefix, '@namespace' => $all_namespaces[$prefix]]
          )
        );
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable(self::CONFIG_NAME);

    $namespaces_array = [];
    foreach (preg_split("/[\r\n]+/", $form_state->getValue(self::RDF_NAMESPACES)) as $line) {
      if (empty($line)) {
        continue;
      }
      $namespace = explode("|", trim($line));
      if (!empty($namespace[0]) && !empty($namespace[1])) {
        $namespaces_array[] = [
          'prefix' => trim($namespace[0]),
          'namespace' => trim($namespace[1]),
        ];
      }
    }

    $config
      ->set(self::REMOVE_JSONLD_FORMAT, $form_state->getValue(self::REMOVE_JSONLD_FORMAT))
      ->set(self::RDF_NAMESPACES, $namespaces_array)
      ->save();
    parent::submitForm($form, $form_state);
  }

}
