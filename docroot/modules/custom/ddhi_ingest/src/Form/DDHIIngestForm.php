<?php

namespace Drupal\ddhi_ingest\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ddhi_ingest\Handlers\DDHIIngestHandler;

class DDHIIngestForm extends FormBase {

  public function getFormId()
  {
    return 'ddhi_ingest_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state)
  {
    //$form = parent::buildForm($form, $form_state);
    $DDHIIngestConfig = $this->config('ddhi_ingest.settings');
    $ingestHandler = new DDHIIngestHandler();

    // GitHub Forms

    $form['source_type_github'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Step One: Retrieve and preflight files from GitHub.'),
      '#description' => $this->t('Retrieve DDHI interviews from GitHub repository and stages them for import.'),
    ];

    $form['source_type'] = [
      '#type' => 'hidden',
      '#value' => DDHI_SOURCE_OPTION_GITHUB,
    ];

    $form['source_type_github']['github_account'] = [
      '#type' => 'textfield',
      '#title' => $this->t('DDHI TEI Repository Account'),
      '#default_value' => !empty($form_state->getValue('github_account')) ? $form_state->getValue('github_account') : $DDHIIngestConfig->get('github_account'),
      '#description' => $this->t('The user/account name associated with the repository.'),
    ];

    $form['source_type_github']['github_repository'] = [
      '#type' => 'textfield',
      '#title' => $this->t('DDHI TEI Repository Name'),
      '#default_value' => !empty($form_state->getValue('github_repository')) ? $form_state->getValue('github_repository') : $DDHIIngestConfig->get('github_repository'),
      '#description' => $this->t('Name of the repository containing the DDHI TEI Interviews in DDHI File Layout Level 1 format.'),
    ];

    // dpm($form_state->getValue('github_branch'));

    $form['source_type_github']['github_branch'] = [
      '#type' => 'textfield',
      '#title' => $this->t('DDHI TEI Branch Name'),
      '#default_value' => !empty($form_state->getValue('github_branch')) ? $form_state->getValue('github_branch') : $DDHIIngestConfig->get('github_branch'),
      '#description' => $this->t('The repository branch to retrieve interviews from.'),
    ];

    $form['source_type_github']['stage'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Stage TEI Files'),
      '#name' => 'submit_stage',
      '#submit'=> ['::submitFormStage'],

    );


    $form['import_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Step Two: Import into Drupal'),
      '#description' => $this->t('Imports staged interviews.'),
    ];

    $form['import_fieldset']['staging_status_msg'] = [
      '#type' => 'markup',
      '#markup' => '<p>There are '. $ingestHandler->stagedInterviewCount() . ' interviews staged for import.</p>',
    ];

    $form['import_fieldset']['import'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Import Interviews'),
      '#name' => 'submit_import',
      '#submit'=> ['::submitFormImport'],
    );

    $form['import_fieldset']['rollback'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Rollback Interviews'),
      '#name' => 'submit_rollback',
      '#submit'=> ['::submitFormRollack'],
    );


    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    parent::validateForm($form, $form_state); // TODO: Change the autogenerated stub
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    // TODO: Implement submitForm() method.
  }

  public function submitFormStage(array &$form, FormStateInterface $form_state)
  {
    $ingestHandler = \Drupal::service('ddhi.ingest.handler')->createInstance($form_state->getValue('source_type'));
    $ingestHandler->setParameters($form_state->getValues());
    $ingestHandler->retrieveSource();
    $ingestHandler->stageSource();
    $ingestHandler->aggregate();

  }

  public function submitFormImport(array &$form, FormStateInterface $form_state) {
    $ingestHandler = \Drupal::service('ddhi.ingest.handler')->createInstance();
    $ingestHandler->setParameters($form_state->getValues());
    $ingestHandler->ingest(2); // Ingest at Level 2
  }

  public function submitFormRollack(array &$form, FormStateInterface $form_state) {
    $ingestHandler = \Drupal::service('ddhi.ingest.handler')->createInstance();
    $ingestHandler->setParameters($form_state->getValues());
    $ingestHandler->rollback();
  }

  public function getEditableConfigNames()
  {
    return [
      'ddhi_ingest.form'
    ];
  }
}
