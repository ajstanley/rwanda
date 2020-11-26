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
    $entity = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'court_case',
    ]);
    $coa_val = $coc_val = $cos_val = $sector_val = $assembly_val = '';
    if ($node) {
      $entity = $node;
      $form_state->set('nid', $node->id());
      if (!$node->get('field_court_of_sector')->isEmpty()) {
        $cos_val = $node->get('field_court_of_sector')->entity->id();
      }
      if (!$node->get('field_court_of_cell')->isEmpty()) {
        $coc_val = $node->get('field_court_of_cell')->entity->id();
      }
      if (!$node->get('field_court_of_appeal')->isEmpty()) {
        $coa_val = $node->get('field_court_of_appeal')->entity->id();
      }
      if (!$node->get('field_sector')->isEmpty()) {
        $sector_val = $node->get('field_sector')->entity->id();
      }
      if (!$node->get('field_general_assembly')->isEmpty()) {
        $assembly_val = $node->get('field_general_assembly')->entity->id();
      }
    }

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
    $district = $form_state->getValue('field_district');
    if ($node) {
      $district = $node->get('field_district')->getValue()[0]['target_id'];
    }
    $this->currentDistrict = $district ? $district : $keys[0];

    // Sector  form_state, node, default
    $sector_ids = $this->entityQuery->get('taxonomy_term')
      ->condition('field_district', $keys[0])
      ->execute();

    $currentSector = $form_state->getValue('field_sector');
    if (!$currentSector && $sector_val) {
      $currentSector = $sector_val;
    }
    if (!$sector_val) {
      $currentSector = reset($sector_ids);
    }

    $this->currentSector = $currentSector;

    // Assembly.
    $assembly_ids = $this->entityQuery->get('taxonomy_term')
      ->condition('field_sector', reset($sector_ids))
      ->execute();

    $currentAssembly = $form_state->getValue('field_general_assembly');
    if (!$currentAssembly && $assembly_val) {
      $currentAssembly = $assembly_val;
    }
    if (!$assembly_val) {
      $currentAssembly = reset($assembly_ids);
    }
    $this->currentAssembly = $currentAssembly;

    $court_options = $this->getCourtOptions($form_state);
    $all_courts = [];
    $candidate_courts = ['court_of_cell', 'court_of_appeal', 'court_of_sector'];
    foreach ($candidate_courts as $candidate_court) {
      foreach ($court_options[$candidate_court] as $k => $v) {
        $all_courts[$v] = $k;
      }
    }
    $all_courts = \array_flip($all_courts);

    $form['box_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Box Number'),
      '#description' => $this->t('5 digits'),
      '#required' => TRUE,
      '#default_value' => $node ? $node->get('title')->value : '',
      '#prefix' => '<div class = "court_selector">',
      '#suffix' => '</div>',
    ];
    $form['field_register_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Register'),
      '#default_value' => $node ? $node->get('field_register_number')->value : '',
      '#prefix' => '<div class = "court_selector">',
      '#suffix' => '</div>',
    ];
    $form['field_trial_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Trial Number'),
      '#default_value' => $node ? $node->get('field_trial_number')->value : '',
      '#prefix' => '<div class = "court_selector">',
      '#suffix' => '</div>',
    ];
    $form['courts'] = [
      '#type' => 'fieldset',
      '#prefix' => '<div id="courts_wrapper" class="clearBoth">',
      '#suffix' => '</div>',
    ];
    $form['courts']['field_district'] = [
      '#type' => 'select',
      '#options' => $term_data,
      '#prefix' => '<div id="district_wrapper" class = "court_selector">',
      '#suffix' => '</div>',
      '#title' => $this->t('District'),
      '#default_value' => $node ? $node->get('field_district')
        ->getValue()[0] : $keys[0],
      '#description' => $this->t('Rwandan Court District'),
      '#ajax' => [
        'callback' => '::changeCourtsOptionsAjax',
        'wrapper' => 'courts_wrapper',
      ],

    ];

    $form['courts']['field_sector'] = [
      '#type' => 'select',
      '#options' => $court_options['sector'],
      '#prefix' => '<div id="sector_wrapper" class = "court_selector">',
      '#suffix' => '</div>',
      '#title' => $this->t('Sector'),
      '#default_value' => $node ? $sector_val : '',
      '#description' => $this->t('Rwandan Court Sector'),
      '#ajax' => [
        'callback' => '::changeCourtsOptionsAjax',
        'wrapper' => 'courts_wrapper',
      ],

    ];

    $form['courts']['field_general_assembly'] = [
      '#type' => 'select',
      '#options' => $court_options['general_assembly'],
      '#prefix' => '<div id="assembly_wrapper" class = "court_selector">',
      '#suffix' => '</div>',
      '#title' => $this->t('General Assembly'),
      '#default_value' => $node ? $assembly_val : '',
      '#description' => $this->t('Rwandan General Assembly'),
      '#ajax' => [
        'callback' => '::changeCourtsOptionsAjax',
        'wrapper' => 'courts_wrapper',
      ],
    ];

    $form['courts']['field_court_of_cell'] = [
      '#type' => 'select',
      '#options' => $court_options['court_of_cell'],
      '#prefix' => '<div id="cell_wrapper" class = "court_selector clearBoth">',
      '#suffix' => '</div>',
      '#title' => $this->t('Court of Cell'),
      '#default_value' => $node ? $coc_val : '',
      '#description' => $this->t('Rwandan Court of Cell'),
      '#states' => [
        'enabled' => [
          ':input[name="field_court_level"]' => ['value' => 'cell'],
        ],
      ],
    ];

    $form['courts']['field_court_of_sector'] = [
      '#type' => 'select',
      '#options' => $court_options['court_of_sector'],
      '#prefix' => '<div id="court_of_sector_wrapper" class = "court_selector">',
      '#suffix' => '</div>',
      '#title' => $this->t('Court of Sector'),
      '#default_value' => $node ? $cos_val : '',
      '#description' => $this->t('Rwandan Court of Sector'),
      '#disabled' => TRUE,
      '#states' => [
        'enabled' => [
          ':input[name="field_court_level"]' => ['value' => 'sector'],
        ],
      ],
    ];

    $form['courts']['field_court_of_appeal'] = [
      '#type' => 'select',
      '#options' => $court_options['court_of_appeal'],
      '#prefix' => '<div id="court_of_appeal_wrapper" class = "court_selector">',
      '#suffix' => '</div>',
      '#title' => $this->t('Court of Appeal'),
      '#default_value' => $node ? $coa_val : '',
      '#description' => $this->t('Rwandan Court of Appeal'),
      '#disabled' => TRUE,
      '#states' => [
        'enabled' => [
          ':input[name="field_court_level"]' => ['value' => 'appeal'],
        ],
      ],
    ];
    $form['courts']['field_court_level'] = [
      '#type' => 'radios',
      '#options' => [
        'cell' => $this->t('Cell'),
        'sector' => $this->t('Sector'),
        'appeal' => $this->t('Appeal'),
      ],
      '#title' => $this->t('Court Level'),
      '#default_value' => $node ? $node->get('field_court_level')->value : 'cell',
      '#prefix' => '<div class = "clearBoth">',
      '#suffix' => '</div>',
    ];
    $form['courts']['field_trial_stage'] = [
      '#type' => 'select',
      '#prefix' => '<div class = "court_selector">',
      '#suffix' => '</div>',
      '#options' => [
        'public' => $this->t('Public'),
        'absentia' => $this->t('In Absentia'),
        'restricted' => $this->t('Restricted'),
      ],
      '#title' => $this->t('Trial Type'),
      '#default_value' => $node ? $node->get('field_trial_stage')->value : '',
    ];
    $form['courts']['field_trial_level'] = [
      '#type' => 'select',
      '#prefix' => '<div class = "court_selector">',
      '#suffix' => '</div>',
      '#options' => [
        'judgement_rendering' => $this->t('Judgement rendering'),
        'opposition' => $this->t('In Absentia'),
        'review' => $this->t('Review'),
        'appeal' => $this->t('Appeal'),
      ],
      '#title' => $this->t('Trial Stage'),
      '#default_value' => $node ? $node->get('field_trial_level')->value : '',
    ];
    $form['courts']['field_trial_location'] = [
      '#type' => 'select',
      '#options' => $all_courts,
      '#prefix' => '<div id="sector_wrapper" class = "court_selector">',
      '#suffix' => '</div>',
      '#title' => $this->t('Trial Location'),
      '#default_value' => $node ? $sector_val : '',
      '#description' => $this->t('Rwandan Court Sector'),
      '#ajax' => [
        'callback' => '::changeCourtsOptionsAjax',
        'wrapper' => 'courts_wrapper',
      ],
    ];
    $form['courts']['field_trial_date'] = [
      '#type' => 'date',
      '#prefix' => '<div class = "court_selector">',
      '#suffix' => '</div>',
      '#title' => $this->t('Trial Date'),
      '#default_value' => $node ? $node->get('field_trial_date')->value : '1990-01-01',
    ];

    $form['field_plaintiff'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'node',
      '#title' => $this->t('Plaintiff'),
      //'#default_value' => $node ? $node->field_plaintiff->entity : '',
      '#description' => $this->t('Select plaintiff.'),
      '#selection_settings' => [
        'target_bundles' => ['person'],
      ],
      '#autocreate' => [
        'bundle' => 'person',
      ],
      '#prefix' => '<div class = "clearBoth accomplice">',
      '#suffix' => '</div>',
    ];
    $form['field_crime'] = [
      '#type' => 'select',
      '#title' => $this->t('Crime'),
      '#options' => $crime_data,
      '#default_value' => $node ? $node->get('field_crime')
        ->getValue()[0]['target_id'] : '',

      '#prefix' => '<div class = "court_selector clearBoth">',
      '#suffix' => '</div>',
    ];
    $form['field_new_crime'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Unlisted Crime Type'),
      '#states' => [
        'enabled' => [
          ':input[name="field_crime"]' => ['value' => 'other'],
        ],
      ],
      '#prefix' => '<div class = "court_selector">',
      '#suffix' => '</div><div class = "clearBoth"></div>',
    ];

    $form['field_accused'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'node',
      '#title' => $this->t('Accused'),
      '#default_value' => $node ? $node->field_accused->entity : '',
      '#description' => $this->t('Select accused.'),
      '#selection_settings' => [
        'target_bundles' => ['person'],
      ],
      '#autocreate' => [
        'bundle' => 'person',
      ],
      '#prefix' => '<div class = "clearBoth accomplice">',
      '#suffix' => '</div>',
    ];

    $form['field_decision'] = [
      '#type' => 'select',
      '#title' => $this->t('Court Decision'),
      '#description' => $this->t('If accused are found guilty or not.'),
      '#options' => [
        'guilty' => $this->t('Guilty'),
        'acquitted' => $this->t('Acquitted'),
        'pardoned' => $this->t('Pardoned'),
      ],
      '#prefix' => '<div class = "court_selector">',
      '#suffix' => '</div>',
    ];

    $form['field_restitution'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Restitution'),
      '#default_value' => $node ? $node->get('field_restitution')->value : '',
      '#states' => [
        'enabled' => [
          ':input[name="field_decision"]' => ['value' => 'guilty'],
        ],
      ],
      '#prefix' => '<div class = "accused_selector">',
      '#suffix' => '</div>',
    ];
    $form['field_tig'] = [
      '#type' => 'textfield',
      '#title' => $this->t('TIG'),
      '#default_value' => $node ? $node->get('field_tig')->value : '',
      '#states' => [
        'enabled' => [
          ':input[name="field_decision"]' => ['value' => 'guilty'],
        ],
      ],
      '#prefix' => '<div class = "accused_selector">',
      '#suffix' => '</div>',
    ];

    $form['field_sentence'] = [
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
          ':input[name="field_decision"]' => ['value' => 'guilty'],
        ],
      ],
      '#prefix' => '<div class = "accused_selector">',
      '#suffix' => '</div><div class="clearBoth"></div>',
    ];


    $form['field_witnesses'] = $paragraphs['field_witnesses'];
    $form['field_accomplices'] = $paragraphs['field_accomplices'];
    $form['field_properties'] = $paragraphs['field_properties_destroyed'];
    $paragraphs['field_observer_name']['widget']['add_more']['#value'] = $this->t("Add observer");
    $form['field_observers'] = $paragraphs['field_observer_name'];

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
        $paragraph = Paragraph::load($target['target_id']);
        if ($paragraph) {
          $paragraph->delete();
        }
      }
    }
    $values = $form_state->getValues();
    $paragraph_mapping = [
      'witnesses' => 'field_witnesses',
      'accomplices' => 'field_accomplices',
      'properties_destroyed' => 'field_properties_destroyed',
    ];

    foreach ($this->getActiveFields() as $field) {
      $new_vals[$field] = $values[$field];
    }
    foreach ($this->getReferenceFields() as $field) {
      $new_vals[$field] = $values[$field] ? ['target_id' => $values[$field]] : NULL;
    }

    foreach ($paragraph_mapping as $type => $field) {
      foreach ($values[$field] as $candidate) {
        if (isset($candidate['subform'])) {
          $subform = $candidate['subform'];
          $paragraph_values = $this->parseSubform($subform, $type);
          if (\count($paragraph_values) > 1) {
            $paragraph = Paragraph::create($paragraph_values);
            $paragraph->save();
            $new_vals[$field][] = [
              'target_id' => $paragraph->id(),
              'target_revision_id' => $paragraph->getRevisionId(),
            ];
          }

        }
      }
    }

    foreach ($values['field_observer_name'] as $observer) {
      if (\is_array($observer) && $observer['target_id']) {
        $new_vals['field_observer_name'][] = ['target_id' => $observer['target_id']];
      }
    }
    if ($values['field_new_crime']) {
      $term = Term::create([
        'name' => $values['field_new_crime'],
        'vid' => 'crimes',
      ]);
      $term->save();
      $new_vals['field_crime']['target_id'] = $term->id();
    }
    $new_vals['title'] = $values['box_number'];
    $new_vals['type'] = 'court_case';
    $new_vals = \array_filter($new_vals);
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
        'parent' => 'field_district',
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
        'parent' => 'field_general_assembly',
        'parent_field' => 'field_general_assembly',
        'default' => $this->currentAssembly,
        'vid' => 'court_of_cell',
      ],
      'court_of_sector' => [
        'parent' => 'field_general_assembly',
        'parent_field' => 'field_general_assembly',
        'default' => $this->currentAssembly,
        'vid' => 'court_of_sector',
      ],
      'court_of_appeal' => [
        'parent' => 'field_general_assembly',
        'parent_field' => 'field_general_assembly',
        'default' => $this->currentAssembly,
        'vid' => 'court_of_appeal',
      ],
    ];
  }

  /**
   * Return array of single valued fields.
   *
   * @return array
   *   Array of active fields
   */
  private function getActiveFields() {
    return [
      'field_district',
      'field_sector',
      'field_general_assembly',
      'field_court_of_cell',
      'field_court_of_sector',
      'field_court_of_appeal',
      'field_register_number',
      'field_trial_number',
      'field_trial_stage',
      'field_trial_level',
      'field_trial_location',
      'field_trial_date',
      'field_decision',
      'field_restitution',
      'field_tig',
      'field_sentence',
      'field_court_level',
    ];
  }

  /**
   * Returns array of referenced fields.
   *
   * @return array
   *   Array of referenced fields.
   */
  private function getReferenceFields() {
    return [
      'field_crime',
      'field_accused',
      'field_plaintiff',
    ];
  }

  /**
   * Parses subform to populate Paragraphs.
   *
   * @param array $subform
   *   The subfom to parse
   * @param string $type
   *  The Paragraph machine name.
   *
   * @return array
   *   The parsed subform.
   */
  private function parseSubform(array $subform, string $type) {
    $parsed['type'] = $type;
    foreach ($subform as $field => $element) {
      if ($element) {
        $values = \array_values($element[0]);
        if ($values[0]) {
          $parsed[$field] = $element[0];
        }
      }
    }
    return $parsed;
  }

}
