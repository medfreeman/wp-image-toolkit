<?php
/*
Plugin Name: Wordpress Image Toolkit
Plugin URI: http://www.superposition.info
Description: Adaptive, retina, and grayscale images support.
Version: 1.3.0
Author: Mehdi Lahlou
Author URI: http://www.superposition.info
Author Email: mehdi.lahlou@free.fr
License: GPL2

  Copyright 2013 Mehdi Lahlou (mehdi.lahlou@free.fr)

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License, version 2, as 
  published by the Free Software Foundation.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
  
*/

define("WP_IMAGE_TOOLKIT_TEXTDOMAIN", "wp-image-toolkit-locale");
define("WP_IMAGE_TOOLKIT_OPTIONS_GROUP", "wp_image_toolkit_options");

// include the options panel
require_once("plugin-options.php");
// include tools (get resolution and pixel ratio from UA or cookie)
require_once("includes/utils.php");
// include git updater
include_once('includes/updater.php');

register_uninstall_hook( __FILE__, array( 'ImagesToolkit', 'uninstall' ) );

class ImagesToolkit {
	
	private $plugin_path;
    private $plugin_url;
    private $wp_url;
    
    private $options;
    private $resolutions;
    private $screen;
    private $retina_display;
    
    private $cache_path;
    private $cache_url;
    
    private $image_is_grayscale;
	 
	/*--------------------------------------------*
	 * Constructor
	 *--------------------------------------------*/
	
	/**
	 * Initializes the plugin by setting localization, filters, and administration functions.
	 */
	function __construct() {
		
		$this->plugin_path = plugin_dir_path( __FILE__ );
        $this->plugin_url = plugin_dir_url( __FILE__ );
        $this->wp_url = get_bloginfo('wpurl');
        
        $this->l10n = WP_IMAGE_TOOLKIT_TEXTDOMAIN;
		$this->options = get_option(WP_IMAGE_TOOLKIT_OPTIONS_GROUP);
		//wp_die(print_r($this->options));
		
		// Load plugin text domain
		add_action( 'init', array( $this, 'plugin_textdomain' ) );
		
		$this->resolutions = explode(',', $this->options['breakpoints']);
		$this->image_resolutions = explode(',', $this->options['breakpoints_images_sizes']);
		
		global $screen;
		$screen = $this->screen = get_screen_properties($this->resolutions, $this->image_resolutions);
		
		$this->retina_display = ($this->screen['pixel_density'] >= 1.5);
		
		$this->cache_path = ABSPATH . '/' . $this->options['cache_path'];
		$this->cache_url = $this->wp_url . '/' . $this->options['cache_path'];
		
		$this->image_is_grayscale = false;
		
		if ($this->options['enable_grayscale']) {
			add_filter( 'post_thumbnail_size', array( $this, 'apply_grayscale_thumbnail' ), 99 );
			add_filter( 'post_thumbnail_html', array( $this, 'alter_grayscale_thumbnail_html' ), 99, 5 );
			add_filter( 'wp_generate_attachment_metadata', array( $this, 'generate_grayscale_images'), 100);
			add_action( 'delete_attachment', array( $this, 'delete_grayscale_images'));
		}
		
		if ($this->options['enable_retina']) {
			add_action( 'after_setup_theme', array( $this, 'add_retina_images_sizes' ), 100 );
			add_filter( 'wp_generate_attachment_metadata', array( $this, 'generate_thumbnails_from_retina_versions'), 99);
		}
		
		if ($this->options['enable_retina'] || $this->options['enable_adaptive']) {
			add_action( 'wp_head', array( $this, 'set_resolution_cookie' ) );
			add_filter( 'post_thumbnail_html', array( $this, 'alter_thumbnail_html' ), 100, 5 );
			add_filter( 'the_content', array( $this, 'process_html_imgs' ), 100 );
		}
        
        if (is_admin() && (!defined('DOING_AJAX') || !DOING_AJAX)) {
			add_filter('apc_validattion_class_name', array( $this, 'custom_validation_class' ), 10, 2);
		} else {
			require_once("includes/simple_html_dom.php");
		}
        
        if (is_admin()) { // note the use of is_admin() to double check that this is happening in the admin
			$config = array(
				'slug' => plugin_basename(__FILE__), // this is the slug of your plugin
				'proper_folder_name' => 'wp-image-toolkit', // this is the name of the folder your plugin lives in
				'api_url' => 'https://api.github.com/repos/medfreeman/wp-image-toolkit', // the github API url of your github repo
				'raw_url' => 'https://raw.github.com/medfreeman/wp-image-toolkit/master', // the github raw url of your github repo
				'github_url' => 'https://github.com/medfreeman/wp-image-toolkit', // the github url of your github repo
				'zip_url' => 'https://github.com/medfreeman/wp-image-toolkit/zipball/master', // the zip url of the github repo
				'sslverify' => true, // wether WP should check the validity of the SSL cert when getting an update, see https://github.com/jkudish/WordPress-GitHub-Plugin-Updater/issues/2 and https://github.com/jkudish/WordPress-GitHub-Plugin-Updater/issues/4 for details
				'requires' => '3.5', // which version of WordPress does your plugin require?
				'tested' => '3.5.2', // which version of WordPress is your plugin tested up to?
				'readme' => 'README.md', // which file to use as the readme for the version number
				'access_token' => '' // Access private repositories by authorizing under Appearance > Github Updates when this example plugin is installed
			);
			new WP_GitHub_Updater($config);
		}
	} // end constructor
	
	/**
	 * Fired when the plugin is uninstalled.
	 *
	 * @param	boolean	$network_wide	True if WPMU superadmin uses "Network Activate" action, false if WPMU is disabled or plugin is activated on an individual blog 
	 */
	public function uninstall( $network_wide ) {
		delete_option(WP_IMAGE_TOOLKIT_OPTIONS_GROUP);
	} // end uninstall

	/**
	 * Loads the plugin text domain for translation
	 */
	public function plugin_textdomain() {
        load_plugin_textdomain( $this->l10n, FALSE, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
	} // end plugin_textdomain
	
	
	/*--------------------------------------------*
	 * Add resolution and pixel ratio cookie
	 *---------------------------------------------*/
	
	public function set_resolution_cookie() {
	?>
		<script>document.cookie='resolution='+Math.max(screen.width,screen.height)+("devicePixelRatio" in window ? ","+devicePixelRatio : ",1")+'; path=/';</script>
	<?php
	} // end set_resolution_cookie
	
	/*--------------------------------------------*
	 * Add retina images sizes to wordpress
	 *---------------------------------------------*/
	
	public function add_retina_images_sizes() {
		foreach(get_intermediate_image_sizes() as $size) {
			list($width, $height, $crop) = $this->get_image_dimensions($size);
			add_image_size( $size . '-@2x', $width * 2, $height * 2, $crop);
		}
	} // end add_retina_images_sizes
	
	
	/*--------------------------------------------*
	 * When retina thumbnail is selected, change back html dimensions of images to original image size
	 *---------------------------------------------*/
	
	public function alter_thumbnail_html($html, $post_id, $post_thumbnail_id, $size, $attr) {
		$adaptive = false;
		$retina = false;
		
		$imgdata = wp_get_attachment_image_src( $post_thumbnail_id, $size );
		$url = $imgdata[0];
		$html_width = $imgdata[1];
		$html_height = $imgdata[2];
		
		/* TODO: Switch / Find correspondences in available wordpress images formats */
		
		if ($this->options['enable_adaptive']) {
			$adapted_image = $this->get_adapted_image($url, $html_width, $html_height);
			if ($adapted_image) {
				$adaptive = true;
				$url = $adapted_image['url'];
				$html_width = $adapted_image['html_width'];
				$html_height = $adapted_image['html_height'];
			}
		} else if ($this->options['enable_retina'] && $this->retina_display) {
			$retina = true;
			$imgdata = wp_get_attachment_image_src( $post_thumbnail_id, $size . '-@2x' );
			$url = $imgdata[0];
			if ($this->options['enable_grayscale'] && $this->image_is_grayscale) {
				$url = $this->get_grayscale_image($url);
			}
		}
			
		if ($adaptive || $retina) {
			$html = $this->replace_img_tags($html, $url, $html_width, $html_height);
		}
		return $html;
	}
	
	public function process_html_imgs($html) {
		if(empty($html)) {
			return $html;
		}
		$html = str_get_html($html);
		
		foreach($html->find('img') as $img_tag) {
			if (!isset($img_tag->src)) {
				continue;
			}
			
			$url = $img_tag->src;
			$original_image_file = ABSPATH . $this->get_relative_path_from_url($url);
			$imgdata = getimagesize($original_image_file);
			
			$orig_width = $imgdata[0];
			$orig_height = $imgdata[1];
			
			$html_width = isset($img_tag->width) ? $img_tag->width : $orig_width;
			$html_height = isset($img_tag->height) ? $img_tag->height : $orig_height;
			
			$adapted_image = $this->get_adapted_image($url, $html_width, $html_height);
			if ($adapted_image) {
				$img_tag = $this->replace_img_tags($img_tag, $adapted_image['url'], $adapted_image['html_width'], $adapted_image['html_height']);
			}
		}
		
		return $html;
	}
	
	private function get_adapted_image($original_url, $original_width, $original_height) {
		if($original_width <= $this->screen['breakpoint']) {
			return false;
		}
		
		$html_width = $this->screen['image_resolution'];
		$html_height = round($html_width * $original_height / $original_width);
		
		$new_width = $html_width;
		$new_height = $html_height;
		
		$folder_prefix = $this->screen['breakpoint'];
		if ($this->retina_display) {
			$folder_prefix .= '-@2x';
			$new_width = $new_width * 2;
			$new_height = $new_height * 2;
		}
		
		$new_width = $html_width * $this->screen['pixel_density'];
		$new_height = $html_height * $this->screen['pixel_density'];
		
		$img_suffix = $this->get_relative_path_from_url($original_url);
		
		if ($this->image_is_grayscale) {
			$img_suffix = $this->get_grayscale_image($img_suffix);
		}
		
		$original_image_file = ABSPATH . $img_suffix;
		$adapted_image_file = $this->cache_path . '/' . $folder_prefix . $img_suffix;
		$adapted_url = $this->cache_url . '/' . $folder_prefix . $img_suffix;
		
		if(!file_exists($adapted_image_file) || $this->options['watch_cache']) {
			$folder = dirname($adapted_image_file);
			if(!file_exists($folder)) {
				if(!mkdir($folder, 0777, true)) {
					return $html;
				}
			}					
			
			if(!$this->create_resampled_image($original_image_file, $adapted_image_file, $new_width, $new_height)) {
				return $html;
			}
		}
		
		return array('url' => $adapted_url, 'html_width' => $html_width, 'html_height' => $html_height);
	}
	
	private function create_resampled_image($original_image_path, $new_image_path, $new_width, $new_height) {
		$original_image = wp_get_image_editor($original_image_path);
		if ( !is_wp_error($original_image) ) {
			$original_image->set_quality($this->options['jpeg_quality']);
			$original_image->resize( $new_width, $new_height, false );
			$original_image->save( $new_image_path );
		}
		
		return $new_image_path;
	}
	
	private function get_relative_path_from_url($url) {
		$pos = strpos($url, $this->wp_url);
		if($pos === false) {
			return false;
		}
		$suffix = substr($url, $pos + mb_strlen($this->wp_url));
		return $suffix;
	}
	
	private function get_grayscale_image($url) {
		$image_ext = strtolower(pathinfo($url, PATHINFO_EXTENSION));
		$grayscale_image_url = dirname($url) . '/' . basename($url, '.' . $image_ext) . '-gray.' . $image_ext;
		return $grayscale_image_url;
	}
	
	private function replace_img_tags($img_tag, $src = false, $width = false, $height = false) {
		if(is_string($img_tag)) {
			$img_tag = str_get_html($img_tag)->find('img', 0);
		}
		if ($src) {
			$img_tag->src = $src;
		}
		if ($width) {
			$img_tag->width = $width;
		}
		if ($height) {
			$img_tag->height = $height;
		}
		
		return $img_tag;
	}
	
	/*--------------------------------------------*
	 * Downscale by 2x every retina image to generate standard ones
	 *---------------------------------------------*/
	
	public function generate_thumbnails_from_retina_versions($meta) {
		$upload_dir = wp_upload_dir();
		
		$sizes = array();
		$sizes = array_keys($meta['sizes']);
		
		foreach($sizes as $size) {
			$retina_suffix = strpos($size, '-@2x');
			if ($retina_suffix !== false) {
				$path = trailingslashit($upload_dir['basedir']).trailingslashit(dirname($meta['file']));
				$retina_file = $path . $meta['sizes'][$size]['file'];
				if(file_exists($retina_file)) {
					$size_small = substr($size, 0, $retina_suffix);
					$standard_image_file =  $path . $meta['sizes'][$size_small]['file'];
					
					list($orig_w, $orig_h, $orig_type) = getimagesize($retina_file);
					
					$image = wp_get_image_editor( $retina_file );
					if ( !is_wp_error( $image ) ) {
						$image->set_quality(100);
						$image->resize( $orig_w / 2, $orig_h / 2, false );
						$image->save( $standard_image_file );
					}
				}
			}
		}
		return $meta;
	}
	
	/*--------------------------------------------*
	 * Detect grayscale thumbnail, replace size by standard one
	 *---------------------------------------------*/
	
	public function apply_grayscale_thumbnail($size) {
		$this->image_is_grayscale = false;
		$grayscale_suffix = strpos($size, '-gray');
		if ($grayscale_suffix !== false) {
			$size = substr($size, 0, $grayscale_suffix);
			$this->image_is_grayscale = true;
		}
		return $size;
	} // end apply_grayscale_thumbnail
	
	/*--------------------------------------------*
	 * When grayscale thumbnail is selected, change back html dimensions of images to original image size
	 *---------------------------------------------*/
	
	public function alter_grayscale_thumbnail_html($html, $post_id, $post_thumbnail_id, $size, $attr) {
		if($this->image_is_grayscale) {
			$standard_image_file = wp_get_attachment_image_src($post_thumbnail_id, $size);
			$extension = pathinfo($standard_image_file[0], PATHINFO_EXTENSION);
			$grayscale_image_file = trailingslashit(dirname($standard_image_file[0])) . basename($standard_image_file[0], '.' . $extension) . '-gray.' . $extension;
			$html = $this->replace_img_tags($html, $grayscale_image_file);
		}
		return $html;
	} // end alter_grayscale_thumbnail_html
	
	/*--------------------------------------------*
	 * Generate grayscale images on media manager upload
	 *---------------------------------------------*/
	
	public function generate_grayscale_images($meta) {
		$sizes = $this->get_images_sizes_files($meta);
		
		$upload_dir = wp_upload_dir();
		$path = trailingslashit($upload_dir['basedir']).trailingslashit(dirname($meta['file']));
		
		foreach($sizes as $size => $image_file) {
				if(file_exists($image_file)) {
					$extension = pathinfo($image_file, PATHINFO_EXTENSION);
					$grayscale_image_file = $path . basename($image_file, '.' . $extension) . '-gray.' . $extension;
					
					list($orig_w, $orig_h, $orig_type) = getimagesize($image_file);
					
					$image =  imagecreatefromstring(file_get_contents($image_file));
					
					$grayscale_image = $image;
					imagefilter($grayscale_image, IMG_FILTER_GRAYSCALE);
					
					switch ($orig_type) {
						case IMAGETYPE_GIF:
							imagegif( $grayscale_image, $grayscale_image_file );
							break;
						case IMAGETYPE_PNG:
							imagepng( $grayscale_image, $grayscale_image_file );
							break;
						case IMAGETYPE_JPEG:
							imagejpeg( $grayscale_image, $grayscale_image_file );
							break;
					}
				}
		}
		return $meta;
	} // end generate_grayscale_images
		
	/*--------------------------------------------*
	 * Delete grayscale images when deleting image from media browser
	 *---------------------------------------------*/
	 
	public function delete_grayscale_images($attachment_id) {
		if (wp_attachment_is_image($attachment_id)) {
			$meta = wp_get_attachment_metadata($attachment_id);
			
			$sizes = $this->get_images_sizes_files($meta);
			
			$upload_dir = wp_upload_dir();
			$path = trailingslashit($upload_dir['basedir']).trailingslashit(dirname($meta['file']));
		
			foreach($sizes as $size => $image_file) {
				$extension = pathinfo($image_file, PATHINFO_EXTENSION);
				$grayscale_image_file = $path . basename($image_file, '.' . $extension) . '-gray.' . $extension;
				if (file_exists($grayscale_image_file)) {
					unlink($grayscale_image_file);
				}
			}
		}
	} // end delete_grayscale_images
	
	/*--------------------------------------------*
	 * Get images sizes and file path
	 *---------------------------------------------*/
	 
	function get_images_sizes_files($meta) {
		$upload_dir = wp_upload_dir();
		
		$sizes = array();
		$sizes = array_keys($meta['sizes']);
		
		$path = trailingslashit($upload_dir['basedir']).trailingslashit(dirname($meta['file']));
		
		$all_sizes = array();
		foreach($sizes as $size) {
			$image_file = $path . $meta['sizes'][$size]['file'];
			$all_sizes[$size] = $image_file;
		}
		
		$all_sizes['full'] = $upload_dir['basedir'] . '/' . $meta['file'];
		
		return $all_sizes;
	} // end get_images_sizes_files
	
	/*--------------------------------------------*
	 * Get dimensions of given image size
	 *---------------------------------------------*/
	 
	private function get_image_dimensions($size) {
		global $_wp_additional_image_sizes;
		if (isset($_wp_additional_image_sizes[$size])) {
			$width = intval($_wp_additional_image_sizes[$size]['width']);
			$height = intval($_wp_additional_image_sizes[$size]['height']);
		} else {
			$width = get_option($size.'_size_w');
			$height = get_option($size.'_size_h');
		}
		return array($width, $height, $crop);
	}
	
	/*--------------------------------------------*
	 * Add custom validation class for admin panel
	 *---------------------------------------------*/
	
	public function custom_validation_class($validationClassName,$obj){
		if ($obj->_Page_Config[option_group] == WP_IMAGE_TOOLKIT_OPTIONS_GROUP) {
			require_once("includes/apc_custom_validation.class.php");
			return 'apc_custom_validation';
		}
		return $validationClassName;
	} // end custom_validation_class
	
} // end class

new ImagesToolkit();
