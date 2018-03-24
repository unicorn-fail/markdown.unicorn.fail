<?php

namespace Drupal\markdown_demo\Controllers;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\markdown_demo\MarkdownDemo;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MarkdownDemoBenchmarks extends ControllerBase {

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

  public function benchmarks() {
    \Drupal::service('page_cache_kill_switch')->trigger();

    $build = [
      '#type' => 'table',
      '#header' => [],
      '#rows' => [],
    ];

    $header =& $build['#header'];
    $rows =& $build['#rows'];

    $formats = [];

    foreach ($this->demo->getExamples() as $example) {
      list ($type, $id) = explode(':', $example->getId());
      $row = [Link::fromTextAndUrl($id, Url::fromRoute('<front>', [], ['query' => [$type => $id]]))];

      foreach ($example->getFormatted() as $format => $formatted) {
        if (!isset($formats[$format])) {
          $formats[$format] = $formatted->getLabel();
        }
        $row[] = $formatted->getTime();
      }

      $rows[] = $row;
    }

    $header = array_merge([$this->t('Source')], array_values($formats));

    return $build;
  }

}
