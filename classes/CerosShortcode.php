<?php

namespace CerosEmbed;

/**
 * Class CerosShortcode
 */
class CerosShortcode {

  /**
   * Initialize the shortcode and hooks
   */
  public function __construct() {
    add_shortcode('ceros-animation', array($this, 'render_shortcode'));
  }

  /**
   * Render the Ceros project shortcode
   *
   * @param array $atts Shortcode attributes.
   * @return string
   */
  public function render_shortcode($atts) {
    // Shortcode attributes
    $atts = shortcode_atts(
      array(
        'id' => '',
        'type' => 'scrolling',
        'title' => '',
      ),
      $atts,
      'ceros-animation'
    );

    // Extract shortcode attributes
    $project_id = $atts['id'];
    $type = $atts['type'];
    $title = $atts['title'];
    $site_url = get_site_url();

    // Check if a project ID is provided
    if (empty($project_id)) {
      return '<p style="color: #f00;">Error: Ceros project ID is missing. Please provide a valid project ID.</p>';
    }

    // Get the public URL to the project folder within the 'embeds' directory
    $project_url = plugin_dir_url(__FILE__) . 'embeds/' . sanitize_file_name($project_id);

    // Embed Ceros project using the public URL
    $file_url = get_post_meta($project_id, '_ceros_file_upload', true);

    if ($type == 'scrolling') {
      // Scrolling
      $embed_code = '<div style="position: relative;width: auto;padding: 0 0 56.25%;height: 0;top: 0;left: 0;bottom: 0;right: 0;margin: 0;border: 0 none" id="experience-' . uniqid() . '" data-aspectRatio="1.77777778" data-mobile-aspectRatio="0.5625" data-tablet-aspectRatio="0.75"><iframe allowfullscreen src="' . esc_url($file_url) . '/index.html" style="position: absolute;top: 0;left: 0;bottom: 0;right: 0;margin: 0;padding: 0;border: 0 none;height: 100%;width: 100%" frameborder="0" class="ceros-experience" title="' . $title . '"></iframe></div><script type="text/javascript" src="survey-report/assets/scroll-proxy.min.js" data-ceros-origin-domains="' . $site_url . '"></script>';
    } else if ($type == 'full') {
      // Full height
      $embed_code = '<div style="position: relative;width: auto;padding: 0 0 432.73%;height: 0;top: 0;left: 0;bottom: 0;right: 0;margin: 0;border: 0 none" id="experience-' . uniqid() . '" data-aspectRatio="0.23108864" data-mobile-aspectRatio="0.08587786" data-tablet-aspectRatio="0.1221374"><iframe allowfullscreen src="' . esc_url($file_url) . '?heightOverride=5539&mobileHeightOverride=6288&tabletHeightOverride=6288" style="position: absolute;top: 0;left: 0;bottom: 0;right: 0;margin: 0;padding: 0;border: 0 none;height: 1px;width: 1px;min-height: 100%;min-width: 100%" frameborder="0" class="ceros-experience" title="' . $title . '" scrolling="no"></iframe></div><script type="text/javascript" src="survey-report/assets/scroll-proxy.min.js" data-ceros-origin-domains="' . $site_url . '"></script>';
    }

    return $embed_code;
  }
}
