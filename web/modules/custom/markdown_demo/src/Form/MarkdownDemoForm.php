<?php

namespace Drupal\markdown_demo\Form;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\markdown_demo\MarkdownDemo;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class MarkdownDemoForm.
 */
class MarkdownDemoForm extends FormBase {

  /**
   * The Markdown Demo service.
   *
   * @var \Drupal\markdown_demo\MarkdownDemo
   */
  protected $demo;

  /**
   * MarkdownDemoForm constructor.
   *
   * @param \Drupal\markdown_demo\MarkdownDemo $demo
   *   The Markdown Demo service.
   */
  public function __construct(MarkdownDemo $demo) {
    $this->demo = $demo;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static (
      $container->get('markdown.demo')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'markdown_demo';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Prevent the form/page from being cached.
    $form['#cache'] = ['max-age' => 0];
    \Drupal::service('page_cache_kill_switch')->trigger();

    // Tell the browser that we're handling XSS filtering.
    header('X-XSS-Protection:0');

    // Attach the demo library.
    $form['#attached']['library'][] = 'markdown_demo/demo';

    $markdown = $this->demo->getMarkdown();

    // Wrapper.
    $form['wrapper'] = [
      '#theme_wrappers' => ['container__markdown__wrapper'],
      '#attributes' => ['class' => ['markdown-wrapper']],
    ];
    $wrapper =& $form['wrapper'];

    // Input.
    $wrapper['input'] = [
      '#theme_wrappers' => ['container__markdown__rendered'],
      '#attributes' => ['class' => ['markdown-input']],
    ];

    $wrapper['input']['size'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['class' => ['markdown-size']],
      '#value' => format_size($markdown->getSize()),
    ];

    $wrapper['input']['markdown'] = [
      '#type' => 'textarea',
      '#default_value' => $markdown->getMarkdown(),
    ];

    $wrapper['input']['expires'] = $markdown->buildExpire();

    $wrapper['input']['parse'] = [
      '#type' => 'submit',
      '#value' => $this->t('Parse'),
      '#attributes' => ['class' => ['btn-primary', 'btn-block', 'btn-lg']],
    ];

    $wrapper['parsers'] = [
      '#theme_wrappers' => ['container__markdown__parsers'],
      '#attributes' => ['class' => ['markdown-parsers']],
    ];

    $wrapper['parsers']['tabs'] = [
      '#theme' => 'item_list',
      '#items' => [],
      '#attributes' => [
        'class' => ['nav', 'nav-tabs'],
        'role' =>  'tablist',
      ],
    ];

    $wrapper['parsers']['panes'] = [
      '#theme_wrappers' => ['container__markdown__rendered'],
      '#attributes' => ['class' => ['markdown-panes', 'tab-content']],
    ];

    $tabs =& $wrapper['parsers']['tabs']['#items'];
    $panes =& $wrapper['parsers']['panes'];

    // Iterate over each parser and fill it in.
    $i = -1;
    foreach ($markdown->getFormatted() as $formatted) {
      $i++;
      $id = Html::getUniqueId($formatted->getFormat());

      $tab = [
        '#type' => 'link',
        '#title' => new FormattableMarkup('@label <small>@time</small>', [
          '@label' => $formatted->getLabel(),
          '@time' => $formatted->getTime(),
        ]),
        '#url' => Url::fromRoute('<none>', [], [
          'fragment' => $id,
          'attributes' => [
            'aria-controls' => $id,
            'role' => 'tab',
            'data-toggle' => 'tab',
          ],
        ]),
        '#wrapper_attributes' => [
          'role' => 'presentation',
        ],
      ];

      $pane = [
        '#theme_wrappers' => ['container__markdown__pane'],
        '#attributes' => [
          'class' => ['tab-pane'],
          'role' => 'tabpanel',
          'id' => $id,
        ],
      ];

      // Set active tab.
      if ($i === 0) {
        $tab['#wrapper_attributes']['class'][] = 'active';
        $pane['#attributes']['class'][] = 'active';
      }

      // Generated HTML.
      $pane['html'] = [
        '#theme_wrappers' => ['container__markdown__html'],
        '#attributes' => ['class' => ['markdown-html']],
      ];

      // Rendered HTML.
      $pane['rendered'] = [
        '#theme_wrappers' => ['container__markdown__rendered'],
        '#attributes' => ['class' => ['markdown-rendered']],
      ];

      $pane['rendered'][$formatted->getFormat()] = [
        '#theme_wrappers' => ['container__grid_12'],
        '#markup' => $formatted->getRenderedHtml(),
      ];

      $code = [
        '#type' => 'html_tag',
        '#tag' => 'code',
        '#attributes' => ['class' => ['language-html']],
        '#value' => $formatted->getEscapedHtml(),
      ];

      $pane['html'][$formatted->getFormat()] = [
        '#type' => 'html_tag',
        '#tag' => 'pre',
        '#value' => \Drupal::service('renderer')->renderPlain($code),
      ];

      $tabs[] = $tab;
      $panes[] = $pane;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $options = [];

    // Extract the submitted markdown.
    if (($markdown = $form_state->getValue('markdown')) && ($cached_markdown = $this->demo->setMarkdown($markdown))) {
      list($type, $id) = explode(':', $cached_markdown->getId());
      $options['query'][$type] = $id;
    }

    // Redirect back to the front page.
    $form_state->setRedirect('<front>', [], $options);
  }

}
