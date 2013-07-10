<?php
/*
Plugin Name: Wordpress Image Toolkit
Plugin URI: http://www.superposition.info
Description: Adaptive, retina, and grayscale images support.
Version: 1.2.0
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
		wp_die(print_r($this->options));
		
		// Load plugin text domain
		add_action( 'init', array( $this, 'plugin_textdomain' ) );
		
		$this->resolutions = explode(',', $this->options['breakpoints']);
		$this->screen = get_screen_properties($this->resolutions);
		
		$this->cache_path = ABSPATH . '/' . $this->options['cache_path'];
		$this->cache_url = $this->wp_url . '/' . $this->options['cache_path'];
		
		$this->image_is_grayscale = false;
		
		
		add_action( 'wp_head', array( $this, 'set_resolution_cookie' ) );
		
		add_filter( 'post_thumbnail_size', array( $this, 'apply_grayscale_thumbnail' ), 99 );
		add_filter( 'post_thumbnail_html', array( $this, 'alter_grayscale_thumbnail_html' ), 99, 5 );
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'generate_grayscale_images'), 100);
		add_action( 'delete_attachment', array( $this, 'delete_grayscale_images'));
		
		if ($this->options['enable_retina']) {
			add_action( 'after_setup_theme', array( $this, 'add_retina_images_sizes' ), 100 );
			add_filter( 'post_thumbnail_size', array( $this, 'select_retina_thumbnail' ), 100 );
			add_filter( 'post_thumbnail_html', array( $this, 'alter_retina_thumbnail_html' ), 100, 5 );
			add_filter( 'wp_generate_attachment_metadata', array( $this, 'generate_thumbnails_from_retina_versions'), 99);
		}
        
        add_filter('apc_validattion_class_name', array( $this, 'custom_validation_class' ), 10, 2);
        
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
		} else {
			require_once("includes/simple_html_dom.php");
		}
		
	} // end constructor
	
	/**
	 * Fired when the plugin is uninstalled.
	 *
	 * @param	boolean	$network_wide	True if WPMU superadmin uses "Network Activate" action, false if WPMU is disabled or plugin is activated on an individual blog 
	 */
	public function uninstall( $network_wide ) {
		delete_option(WP_RETINA_ADAPTIVE_OPTIONS_GROUP);
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
	 * Select retina thumbnail when appropriate, in place of standard image
	 *---------------------------------------------*/
	
	public function select_retina_thumbnail($size) {
		if ($this->screen['pixel_density'] > 1) {
			return $size . '-@2x';
		}
		return $size;
	} // end select_retina_thumbnail
	
	
	/*--------------------------------------------*
	 * When retina thumbnail is selected, change back html dimensions of images to original image size
	 *---------------------------------------------*/
	
	public function alter_retina_thumbnail_html($html, $post_id, $post_thumbnail_id, $size, $attr) {
		$retina = false;
		$adapted = false;
		
		$retina_suffix = strpos($size, '-@2x');
		if ($retina_suffix !== false) {
			$retina = true;
			$size = substr($size, 0, $retina_suffix);
		}
		
		$imgdata = wp_get_attachment_image_src( $post_thumbnail_id, $size );
		$url = $imgdata[0];
		$html_width = $imgdata[1];
		$html_height = $imgdata[2];
		
		/* TODO: Same for post images */
		/* TODO: Add html library simplehtmldom */
		
		/* TODO: Add a correspondence table to perfectly control images sizes depending on the context */
		/* TODO: Switch / Find correspondences in available wordpress images formats */
		if($html_width > $this->screen['breakpoint']) {
			$adapted = true;
			
			$tmp_width = $this->screen['breakpoint'];
			$tmp_height = round($tmp_width * $html_height / $html_width);
			
			$new_width = $tmp_width * $this->screen['pixel_density'];
			$new_height = $tmp_height * $this->screen['pixel_density'];
			
			$pos = strpos($url, $this->wp_url);
			if($pos !== false) {
				$img_suffix = substr($url, $pos + mb_strlen($this->wp_url));
				$image_ext = strtolower(pathinfo($img_suffix, PATHINFO_EXTENSION));
				
				if ($this->image_is_grayscale) {
					$img_suffix = dirname($img_suffix) . '/' . basename($img_suffix, '.' . $image_ext) . '-gray.' . $image_ext;
				}
				
				$original_image_file = ABSPATH . $img_suffix;
				$adapted_image_file = $this->cache_path . '/' . $this->screen['breakpoint'] . $img_suffix;
				$adapted_url = $this->cache_url . '/' . $this->screen['breakpoint'] . $img_suffix;
				
				if(!file_exists($adapted_image_file)) {
					$folder = dirname($adapted_image_file);
					if(!file_exists($folder)) {
						if(!mkdir($folder, 0777, true)) {
							return $html;
						}
					}					
					
					/* TODO: replace by wordpress new image manipulation functions : wp_get_image_editor */
					$original_image = wp_load_image($original_image_file);
					$adapted_image = imagecreatetruecolor($new_width, $new_height);
					
					/* TODO: return original image if requested dimensions are higher than its size */
					imagecopyresampled($adapted_image , $original_image, 0, 0, 0, 0, $new_width, $new_height, $html_width, $html_height);
					
					switch ($image_ext) {
						case 'gif':
							imagegif( $adapted_image, $adapted_image_file );
							break;
						case 'png':
							imagepng( $adapted_image, $adapted_image_file );
							break;
						case 'jpg':
						case 'jpeg':
							imagejpeg( $adapted_image, $adapted_image_file );
							break;
					}
				}
				$html_width = $tmp_width;
				$html_height = $tmp_height;
			}
		}
			
		if ($retina || $adapted) {
			$html = preg_replace("/width=\"[0-9]*\"/", "width=\"".$html_width."\"", $html);
			$html = preg_replace("/height=\"[0-9]*\"/", "height=\"".$html_height."\"", $html);
		}
		if ($adapted) {
			$html = preg_replace("!(<img[^>]+src\s*=\s*['\"])([^'\"]+)(['\"][^>]*>)!i", "$1" . $adapted_url . "$3", $html);
		}
		return $html;
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
					
					$standard_image = imagecreatetruecolor($orig_w / 2, $orig_h / 2);
					$image = wp_load_image($retina_file);
					
					// Resize
					imagecopyresampled($standard_image, $image, 0, 0, 0, 0, $orig_w / 2, $orig_h / 2, $orig_w, $orig_h);
					
					switch ($orig_type) {
						case IMAGETYPE_GIF:
							imagegif( $standard_image, $standard_image_file );
							break;
						case IMAGETYPE_PNG:
							imagepng( $standard_image, $standard_image_file, 0 );
							break;
						case IMAGETYPE_JPEG:
							imagejpeg( $standard_image, $standard_image_file, 100 );
							break;
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
			preg_match("/(.*)<img(.*)src=\"([^\"]+)\"(.*)\/>(.*)/i", $html, $matches);
			$standard_image_file = $matches[3];
			$extension = pathinfo($standard_image_file, PATHINFO_EXTENSION);
			$grayscale_image_file = trailingslashit(dirname($standard_image_file)) . basename($standard_image_file, '.' . $extension) . '-gray.' . $extension;
			$html = $matches[1] . '<img' . $matches[2] . 'src="' . $grayscale_image_file . '"'.  $matches[4] . '/>' . $matches[5];
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
					
					$image = wp_load_image($image_file);
					
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
		if ($obj->_Page_Config[option_group] == WP_RETINA_ADAPTIVE_OPTIONS_GROUP) {
			require_once("includes/apc_custom_validation.class.php");
			return 'apc_custom_validation';
		}
		return $validationClassName;
	} // end custom_validation_class
	
} // end class

new ImagesToolkit();
