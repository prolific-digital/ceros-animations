<?php

namespace CerosEmbed;

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
      'name' => _x('Ceros', 'Post Type General Name', 'your-plugin-textdomain'),
      'singular_name' => _x('Ceros', 'Post Type Singular Name', 'your-plugin-textdomain'),
      'menu_name' => __('Ceros', 'your-plugin-textdomain'),
      'name_admin_bar' => __('Ceros', 'your-plugin-textdomain'),
      'archives' => __('Ceros Archives', 'your-plugin-textdomain'),
      'attributes' => __('Ceros Attributes', 'your-plugin-textdomain'),
      'parent_item_colon' => __('Parent Ceros:', 'your-plugin-textdomain'),
      'all_items' => __('All Ceros', 'your-plugin-textdomain'),
      'add_new_item' => __('Add New Ceros', 'your-plugin-textdomain'),
      'add_new' => __('Add New', 'your-plugin-textdomain'),
      'new_item' => __('New Ceros', 'your-plugin-textdomain'),
      'edit_item' => __('Edit Ceros', 'your-plugin-textdomain'),
      'update_item' => __('Update Ceros', 'your-plugin-textdomain'),
      'view_item' => __('View Ceros', 'your-plugin-textdomain'),
      'view_items' => __('View Ceros', 'your-plugin-textdomain'),
      'search_items' => __('Search Ceros', 'your-plugin-textdomain'),
      'not_found' => __('Not found', 'your-plugin-textdomain'),
      'not_found_in_trash' => __('Not found in Trash', 'your-plugin-textdomain'),
      'featured_image' => __('Featured Image', 'your-plugin-textdomain'),
      'set_featured_image' => __('Set featured image', 'your-plugin-textdomain'),
      'remove_featured_image' => __('Remove featured image', 'your-plugin-textdomain'),
      'use_featured_image' => __('Use as featured image', 'your-plugin-textdomain'),
      'insert_into_item' => __('Insert into Ceros', 'your-plugin-textdomain'),
      'uploaded_to_this_item' => __('Uploaded to this Ceros', 'your-plugin-textdomain'),
      'items_list' => __('Ceros list', 'your-plugin-textdomain'),
      'items_list_navigation' => __('Ceros list navigation', 'your-plugin-textdomain'),
      'filter_items_list' => __('Filter Ceros list', 'your-plugin-textdomain'),
    );

    $args = array(
      'label' => __('Ceros', 'your-plugin-textdomain'),
      'description' => __('Ceros created with your plugin.', 'your-plugin-textdomain'),
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
