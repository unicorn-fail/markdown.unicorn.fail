<?php

namespace Drupal\markdown_demo;

class CachedFormattedMarkdown extends ExampleFormattedMarkdown {

  /**
   * {@inheritdoc}
   */
  const ITERATIONS = 1;

  /**
   * {@inheritdoc}
   */
  const TYPE = 'cached';

  /**
   * {@inheritdoc}
   */
  protected $expire = '+1 day';

}
