<?php

namespace Drupal\markdown_demo;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\markdown\ParsedMarkdown;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;

class ExampleFormattedMarkdown extends ParsedMarkdown {

  use DependencySerializationTrait;
  use StringTranslationTrait;

  /**
   * The amount of of loop iterations used to average the results.
   *
   * @var int
   */
  const ITERATIONS = 10;

  /**
   * The type of object this is.
   *
   * @var string
   */
  const TYPE = 'example';

  /**
   * The default format to use when none is specified.
   *
   * @var string
   */
  protected static $defaultFormat = 'commonmark';

  /**
   * The formats to iterate over.
   *
   * @var array
   */
  protected static $formats = ['cmark', 'commonmark', 'parsedownextra', 'php_markdown'];

  /**
   * The benchmarked formatted markdown.
   *
   * @var \Drupal\markdown_demo\BenchmarkedFormattedMarkdown[]
   */
  protected $formatted;

  /**
   * {@inheritdoc}
   */
  public function __construct($markdown = '') {
    // Parse all supported formats.
    $formatted = NULL;
    $this->formatted = [];
    foreach (static::$formats as $format) {
      $formatted = new BenchmarkedFormattedMarkdown($markdown, $format, static::ITERATIONS);
      $this->formatted[$format] = $formatted;
    }

    parent::__construct($markdown, $this->getFormatted(static::$defaultFormat)->getRenderedHtml());
  }

  public static function createFromPath($path) {
    return static::create(file_get_contents($path));
  }

  public static function createFromUrl($url) {
    if ($url instanceof Url) {
      $url = $url->setAbsolute()->toString();
    }
    $response = \Drupal::httpClient()->get($url);
    if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 400) {
      $contents = $response->getBody()->getContents();
    }
    else {
      throw new FileNotFoundException((string) $url);
    }
    return static::create($contents);
  }

  /**
   * {@inheritdoc}
   */
  public static function load($id = NULL) {
    $query = \Drupal::requestStack()->getCurrentRequest()->query;

    // Allow authenticated users to bypass cache.
    if ($query->get('bypass-cache') && \Drupal::currentUser()->isAuthenticated()) {
      return NULL;
    }

    // Retrieve the identifier from the URL.
    if (!$id) {
      $id = $query->get(static::TYPE);
    }

    // Prepend the type prefix.
    if ($id) {
      $id = static::TYPE . ":$id";
    }

    return parent::load($id);
  }

  public function buildExpire() {
    $expire = $this->getExpire() ?: NULL;
    $access = $expire && $expire > 0;
    if ($access) {
      /** @var \Drupal\Core\Datetime\DateFormatterInterface $date_formatter */
      $date_formatter = \Drupal::service('date.formatter');
      $expire = new FormattableMarkup('<time class="timeago" datetime="@datetime" title="@label">@label</time>', [
        '@datetime' => $date_formatter->format($expire, 'custom', 'c'),
        '@label' => $date_formatter->formatTimeDiffUntil($expire),
      ]);
    }
    return [
      '#access' => $access,
      '#type' => 'item',
      '#title' => $this->t('Expires: '),
      '#wrapper_attributes' => ['class' => ['markdown-expires']],
      '#markup' => $expire,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getId() {
    return static::TYPE . ':' . parent::getId();
  }

  public function getFormatted($format = NULL) {
    if ($format === NULL) {
      return $this->formatted;
    }

    return isset($this->formatted[$format]) ? $this->formatted[$format] : NULL;
  }

}
