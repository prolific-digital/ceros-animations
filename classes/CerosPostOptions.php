<?php

namespace CerosEmbed;

/**
 * Class CerosPostOptions
 */
class CerosPostOptions {

  /**
   * Initialize the shortcode and hooks
   */
  public function __construct() {
    add_action('add_meta_boxes', array($this, 'add_meta_box'));
    add_action('add_meta_boxes', array($this, 'add_meta_box_sidebar'));
    add_filter('upload_mimes', array(
      $this, 'custom_upload_mimes'
    ));
    add_action('save_post_ceros', array($this, 'handle_upload'));
  }

  // Add the custom meta box for the "Ceros" custom post type
  public function add_meta_box() {
    add_meta_box(
      'ceros_file_upload', // Meta box ID
      'Ceros Animation', // Meta box title
      array(
        $this, 'render_meta_box'
      ), // Callback to render the meta box content
      'ceros', // Custom post type slug
      'normal', // Context (normal, side, advanced)
      'default' // Priority (default, high, low)
    );
  }

  // Add the custom meta box for the "Ceros" custom post type
  public function add_meta_box_sidebar() {
    add_meta_box(
      'ceros_file_upload_d', // Meta box ID
      'Ceros Animation', // Meta box title
      array(
        $this, 'side_content'
      ), // Callback to render the meta box content
      'ceros', // Custom post type slug
      'side', // Context (normal, side, advanced)
      'default' // Priority (default, high, low)
    );
  }

  public function side_content() {
    echo '<p><b>ID:</b> unique ID for this embed</p>';
    echo '<p><b>Type:</b> scroll, full</p>';
    echo '<p><b>Title:</b> used for screen readers</p>';
    echo '<p><a href="https://prolificdigital.notion.site/Ceros-WordPress-Plugin-2165805d1e9f4fd5ad58ab64b5a5d473?pvs=4" target="_blank">View documentation for details</a></p>';
  }

  // Callback to render the custom meta box content
  public function render_meta_box($post) {
    wp_nonce_field(basename(__FILE__), 'ceros_file_upload_nonce');
    $file_url = get_post_meta($post->ID, '_ceros_file_upload', true);
?>

    <input type="file" id="ceros_file_upload_field" name="ceros_file_upload" accept=".zip">
    <p><i>Upload your .zip file above and be sure that your files are at the root of your zipped folder. </br></i><a href="https://prolificdigital.notion.site/Ceros-WordPress-Plugin-2165805d1e9f4fd5ad58ab64b5a5d473?pvs=4" target="_blank">View our documentation</a> for more details.</p>

    <p><?php echo $file_url ? '<p><b>Current File: </b></p>' . $file_url . '/index.html' : '<p><b>No file uploaded yet.</b></p>'; ?></p>

    <p><b>Shortcode:</b></p>
    <pre><input type="text" readonly value="[ceros-animation id=&quot;<?php the_ID(); ?>&quot; type=&quot;scrolling&quot; title=&quot;<?php the_title(); ?>&quot;]" class="widefat"></pre>

    <?php if ($file_url) : ?>
      <p><b>Preview:</b></p>
      <div style='position: relative;width: auto;padding: 0 0 56.25%;height: 0;top: 0;left: 0;bottom: 0;right: 0;margin: 0;border: 0 none' id="experience-5fa993f346f86" data-aspectRatio="1.77777778" data-mobile-aspectRatio="0.5625" data-tablet-aspectRatio="0.75"><iframe allowfullscreen src='<?php echo $file_url; ?>/index.html' style='position: absolute;top: 0;left: 0;bottom: 0;right: 0;margin: 0;padding: 0;border: 0 none;height: 100%;width: 100%' frameborder='0' class='ceros-experience' title='2020 Health Plan Member Engagement Report'></iframe></div>
      <script type="text/javascript" src="survey-report/assets/scroll-proxy.min.js" data-ceros-origin-domains=""></script>
    <?php endif; ?>

    <script>
      // This is neccessary to handle file uploads.
      jQuery(document).ready(function() {
        jQuery('#post').attr('enctype', 'multipart/form-data');
      });
    </script>
<?php
  }

  public function custom_upload_mimes($mime_types) {
    return array_merge($mime_types, array(
      'zip' => 'application/zip',
      // Need to test future support for these file types
      // 'tar' => 'application/x-tar',
      // 'tgz' => 'application/x-tar-gz',
      // 'gzip' => 'application/x-zip',
      // 'gz' => 'application/x-zip',
    ));
    return $mime_types;
  }

  public function handle_upload($post_id) {
    if (!isset($_POST['ceros_file_upload_nonce']) || !wp_verify_nonce($_POST['ceros_file_upload_nonce'], basename(__FILE__))) {
      return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
      return;
    }
    if (!current_user_can('edit_post', $post_id)) {
      return;
    }

    $file = $_FILES['ceros_file_upload'];

    if ($file['error'] === 0) {
      $source = $_FILES['ceros_file_upload']['tmp_name'];

      // Set the custom uploads directory path
      $upload_dir = wp_upload_dir();
      wp_mkdir_p($upload_dir['basedir'] . '/ceros');
      $custom_directory = $upload_dir['basedir'] . '/ceros';

      $filename = sanitize_file_name($_FILES['ceros_file_upload']['name']);
      $base_filename = pathinfo($filename, PATHINFO_FILENAME);
      $destination = trailingslashit($custom_directory) . $filename;

      // Increment the ZIP file name if one with the current name already exists
      if (file_exists($destination)) {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $base_filename = pathinfo($filename, PATHINFO_FILENAME);
        $i = 1;
        while (file_exists($destination)) {
          $filename = $base_filename . '_' . $i . '.' . $extension;
          $destination = trailingslashit($custom_directory) . $filename;
          $i++;
        }
      }

      move_uploaded_file($source, $destination);

      $folder_name = pathinfo($filename, PATHINFO_FILENAME);
      $unzip_folder = trailingslashit($custom_directory) . $folder_name;

      // Create a temp folder to initially unzip the files to
      $temp_unzip_folder = trailingslashit($custom_directory) . 'temp_unzip';
      wp_mkdir_p($temp_unzip_folder);

      WP_Filesystem();
      $unzipped_file = unzip_file($destination, $temp_unzip_folder);

      // If there's a single directory inside temp_unzip_folder, move its contents to the desired destination
      $dir_contents = scandir($temp_unzip_folder);
      if (count($dir_contents) == 3) { // "." , "..", and the directory
        $nested_folder = trailingslashit($temp_unzip_folder) . $dir_contents[2]; // The directory
        rename($nested_folder, $unzip_folder);
      }

      // Remove the temp folder after the operations
      rmdir($temp_unzip_folder);

      if ($unzipped_file) {
        $file_path = $upload_dir['baseurl'] . '/ceros/' . $folder_name;
        update_post_meta($post_id, '_ceros_file_upload', $file_path);
      }
    }
  }
}
