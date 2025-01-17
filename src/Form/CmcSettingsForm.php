<?php

declare(strict_types=1);

namespace Drupal\cmc\Form;

use Drupal\Core\Form\ConfigFormBase;
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
      '#default_value' => $config->get('operation_mode'),
    ];

    $form['front_end_only'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Check front-end only'),
      '#description' => $this->t('When enabled, cache tags will only be checked on pages using the default theme (front-end). When disabled, admin pages will be checked as well.'),
      '#default_value' => $config->get('front_end_only'),
    ];

    $form['skip_urls'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Skip URLs'),
      '#description' => $this->t("A comma or new-line separated list of relative URLs that should not be checked."),
      '#default_value' => implode("\r\n", $config->get('skip_urls')),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $skip_urls = preg_replace('/[\s, ]/', ',', $form_state->getValue('skip_urls'));
    $skip_urls = array_values(array_filter(explode(',', $skip_urls)));

    $this->config('cmc.settings')
      ->set('operation_mode', $form_state->getValue('operation_mode'))
      ->set('front_end_only', $form_state->getValue('front_end_only'))
      ->set('skip_urls', $skip_urls)
      ->save();

    parent::submitForm($form, $form_state);
  }

}
