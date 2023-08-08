<?php

/**
 * Plugin Name: Ceros Embed
 * Plugin URI: https://prolificdigital.com
 * Description: Self host your Ceros animations.
 * Version: 1.0.0
 * Author: Prolific Digital
 * Author URI: https://prolificdigital.com
 * Text Domain: ceros-embed
 * License: GPL3.0
 */

// If this file is accessed directly, abort.
if (!defined('WPINC')) {
  die;
}

// Include Composer's autoloader
require 'vendor/autoload.php';

use CerosEmbed\InitCeros;

$Ceros_Init = new InitCeros();
