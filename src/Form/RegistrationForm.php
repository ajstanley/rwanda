<?php

namespace Drupal\rwanda\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form to create potential registrants.
 */
class RegistrationForm extends FormBase {

  /**
   * Country Service.
   *
   * @var \Drupal\countries_field\Controller
   */
  private $serviceCountries;

  /**
   * {@inheritdoc}
   */
  public function __construct(\Controller $serviceCountries) {
    $this->serviceCountries = $serviceCountries;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('countries_field.countries'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'registration_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, Node $node = NULL) {
    if ($node) {
      $form_state->set('nid', $node->id());
    }

    $form['registrants'] = [
      '#type' => 'table',
      '#title' => $this->t('registrants'),
      '#description' => $this->t('Potential system registrants'),
      '#weight' => '0',
    ];
    $form['registrants'][0]['first_name'] = [
      '#type' => 'textfield',
      '#title' => t('First Name'),
      '#default_value' => $node ? $node->get('field_first_name')->value : '',
    ];
    $form['registrants'][0]['country'] = [
      '#type' => 'select',
      '#options' => $this->serviceCountries->getCountriesData(),
      '#title' => t('Nationality'),
      '#default_value' => $node ? $node->get('field_country')->value : 'rw',
    ];
    $form['registrants'][0]['telephone'] = [
      '#type' => 'tel',
      '#title' => t('Telephone'),
      '#default_value' => $node ? $node->get('field_telephone')->value : '',
    ];
    $form['registrants'][1]['last_name'] = [
      '#title' => t('Last Name'),
      '#type' => 'textfield',
      '#default_value' => $node ? $node->get('field_last_name')->value : '',
    ];
    $form['registrants'][1]['profession'] = [
      '#type' => 'textfield',
      '#title' => t('Profession'),
      '#default_value' => $node ? $node->get('field_profession')->value : '',
    ];
    $form['registrants'][1]['email'] = [
      '#type' => 'email',
      '#title' => t('EMail'),
      '#default_value' => $node ? $node->get('field_email')->value : '',
    ];
    $ranges = [
      '20' => ('20 - 29'),
      '30' => ('30 - 39'),
      '40' => ('40 - 49'),
      '50' => ('50 - 59'),
      '60' => ('60 - 69'),
      '70' => ('70 - 79'),

    ];
    $form['registrants'][2]['age_range'] = [
      '#type' => 'select',
      "#options" => $ranges,
      '#title' => t('Age Range'),
      '#default_value' => $node ? $node->get('field_age_range')->value : '',
    ];
    $form['registrants'][2]['email_type'] = [
      '#title' => t('EMail type'),
      '#type' => 'select',
      '#options' => [
        'professional' => 'Professional',
        'personal' => 'Personal',
      ],
      '#default_value' => $node ? $node->get('field_email_type')->value : '',
    ];
    $form['registrants'][2]['upload'] = [
      '#title' => t('Upload documents'),
      '#type' => 'managed_file',
      '#upload_location' => 'public://registrant_doc/',
      '#upload_validators' => [
        'file_validate_extensions' => ['txt pdf docx'],
      ],
      '#default_value' => $node ? [$node->get('field_upload')->target_id] : '',

    ];
    $form['registrants'][3]['gender'] = [
      '#title' => t('Gender'),
      '#type' => 'select',
      '#options' => [
        'male' => 'Male',
        'female' => 'Female',
        'other' => 'Other',
      ],
      '#default_value' => $node ? $node->get('field_gender')->value : '',
    ];
    $form['registrants'][3]['access_reason'] = [
      '#title' => t('Reason For Access'),
      '#type' => 'textfield',
      '#default_value' => $node ? $node->get('field_access_reason')->value : '',

    ];
    $form['registrants'][3]['additional_comments'] = [
      '#title' => t('Additional Comments'),
      '#type' => 'textfield',
      '#default_value' => $node ? $node->get('field_additional_comments')->value : '',

    ];
    $form['registrants'][4]['approval_status'] = [
      '#title' => t('Approval Status'),
      '#type' => 'select',
      '#options' => [
        'pending' => t('Pending'),
        'approved' => t('Approved'),
        'incomplete' => t('Incomplete'),
        'rejected' => t('Rejected'),
      ],
      '#default_value' => $node ? $node->get('field_approval_status')->value : '',
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
    $all_fields = $form_state->getValue('registrants');
    $fields['type'] = 'registrant';
    $fields['title'] = $all_fields[0]['first_name'] . " " . $all_fields[1]['last_name'];
    foreach ($all_fields as $field_group) {
      foreach ($field_group as $key => $value) {
        if ($key == 'upload' && $value) {
          $file = File::load($value[0]);
          $file->setPermanent();
          $file->save();
          $fields['field_upload'] = $file->id();
          continue;
        }
        $fields["field_{$key}"] = $value;
      }
    }

    if ($form_state->get('nid')) {
      $node = Node::load($form_state->get('nid'));
      foreach ($fields as $property => $value) {
        $node->set($property, $value);
      }
    }
    else {
      $node = Node::create($fields);
    }
    $node->save();
  }

}
