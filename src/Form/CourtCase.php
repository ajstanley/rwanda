<?php

namespace Drupal\rwanda\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form to build Court Case.
 */
class CourtCase extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Query Factory.
   *
   * @var \Drupal\Core\Entity\Query\QueryFactory
   */
  protected $entityQuery;

  /**
   * The currently selected District.
   *
   * @var string
   */
  protected $currentDistrict;

  /**
   * THe currently selected Sector.
   *
   * @var string
   */
  protected $currentSector;

  /**
   * The currently selected General Assembly.
   *
   * @var string
   */
  protected $currentAssembly;

  /**
   * Class constructor.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, QueryFactory $entityQuery) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityQuery = $entityQuery;
    $this->currentDistrict = '';
    $this->current_sector = '';
    $this->current_assembly = '';
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    return new static(
    // Load the service required to construct this class.
      $container->get('entity_type.manager'),
      $container->get('entity.query')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'court_case';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Crimes.
    $vid = 'crimes';
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')
      ->loadTree($vid);
    foreach ($terms as $term) {
      $crime_data[$term->tid] = $term->name;
    }
    $crime_data['other'] = $this->t('Other');

    // District.
    $vid = 'district';
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')
      ->loadTree($vid);
    foreach ($terms as $term) {
      $term_data[$term->tid] = $term->name;
    }
    $keys = \array_keys($term_data);
    $district = $form_state->getValue('district');
    $this->currentDistrict = $district ? $district : $keys[0];
    $sector_ids = $this->entityQuery->get('taxonomy_term')
      ->condition('field_district', $keys[0])
      ->execute();
    $currentSector = $form_state->getValue('sector');
    $this->current_sector = $currentSector ? $currentSector : reset($sector_ids);

    // Assembly.
    $assembly_ids = $this->entityQuery->get('taxonomy_term')
      ->condition('field_sector', reset($sector_ids))
      ->execute();

    $currentAssembly = $form_state->getValue('general_assembly');
    $this->current_assembly = $currentAssembly ? $currentAssembly : reset($assembly_ids);

    $court_options = $this->getCourtOptions($form_state);
    $date_format = 'Y-m-d H:i';
    $form['courts'] = [
      '#type' => 'fieldset',
      '#prefix' => '<div id="courts_wrapper">',
      '#suffix' => '</div>',
    ];
    $form['courts']['district'] = [
      '#type' => 'select',
      '#options' => $term_data,
      '#prefix' => '<div id="district_wrapper" class = "court_selector">',
      '#suffix' => '</div>',
      '#title' => $this->t('District'),
      '#description' => $this->t('Rwandan Court District'),
      // '#default_value' => $keys[0],
      '#ajax' => [
        'callback' => '::changeCourtsOptionsAjax',
        'wrapper' => 'courts_wrapper',
      ],

    ];

    $form['courts']['sector'] = [
      '#type' => 'select',
      '#options' => $court_options['sector'],
      '#prefix' => '<div id="sector_wrapper" class = "court_selector">',
      '#suffix' => '</div>',
      '#title' => $this->t('Sector'),
      '#description' => $this->t('Rwandan Court Sector'),
      '#ajax' => [
        'callback' => '::changeCourtsOptionsAjax',
        'wrapper' => 'courts_wrapper',
      ],

    ];

    $form['courts']['general_assembly'] = [
      '#type' => 'select',
      '#options' => $court_options['general_assembly'],
      '#prefix' => '<div id="assembly_wrapper" class = "court_selector">',
      '#suffix' => '</div>',
      '#title' => $this->t('General Assembly'),
      '#description' => $this->t('Rwandan General Assembly'),
      '#ajax' => [
        'callback' => '::changeCourtsOptionsAjax',
        'wrapper' => 'courts_wrapper',
      ],
    ];

    $form['courts']['court_of_cell'] = [
      '#type' => 'select',
      '#options' => $court_options['court_of_cell'],
      '#prefix' => '<div id="cell_wrapper" class = "court_selector clearBoth">',
      '#suffix' => '</div>',
      '#title' => $this->t('Court of Cell'),
      '#description' => $this->t('Rwandan Court of Cell'),
      '#states' => [
        'enabled' => [
          ':input[name="level"]' => ['value' => 'cell'],
        ],
      ],
    ];

    $form['courts']['court_of_sector'] = [
      '#type' => 'select',
      '#options' => $court_options['court_of_sector'],
      '#prefix' => '<div id="court_of_sector_wrapper" class = "court_selector">',
      '#suffix' => '</div>',
      '#title' => $this->t('Court of Sector'),
      '#description' => $this->t('Rwandan Court of Sector'),
      '#disabled' => TRUE,
      '#states' => [
        'enabled' => [
          ':input[name="level"]' => ['value' => 'sector'],
        ],
      ],
    ];

    $form['courts']['court_of_appeal'] = [
      '#type' => 'select',
      '#options' => $court_options['court_of_appeal'],
      '#prefix' => '<div id="court_of_appeal_wrapper" class = "court_selector">',
      '#suffix' => '</div>',
      '#title' => $this->t('Court of Appeal'),
      '#description' => $this->t('Rwandan Court of Appeal'),
      '#disabled' => TRUE,
      '#states' => [
        'enabled' => [
          ':input[name="level"]' => ['value' => 'appeal'],
        ],
      ],
    ];
    $form['level'] = [
      '#type' => 'radios',
      '#options' => [
        'cell' => $this->t('Cell'),
        'sector' => $this->t('Sector'),
        'appeal' => $this->t('Appeal'),
      ],
      '#title' => $this->t('Court Level'),
      '#default_value' => 'cell',
      '#prefix' => '<div class = "clearBoth">',
      '#suffix' => '</div>',
    ];
    $form['trial_stage'] = [
      '#type' => 'select',
      '#prefix' => '<div class = "court_selector">',
      '#suffix' => '</div>',
      '#options' => [
        'public' => $this->t('Public'),
        'absentia' => $this->t('In Absentia'),
        'restricted' => $this->t('Restricted'),
      ],
      '#title' => $this->t('Trial Stage'),
      '#default_value' => 'public',
    ];
    $form['trial_level'] = [
      '#type' => 'select',
      '#prefix' => '<div class = "court_selector">',
      '#suffix' => '</div>',
      '#options' => [
        'judgement_rendering' => $this->t('Judgement rendering'),
        'opposition' => $this->t('In Absentia'),
        'review' => $this->t('Review'),
        'appeal' => $this->t('Appeal'),
      ],
      '#title' => $this->t('Trial Level'),
      '#default_value' => 'judgement_rendering',
    ];
    $form['trial_location'] = [
      '#type' => 'textfield',
      '#prefix' => '<div class = "court_selector">',
      '#suffix' => '</div>',
      '#title' => $this->t('Trial Location'),
    ];
    $form['trial_date'] = [
      '#type' => 'date',
      '#prefix' => '<div class = "court_selector">',
      '#suffix' => '</div>',
      '#title' => $this->t('Trial Date'),
      '#default_value' => [
        'year' => 2020,
        'month' => 2,
        'day' => 15,
      ],
    ];
    $form['accused'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'node',
      '#title' => $this->t('Accused'),
      '#description' => $this->t('Select accused.'),
      '#selection_settings' => [
        'target_bundles' => ['person'],
      ],
      '#autocreate' => [
        'bundle' => 'person',
      ],
      '#prefix' => '<div class = "clearBoth participants">',
      '#suffix' => '</div>',
    ];
    $form['plaintiff'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'node',
      '#title' => $this->t('Plaintiff'),
      '#description' => $this->t('Select plaintiff.'),
      '#selection_settings' => [
        'target_bundles' => ['person'],
      ],
      '#autocreate' => [
        'bundle' => 'person',
      ],
      '#prefix' => '<div class = "participants">',
      '#suffix' => '</div>',
    ];
    $form['crime'] = [
      '#type' => 'select',
      '#title' => $this->t('Crime'),
      '#options' => $crime_data,
      '#prefix' => '<div class = "court_selector">',
      '#suffix' => '</div>',
    ];
    $form['new_crime'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Unlisted Crime Type'),
      '#states' => [
        'enabled' => [
          ':input[name="crime"]' => ['value' => 'other'],
        ],
      ],
      '#prefix' => '<div class = "court_selector">',
      '#suffix' => '</div>',
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#prefix' => '<div class = "clearBoth">',
      '#suffix' => '</div>',
    ];
    $form['#theme'] = 'rwanda_court';

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
      \Drupal::messenger()
        ->addMessage($key . ': ' . ($key === 'text_format' ? $value['value'] : $value));
    }
  }

  /**
   * Ajax callback to change options for sector.
   */
  public function changeCourtsOptionsAjax(array &$form, FormStateInterface $form_state) {
    return $form['courts'];
  }

  /**
   * Get options for Courts.
   */
  public function getCourtOptions(FormStateInterface $form_state) {
    $configs = $this->buildOptionsArray();
    foreach ($configs as $key => $config) {
      $parent = $form_state->getValue($config['parent']);
      $options = [];
      if (isset($all_options[$config['parent']])) {
        $candidates = \array_keys($all_options[$config['parent']]);
        if (!in_array($config['default'], $candidates)) {
          $new_vals = array_keys($all_options[$config['parent']]);
          $config['default'] = $new_vals[0];
          $parent = $new_vals[0];
        }
      }
      if (!$parent) {
        $parent = $config['default'];
      }
      $ids = $this->entityQuery->get('taxonomy_term')
        ->condition($config['parent_field'], $parent)
        ->condition('vid', $config['vid'])
        ->execute();
      foreach ($ids as $id) {
        $term = Term::load($id);
        $options[$term->id()] = $term->getName();
      }
      $all_options[$key] = $options;
    }
    $parent = $form_state->getValue($config['parent']);
    $options = [];
    if (!$parent) {
      $parent = $config['default'];
    }
    $ids = $this->entityQuery->get('taxonomy_term')
      ->condition($config['parent_field'], $parent)
      ->condition('vid', $config['vid'])
      ->execute();
    foreach ($ids as $id) {
      $term = Term::load($id);
      $options[$term->id()] = $term->getName();
    }
    $general_term = Term::load($this->current_assembly);
    $parent_id = $general_term->get('field_sector');
    $parent_term = Term::load($parent_id->getValue()[0]['target_id']);
    $id = $parent_term->id();
    return $all_options;
  }

  /**
   * Build full options for form elements.
   *
   * @return array
   *   Full option array.
   */
  public function buildOptionsArray() {
    return [
      'sector' => [
        'parent' => 'district',
        'parent_field' => 'field_district',
        'default' => $this->currentDistrict,
        'vid' => 'sector',
      ],
      'general_assembly' => [
        'parent' => 'sector',
        'parent_field' => 'field_sector',
        'default' => $this->current_sector,
        'vid' => 'general_assembly',
      ],
      'court_of_cell' => [
        'parent' => 'general_assembly',
        'parent_field' => 'field_general_assembly',
        'default' => $this->current_assembly,
        'vid' => 'court_of_cell',
      ],
      'court_of_sector' => [
        'parent' => 'general_assembly',
        'parent_field' => 'field_general_assembly',
        'default' => $this->current_assembly,
        'vid' => 'court_of_sector',
      ],
      'court_of_appeal' => [
        'parent' => 'general_assembly',
        'parent_field' => 'field_general_assembly',
        'default' => $this->current_assembly,
        'vid' => 'court_of_appeal',
      ],
    ];
  }

}