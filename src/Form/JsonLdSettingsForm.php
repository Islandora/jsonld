<?php

namespace Drupal\jsonld\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class JsonLdSettingsForm.
 *
 * @package Drupal\jsonld\Form
 */
class JsonLdSettingsForm extends ConfigFormBase {

  const CONFIG_NAME = 'jsonld.settings';

  const REMOVE_JSONLD_FORMAT = 'remove_jsonld_format';

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
    $form = [
      self::REMOVE_JSONLD_FORMAT => [
        '#type' => 'checkbox',
        '#title' => $this->t('Remove jsonld parameter from @ids'),
        '#description' => $this->t('This will alter any @id parameters to remove "?_format=jsonld"'),
        '#default_value' => $config->get(self::REMOVE_JSONLD_FORMAT) ? $config->get(self::REMOVE_JSONLD_FORMAT) : FALSE,
      ],
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable(self::CONFIG_NAME);
    $config->set(self::REMOVE_JSONLD_FORMAT, $form_state->getValue(self::REMOVE_JSONLD_FORMAT))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
