<?php

namespace Drupal\custom_module_faq\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'FaqCategoryGrid' block.
 *
 * @Block(
 *  id = "faq_category_grid_block",
 *  admin_label = @Translation("FAQ Category Grid block"),
 * )
 */
class FaqCategoryGridBlock extends BlockBase implements ContainerFactoryPluginInterface {
  use LoggerChannelTrait;

  /**
   * EntityTypeManager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Language manager service.
   *
   * @var \Drupal\Component\Plugin\Context\ContextInterface[]|\Drupal\Core\Language\LanguageManager|object|null
   */
  protected $languageManager;

  /**
   * Entity repository service.
   *
   * @var \Drupal\Component\Plugin\Context\ContextInterface[]|\Drupal\Core\Entity\EntityRepository|object|null
   */
  protected $entityRepository;

  /**
   * Alias manager service.
   *
   * @var \Drupal\Component\Plugin\Context\ContextInterface[]|\Drupal\path_alias\AliasManager|object|null
   */
  protected $aliasManager;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

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
    $this->aliasManager = $container->get('path_alias.manager');

    $this->logger = $this->getLogger('custom_module_faq_category_block');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {

    $currentLanguage = $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();

    // Get root categories.
    $categories = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties(['vid' => 'faq_categories', 'parent' => '0']);

    $formattedCategories = [];
    // Remove categories from list if they do not have children.
    foreach ($categories as $key => $category) {
      $subCategory = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties(['parent' => $category->id()]);
      if (!$subCategory) {
        unset($categories[$key]);
        continue;
      }

      $categoryTranslation = $this->entityRepository->getTranslationFromContext($category, $currentLanguage);
      $formattedCategories[] = [
        'name' => $categoryTranslation->getName(),
        'icon' => $categoryTranslation->get('field_faq_grid_icon')->value,
        'weight' => $categoryTranslation->getWeight(),
        'link' => Url::fromRoute('entity.taxonomy_term.canonical', ['taxonomy_term' => $categoryTranslation->id()]),
      ];
    }

    // Sort categories based on weight.
    uasort($formattedCategories, function ($a, $b) {
      return $a['weight'] <=> $b['weight'];
    });

    return [
      '#theme' => 'faq_category_grid_block',
      '#categories' => $formattedCategories,
    ];
  }

}
