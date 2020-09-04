<?php

namespace Drupal\rwanda\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class CourtCase.
 */
class CourtCase extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Entity\Query\QueryFactory
   */
  protected $entityQuery;

  /**
   * Class constructor.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, QueryFactory $entityQuery) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityQuery = $entityQuery;
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
    $vid = 'district';
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')
      ->loadTree($vid);
    foreach ($terms as $term) {
      $term_data[$term->tid] = $term->name;
    }
    $keys = \array_keys($term_data);
    $sector_ids = $this->entityQuery->get('taxonomy_term')
      ->condition('field_district', $keys[0])
      ->execute();
    $default_sector = reset($sector_ids);
    $assembly_ids = $this->entityQuery->get('taxonomy_term')
      ->condition('field_sector', reset($sector_ids))
      ->execute();
    $default_assembly = reset($assembly_ids);

    $form['district'] = [
      '#type' => 'select',
      '#options' => $term_data,
      '#prefix' => '<div id="district_wrapper" class = "court_selector">',
      '#suffix' => '</div>',
      '#title' => $this->t('District'),
      '#description' => $this->t('Rwandan Court District'),
      '#default_value' => $keys[0],
      '#ajax' => [
        'callback' => '::changeSectorOptionsAjax',
        'wrapper' => 'sector_wrapper',
      ],

    ];
    $sector_data = [
      'parent' => 'district',
      'parent_field' => 'field_district',
      'default' => $keys[0],
      'vid' => 'sector',
    ];
    $form['sector'] = [
      '#type' => 'select',
      '#options' => $this->getCourtOptions($form_state, $sector_data),
      '#prefix' => '<div id="sector_wrapper" class = "court_selector">',
      '#suffix' => '</div>',
      '#title' => $this->t('Sector'),
      '#description' => $this->t('Rwandan Court Sector'),
      '#ajax' => [
        'callback' => '::changeAssemblyOptionsAjax',
        'wrapper' => 'assembly_wrapper',
      ],

    ];
    $assembly_data = [
      'parent' => 'sector',
      'parent_field' => 'field_sector',
      'default' => $default_sector,
      'vid' => 'general_assembly',
    ];
    $form['general_assembly'] = [
      '#type' => 'select',
      '#options' => $this->getCourtOptions($form_state, $assembly_data),
      '#prefix' => '<div id="assembly_wrapper" class = "court_selector">',
      '#suffix' => '</div>',
      '#title' => $this->t('General Assembly'),
      '#description' => $this->t('Rwandan General Assembly'),

    ];
    $form['level'] = [
      '#type' => 'radios',
      '#options' => [
        'cell' => $this->t('Cell'),
        'sector' => $this->t('Sector'),
        'appeal' => $this->t('Appeal'),
      ],
      '#title' => $this->t('Court Level'),
      '#prefix' => '<div class = "clearBoth">',
      '#suffix' => '</div>',
    ];
    $court_of_cell_data = [
      'parent' => 'general_assembly',
      'parent_field' => 'field_general_assembly',
      'default' => $default_assembly,
      'vid' => 'court_of_cell',
    ];
    $form['court_of_cell'] = [
      '#type' => 'select',
      '#options' => $this->getCourtOptions($form_state, $court_of_cell_data),
      '#prefix' => '<div id="cell_wrapper" class = "court_selector">',
      '#suffix' => '</div>',
      '#title' => $this->t('Court of Cell'),
      '#description' => $this->t('Rwandan Court of Cell'),
      '#states' => [
        'enabled' => [
          ':input[name="level"]' => ['value' => 'cell'],
        ],
      ],
    ];
    $court_of_sector_data = [
      'parent' => 'general_assembly',
      'parent_field' => 'field_general_assembly',
      'default' => $default_assembly,
      'vid' => 'court_of_sector',
    ];
    $form['court_of_sector'] = [
      '#type' => 'select',
      '#options' => $this->getCourtOptions($form_state, $court_of_sector_data),
      '#prefix' => '<div id="court_of_sector_wrapper" class = "court_selector">',
      '#suffix' => '</div>',
      '#title' => $this->t('Court of Sector'),
      '#description' => $this->t('Rwandan Court of Sector'),
      '#states' => [
        'enabled' => [
          ':input[name="level"]' => ['value' => 'sector'],
        ],
      ],
    ];
    $court_of_appeal_data = [
      'parent' => 'general_assembly',
      'parent_field' => 'field_general_assembly',
      'default' => $default_assembly,
      'vid' => 'court_of_appeal',
    ];
    $form['court_of_appeal'] = [
      '#type' => 'select',
      '#options' => $this->getCourtOptions($form_state, $court_of_appeal_data),
      '#prefix' => '<div id="court_of_appeal_wrapper" class = "court_selector">',
      '#suffix' => '</div>',
      '#title' => $this->t('Court of Appeal'),
      '#description' => $this->t('Rwandan Court of Appeal'),
      '#states' => [
        'enabled' => [
          ':input[name="level"]' => ['value' => 'appeal'],
        ],
      ],
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
  public function changeSectorOptionsAjax(array &$form, FormStateInterface $form_state) {
    return $form['sector'];
  }

  /**
   * Ajax callback to change options for general Assembly.
   */
  public function changeAssemblyOptionsAjax(array &$form, FormStateInterface $form_state) {
    return $form['general_assembly'];
  }

  /**
   * Get options for Courts.
   */

  public function getCourtOptions(FormStateInterface $form_state, array $config) {
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
    return $options;
  }

}
