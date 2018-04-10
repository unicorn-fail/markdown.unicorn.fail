<?php

namespace Drupal\markdown_demo;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Html;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\markdown\Markdown;
use Drupal\markdown\MarkdownBenchmark;
use Drupal\markdown\MarkdownBenchmarkAverages;
use Drupal\markdown\Plugin\Markdown\MarkdownParserBenchmarkInterface;

class BenchmarkedFormattedMarkdown {

  use DependencySerializationTrait;
  use StringTranslationTrait;

  /**
   * The filter format to use.
   *
   * @var string
   */
  protected $format;

  /**
   * The benchmark averages.
   *
   * @var \Drupal\markdown\MarkdownBenchmarkAverages
   */
  protected $benchmarkAverages;

  /**
   * A MarkdownParser plugin configured for the current format.
   *
   * @var \Drupal\markdown\Plugin\Markdown\MarkdownParserInterface
   */
  protected $parser;

  /**
   * FormattedMarkdown constructor.
   *
   * @param string $markdown
   *   The markdown being formatted.
   * @param string $format
   *   The format identifier to use.
   * @param int $iterations
   *   The amount of of loop iterations used to average the results of each
   *   MarkdownParser benchmark.
   */
  public function __construct($markdown, $format, $iterations = 10) {
    $this->format = $format;
    $parser = $this->getParser();
    if ($parser instanceof MarkdownParserBenchmarkInterface) {
      $this->benchmarkAverages = $parser->benchmarkAverages($markdown, $format, $iterations);
    }
    else {
      $this->benchmarkAverages = MarkdownBenchmarkAverages::create($iterations, MarkdownBenchmark::create('fallback', NULL, NULL, $parser->parse($markdown)));
    }
  }

  /**
   * Builds a render array of the human-readable benchmark averages.
   *
   * @param string $type
   *   The type of benchmark to build, can be one of:
   *   - parsed
   *   - rendered
   *   - total (default)
   *   - all
   *
   * @return array
   *   A render array.
   */
  public function buildBenchmarkAverages($type = 'total') {
    if ($this->benchmarkAverages->hasBenchmarks()) {
      $variables = [
        '@average' => $this->benchmarkAverages->getIterationCount(),
        '@parsed' => $this->benchmarkAverages->getAverage('parsed'),
        '@rendered' => $this->benchmarkAverages->getAverage('rendered'),
        '@total' => $this->benchmarkAverages->getAverage('total'),
      ];
      switch ($type) {
        case 'parsed':
          $label = new FormattableMarkup('<span class="parsed">~@parsed<em>ms</em></span>', $variables);
          $title = $this->t('(@average𝒙) Parsed Time (rendered @renderedms, total @totalms)', $variables);
          break;

        case 'rendered':
          $label = new FormattableMarkup('<span class="rendered">~@rendered<em>ms</em></span>', $variables);
          $title = $this->t('(@average𝒙) Rendered Time (parsed @parsedms, total @totalms)', $variables);
          break;

        case 'all':
          $label = new FormattableMarkup('<span class="parsed">~@parsed<em>ms</em></span> / <span class="rendered">~@rendered<em>ms</em></span> / <span class="total">~@total<em>ms</em></span>', $variables);
          $title = $this->t('(@average𝒙) - Parsed / Rendered / Total', $variables);
          break;

        // Total.
        default:
          $label = new FormattableMarkup('<span class="total">~@total<em>ms</em></span>', $variables);
          $title = $this->t('(@average𝒙) Total Time (parsed @parsedms, rendered @renderedms)', $variables);
          break;
      }
    }
    else {
      $title = $this->t('This MarkdownParser plugin does not implement benchmark testing.');
      $label = $this->t('N/A');
    }

    return [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#attributes' => [
        'class' => ['markdown-benchmark'],
        'data-toggle' => 'tooltip',
        'data-placement' => 'bottom',
        'title' => $title,
      ],
      '#value' => $label,
    ];
  }

  /**
   * Retrieves the benchmark difference between start and stop times.
   *
   * @param string $type
   *   The type of benchmark to retrieve, can be one of:
   *   - parsed
   *   - rendered
   *   - total (default)
   *
   * @return \DateInterval
   *   The benchmark difference.
   */
  public function getDiff($type = 'total') {
    return $this->benchmarkAverages->getLastBenchmark($type)->getDiff();
  }

  /**
   * Retrieves the benchmark emd time.
   *
   * @param string $type
   *   The type of benchmark to retrieve, can be one of:
   *   - parsed
   *   - rendered
   *   - total (default)
   *
   * @return \DateTime
   *   The benchmark stop time.
   */
  public function getEnd($type = 'total') {
    return $this->benchmarkAverages->getLastBenchmark($type)->getEnd();
  }

  /**
   * Retrieves the escaped rendered HTML.
   *
   * @param string $type
   *   The type of benchmark to retrieve, can be one of:
   *   - parsed
   *   - rendered
   *   - total (default)
   *
   * @return string
   *   The escaped rendered HTML.
   */
  public function getEscapedHtml($type = 'total') {
    return Html::escape($this->getRenderedHtml($type));
  }

  /**
   * Retrieves the format identifier.
   *
   * @return string
   *   The format identifier.
   */
  public function getFormat() {
    return $this->format;
  }

  /**
   * Retrieves the human-readable label for the Markdown parser that was used.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The label.
   */
  public function getLabel() {
    if ($parser = $this->getParser()) {
      return $parser->label();
    }
    return $this->t('Unknown format: @format', ['@format' => $this->format]);
  }

  /**
   * Retrieves the amount of milliseconds from the diff.
   *
   * @param string $type
   *   The type of benchmark to retrieve, can be one of:
   *   - parsed
   *   - rendered
   *   - total (default)
   * @param bool $format
   *   Flag indicating whether to format the result to two decimals.
   *
   * @return string|float
   *   The milliseconds.
   */
  public function getMilliseconds($type = 'total', $format = TRUE) {
    return $this->benchmarkAverages->getLastBenchmark($type)->getMilliseconds($format);
  }

  /**
   * Retrieves the MarkdownParser plugin used for this format.
   *
   * @return \Drupal\markdown\Plugin\Markdown\MarkdownParserInterface
   *   The MarkdownParser plugin.
   */
  public function getParser() {
    if (!isset($this->parser)) {
      $this->parser = Markdown::create()->getParser(NULL, $this->getFormat());
    }
    return $this->parser;
  }

  /**
   * Retrieves the rendered HTML markup.
   *
   * @param string $type
   *   The type of benchmark to retrieve, can be one of:
   *   - parsed
   *   - rendered
   *   - total (default)
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   The rendered markup.
   */
  public function getRenderedHtml($type = 'total') {
    return $this->benchmarkAverages->getLastBenchmark($type)->getResult();
  }

  /**
   * Retrieve the benchmark start time.
   *
   * @param string $type
   *   The type of benchmark to retrieve, can be one of:
   *   - parsed
   *   - rendered
   *   - total (default)
   *
   * @return bool|\DateTime
   */
  public function getStart($type = 'total') {
    return $this->benchmarkAverages->getLastBenchmark($type)->getStart();
  }

}
