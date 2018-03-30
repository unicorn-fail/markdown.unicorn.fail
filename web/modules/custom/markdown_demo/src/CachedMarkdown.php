<?php

namespace Drupal\markdown_demo;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;

class CachedMarkdown {

  use DependencySerializationTrait;
  use StringTranslationTrait;

  /**
   * @var array
   */
  protected static $formats = ['cmark', 'commonmark', 'parsedownextra', 'php_markdown'];

  /**
   * @var array
   */
  protected $benchmarks;

  /**
   * @var int
   */
  protected $expire;

  /**
   * @var string
   */
  protected $id;

  /**
   * @var string
   */
  protected $markdown;

  /**
   * @var \Drupal\markdown_demo\FormattedMarkdown[]
   */
  protected $formatted;

  protected $size;

  /**
   * CachedMarkdown constructor.
   *
   * @param string $id
   *   A unique identifier.
   * @param string $markdown
   *   The raw markdown string.
   * @param int $expire
   *   Optional. The UNIX timestamp of when this instance expires. Defaults to
   *   1 day from now.
   */
  public function __construct($id, $markdown, $expire = NULL) {
    $this->id = $id;
    $this->expire = $expire ?: strtotime('+1 day', \Drupal::time()->getRequestTime());
    $this->setMarkdown($markdown);
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

  public function getBenchmarks() {
    return $this->benchmarks;
  }

  public function getExpire() {
    return $this->expire;
  }

  public function getFormatted() {
    return $this->formatted;
  }

  public function getId() {
    return $this->id;
  }

  public function getMarkdown() {
    return $this->markdown;
  }

  public function getSize() {
    return $this->size;
  }

  public function matches($markdown) {
    return static::normalizeMarkdown($markdown) === $this->markdown;
  }

  public static function normalizeMarkdown($markdown) {
    return trim(preg_replace('/\\r\\n|\\n/', "\n", $markdown));
  }

  public function setMarkdown($markdown) {
    $this->markdown = static::normalizeMarkdown($markdown);
    $this->size = Unicode::strlen($markdown);

    // Parse all supported formats.
    $this->formatted = [];
    foreach (static::$formats as $format) {
      $this->formatted[$format] = new FormattedMarkdown($format, $markdown);
    }

    $this->benchmarks = [];
    foreach ($this->formatted as $format => $formatted) {
      $current_time = $formatted->getDiff();
      foreach ($this->formatted as $format2 => $formatted2) {
        if ($format === $format2) {
          continue;
        }
        $diff = $formatted2->getDiff()->f / $current_time->f;
        if ($diff > 0) {
          $this->benchmarks[$format][$format2] = $this->t('@amount times faster than @parser', [
            '@amount' => ceil($diff),
            '@parser' => $formatted2->getLabel(),
          ]);
        }
        elseif ($diff < 0) {
          $this->benchmarks[$format][$format2] = $this->t('@amount times slower than @parser', [
            '@amount' => ceil($diff),
            '@parser' => $formatted2->getLabel(),
          ]);
        }
      }
    }

    // Cache the instance.
    \Drupal::cache('markdown')->set($this->id, $this, $this->expire);

    return $this;
  }

  /**
   * @param string $markdown
   * @param string $id
   * @param int $expire
   *
   * @return static
   */
  public static function create($markdown, $id = NULL, $expire = NULL) {
    if ($id === NULL) {
      $id = MarkdownDemo::CACHE_PARAMETER . ':' . Crypt::hashBase64($markdown);
    }

    // Attempt to load an already cached item or create a new one if needed.
    return static::load($id) ?: new static($id, $markdown, $expire);
  }

  public static function createFromPath($path, $id = NULL, $expire = NULL) {
    return static::create(file_get_contents($path), $id, $expire);
  }

  public static function createFromUrl($url, $id = NULL, $expire = NULL) {
    try {
      $contents = \Drupal::httpClient()->get($url)->getBody()->getContents();
    }
    catch (\Exception $e) {
      $contents = $e->getMessage();
    }
    return static::create($contents, $id, $expire);
  }

  /**
   * @param null $id
   *
   * @return static|null
   */
  public static function load($id = NULL) {
    $id = $id ?: \Drupal::requestStack()->getCurrentRequest()->query->get(MarkdownDemo::CACHE_PARAMETER);
    if ($id && ($cache = \Drupal::cache('markdown')->get($id)) && $cache->data instanceof static) {
      return $cache->data;
    }
    return NULL;
  }

}
