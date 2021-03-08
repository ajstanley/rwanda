<?php

namespace Drupal\rwanda\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;


/**
 * Class CreatePersons.
 */
class CreatePersons extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'create_persons';
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
    $field_map = [
      'GABO' => 'Male',
      'GORE' => "Female",
    ];
    $values = $form_state->getValues();
    $fid = $values['upload_csv'][0];
    $file = File::load($fid);
    $rows = array_map('str_getcsv', file($file->getFileUri()));
    $file->delete();
    $headers = array_shift($rows);
    foreach ($rows as $row) {
      $csv[] = array_combine($headers, $row);
    }
    $csv = \array_slice($csv, 0, 10);  //limit results for testing.
    foreach ($csv as $candidate) {
      $current_node_id = $this->checkNode($candidate['ACCUSED']);
      if ($current_node_id) {
        $current_node = Node::load($current_node_id);
        $DOB = $current_node->get('field_dob')->value;
        if ($DOB == $candidate['DOB']) {
          continue;
        }
      }
      $accused = [];
      // get mother target_id
      if ($candidate['MOTHER']) {
        $mother_nid = $this->checkNode($candidate['MOTHER']);
        if (!$mother_nid) {
          $contents = [
            'title' => $candidate['MOTHER'],
            'field_gender' => 'Female',
          ];
          $mother_nid = $this->buildPerson($contents);
          $accused['field_mother']['target_id'] = $mother_nid;
        }
      }
      // get father target_id
      if ($candidate['FATHER']) {
        $father_nid = $this->checkNode($candidate['FATHER']);
        if (!$father_nid) {
          $contents = [
            'title' => $candidate['FATHER'],
            'field_gender' => 'Male',
          ];
          $father_nid = $this->buildPerson($contents);
          $accused['field_father']['target_id'] = $father_nid;
        }
      }
      // build accused person object
      $accused['title'] = $candidate['ACCUSED'];
      $accused['field_gender'] = $field_map[$candidate['GENDER']];
      $accused['field_occupation'] = $candidate['PROFESSION1994'];
      $accused['field_dob'] = $candidate['DOB'];
      $accused_nid = $this->buildPerson($accused);
    }
    return;
  }

  public function buildPerson($contents) {
    $contents['field_full_name'] = $contents['title'];
    $contents['field_verified'] = FALSE;
    $contents['type'] = 'person';
    $node = Node::create($contents);
    $node->save();
    return $node->id();
  }

  public function checkNode($name) {
    $retval = FALSE;
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'person')
      ->condition('field_full_name', $name);
    $nids = $query->execute();
    if ($nids) {
      $vals = \array_values($nids);
      $retval = $vals[0];
    }
    return $retval;
  }

  public function normalizeString($string) {
    return \ucwords(\strtolower($string));
  }

}
