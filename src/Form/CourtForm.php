<?php

namespace Drupal\rwanda\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\taxonomy\Entity\Term;

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
    $count = 0;
    $values = $form_state->getValues();
    $fid = $values['upload_csv'][0];
    $file = File::load($fid);
    $filename = $file->label();
    $rows = array_map('str_getcsv', file($file->getFileUri()));

    array_shift($rows);
    $current_vals = [];
    $empty = 0;
    $preexists = 0;
    $updated = 0;
    $parent_term = NULL;
    $parents = [
      'district' => NULL,
      'sector' => 'district',
      'general_assembly' => 'sector',
      'court_of_sector' => 'general_assembly',
      'court_of_appeal' => 'general_assembly',
      'court_of_cell' => 'general_assembly',
    ];
    $headers = \array_keys($parents);
    $csv = [];
    foreach ($rows as $row) {
      $row = \array_slice($row, 0, 6);
      $csv[] = array_combine($headers, $row);
      if ($row[5] == '') {
        $empty++;
      }
    }
    $file->delete();
    foreach ($csv as $data) {
      foreach ($data as $key => $value) {
        $value = trim($value);
        if (!$value) {
          continue;
        }
        foreach ($headers as $header) {
          $replacement = trim($data[$header]);

          if ($replacement) {
            $current_vals[$header] = $replacement;
          }
        }
        $key = $this->cleanString($key);
        $value = $this->cleanString($value);
        $vocab = \strtolower($key);
        $candidate = \strtolower($value);
        $candidate = \ucfirst($candidate);
        $components = [
          'name' => $candidate,
          'vid' => \strtolower($key),
        ];

        if ($parents[$vocab]) {
          $name = $this->cleanString($current_vals[$parents[$vocab]]);
          $name = \strtolower($name);
          $name = \ucfirst($name);
          $parent_terms = \Drupal::entityTypeManager()
            ->getStorage('taxonomy_term')
            ->loadByProperties(['name' => $name, 'vid' => $parents[$vocab]]);
          $parent_term = reset($parent_terms);
        }
        // Check to see if term exists already in this vocabulary.
        $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
          ->loadByProperties($components);
        $term = reset($terms);
        if ($term && $parent_term) {
          $field_name = "field_{$parents[$vocab]}";
          $term->$field_name->setValue($parent_term->id());
          $term->save();
          $updated++;
        }
        if (!$term) {
          if ($parent_term) {
            $components["field_{$parents[$vocab]}"] = $parent_term->id();
          }
          $term = Term::create($components)->save();
          $count++;
        }
      }
    }
    \Drupal::messenger()
      ->addStatus("$count terms have been added from $filename");
    \Drupal::messenger()->addStatus("$empty empty rows.");
    \Drupal::messenger()->addStatus("$updated terms have been updated.");

  }

  private function cleanString($string) {
    $string = trim($string);
    $string = preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $string);
    return trim($string);
  }
}
