<?php

namespace Drupal\rwanda\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Class CourtForm.
 */
class CourtForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'court_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['upload_csv'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload CSV'),
      '#description' => $this->t('Load field to be added to taxonomies'),
      '#upload_location' => 'public://csvs',
      '#upload_validators' => [
        'file_validate_extensions' => ['csv'],
      ],
      '#weight' => '0',
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    foreach ($form_state->getValues() as $key => $value) {
      // @TODO: Validate fields.
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $fid = $values['upload_csv'][0];
    $file = File::load($fid);
    $rows = array_map('str_getcsv', file($file->getFileUri()));

    $headers = array_shift($rows);
    $csv = [];
    foreach ($headers as $header) {
      $header = \strtolower($header);
      $header = $this->cleanString($header);
      $vocab = Vocabulary::load($header);
      if (!$vocab) {
        $this->buildVocab($header);
      }
    }
    foreach ($rows as $row) {
      $csv[] = array_combine($headers, $row);
    }
    $file->delete();
    foreach ($csv as $data) {
      foreach ($data as $key => $value) {
        if (!$value) {
          continue;
        }
        $key = $this->cleanString($key);
        $value = $this->cleanString($value);
        $vocab = \strtolower($key);
        $candidate = \strtolower($value);
        $candidate = \ucfirst($candidate);
        // Check to see if term exists already in this vocabulary.
        $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
          ->loadByProperties(['name' => $candidate, 'vid' => $vocab]);
        $term = reset($terms);
        if (!$term && $candidate) {
          $term = Term::create([
            'name' => $candidate,
            'vid' => \strtolower($key),
          ])->save();
        }

      }

    }
  }

  private function buildVocab($vid) {
    $vid = preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $vid);
    $vid = $this->cleanString($vid);
    $name = \ucfirst($vid);
    $name = trim($name);
    $vocabulary = Vocabulary::create(
      [
        'vid' => $vid,
        'description' => 'Rwandan Courts',
        'name' => $name,
      ]
    )->save();
  }
  private function cleanString($string) {
    $string = preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $string);
    return trim($string);
  }
}
