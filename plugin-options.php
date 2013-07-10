<?php
  //include the main class file
  require_once("admin-page-class/admin-page-class.php");
  
  
  /**
   * configure your admin page
   */
  $config = array(    
    'menu'           => 'settings',             //sub page to settings page
    'page_title'     => __('Wordpress Image Toolkit', WP_RETINA_ADAPTIVE_TEXTDOMAIN),       //The name of this page 
    'capability'     => 'update_core',         // The capability needed to view the page 
    'option_group'   => WP_IMAGE_TOOLKIT_OPTIONS_GROUP,       //the name of the option to create in the database
    'id'             => 'wp_image_toolkit_admin_page',            // meta box id, unique per page
    'fields'         => array(),            // list of fields (can be added by field arrays)
    'local_images'   => true,          // Use local or hosted images (meta box images for add/remove)
    'use_with_theme' => false          //change path if used with theme set to true, false for a plugin or anything else for a custom path(default false).
  );  
  
  /**
   * instantiate your admin page
   */
  $options_panel = new BF_Admin_Page_Class($config);
  $options_panel->OpenTabs_container('');
  
  /**
   * define your admin page tabs listing
   */
  $options_panel->TabsListing(array(
    'links' => array(
      'options_1' =>  __('Adaptive Images',WP_RETINA_ADAPTIVE_TEXTDOMAIN),
      'options_2' =>  __('Retina Images',WP_RETINA_ADAPTIVE_TEXTDOMAIN),
      'options_3' =>  __('Grayscale Images',WP_RETINA_ADAPTIVE_TEXTDOMAIN),
      'options_4' =>  __('Import Export',WP_RETINA_ADAPTIVE_TEXTDOMAIN),
    )
  ));
  
  /**
   * Open admin page adaptive images tab
   */
  $options_panel->OpenTab('options_1');

  /**
   * Add fields to admin page adaptive images tab
   */

  $options_panel->addParagraph(__('These settings apply to images that are bigger than actual screen size.',WP_RETINA_ADAPTIVE_TEXTDOMAIN));
  
  $options_panel->addCheckbox('enable_adaptive',array('name'=> __('Enable adaptive images support ',WP_RETINA_ADAPTIVE_TEXTDOMAIN), 'std' => false, 'desc' => __('Creates adaptive images automatically when source image is bigger than screen.',WP_RETINA_ADAPTIVE_TEXTDOMAIN)));
  
  $options_panel->addText('breakpoints', array('name'=> __('Breakpoints ',WP_RETINA_ADAPTIVE_TEXTDOMAIN), 'std'=> '1024,800', 'validate_func' => 'validate_comma_numeric', 'desc' => __('Enter your resolution breakpoints in descending order, comma-separated (screen widths, in pixels)',WP_RETINA_ADAPTIVE_TEXTDOMAIN)));
  $options_panel->addText('jpeg_quality', array('name'=> __('JPEG quality ',WP_RETINA_ADAPTIVE_TEXTDOMAIN), 'std'=> '70', 'validate' => array(
          'minvalue' => array('param' => 1,'message' => __('must be numeric with a min value of 1',WP_RETINA_ADAPTIVE_TEXTDOMAIN)),
          'maxvalue' => array('param' => 100,'message' => __('must be numeric with a max value of 100',WP_RETINA_ADAPTIVE_TEXTDOMAIN))
      ), 'desc' => __('Image quality for generated jpegs (1-100)',WP_RETINA_ADAPTIVE_TEXTDOMAIN)));
  $options_panel->addCheckbox('sharpen_images',array('name'=> __('Sharpen Images ',WP_RETINA_ADAPTIVE_TEXTDOMAIN), 'std' => true, 'desc' => __('Shrinking images can blur details, perform a sharpen on re-scaled images ?',WP_RETINA_ADAPTIVE_TEXTDOMAIN)));
  $options_panel->addText('cache_path', array('name'=> __('Cache path ',WP_RETINA_ADAPTIVE_TEXTDOMAIN), 'std'=> 'wp-content/ai-cache', 'validate_func' => 'validate_path_exists', 'desc' => __('Where to store the generated re-sized images (no starting and trailing slashes).<br/>Specify from your wordpress base directory !',WP_RETINA_ADAPTIVE_TEXTDOMAIN)));
  $options_panel->addCheckbox('watch_cache',array('name'=> __('Watch cache ',WP_RETINA_ADAPTIVE_TEXTDOMAIN), 'std' => false, 'desc' => __('Ensures updated source images are re-cached',WP_RETINA_ADAPTIVE_TEXTDOMAIN)));
  $options_panel->addText('browser_cache_duration', array('name'=> __('Cache duration ',WP_RETINA_ADAPTIVE_TEXTDOMAIN), 'std'=> '60*60*24*7', 'validate' => 'numeric', 'desc' => __('How long will the visitor\'s browser cache the image (seconds) ?<br/>Default: 7 days',WP_RETINA_ADAPTIVE_TEXTDOMAIN)));
  
  /*//text field
  $options_panel->addText('text_field_id', array('name'=> __('My Text ',WP_RETINA_ADAPTIVE_TEXTDOMAIN), 'std'=> 'text', 'desc' => __('Simple text field description',WP_RETINA_ADAPTIVE_TEXTDOMAIN)));
  //textarea field
  $options_panel->addTextarea('textarea_field_id',array('name'=> __('My Textarea ',WP_RETINA_ADAPTIVE_TEXTDOMAIN), 'std'=> 'textarea', 'desc' => __('Simple textarea field description',WP_RETINA_ADAPTIVE_TEXTDOMAIN)));
  //checkbox field
  $options_panel->addCheckbox('checkbox_field_id',array('name'=> __('My Checkbox ',WP_RETINA_ADAPTIVE_TEXTDOMAIN), 'std' => true, 'desc' => __('Simple checkbox field description',WP_RETINA_ADAPTIVE_TEXTDOMAIN)));
  //select field
  $options_panel->addSelect('select_field_id',array('selectkey1'=>'Select Value1','selectkey2'=>'Select Value2'),array('name'=> __('My select ',WP_RETINA_ADAPTIVE_TEXTDOMAIN), 'std'=> array('selectkey2'), 'desc' => __('Simple select field description',WP_RETINA_ADAPTIVE_TEXTDOMAIN)));
  //radio field
  $options_panel->addRadio('radio_field_id',array('radiokey1'=>'Radio Value1','radiokey2'=>'Radio Value2'),array('name'=> __('My Radio Filed',WP_RETINA_ADAPTIVE_TEXTDOMAIN), 'std'=> array('radiokey2'), 'desc' => __('Simple radio field description',WP_RETINA_ADAPTIVE_TEXTDOMAIN)));
  /**
   * Close adaptive images tab
   */
  $options_panel->CloseTab();


  /**
   * Open admin page retina options tab
   */
  $options_panel->OpenTab('options_2');
  
  $options_panel->addParagraph(__("These settings apply to images sizes that are declared in wordpress core, themes and plugins.",WP_RETINA_ADAPTIVE_TEXTDOMAIN));
  
  /**
   * Add fields to retina options tab
   */
  $options_panel->addCheckbox('enable_retina',array('name'=> __('Enable retina images support ',WP_RETINA_ADAPTIVE_TEXTDOMAIN), 'std' => false, 'desc' => __('Creates retina image sizes automatically, and ensures the good image is displayed automatically when calling the_post_thumbnail and derivatives.',WP_RETINA_ADAPTIVE_TEXTDOMAIN)));
  
  /*$retina_images_show_in_wordpress[] = $options_panel->addCheckbox('generate_images_from_retina_version',array('name'=> __('Generate standard images from retina version ',WP_RETINA_ADAPTIVE_TEXTDOMAIN), 'std' => false, 'desc' => __('Creates standard images sizes automatically, by downscaling 2x from retina images.',WP_RETINA_ADAPTIVE_TEXTDOMAIN)),true);
  
  //conditional block 
  $options_panel->addCondition('retina_images_show_in_wordpress',
      array(
        'name'   => __('Add retina images to wordpress sizes ',WP_RETINA_ADAPTIVE_TEXTDOMAIN),
        'desc'   => __('Useful when you use a plugin that allows you to manually crop images individually, so you have complete control over your images.<br>Perfectly coupled with <a href="http://wordpress.org/extend/plugins/post-thumbnail-editor/">post thumbnail editor</a>',WP_RETINA_ADAPTIVE_TEXTDOMAIN),
        'fields' =>  $retina_images_show_in_wordpress,
        'std'    => false
      )
  );*/
  
  /**
   * Close retina options tab
   */ 
  $options_panel->CloseTab();

  /**
   * Open admin page import / export tab
   */
  $options_panel->OpenTab('options_4');
  
  //title
  $options_panel->Title(__("Import Export",WP_RETINA_ADAPTIVE_TEXTDOMAIN));
  
  /**
   * add import export functionallty
   */
  $options_panel->addImportExport();

  /**
   * Close import / export tab
   */
  $options_panel->CloseTab();
