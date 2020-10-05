<?php

namespace Drupal\custom_module_faq\Plugin\Block;

use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\media\Entity\Media;
use Drupal\file\Entity\File;
use Drupal\views\Views;
use Drupal\views_exposed_filter_blocks\Plugin\Block\ViewsExposedFilterBlocksBlock;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a separate views exposed filter block.
 *
 * @Block(
 *   id = "custom_module_faq_search_exposed_filter_block",
 *   admin_label = @Translation("FAQ Search Block")
 * )
 */
class FaqSearchBlock extends ViewsExposedFilterBlocksBlock implements ContainerFactoryPluginInterface {

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
      'view_display' => 'faq_search:faq_search_page',
      'faq_category_id' => '',
      'background_image' => '',
      'mobile_background_image' => '',
      'label_display' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['faq_category_id'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('FAQ Category Reference'),
      '#description' => $this->t('Choose the FAQ category to get the Q&A for. When left blank the url will be checked for the term to use, if no term is found all FAQs will be searched.'),
      '#required' => FALSE,
      '#target_type' => 'taxonomy_term',
      '#selection_settings' => [
        'target_bundles' => ['faq_categories'],
      ],
      '#default_value' => $this->configuration['faq_category_id'],
    ];

    $form['background_image'] = [
      '#type' => 'media_library',
      '#allowed_bundles' => ['image'],
      '#default_value' => $this->configuration['background_image'],
      '#title' => $this->t('Background Image'),
      '#description' => $this->t('Image to show as background of FAQ search.'),
    ];

    $form['mobile_background_image'] = [
      '#type' => 'media_library',
      '#allowed_bundles' => ['image'],
      '#default_value' => $this->configuration['mobile_background_image'],
      '#title' => $this->t('Mobile Background Image'),
      '#description' => $this->t('Image to show as mobile background of FAQ search.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['faq_category_id'] = $form_state->getValue('faq_category_id');
    $this->configuration['background_image'] = $form_state->getValue('background_image');
    $this->configuration['mobile_background_image'] = $form_state->getValue('mobile_background_image');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $view_display = $this->configuration['view_display'];
    if (!empty($view_display)) {
      [$view_id, $display_id] = explode(':', $view_display);
      if (empty($view_id) || empty($display_id)) {
        return;
      }
      $view = Views::getView($view_id);
      if (!empty($view)) {
        $view->setDisplay($display_id);
        $view->initHandlers();
        $form_state = (new FormState())
          ->setStorage([
            'view' => $view,
            'display' => &$view->display_handler->display,
            'rerender' => TRUE,
          ])
          ->setMethod('get')
          ->setAlwaysProcess()
          ->disableRedirect();
        $form_state->set('rerender', NULL);
        $form = \Drupal::formBuilder()
          ->buildForm('\Drupal\views\Form\ViewsExposedForm', $form_state);

        // Load the term from the settings first, then check for the term in the
        // path.
        $parentCategory = FALSE;
        if ($this->configuration['faq_category_id']) {
          $parentCategory = $this->entityTypeManager->getStorage('taxonomy_term')->load($this->configuration['faq_category_id']);
        }
        elseif ($this->routeMatch->getParameter('taxonomy_term')) {
          $parentCategory = $this->routeMatch->getParameter('taxonomy_term');
        }

        if ($parentCategory) {
          $subcategories = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties(['parent' => $parentCategory->id()]);
          $defaultValues = [];
          foreach ($subcategories as $key => $subCategory) {
            array_push($defaultValues, $key);
            $form['subcategory' . $key]['#type'] = 'hidden';
            $form['subcategory' . $key]['#name'] = 'subcategory[]';
            $form['subcategory' . $key]['#value'] = $key;
            $form['subcategory' . $key]['#weight'] = 100;
          }
        }
        $form['subcategory']['#access'] = FALSE;
        $form['#action'] = Url::fromRoute('view.faq_search.faq_search_page')->toString();

        $form['actions']['submit']['#value'] = html_entity_decode('&#xe919;');
        $form['actions']['submit']['#attributes']['class'] = [
          'e-btn--primary',
          'icon',
          'icon-search-heavy',
        ];

        $backgroundImageUrl = '';
        if ($this->configuration['background_image']) {
          $backgroundImage = Media::load($this->configuration['background_image']);
          $backgroundImagefid = $backgroundImage->image->target_id;
          $backgroundImageFile = File::load($backgroundImagefid);
          $backgroundImageUrl = $backgroundImageFile->url();
        }

        $mobileBackgroundImageUrl = '';
        if ($this->configuration['mobile_background_image']) {
          $mobileBackgroundImage = Media::load($this->configuration['mobile_background_image']);
          $mobileBackgroundImagefid = $mobileBackgroundImage->image->target_id;
          $mobileBackgroundImageFile = File::load($mobileBackgroundImagefid);
          $mobileBackgroundImageUrl = $mobileBackgroundImageFile->url();
        }

        return [
          '#type' => 'container',
          '#theme' => 'faq_category_search_block',
          '#form' => $form,
          '#background_image_url' => $backgroundImageUrl,
          '#mobile_background_image_url' => $mobileBackgroundImageUrl,
        ];
      }
      else {
        $error = $this->t('View "%view_id" or its given display: "%display_id" doesn\'t exist. Please check the views exposed filter block configuration.', [
          '%view_id' => $view_id,
          '%display_id' => $display_id,
        ]);
        \Drupal::logger('type')->error($error);
        return [
          '#type' => 'inline_template',
          '#template' => '{{ error }}',
          '#context' => [
            'error' => $error,
          ],
        ];
      }
    }
  }

}
