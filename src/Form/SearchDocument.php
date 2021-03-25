<?php

namespace Drupal\rwanda\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class SearchDocument.
 */
class SearchDocument extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'search_document';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attached']['library'][] = 'rwanda/rwanda_search';
    $form['accused'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Accused full names'),
      '#required' => TRUE,
      '#prefix' => '<div class = "search_element">',
      '#suffix' => '</div>',
    ];
    $form['district'] = [
      '#type' => 'select',
      '#title' => $this->t('District'),
      '#options' => [
        'bugsera' => 'Bugsera',
        'burera' => 'Burera',
        'gakenke' => 'Gakenke'
      ],
      '#description' => $this->t('Enter District.'),
      '#prefix' => '<div class = "search_element">',
      '#suffix' => '</div>',
    ];
    $form['sector'] = [
      '#type' => 'select',
      '#title' => $this->t('Sector'),
      '#options' => [
        'nemba' => 'Nemba',
        'busengo' => 'Busengo',
        'coko' => 'Coko'
      ],
      '#description' => $this->t('Enter Sector.'),
      '#prefix' => '<div class = "search_element">',
      '#suffix' => '</div>',
    ];
    $form['cell'] = [
      '#type' => 'select',
      '#title' => $this->t('Cell'),
      '#options' => [
        'nyanbyonbo' => 'Nyanbyonbo',
        'bungwe' => 'Bungwe',
        'gatenga' => 'Gatenga'
      ],
      '#description' => $this->t('Enter Cell.'),
      '#prefix' => '<div class = "search_element">',
      '#suffix' => '</div>',
    ];
    $form['village'] = [
      '#type' => 'select',
      '#title' => $this->t('Village'),
      '#options' => [
        'nyanbyonbo' => 'Nyanbyonbo',
        'bungwe' => 'Bungwe',
        'gatenga' => 'Gatenga'
      ],
      '#description' => $this->t('Enter Village.'),
      '#prefix' => '<div class = "search_element">',
      '#suffix' => '</div>',
    ];
    $form['court'] = [
      '#type' => 'select',
      '#title' => $this->t('Court'),
      '#options' => [
        'nyanbyonbo' => 'Nyanbyonbo',
        'bungwe' => 'Bungwe',
        'gatenga' => 'Gatenga'
      ],
      '#description' => $this->t('Enter Court.'),
      '#prefix' => '<div class = "search_element">',
      '#suffix' => '</div>',
    ];
    $form['doctype'] = [
      '#type' => 'select',
      '#title' => $this->t('Document Type'),
      '#options' => [
        'charge' => 'Charge Sheet',
        'warrant' => 'Warrant of Arrest',
        'release' => 'Warrant of Immediate Release'
      ],
      '#description' => $this->t('Enter Document type.'),
      '#prefix' => '<div class = "search_element">',
      '#suffix' => '</div>',
    ];
    $form['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#options' => [
        'pending' => 'Pending',
        'complete' => 'Complete',
        'incomplete' => 'Incomplete',
      ],
      '#description' => $this->t('Choose Status'),
      '#prefix' => '<div class = "search_element">',
      '#suffix' => '</div>',
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
    // Display result.
    foreach ($form_state->getValues() as $key => $value) {
      \Drupal::messenger()->addMessage($key . ': ' . ($key === 'text_format'?$value['value']:$value));
    }
  }

}
