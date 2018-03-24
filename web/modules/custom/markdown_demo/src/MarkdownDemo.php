<?php

namespace Drupal\markdown_demo;

use Drupal\Component\Utility\Bytes;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Cache\Cache;
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
   * The URL parameter used for cached markdown requests.
   */
  const CACHE_PARAMETER = 'cache';

  /**
   * The URL parameter used for predefined example markdown requests.
   */
  const EXAMPLE_PARAMETER = 'example';

  /**
   * The maximum file size limit a user is allowed to upload.
   */
  const SIZE_LIMIT = '50K';

  /**
   * A list of examples.
   */
  const EXAMPLES = [
    'bootstrap' => 'https://github.com/twbs/bootstrap/raw/v4-dev/README.md',
    'commonmark' => 'https://github.com/commonmark/CommonMark/raw/master/README.md',
    'homebrew' => 'https://github.com/Homebrew/homebrew-php/raw/master/README.md',
    'jquery' => 'https://github.com/jquery/jquery/raw/master/README.md',
    'markdown' => 'https://daringfireball.net/projects/markdown/syntax.text',
    'php-markdown' => 'https://github.com/michelf/php-markdown/raw/lib/Readme.md',
    'textmate' => 'https://github.com/textmate/textmate/raw/master/README.md',
    'thephpleague/commonmark' => 'https://github.com/thephpleague/commonmark/raw/master/README.md',
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
   * @var \Drupal\markdown_demo\CachedMarkdown[]
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

    $this->cachedMarkdown[static::CACHE_PARAMETER . ':expired'] = CachedMarkdown::load(static::CACHE_PARAMETER . ':expired') ?: CachedMarkdown::create('Cached URLs are only valid for 24 hours. This cached URL has expired and is no longer available.', static::CACHE_PARAMETER . ':expired', Cache::PERMANENT);
    $this->cachedMarkdown[static::CACHE_PARAMETER . ':too-large'] = CachedMarkdown::load(static::CACHE_PARAMETER . ':too-large') ?: CachedMarkdown::create($this->t('For the purposes of this demonstration site, submitted Markdown length may not exceed @size.', [
      '@size' => static::SIZE_LIMIT,
    ]), static::CACHE_PARAMETER . ':too-large', Cache::PERMANENT);
    $this->cachedMarkdown['example:default'] = CachedMarkdown::load('example:default') ?: CachedMarkdown::createFromPath(drupal_get_path('module', 'markdown') . '/README.md', 'example:default', Cache::PERMANENT);

    foreach (static::EXAMPLES as $id => $url) {
      $this->cachedMarkdown["example:$id"] = CachedMarkdown::load("example:$id") ?: CachedMarkdown::createFromUrl($url, "example:$id", Cache::PERMANENT);
    }

    // Remove expired markdown objects.
    $this->cache->garbageCollection();
  }

  /**
   * Retrieves the cached examples.
   *
   * @return \Drupal\markdown_demo\CachedMarkdown[]
   */
  public function getExamples() {
    $examples = [];
    foreach (array_keys($this->cachedMarkdown) as $key) {
      if (strpos($key, 'example:') === 0) {
        $examples[$key] = $this->cachedMarkdown[$key];
      }
    }
    return $examples;
  }

  public function getMarkdown() {
    $query = $this->requestStack->getCurrentRequest()->query;

    // Retrieve predefined example markdown object.
    if (($example = $query->get(static::EXAMPLE_PARAMETER)) && isset($this->cachedMarkdown["example:$example"])) {
      return $this->cachedMarkdown["example:$example"];
    }

    // Retrieve cached markdown object.
    if ($id = $query->get(static::CACHE_PARAMETER)) {
      return CachedMarkdown::load(static::CACHE_PARAMETER . ":$id") ?: $this->cachedMarkdown[static::CACHE_PARAMETER . ':expired'];
    }

    return $this->cachedMarkdown['example:default'];
  }

  protected function findMarkdown($markdown) {
    $found = FALSE;
    foreach ($this->cachedMarkdown as $cached_markdown) {
      if ($markdown === $cached_markdown->getMarkdown()) {
        $found = TRUE;
        break;
      }
    }
    return $found;
  }

  /**
   * @param $markdown
   *
   * @return \Drupal\markdown_demo\CachedMarkdown|null
   */
  public function setMarkdown($markdown) {
    // Replace new lines.
    $markdown = trim(preg_replace('/\\r\\n|\\n/', "\n", $markdown));

    // Determine file size.
    $size = Unicode::strlen($markdown);

    if ($size > Bytes::toInt(static::SIZE_LIMIT)) {
      return $this->cachedMarkdown[static::CACHE_PARAMETER . ':too-large'];
    }

    // Don't duplicate existing cached markdown.
    if ($size && !$this->findMarkdown($markdown)) {
      return CachedMarkdown::create($markdown);
    }
  }

}
