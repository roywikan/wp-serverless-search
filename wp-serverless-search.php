<?php

/**
 * Plugin Name: WP Serverless Search
 * Plugin URI: https://github.com/emaildano/wp-serverless-search
 * Description: A static search plugin for WordPress.
 * Version: 0.0.1
 * Author: DigitalCube, Daniel Olson
 * Author URI: https://digitalcube.jp
 * License: GPL2
 * Text Domain: wp-serverless-search
 */


/**
 * On Plugin Activation
 */

function wp_sls_search_install()
{
  // trigger our function that registers the custom post type
  create_wp_sls_dir();
  create_search_feed();
}

add_action('init', 'create_wp_sls_dir');
register_activation_hook(__FILE__, 'wp_sls_search_install');

/**
 * Create WP SLS Dir
 */

function create_wp_sls_dir()
{

  $upload_dir = wp_get_upload_dir();
  $save_path = $upload_dir['basedir'] . '/wp-sls/.';
  $dirname = dirname($save_path);

  if (!is_dir($dirname)) {
    mkdir($dirname, 0755, true);
  }
}

add_action('publish_post', 'create_search_feed');

function create_search_feed()
{
  require_once(ABSPATH . 'wp-admin/includes/export.php');

  add_filter('the_content_export', '__return_empty_string');
  add_filter('the_excerpt_export', '__return_empty_string');

  ob_start();

  $wpExportOptions = array(
    'content'    => 'post',
    'status'     => 'publish',
  );

  export_wp($wpExportOptions);

  $xml = ob_get_clean();

  remove_filter('the_content_export', '__return_empty_string');
  remove_filter('the_excerpt_export', '__return_empty_string');

  $upload_dir = wp_get_upload_dir();
  $save_path = $upload_dir['basedir'] . '/wp-sls/search-feed.xml';

  file_put_contents($save_path, $xml);
  
  
  



  $xml = simplexml_load_file($save_path);
  
  // Remove the <wp:author> and <generator> elements
  unset($xml->channel->generator);

  // Remove all elements with the "content" namespace
  foreach ($xml->getDocNamespaces() as $namespace) {
      $xml->registerXPathNamespace('content', $namespace);
      foreach ($xml->xpath('//content:*') as $contentElement) {
          unset($contentElement[0]);
      }
  }

// Remove all elements with the "wp:" namespace
foreach ($xml->getDocNamespaces() as $namespace) {
    $xml->registerXPathNamespace('wp', $namespace);
    $elements = $xml->xpath('//wp:*');
    for ($i = count($elements) - 1; $i >= 0; $i--) {
        unset($elements[$i][0]);
    }
}

  

  foreach ($xml->channel->item as $item) {
    $wp = $item->children('wp', true);
    $dc = $item->children('dc', true);

    unset($wp->post_parent);
    unset($wp->menu_order);
    unset($wp->post_type);
    unset($wp->post_password);
    unset($wp->post_date_gmt);
    unset($wp->post_modified);
    unset($wp->post_modified_gmt);
    unset($wp->comment_status);
    unset($wp->ping_status);
    unset($wp->status);
    unset($wp->is_sticky);
    unset($wp->post_date);
    unset($wp->post_name);
    unset($wp->postmeta);
    unset($wp->meta_key);
    unset($dc->creator);

    unset($item->guid);
    unset($item->category);


  }

  $new_xml = $xml->asXML();
  
  

  $new_xml = preg_replace('/<!--.*?-->/s', '', $new_xml);

  // Convert the XML object to string and remove whitespace and tabs
  $new_xml = preg_replace('/^\s+|\n\n|\r\r|\s+$/m', '', $new_xml);



  
  
  file_put_contents($save_path, $new_xml);
}

/**
 * Set Plugin Defaults
 */

function wp_sls_search_default_options()
{
  $options = array(
    'wp_sls_search_form' => '[role=search]',
    'wp_sls_search_form_input' => 'input[type=search]',
  );

  foreach ($options as $key => $value) {
    update_option($key, $value);
  }
}

if (!get_option('wp_sls_search_form')) {
  register_activation_hook(__FILE__, 'wp_sls_search_default_options');
}

/**
 * Admin Settings Menu
 */

add_action('admin_menu', 'wp_sls_search');
function wp_sls_search()
{
  add_options_page(
    'WP Serverless Search',
    'WP Serverless Search',
    'manage_options',
    'wp-sls-search',
    'wp_sls_search_options'
  );
}

require_once('lib/includes.php');

/*
* Scripts
*/

add_action('wp_enqueue_scripts', 'wp_sls_search_assets');
add_action('admin_enqueue_scripts', 'wp_sls_search_assets');

function wp_sls_search_assets()
{

  $shifter_js = plugins_url('main/main.js', __FILE__);

  $search_params = array(
    'searchForm' => get_option('wp_sls_search_form'),
    'searchFormInput' => get_option('wp_sls_search_form_input')
  );

  wp_register_script('wp-sls-search-js', $shifter_js, array('jquery', 'micromodal', 'fusejs'), null, true);
  wp_localize_script('wp-sls-search-js', 'searchParams', $search_params);
  wp_enqueue_script('wp-sls-search-js');

  wp_register_script('fusejs', 'https://cdnjs.cloudflare.com/ajax/libs/fuse.js/3.2.1/fuse.min.js', null, null, true);
  wp_enqueue_script('fusejs');

  wp_register_script('micromodal', 'https://cdn.jsdelivr.net/npm/micromodal/dist/micromodal.min.js', null, null, true);
  wp_enqueue_script('micromodal');

  wp_register_style("wp-sls-search-css", plugins_url('/main/main.css', __FILE__));
  wp_enqueue_style("wp-sls-search-css");
}

function wp_sls_search_modal()
{ ?>
  <div class="wp-sls-search-modal" id="wp-sls-search-modal" aria-hidden="true">
    <div class="wp-sls-search-modal__overlay" tabindex="-1" data-micromodal-overlay>
      <div class="wp-sls-search-modal__container" role="dialog" aria-labelledby="modal__title" aria-describedby="modal__content">
        <header class="wp-sls-search-modal__header">
          <a href="#" aria-label="Close modal" data-micromodal-close></a>
        </header>
        <form role="search" method="get" class="search-form">
          <label for="wp-sls-earch-field">
            <span class="screen-reader-text">Search for:</span>
          </label>
          <input id="wp-sls-earch-field" class="wp-sls-search-field" type="search" autocomplete="off" class="search-field" placeholder="Search …" value="" name="s">
        </form>
        <div role="document"></div>
      </div>
    </div>
  </div>
<?php }

add_action('wp_footer', 'wp_sls_search_modal');
