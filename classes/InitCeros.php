<?php

namespace CerosEmbed;

use CerosEmbed\CerosPostType;
use CerosEmbed\CerosPostOptions;
use CerosEmbed\CerosShortcode;

/**
 * Class InitCeros
 */
class InitCeros {
  private $customPostType;
  private $customPostOptions;
  private $cerosShortcode;

  public function __construct() {
    $this->initCustomPostType();
    $this->initCustomPostOptions();
    $this->initCerosShortcode();
  }

  private function initCustomPostType() {
    $this->customPostType = new CerosPostType();
  }
  private function initCustomPostOptions() {
    $this->customPostOptions = new CerosPostOptions();
  }
  private function initCerosShortcode() {
    $this->cerosShortcode = new CerosShortcode();
  }
}
