<?php

namespace Drupal\markdown_demo;

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

  protected $markdown;

  protected $escapedHtml;

  protected $renderedHtml;

  /**
   * @var \DateTime
   */
  protected $start;

  /**
   * @var \DateTime
   */
  protected $stop;

  /**
   * @var \DateInterval
   */
  protected $diff;

  /**
   * @var \Drupal\Core\StringTranslation\TranslatableMarkup
   */
  protected $time;

  public function __construct($format, $markdown) {
    $this->format = $format;
    $this->start = \DateTime::createFromFormat('U.u', sprintf('%.6F', microtime(TRUE)));
    $this->renderedHtml = check_markup($markdown, $format);
    $this->stop = \DateTime::createFromFormat('U.u', sprintf('%.6F', microtime(TRUE)));
    $this->escapedHtml = Html::escape($this->renderedHtml);

    // Determine the time difference.
    $this->diff = $this->start->diff($this->stop);

    $ms = 0;
    $ms += $this->diff->m * 2630000000;
    $ms += $this->diff->d * 86400000;
    $ms += $this->diff->h * 3600000;
    $ms += $this->diff->i * 60000;
    $ms += $this->diff->s * 1000;
    $ms += $this->diff->f * 1000;

    $this->time = $this->t("@ms<em>ms</em>", ['@ms' => abs(ceil($ms))]);
  }

  public function getFormat() {
    return $this->format;
  }

  public function getLabel() {
    /** @var \Drupal\filter\FilterFormatInterface $format */
    /** @var \Drupal\markdown\Plugin\Filter\MarkdownFilterInterface $markdown_filter */
    if (($format = FilterFormat::load($this->format)) && ($markdown_filter = $format->filters('markdown'))) {
      return $markdown_filter->getParser()->label();
    }
  }

  public function getMarkdown() {
    return $this->markdown;
  }

  public function getEscapedHtml() {
    return $this->escapedHtml;
  }

  public function getRenderedHtml() {
    return $this->renderedHtml;
  }

  public function getStart() {
    return $this->start;
  }

  public function getStop() {
    return $this->stop;
  }

  public function getDiff() {
    return $this->diff;
  }

  public function getTime() {
    return $this->time;
  }

}
