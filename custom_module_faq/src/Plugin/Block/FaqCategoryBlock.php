<?php

namespace Drupal\custom_module_faq\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'FaqBlockCategory' block.
 *
 * @Block(
 *  id = "faq_category_block",
 *  admin_label = @Translation("FAQ Category block"),
 * )
 */
class FaqCategoryBlock extends BlockBase implements ContainerFactoryPluginInterface {
  use LoggerChannelTrait;

  /**
   * EntityTypeManager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Language manager service.
   *
   * @var \Drupal\Component\Plugin\Context\ContextInterface[]|\Drupal\Core\Language\LanguageManager|object|null
   */
  protected $languageManager;

  /**
   * Entity repository service.
   *
   * @var \Drupal\Core\Entity\EntityRepository
   */
  protected $entityRepository;

  /**
   * The current route matcher.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new self($configuration, $plugin_id, $plugin_definition);
    $instance->injectServices($container);

    return $instance;
  }

  /**
   * Inject additional service dependencies.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   */
  protected function injectServices(ContainerInterface $container) {
    $this->languageManager = $container->get('language_manager');
    $this->entityRepository = $container->get('entity.repository');
    $this->entityTypeManager = $container->get('entity_type.manager');
    $this->routeMatch = $container->get('current_route_match');

    $this->logger = $this->getLogger('custom_module_faq');
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'faq_category_id' => '',
      'default_open' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $categories = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties(['tid' => $this->configuration['faq_category_id']]);
    $category = reset($categories);
    $taxonomyTerm = $this->configuration['faq_category_id'] ? $category : '';
    $form['faq_category_id'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('FAQ Category Reference'),
      '#description' => $this->t('Choose the FAQ category to get the Q&A for. When left blank the url will be checked for the term to use.'),
      '#required' => FALSE,
      '#target_type' => 'taxonomy_term',
      '#selection_settings' => [
        'target_bundles' => ['faq_categories'],
      ],
      '#default_value' => $taxonomyTerm,
    ];

    $form['default_open'] = [
      '#type' => 'checkbox',
      '#title' => 'Default Open',
      '#description' => $this->t("Default this category's accordions to open on page load."),
      '#default_value' => $this->configuration['default_open'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['faq_category_id'] = $form_state->getValue(
      'faq_category_id'
    );

    $this->configuration['default_open'] = $form_state->getValue(
      'default_open'
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $currentLanguage = $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();

    // Load the term from the settings first, then check for the term in the
    // path.
    if ($this->configuration['faq_category_id']) {
      $parentCategory = $this->entityTypeManager->getStorage('taxonomy_term')->load($this->configuration['faq_category_id']);
    }
    elseif ($this->routeMatch->getParameter('taxonomy_term')) {
      $parentCategory = $this->routeMatch->getParameter('taxonomy_term');
    }
    else {
      return [];
    }

    $categories = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties(['parent' => $parentCategory->id()]);

    $formattedCategories = [];
    foreach ($categories as $key => $subCategory) {
      $questions = $this->entityTypeManager->getStorage('node')->loadByProperties(['type' => 'faq', 'field_category' => $subCategory->id()]);

      // Remove the sub category if it doesn't have any questions related to it.
      if (!$questions) {
        unset($categories[$key]);
        continue;
      }

      $subCatTranslation = $this->entityRepository->getTranslationFromContext($subCategory, $currentLanguage);
      $formattedCategories[$key] = [
        'name' => $subCatTranslation->getName(),
        'weight' => $subCatTranslation->getWeight(),
        'open' => $subCatTranslation->field_faq_accordion_default_open->value,
      ];

      foreach ($questions as $question) {
        $questionTranslation = $this->entityRepository->getTranslationFromContext($question, $currentLanguage);
        $formattedCategories[$key]['questions'][] = [
          'question' => $questionTranslation->getTitle(),
          'answer' => $questionTranslation->field_answer->value,
          'priority' => (int) $questionTranslation->field_priority->value,
        ];
      }

      // Sort questions based on priority.
      usort($formattedCategories[$key]['questions'], function ($a, $b) {
        return $a['priority'] <=> $b['priority'];
      });
    }

    // Sort sub categories based on weight.
    uasort($formattedCategories, function ($a, $b) {
      return $a['weight'] <=> $b['weight'];
    });

    $parentCategoryTranslation = $this->entityRepository->getTranslationFromContext($parentCategory, $currentLanguage);

    return [
      '#theme' => 'faq_category_block',
      '#category' => $parentCategoryTranslation->getName(),
      '#subcategories' => $formattedCategories,
      '#default_open' => $this->configuration['default_open'] ? TRUE : FALSE,
    ];
  }

}
