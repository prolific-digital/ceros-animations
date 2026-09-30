<?php

namespace CerosEmbed;

defined( 'ABSPATH' ) || exit;

/**
 * Class CerosPostType
 */
class CerosPostType {

  /**
   * Initialize the shortcode and hooks
   */
  public function __construct() {
    add_action('init', array($this, 'custom_post_type_ceros'), 0);
  }

  /**
   * Register Custom Post Type: Ceros
   */
  public function custom_post_type_ceros() {
    $labels = array(
      'name' => _x('Ceros', 'Post Type General Name', 'ceros-embed'),
      'singular_name' => _x('Ceros', 'Post Type Singular Name', 'ceros-embed'),
      'menu_name' => __('Ceros', 'ceros-embed'),
      'name_admin_bar' => __('Ceros', 'ceros-embed'),
      'archives' => __('Ceros Archives', 'ceros-embed'),
      'attributes' => __('Ceros Attributes', 'ceros-embed'),
      'parent_item_colon' => __('Parent Ceros:', 'ceros-embed'),
      'all_items' => __('All Ceros', 'ceros-embed'),
      'add_new_item' => __('Add New Ceros', 'ceros-embed'),
      'add_new' => __('Add New', 'ceros-embed'),
      'new_item' => __('New Ceros', 'ceros-embed'),
      'edit_item' => __('Edit Ceros', 'ceros-embed'),
      'update_item' => __('Update Ceros', 'ceros-embed'),
      'view_item' => __('View Ceros', 'ceros-embed'),
      'view_items' => __('View Ceros', 'ceros-embed'),
      'search_items' => __('Search Ceros', 'ceros-embed'),
      'not_found' => __('Not found', 'ceros-embed'),
      'not_found_in_trash' => __('Not found in Trash', 'ceros-embed'),
      'featured_image' => __('Featured Image', 'ceros-embed'),
      'set_featured_image' => __('Set featured image', 'ceros-embed'),
      'remove_featured_image' => __('Remove featured image', 'ceros-embed'),
      'use_featured_image' => __('Use as featured image', 'ceros-embed'),
      'insert_into_item' => __('Insert into Ceros', 'ceros-embed'),
      'uploaded_to_this_item' => __('Uploaded to this Ceros', 'ceros-embed'),
      'items_list' => __('Ceros list', 'ceros-embed'),
      'items_list_navigation' => __('Ceros list navigation', 'ceros-embed'),
      'filter_items_list' => __('Filter Ceros list', 'ceros-embed'),
    );

    $args = array(
      'label' => __('Ceros', 'ceros-embed'),
      'description' => __('Ceros created with your plugin.', 'ceros-embed'),
      'labels' => $labels,
      'supports' => array('title'),
      'taxonomies' => array(),
      'hierarchical' => false,
      'public' => false,
      'show_ui' => true,
      'show_in_menu' => true,
      'menu_position' => 5,
      'menu_icon' => 'dashicons-images-alt2',
      'show_in_admin_bar' => false,
      'show_in_nav_menus' => false,
      'can_export' => true,
      'has_archive' => false,
      'exclude_from_search' => true,
      'publicly_queryable' => false,
      'capability_type' => 'post',
    );

    register_post_type('ceros', $args);
  }
}
