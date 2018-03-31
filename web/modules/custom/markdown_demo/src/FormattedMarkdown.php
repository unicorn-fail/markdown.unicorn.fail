<?php

namespace Drupal\markdown_demo;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Html;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\filter\Entity\FilterFormat;

class FormattedMarkdown {

  use DependencySerializationTrait;
  use StringTranslationTrait;

  /**
   * The filter format to use.
   *
   * @var string
   */
  protected $format;

  /**
   * The parsed benchmark.
   *
   * @var \Drupal\markdown\MarkdownBenchmark
   */
  protected $benchmarkParsed;

  /**
   * The rendered benchmark.
   *
   * @var \Drupal\markdown\MarkdownBenchmark
   */
  protected $benchmarkRendered;

  /**
   * The total benchmark.
   *
   * @var \Drupal\markdown\MarkdownBenchmark
   */
  protected $benchmarkTotal;

  /**
   * FormattedMarkdown constructor.
   *
   * @param string $format
   *   The format identifier to use.
   * @param string $markdown
   *   The markdown being formatted.
   */
  public function __construct($format, $markdown) {
    $this->format = $format;
    list($this->benchmarkParsed, $this->benchmarkRendered, $this->benchmarkTotal) = $this->getParser()->benchmark($markdown, $format);
  }

  /**
   * Builds a render array of the human-readable benchmark diff.
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
  public function buildBenchmark($type = 'total') {
    $rendered = $this->benchmarkRendered->getMilliseconds();
    $parsed = $this->benchmarkParsed->getMilliseconds();
    $total = $this->benchmarkTotal->getMilliseconds();

    switch ($type) {
      case 'parsed':
        $label = new FormattableMarkup('<span class="parsed">~@parsed<em>ms</em></span>', ['@parsed' => $parsed]);
        $title = $this->t('Parsed Time (rendered @renderedms, total @totalms)', [
          '@rendered' => $rendered,
          '@total' => $total,
        ]);
        break;

      case 'rendered':
        $label = new FormattableMarkup('<span class="rendered">~@rendered<em>ms</em></span>', ['@rendered' => $rendered]);
        $title = $this->t('Rendered Time (parsed @parsedms, total @totalms)', [
          '@parsed' => $parsed,
          '@total' => $total,
        ]);
        break;

      case 'all':
        $label = new FormattableMarkup('<span class="parsed">~@parsed<em>ms</em></span> / <span class="rendered">~@rendered<em>ms</em></span> / <span class="total">~@total<em>ms</em></span>', [
          '@parsed' => $parsed,
          '@rendered' => $rendered,
          '@total' => $total,
        ]);
        $title = $this->t('Parsed / Rendered / Total');
        break;

      // Total.
      default:
        $label = new FormattableMarkup('<span class="total">~@total<em>ms</em></span>', ['@total' => $total]);
        $title = $this->t('Total Time (parsed @parsedms, rendered @renderedms)', [
          '@parsed' => $parsed,
          '@rendered' => $rendered,
        ]);
        break;
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
    switch ($type) {
      case 'parsed':
        return $this->benchmarkParsed->getDiff();

      case 'rendered':
        return $this->benchmarkRendered->getDiff();

      default:
        return $this->benchmarkTotal->getDiff();
    }
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
    switch ($type) {
      case 'parsed':
        return $this->benchmarkParsed->getEnd();

      case 'rendered':
        return $this->benchmarkRendered->getEnd();

      default:
        return $this->benchmarkTotal->getEnd();
    }
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
    switch ($type) {
      case 'parsed':
        return $this->benchmarkParsed->getMilliseconds($format);

      case 'rendered':
        return $this->benchmarkRendered->getMilliseconds($format);

      default:
        return $this->benchmarkTotal->getMilliseconds($format);
    }
  }

  /**
   * Retrieves the MarkdownParser plugin used for this format.
   *
   * @return \Drupal\markdown\Plugin\Markdown\MarkdownParserInterface
   *   The MarkdownParser plugin.
   */
  public function getParser() {
    /** @var \Drupal\filter\FilterFormatInterface $format */
    /** @var \Drupal\markdown\Plugin\Filter\MarkdownFilterInterface $filter */
    if (($format = FilterFormat::load($this->format)) && ($filter = $format->filters('markdown'))) {
      return $filter->getParser();
    }
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
    switch ($type) {
      case 'parsed':
        return $this->benchmarkParsed->getResult();

      case 'rendered':
        return $this->benchmarkRendered->getResult();

      default:
        return $this->benchmarkTotal->getResult();
    }
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
    switch ($type) {
      case 'parsed':
        return $this->benchmarkParsed->getStart();

      case 'rendered':
        return $this->benchmarkRendered->getStart();

      default:
        return $this->benchmarkTotal->getStart();
    }
  }

}
