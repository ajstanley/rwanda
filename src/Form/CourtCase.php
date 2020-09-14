<?php

namespace Drupal\rwanda\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
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
    $this->currentSector = '';
    $this->currentAssembly = '';
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
    $district = $form_state->getValue(['courts', 'district']);
    $this->currentDistrict = $district ? $district : $keys[0];
    $sector_ids = $this->entityQuery->get('taxonomy_term')
      ->condition('field_district', $keys[0])
      ->execute();
    $currentSector = $form_state->getValue(['courts', 'sector']);
    $this->currentSector = $currentSector ? $currentSector : reset($sector_ids);

    // Assembly.
    $assembly_ids = $this->entityQuery->get('taxonomy_term')
      ->condition('field_sector', reset($sector_ids))
      ->execute();

    $currentAssembly = $form_state->getValue(['courts', 'general_assembly']);
    $this->currentAssembly = $currentAssembly ? $currentAssembly : reset($assembly_ids);

    $court_options = $this->getCourtOptions($form_state);
    $form['#tree'] = TRUE;
    $form['box_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Box Number'),
      '#description' => $this->t('5 digits'),
      '#prefix' => '<div class = "court_selector">',
      '#suffix' => '</div>',
    ];
    $form['register_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Register'),
      '#prefix' => '<div class = "court_selector">',
      '#suffix' => '</div>',
    ];
    $form['courts'] = [
      '#type' => 'fieldset',
      '#prefix' => '<div id="courts_wrapper" class="clearBoth">',
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

    // Container for our repeating fields.
    if (!$form_state->get('num_witnesses')) {
      $form_state->set('num_witnesses', 1);
    }
    $form['witnesses'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Witnesses'),
      '#prefix' => '<div id ="witness-box" class = "clearBoth">',
      '#suffix' => '</div>',
    ];
    // Add our witnesses fields.
    for ($counter = 0; $counter < $form_state->get('num_witnesses'); $counter++) {
      $form['witnesses'][$counter]['witness_name'] = [
        '#type' => 'entity_autocomplete',
        '#target_type' => 'node',
        '#title' => $this->t('Witness Name'),
        '#selection_settings' => [
          'target_bundles' => ['person'],
        ],
        '#autocreate' => [
          'bundle' => 'person',
        ],
        '#prefix' => '<div class = "accomplice">',
        '#suffix' => '</div>',
      ];

      $form['witnesses'][$counter]['witness_type'] = [
        '#type' => 'select',
        '#title' => $this->t('Witness Type'),
        '#options' => [
          'defending' => $this->t('Defending'),
          'accusing' => $this->t('Accusing'),
        ],
        '#prefix' => '<div class = "accomplice">',
        '#suffix' => '</div>',
      ];

    }
    // Button to add more names.
    $form['witnesses']['add_witness'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add another witness'),
      '#attributes' => ['class' => ['rounded']],
      '#prefix' => '<div class = "clearBoth addSpace">',
      '#suffix' => '</div>',
    ];

    // Container for our repeating fields.
    if (!$form_state->get('num_accomplices')) {
      $form_state->set('num_accomplices', 1);
    }
    $form['accomplices'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Accomplices'),
      '#prefix' => '<div class = "clearBoth">',
      '#suffix' => '</div>',
    ];

    // Add our accomplices fields.
    for ($counter = 0; $counter < $form_state->get('num_accomplices'); $counter++) {
      $form['accomplices'][$counter]['accomplice_name'] = [
        '#type' => 'entity_autocomplete',
        '#target_type' => 'node',
        '#title' => $this->t('Accomplice Name @num', ['@num' => ($counter + 1)]),
        '#selection_settings' => [
          'target_bundles' => ['person'],
        ],
        '#autocreate' => [
          'bundle' => 'person',
        ],
        '#prefix' => '<div class = "accomplice">',
        '#suffix' => '</div>',
      ];
      $form['accomplices'][$counter]['accomplice_sentence'] = [
        '#type' => 'select',
        '#options' => [
          'convicted' => $this->t('Convicted'),
          'acquitted' => $this->t('Acquitted'),
          'pardoned' => $this->t('Pardoned'),
        ],
        '#title' => $this->t('Sentence @num', ['@num' => ($counter + 1)]),
        '#prefix' => '<div class = "accomplice">',
        '#suffix' => '</div>',
      ];
    }

    // Button to add more names.
    $form['accomplices']['addname'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add another accomplice'),
      '#attributes' => ['class' => ['rounded']],
      '#prefix' => '<div class = "clearBoth addSpace">',
      '#suffix' => '</div>',
    ];

    $form['properties'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Properties destroyed'),
      '#description' => $this->t('List of properties destroyed and their numbers. Example: 5 chairs, 2 cars, 1 house,etc…'),
    ];
    $form['decision'] = [
      '#type' => 'select',
      '#title' => $this->t('Court Decision'),
      '#description' => $this->t('If accused are found guilty or not.'),
      '#options' => [
        'guilty' => $this->t('Guilty'),
        'acquitted' => $this->t('Acquitted'),
      ],
      '#prefix' => '<div class = "court_selector clearBoth">',
      '#suffix' => '</div>',
    ];
    $form['outcome'] = [
      '#type' => 'select',
      '#title' => $this->t('Outcome'),
      '#options' => [
        'imprisonment' => $this->t("Imprisonment"),
        'restitution' => $this->t('Restitution'),
        'tig' => $this->t('TIG'),
        'pardoned' => $this->t('Pardoned'),
      ],
      '#states' => [
        'enabled' => [
          ':input[name="decision"]' => ['value' => 'guilty'],
        ],
      ],
      '#prefix' => '<div class = "court_selector">',
      '#suffix' => '</div>',
    ];

    $form['sentence'] = [
      '#type' => 'select',
      '#title' => $this->t('Sentence'),
      '#options' => [
        'life' => $this->t('Life Sentence'),
        '1_5' => $this->t('1 to 5  years'),
        '6_10' => $this->t('6 to 10 years'),
        '11_20' => $this->t('11 to 20 years'),
        '20' => $this->t('21 or more years'),
      ],
      '#states' => [
        'enabled' => [
          ':input[name="outcome"]' => ['value' => 'imprisonment'],
          ':input[name="decision"]' => ['value' => 'guilty'],
        ],
      ],
      '#prefix' => '<div class = "court_selector">',
      '#suffix' => '</div>',
    ];
    if (!$form_state->get('num_observers')) {
      $form_state->set('num_observers', 1);
    }
    $form['observers'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Observers'),
      '#prefix' => '<div class = "clearBoth">',
      '#suffix' => '</div>',
    ];
    // Add our witnesses fields.
    for ($counter = 0; $counter < $form_state->get('num_observers'); $counter++) {
      $form['observers'][$counter]['observer_name'] = [
        '#type' => 'entity_autocomplete',
        '#target_type' => 'node',
        '#title' => $this->t('Observer Name'),
        '#selection_settings' => [
          'target_bundles' => ['person'],
        ],
        '#autocreate' => [
          'bundle' => 'person',
        ],
        '#prefix' => '<div class = "accomplice clearBoth">',
        '#suffix' => '</div>',
      ];
    }

    // Button to add more names.
    $form['observers']['add_observer'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add another observer'),
      '#attributes' => ['class' => ['rounded']],
      '#prefix' => '<div class = "clearBoth addSpace">',
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
    $values = $form_state->getValues();
    switch ($values['op']) {
      case 'Add another accomplice':
        $this->addNewFields($form, $form_state, 'num_accomplices');
        break;

      case 'Add another witness':
        $this->addNewFields($form, $form_state, 'num_witnesses');
        break;

      case 'Add another observer':
        $this->addNewFields($form, $form_state, 'num_observers');
        break;

      default:

        foreach ($this->getActiveFields() as $field) {
          $new_vals['field_' . $field] = $values[$field];
        }
        foreach ($this->getReferenceFields() as $field) {
          if ($values[$field]) {
            $new_vals['field_' . $field] = ['target_id' => $values[$field]];
          }
        }
        foreach (array_keys($values['courts']) as $field) {
          if ($values['courts'][$field]) {
            $new_vals['field_' . $field] = ['target_id' => $values['courts'][$field]];
          }
        }

        foreach ($values['witnesses'] as $witness) {
          if (\is_array($witness) && isset($witness['witness_name'])) {
            $paragraph = $this->makeWitnessParagraph($witness);
            $new_vals['field_witnesses'][] = [
              'target_id' => $paragraph->id(),
              'target_revision_id' => $paragraph->getRevisionId(),
            ];
          }
        }

        foreach ($values['accomplices'] as $accomplice) {
          if (\is_array($accomplice) && isset($accomplice['accomplice_name'])) {
            $paragraph = $this->makeAccompliceParagraph($accomplice);
            $new_vals['field_accomplices'][] = [
              'target_id' => $paragraph->id(),
              'target_revision_id' => $paragraph->getRevisionId(),
            ];
          }
        }
        foreach ($values['observers'] as $observer) {
          if (\is_array($observer)) {
            $new_vals['field_observer_name'][] = ['target_id' => $observer['observer_name']];
          }

        }
        $new_vals['title'] = $values['box_number'];
        $new_vals['type'] = 'court_case';
        $node = Node::create($new_vals);
        $node->save();
    }
    // Display result.
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
    $general_term = Term::load($this->currentAssembly);
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
        'default' => $this->currentSector,
        'vid' => 'general_assembly',
      ],
      'court_of_cell' => [
        'parent' => 'general_assembly',
        'parent_field' => 'field_general_assembly',
        'default' => $this->currentAssembly,
        'vid' => 'court_of_cell',
      ],
      'court_of_sector' => [
        'parent' => 'general_assembly',
        'parent_field' => 'field_general_assembly',
        'default' => $this->currentAssembly,
        'vid' => 'court_of_sector',
      ],
      'court_of_appeal' => [
        'parent' => 'general_assembly',
        'parent_field' => 'field_general_assembly',
        'default' => $this->currentAssembly,
        'vid' => 'court_of_appeal',
      ],
    ];
  }

  /**
   * Handle adding new.
   */
  private function addNewFields(array &$form, FormStateInterface $form_state, string $counter) {

    // Add 1 to the number of names.
    $current = $form_state->get($counter);
    $form_state->set($counter, ($current + 1));

    // Rebuild the form.
    $form_state->setRebuild();
  }

  private function getActiveFields() {
    return [
      'register_number',
      'trial_stage',
      'trial_level',
      'trial_location',
      'trial_date',
      'new_crime',
      'properties',
      'outcome',
      'sentence',
    ];
  }

  private function getReferenceFields() {
    return [
      'crime',
      'accused',
      'plaintiff',
    ];
  }

  /**
   * Creates Witness paragraphs.
   *
   * @param $inputs
   *   Values to build paragraph
   *
   * @return \Drupal\Core\Entity\EntityInterface|\Drupal\paragraphs\Entity\Paragraph
   *   The newly constructed paragraph.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function makeWitnessParagraph($inputs) {

    $paragraph_values = [
      'type' => 'witnesses',
      'field_witness_name' => [
        'target_id' => $inputs['witness_name'],
      ],
      'field_witness_type' => [
        'value' => $inputs['witness_type'],
      ],
    ];
    $paragraph = Paragraph::create($paragraph_values);
    $paragraph->save();
    return $paragraph;

  }

  /**
   * Creates Accomplice paragraphs.
   *
   * @param $inputs
   *   Values to build paragraph
   *
   * @return \Drupal\Core\Entity\EntityInterface|\Drupal\paragraphs\Entity\Paragraph
   *  The newly constructed paragraph.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function makeAccompliceParagraph($inputs) {
    $paragraph_values = [
      'type' => 'accomplices',
      'field_accomplice_name' => [
        'target_id' => $inputs['accomplice_name'],
      ],
      'field_accomplice_sentence' => [
        'value' => $inputs['accomplice_sentence'],
      ],
    ];
    $paragraph = Paragraph::create($paragraph_values);
    $paragraph->save();
    return $paragraph;
  }

}
