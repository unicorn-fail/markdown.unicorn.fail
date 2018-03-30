<?php

namespace Drupal\markdown_demo\Controllers;

use Drupal\Core\Controller\ControllerBase;
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
      $row = [
        ['data' => [
          'link' => [
            '#type' => 'link',
            '#title' => $example->getLabel(),
            '#url' =>  Url::fromRoute('<front>', [], ['query' => [$type => $id]]),
          ],
          'size' => [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#attributes' => ['class' => ['markdown-benchmark']],
            '#value' => format_size($example->getSize()),
          ]
        ]]
      ];

      $rendered_ms = array_map(function (/** @type \Drupal\markdown_demo\FormattedMarkdown $formatted */$formatted) {
        return $formatted->getMilliseconds(TRUE);
      }, $example->getFormatted());

      $max = max($rendered_ms);
      $min = min($rendered_ms);
      foreach ($example->getFormatted() as $format => $formatted) {
        $ms = $formatted->getMilliseconds(TRUE);
        if ($ms === $max) {
          $class = 'markdown-benchmark--slow';
        }
        elseif ($ms === $min) {
          $class = 'markdown-benchmark--fast';
        }
        else {
          $class = 'markdown-benchmark--medium';
        }
        if (!isset($formats[$format])) {
          $formats[$format] = $formatted->getLabel();
        }
        $benchmark = $formatted->buildBenchmark(TRUE);
        $benchmark['#attributes']['class'][] = $class;
        $row[] = ['data' => $benchmark];
      }

      $rows[] = $row;
    }

    $header = array_merge([$this->t('Source')], array_values($formats));

    return $build;
  }

}
