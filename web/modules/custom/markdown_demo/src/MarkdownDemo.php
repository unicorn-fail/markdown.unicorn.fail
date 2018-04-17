<?php

namespace Drupal\markdown_demo;

use Drupal\Component\Utility\Bytes;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class MarkdownDemo.
 */
class MarkdownDemo {

  use DependencySerializationTrait;
  use StringTranslationTrait;

  /**
   * The maximum file size limit a user is allowed to upload.
   */
  const SIZE_LIMIT = '50K';

  /**
   * A list of examples.
   */
  const EXAMPLES = [
    'bootstrap' => [
      'label' => 'Bootstrap (README.md)',
      'url' => 'https://github.com/twbs/bootstrap/raw/v4-dev/README.md',
    ],
    'commonmark' => [
      'label' => 'CommonMark(README.md)',
      'url' => 'https://github.com/commonmark/CommonMark/raw/master/README.md',
    ],
    'homebrew' => [
      'label' => 'Homebrew (README.md)',
      'url' => 'https://github.com/Homebrew/brew/raw/master/README.md',
    ],
    'jquery' => [
      'label' => 'jQuery (README.md)',
      'url' => 'https://github.com/jquery/jquery/raw/master/README.md',
    ],
    'markdown' => [
      'label' => 'Markdown (Original/Syntax)',
      'url' => 'https://daringfireball.net/projects/markdown/syntax.text',
    ],
    'php-markdown' => [
      'label' => 'PHP Markdown (README.md)',
      'url' => 'https://github.com/michelf/php-markdown/raw/lib/Readme.md',
    ],
    'textmate' => [
      'label' => 'TextMate (README.md)',
      'url' => 'https://github.com/textmate/textmate/raw/master/README.md',
    ],
    'thephpleague/commonmark' => [
      'label' => 'thephpleague/commonmark (README.md)',
      'url' => 'https://github.com/thephpleague/commonmark/raw/master/README.md',
    ],
  ];

  /**
   * The backend cache.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * A list of cached markdown objects.
   *
   * @var \Drupal\markdown_demo\CachedFormattedMarkdown[]
   */
  protected $cachedMarkdown = [];

  /**
   * The current request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * MarkdownDemo constructor.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   */
  public function __construct(CacheBackendInterface $cache_backend, RequestStack $request_stack) {
    $this->cache = $cache_backend;
    $this->requestStack = $request_stack;

    $this->cachedMarkdown = array_merge(
      $this->getExamples(TRUE),
      [$this->getCacheExpiredMarkdown()],
      [$this->getCacheFileSizeLimitMarkdown()]
    );

    // Remove expired markdown objects.
    $this->cache->garbageCollection();
  }

  public function getCache($id = NULL) {
    if ($id === NULL) {
      $id = $this->requestStack->getCurrentRequest()->query->get(CachedFormattedMarkdown::TYPE);
    }

    // Immediately return if there is valid cache identifier.
    if (!$id) {
      return NULL;
    }

    return CachedFormattedMarkdown::load($id) ?: $this->getCacheExpiredMarkdown();
  }

  public function getCacheExpiredMarkdown() {
    return CachedFormattedMarkdown::load('expired') ?: CachedFormattedMarkdown::create('Cached URLs are only valid for 24 hours. This cached URL has expired and is no longer available.')
      ->setId('expired')
      ->save();
  }

  public function getCacheFileSizeLimitMarkdown() {
    return CachedFormattedMarkdown::load('file-size-limit') ?: CachedFormattedMarkdown::create($this->t('For the purposes of this demonstration site, submitted Markdown length may not exceed @size.', ['@size' => static::SIZE_LIMIT]))
      ->setId('file-size-limit')
      ->save();
  }

  public function getDefaultMarkdown() {
    return ExampleFormattedMarkdown::load('default') ?: ExampleFormattedMarkdown::createFromPath(drupal_get_path('module', 'markdown') . '/README.md')
      ->setId('default')
      ->setLabel('Markdown for Drupal (Default)')
      ->save();
  }

  public function getExample($id = NULL) {
    if ($id === NULL) {
      $id = $this->requestStack->getCurrentRequest()->query->get(ExampleFormattedMarkdown::TYPE);
    }

    // Immediately return if there is valid example identifier.
    if (!$id) {
      return NULL;
    }

    $examples = $this->getExamples();
    return isset($examples[$id]) ? $examples[$id] : NULL;
  }

  /**
   * Retrieves the cached examples.
   *
   * @param bool $include_default
   *   Flag indicating whether to include the default markdown file as part
   *   of the examples.
   *
   * @return \Drupal\markdown_demo\CachedFormattedMarkdown[]
   */
  public function getExamples($include_default = FALSE) {
    static $examples;
    if (!isset($examples)) {
      $examples = [];
      foreach (static::EXAMPLES as $id => $info) {
        $examples[$id] = ExampleFormattedMarkdown::load($id) ?: ExampleFormattedMarkdown::createFromUrl($info['url'])
          ->setId($id)
          ->setLabel($info['label'])
          ->save();
      }
    }

    if ($include_default) {
      return [$this->getDefaultMarkdown()] + $examples;
    }

    return $examples;
  }

  public function getMarkdown() {
    // Retrieve predefined example markdown object.
    if ($example = $this->getExample()) {
      return $example;
    }

    // Retrieve cached markdown object.
    if ($cache = $this->getCache()) {
      return $cache;
    }

    // Otherwise, just return the default markdown.
    return $this->getDefaultMarkdown();
  }

  protected function findMarkdown($markdown) {
    foreach ($this->cachedMarkdown as $cached_markdown) {
      if ($cached_markdown->matches($markdown)) {
        return $cached_markdown;
      }
    }
    return FALSE;
  }

  /**
   * @param $markdown
   *
   * @return \Drupal\markdown_demo\CachedFormattedMarkdown|null
   */
  public function setMarkdown($markdown) {
    // Determine file size.
    $size = Unicode::strlen($markdown);

    if ($size > Bytes::toInt(static::SIZE_LIMIT)) {
      return $this->getCacheFileSizeLimitMarkdown();
    }

    // Don't duplicate existing cached markdown.
    if ($size) {
      return $this->findMarkdown($markdown) ?: CachedFormattedMarkdown::create($markdown)->save();
    }
  }

}
