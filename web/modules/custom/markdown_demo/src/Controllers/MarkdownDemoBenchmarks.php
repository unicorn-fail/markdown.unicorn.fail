<?php

namespace Drupal\markdown_demo\Controllers;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\Markup;
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

    $build['approximation'] = [
      '#theme_wrappers' => ['container'],
      '#attributes' => ['class' => ['alert', 'alert-info', 'margin-bottom']],
      '#markup' => $this->t('<strong>NOTE:</strong> The following benchmarks should be viewed within the context of a normal Drupal installation. While fairly accurate, they may include very slight overhead due to the nature of Drupal rendering and PHP autoloading. This may not be the most direct or "raw" comparison of the individual Markdown parsers themselves.'),
    ];

    $build['benchmarks'] = [
      '#type' => 'table',
      '#attributes' => ['class' => ['benchmarks']],
      '#header' => [],
      '#rows' => [],
    ];

    $header =& $build['benchmarks']['#header'];
    $rows =& $build['benchmarks']['#rows'];

    $parsed_ms = [];
    $rendered_ms = [];
    $total_ms = [];

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

      $parsed_ms[] = array_map(function (/** @type \Drupal\markdown_demo\BenchmarkedFormattedMarkdown $formatted */$formatted) {
        return $formatted->getMilliseconds('parsed', FALSE);
      }, $example->getFormatted());

      $rendered_ms[] = array_map(function (/** @type \Drupal\markdown_demo\BenchmarkedFormattedMarkdown $formatted */$formatted) {
        return $formatted->getMilliseconds('rendered', FALSE);
      }, $example->getFormatted());

      $total_ms[] = $example_total_ms = array_map(function (/** @type \Drupal\markdown_demo\BenchmarkedFormattedMarkdown $formatted */$formatted) {
        return $formatted->getMilliseconds('total', FALSE);
      }, $example->getFormatted());

      $max = max($example_total_ms);
      $min = min($example_total_ms);
      foreach ($example->getFormatted() as $format => $formatted) {
        $ms = $formatted->getMilliseconds('total', FALSE);
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
        $benchmark = $formatted->buildBenchmarkAverages('all');
        $benchmark['#attributes']['class'][] = $class;
        $row[] = ['data' => $benchmark];
      }

      $rows[] = $row;
    }

    $header = array_merge([''], array_values($formats));

    $sums = [];
    foreach (['parsed_ms', 'rendered_ms', 'total_ms'] as $prop) {
      $type = basename($prop, '_ms');
      if (!isset($sums[$type])) {
        $sums[$type] = [];
      }
      foreach ($$prop as $times) {
        foreach ($times as $format => $ms) {
          if (!isset($sums[$type][$format])) {
            $sums[$type][$format] = 0;
          }
          $sums[$type][$format] += $ms;
        }
      }
    }

    $averages = [];
    foreach ($sums as $type => $times) {
      $prop = $type . '_ms';
      $count = count($$prop);
      foreach ($times as $format => $sum) {
        $averages[$type][$format] = $sum / $count;
      }
    }

    // Parsed averages.
    $build['parsed'] = $this->buildFasterSlowerTable('parsed', $averages, $formats);
    $build['parsed']['#caption'] = $this->t('Parsing Averages');

    // Render averages.
    $build['rendered'] = $this->buildFasterSlowerTable('rendered', $averages, $formats);
    $build['rendered']['#caption'] = $this->t('Rendering Averages');

    // Total averages.
    $build['total'] = $this->buildFasterSlowerTable('total', $averages, $formats);
    $build['total']['#caption'] = $this->t('Total Averages');

    return $build;
  }

  protected function getFasterSlowerAverages($averages) {
    $result = [];
    foreach ($averages as $format => $average) {
      $current_average = $average;
      foreach ($averages as $format2 => $average2) {
        if ($format === $format2) {
          continue;
        }
        if ($current_average < $average2) {
          $result[$format][$format2] = $this->t('<span class="markdown-benchmark markdown-benchmark--fast">~@amount<em>x</em> faster</span>', [
            '@amount' => ceil($average2 / $current_average),
          ]);
        }
        else {
          $result[$format][$format2] = $this->t('<span class="markdown-benchmark markdown-benchmark--slow">~@amount<em>x</em> slower</span>', [
            '@amount' => ceil($current_average / $average2),
          ]);
        }
      }
    }
    return $result;
  }

  protected function buildFasterSlowerTable($type, $averages, $formats) {
    $build = [
      '#type' => 'table',
      '#attributes' => ['class' => [Html::cleanCssIdentifier($type . '-averages')]],
      '#header' => array_merge([''], $formats),
      '#rows' => [],
    ];

    $columns = array_flip(array_keys($formats));
    foreach ($this->getFasterSlowerAverages($averages[$type]) as $format => $data) {
      $ms = $averages[$type][$format];
      $max = max($averages[$type]);
      $min = min($averages[$type]);

      if ($ms === $max) {
        $class = 'markdown-benchmark--slow';
      }
      elseif ($ms === $min) {
        $class = 'markdown-benchmark--fast';
      }
      else {
        $class = 'markdown-benchmark--medium';
      }

      $row = [[
        'data' => [
          '#markup' => new FormattableMarkup('<span class="markdown-benchmark markdown-benchmark--average @class">~@ms<em>ms</em></span> @format', [
            '@format' => $formats[$format],
            '@ms' => number_format($ms, 2),
            '@class' => $class,
          ]),
        ],
      ]];

      $row[$columns[$format] + 1] = ['data' => ['#markup' => Markup::create('&mdash;')]];
      foreach ($data as $format2 => $markup) {
        $row[$columns[$format2] + 1] = $markup;
      }

      ksort($row);

      $build['#rows'][] = $row;
    }
    return $build;
  }

}
