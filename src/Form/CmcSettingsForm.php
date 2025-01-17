<?php

declare(strict_types=1);

namespace Drupal\cmc\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\ConfigTarget;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure CMC settings.
 */
class CmcSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cmc_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['cmc.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('cmc.settings');

    $form['operation_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Operation mode'),
      '#description' => $this->t('<b>Display errors:</b> Will inject missing tags at the top of each failing page.<br><b>Strict:</b> Will throw an exception when there are missing cache tags on a page.'),
      '#options' => [
        'disabled' => $this->t('Disabled'),
        'errors' => $this->t('Display errors'),
        'strict' => $this->t('Strict'),
      ],
      '#config_target' => 'cmc.settings:operation_mode',
    ];

    $form['skip_admin'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Skip admin pages'),
      '#description' => $this->t('When enabled, cache tags will only be checked on pages using the default theme (front-end). When disabled, admin pages will be checked as well.'),
      '#config_target' => 'cmc.settings:skip_admin',
    ];

    $form['skip_urls'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Skip URLs'),
      '#description' => $this->t("A comma or new-line separated list of relative URLs that should not be checked."),
      '#default_value' => implode("\r\n", $config->get('skip_urls')),
      '#config_target' => new ConfigTarget(
        'cmc.settings',
        'skip_urls',
        // Converts config value to a form value.
        fn($value) => implode("\r\n", $value),
        // Converts form value to a config value.
        fn($value) => array_map('trim', explode("\r\n", trim($value))),
      ),
    ];

    return parent::buildForm($form, $form_state);
  }

}
