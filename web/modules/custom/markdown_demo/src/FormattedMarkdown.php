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
   * @var string
   */
  protected $format;


  /**
   * The raw benchmark.
   *
   * @var \Drupal\markdown_demo\Benchmark
   */
  protected $parsedBenchmark;

  /**
   * The Drupal benchmark.
   *
   * @var \Drupal\markdown_demo\Benchmark
   */
  protected $renderedBenchmark;

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
    $this->renderedBenchmark = Benchmark::create('check_markup', [$markdown, $format]);
    $this->parsedBenchmark = Benchmark::create([$this->getParser(), 'parse'], [$markdown]);
  }

  /**
   * Builds a render array of the human-readable benchmark diff.
   *
   * @param bool $all
   *   Flag indicating whether to show all benchmark times in the label
   *   (and use tooltip for labels).
   *
   * @return array
   *   A render array.
   */
  public function buildBenchmark($all = FALSE) {
    $total = $this->renderedBenchmark->getMilliseconds();
    $parsed = $this->parsedBenchmark->getMilliseconds();
    $rendered = $total - $parsed;

    if ($all) {
      $label = new FormattableMarkup('@parsed<em>ms</em> / @rendered<em>ms</em> / @total<em>ms</em>', [
        '@parsed' => $parsed,
        '@rendered' => $rendered,
        '@total' => $total,
      ]);
      $title = $this->t('Parsed / Rendered / Total');
    }
    else {
      $label = new FormattableMarkup('@total<em>ms</em>', ['@total' => $total]);
      $title = $this->t('Total Time (parsed @parsedms, rendered @renderedms)', [
        '@parsed' => $parsed,
        '@rendered' => $rendered,
      ]);
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
   * @param bool $raw
   *   Flag indicating whether to return the raw benchmark (not processed
   *   through Drupal's internal sub-systems, e.g. formatting, filtering,
   *   rendering, etc.).
   *
   * @return \DateInterval
   *   The benchmark difference.
   */
  public function getDiff($raw = FALSE) {
    return $raw ? $this->parsedBenchmark->getDiff() : $this->renderedBenchmark->getDiff();
  }

  /**
   * Retrieves the escaped rendered HTML.
   *
   * @param bool $raw
   *   Flag indicating whether to return the raw benchmark (not processed
   *   through Drupal's internal sub-systems, e.g. formatting, filtering,
   *   rendering, etc.).
   *
   * @return string
   *   The escaped rendered HTML.
   */
  public function getEscapedHtml($raw = FALSE) {
    return Html::escape($this->getRenderedHtml($raw));
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
   * @param bool $raw
   *   Flag indicating whether to return the raw benchmark (not processed
   *   through Drupal's internal sub-systems, e.g. formatting, filtering,
   *   rendering, etc.).
   *
   * @return float|int
   *   The milliseconds.
   */
  public function getMilliseconds($raw = FALSE) {
    return $raw ? $this->parsedBenchmark->getMilliseconds() : $this->renderedBenchmark->getMilliseconds();
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
   * @param bool $raw
   *   Flag indicating whether to return the raw benchmark (not processed
   *   through Drupal's internal sub-systems, e.g. formatting, filtering,
   *   rendering, etc.).
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   The rendered markup.
   */
  public function getRenderedHtml($raw = FALSE) {
    return $raw ? $this->parsedBenchmark->getResult() : $this->renderedBenchmark->getResult();
  }

  /**
   * Retrieve the benchmark start time.
   *
   * @param bool $raw
   *   Flag indicating whether to return the raw benchmark (not processed
   *   through Drupal's internal sub-systems, e.g. formatting, filtering,
   *   rendering, etc.).
   *
   * @return bool|\DateTime
   */
  public function getStart($raw = FALSE) {
    return $raw ? $this->parsedBenchmark->getStart() : $this->renderedBenchmark->getStart();
  }

  /**
   * Retrieves the benchmark stop time.
   *
   * @param bool $raw
   *   Flag indicating whether to return the raw benchmark (not processed
   *   through Drupal's internal sub-systems, e.g. formatting, filtering,
   *   rendering, etc.).
   *
   * @return \DateTime
   *   The benchmark stop time.
   */
  public function getStop($raw = FALSE) {
    return $raw ? $this->parsedBenchmark->getStop() : $this->renderedBenchmark->getStop();
  }

}
