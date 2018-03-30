<?php

namespace Drupal\markdown_demo;

use Drupal\Core\StringTranslation\StringTranslationTrait;

class Benchmark {

  use StringTranslationTrait;

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
   * The result of the callback.
   *
   * @var mixed
   */
  protected $result;

  /**
   * Benchmark constructor.
   *
   * @param callable $callback
   *   A callback to benchmark.
   * @param array $args
   *   An array of arguments to pass to the callback.
   */
  public function __construct(callable $callback, array $args = []) {
    $this->start = \DateTime::createFromFormat('U.u', sprintf('%.6F', microtime(TRUE)));
    $this->result = call_user_func_array($callback, $args);
    $this->stop = \DateTime::createFromFormat('U.u', sprintf('%.6F', microtime(TRUE)));
    $this->diff = $this->start->diff($this->stop);
  }

  /**
   * Creates a new Benchmark instance.
   *
   * @param callable $callback
   *   A callback to benchmark.
   * @param array $args
   *   An array of arguments to pass to the callback.
   *
   * @return static
   */
  public static function create(callable $callback, array $args = []) {
    return new static($callback, $args);
  }

  /**
   * Retrieves the benchmark difference between start and stop times.
   *
   * @return \DateInterval
   *   The benchmark difference.
   */
  public function getDiff() {
    return $this->diff;
  }

  /**
   * Retrieves the amount of milliseconds from the diff.
   *
   * @return float|int
   *   The milliseconds.
   */
  public function getMilliseconds() {
    $ms = 0;
    $ms += $this->diff->m * 2630000000;
    $ms += $this->diff->d * 86400000;
    $ms += $this->diff->h * 3600000;
    $ms += $this->diff->i * 60000;
    $ms += $this->diff->s * 1000;
    $ms += $this->diff->f * 1000;
    return abs(ceil($ms));
  }

  /**
   * Retrieves the result of the callback that was invoked.
   *
   * @return mixed
   *   The callback result.
   */
  public function getResult() {
    return $this->result;
  }

  /**
   * Retrieves the benchmark start time.
   *
   * @return \DateTime
   *   The benchmark start time.
   */
  public function getStart() {
    return $this->start;
  }

  /**
   * Retrieves the benchmark stop time.
   *
   * @return \DateTime
   *   The benchmark stop time.
   */
  public function getStop() {
    return $this->stop;
  }

}
