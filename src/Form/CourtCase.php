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
  public function buildForm(array $form, FormStateInterface $form_state, Node $node = NULL) {
    if ($node) {
      $form_state->set('nid', $node->id());
    }
    else {
      $node = $this->entityTypeManager->getStorage('node')->create([
        'type' => 'court_case',
      ]);
    }
    $entity = $node;
    $form_state->set('entity', $entity);
    $form_display = $this->entityTypeManager->getStorage('entity_form_display')
      ->load('node.court_case.default');
    $form_state->set('form_display', $form_display);
    $form['#parents'] = [];
    $new_elements = [
      'field_accomplices',
      'field_properties_destroyed',
      'field_witnesses',
      'field_observer_name',
    ];
    $paragraphs = [];
    foreach ($form_display->getComponents() as $name => $component) {
      if (\in_array($name, $new_elements)) {
        $widget = $form_display->getRenderer($name);
        if (!$widget) {
          continue;
        }
        $items = $entity->get($name);
        $items->filterEmptyItems();
        $paragraphs[$name] = $widget->form($items, $form, $form_state);
        $paragraphs[$name]['#access'] = $items->access('edit');
      }
    }

    if ($node) {
      $form_state->set('nid', $node->id());
    }
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
    $convictions = [
      'imprisonment' => $this->t("Imprisonment"),
      'restitution' => $this->t('Restitution'),
      'tig' => $this->t('TIG'),
      'pardoned' => $this->t('Pardoned'),
    ];

    $court_options = $this->getCourtOptions($form_state);
    //$form['#tree'] = TRUE;
    $form['box_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Box Number'),
      '#description' => $this->t('5 digits'),
      '#required' => TRUE,
      '#default_value' => $node ? $node->get('title')->value : '',
      '#prefix' => '<div class = "court_selector">',
      '#suffix' => '</div>',
    ];
    $form['register_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Register'),
      '#default_value' => $node ? $node->get('field_register_number')->value : '',
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
      '#default_value' => $node ? $node->get('field_district')->value : '',
      '#description' => $this->t('Rwandan Court District'),
      '#default_value' => $keys[0],
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
      '#default_value' => $node ? $node->get('field_sector')->value : '',
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
      '#default_value' => $node ? $node->get('field_general_assembly')->value : '',
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
      '#default_value' => $node ? $node->get('field_court_of_cell')->value : '',
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
      '#default_value' => $node ? $node->get('field_court_of_sector')->value : '',
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
      '#default_value' => $node ? $node->get('field_court_of_appeal')->value : '',
      '#description' => $this->t('Rwandan Court of Appeal'),
      '#disabled' => TRUE,
      '#states' => [
        'enabled' => [
          ':input[name="level"]' => ['value' => 'appeal'],
        ],
      ],
    ];
    $form['court_level'] = [
      '#type' => 'radios',
      '#options' => [
        'cell' => $this->t('Cell'),
        'sector' => $this->t('Sector'),
        'appeal' => $this->t('Appeal'),
      ],
      '#title' => $this->t('Court Level'),
      // '#default_value' => $node ? $node->get('field_court_level')->value : 'cell',
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
      '#default_value' => $node ? $node->get('field_trial_stage')->value : '',
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
      '#default_value' => $node ? $node->get('field_trial_level')->value : '',
    ];
    $form['trial_location'] = [
      '#type' => 'textfield',
      '#default_value' => $node ? $node->get('field_trial_stage')->value : '',

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
      '#suffix' => '</div><div class = "clearBoth"></div>',
    ];

    $form['witnesses'] = $paragraphs['field_witnesses'];
    $form['accomplices'] = $paragraphs['field_accomplices'];
    $form['properties'] = $paragraphs['field_properties_destroyed'];
    $paragraphs['field_observer_name']['widget']['add_more']['#value'] = $this->t("Add observer");
    $form['observers'] = $paragraphs['field_observer_name'];
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
      '#options' => $convictions,
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
      '#suffix' => '</div><div class="clearBoth"></div>',
    ];


    $form['#attached']['library'][] = 'rwanda/rwanda_court';
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#prefix' => '<div class = "clearBoth">',
      '#suffix' => '</div>',
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
    if ($form_state->get('nid')) {
      $node = Node::load($form_state->get('nid'));
      $current = $node->get('field_witnesses');
      $targets = $current->getValue();
      foreach ($targets as $target) {
        Paragraph::load($target['target_id'])->delete();
      }
    }
    $values = $form_state->getValues();
    $paragraph_mapping = [
      'witnesses' => 'field_witnesses',
      'accomplices' => 'field_accomplices',
      'properties_destroyed' => 'field_properties_destroyed',
    ];

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

    foreach ($paragraph_mapping as $type => $field) {
      foreach ($values[$field] as $candidate) {
        $subform = $candidate['subform'];
        if (!$subform) {
          continue;
        }
        $paragraph_values = $this->parseSubform($subform, $type);
        $paragraph = Paragraph::create($paragraph_values);
        $paragraph->save();
        $new_vals[$field][] = [
          'target_id' => $paragraph->id(),
          'target_revision_id' => $paragraph->getRevisionId(),
        ];
      }
    }

    foreach ($values['field_observer_name'] as $observer) {
      if (\is_array($observer)) {
        $new_vals['field_observer_name'][] = ['target_id' => $observer['observer_name']];
      }
    }
    if ($values['new_crime']) {
      $term = Term::create([
          'name' => $values['new_crime'],
          'vid' => 'crimes',
        ]
      );
      $term->save();
      $new_vals['field_crime']['target_id'] = $term->id();
    }
    $new_vals['title'] = $values['box_number'];
    $new_vals['type'] = 'court_case';
    if ($form_state->get('nid')) {
      $node = Node::load($form_state->get('nid'));
      foreach ($new_vals as $property => $value) {
        $node->set($property, $value);
      }
    }
    else {
      $node = Node::create($new_vals);
    }
    $node->save();
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
    $all_options = [];
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


  private function getActiveFields() {
    return [
      'register_number',
      'trial_stage',
      'trial_level',
      'trial_location',
      'trial_date',
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
        'target_id' => $inputs['field_witness_name'][0]['target_id'],
      ],
      'field_witness_type' => [
        'value' => $inputs['field_witness_type'][0]['value'],
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
      'field_court_decision' => [
        'value' => $inputs['accomplice_decision'],
      ],
      'field_outcome' => [
        'value' => $inputs['accomplice_outcome'],
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
  private function makePropertyParagraph($inputs) {
    $paragraph_values = [
      'type' => 'properties_destroyed',
      'field_destroyed_item' => [
        'value' => $inputs['destroyed_item'],
      ],
      'field_number_of_destroyed_items' => [
        'value' => $inputs['number_of_destroyed_items'],
      ],
    ];
    $paragraph = Paragraph::create($paragraph_values);
    $paragraph->save();
    return $paragraph;
  }

  private function parseSubform(array $subform, string $type) {
    $parsed['type'] = $type;
    foreach ($subform as $field => $element) {
      $parsed[$field] = $element[0];
    }
    return $parsed;
  }

}
