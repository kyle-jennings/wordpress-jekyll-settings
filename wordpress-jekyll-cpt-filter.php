<?php
/*
Plugin Name: WordPress to Jekyll CPT Filter
Description: Provides a settings page to select exactly which post types get exported wiith Ben Balter's <a href="https://wordpress.org/plugins/jekyll-exporter/" target="_blank" >Jekyll Exporter</a> plugin
Author: Kyle Jennings
Author URI: https://github.com/kyle-jennings
Version: 0.1
Text Domain: wordpress-jekyll-cpt-filter
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html

Copyright 2017 Kyle Jennings

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



class jekyll_cpt_filter {

  public $post_types;

  function __construct()
  {


		add_action( 'current_screen', array( $this, 'saveFields' ) );
    add_action( 'admin_init', array( $this, 'collectAllPostTypes') );
    add_action( 'admin_menu', array( $this, 'registerMenu' ) );
    add_filter('jekyll_export_post_types', array($this, 'setPostTypes'));
  }


  function setPostTypes($defaults)
  {

    return (array) get_option('wordpress-jekyll-cpts', array( 'post', 'page', 'revision' ) );
  }

  function collectAllPostTypes()
  {
    $post_types = get_post_types();
    $this->post_types = $post_types;
  }


  function registerMenu()
  {
    add_management_page(
      __( 'WordPress to Jekyll Settings', 'wordpress-jekyll-cpt-filter' ),
      __( 'WordPress to Jekyll Settings', 'wordpress-jekyll-cpt-filter' ),
      'manage_options',
      'wordpress-jekyll-cpt-filter',
      array($this, 'settingsPage')
    );
  }


   static public function settingsFields($name, $checked) {
     $output = '';

     $find = array('_', '-');

     $label = ucwords(str_replace($find, ' ', $name));


     $output .= '<input class="" id="cpt-'.$name.'" type="checkbox"
      name="jekyll_cpts[]" value="'.$name.'" '.$checked.'>';
     $output .= '<label for="cpt-'.$name.'">' . $label . '</label>';

     return $output;
   }


  /**
   * The settings page form
   */
  function settingsPage()
  {
    $saved = (array) get_option('wordpress-jekyll-cpts', array( 'post', 'page', 'revision' ) );

    $output = '';
    $output .= '<h1> WordPress to Jekyll export Settings </h1>';

    $output .= '<form method="post">';
      $output .= wp_nonce_field( 'jekyll_cpts_saved', 'jekyll_cpts_saved', true, false ) ;

      // the post types
      $output .= '<h2> Which post types do you want to export into Jekyll? </h2>';
      $output .= '<ul>';
      foreach($this->post_types as $name):
        $checked = in_array( $name, $saved ) ? 'checked="checked"' : '';
        $output .= '<li>' . self::settingsFields($name, $checked) . '</li>';
      endforeach;
      $output .= '</ul>';

      // the meta information
      // $output .= '<h2> Do you want to force any meta information on everything? </h2>';

      $output .= '<input type="submit" name="submit" id="submit"
        class="button button-primary" value="Save">';

    $output .= '</form>';

    echo $output;
  }


  /**
   * Save the fields
   *
   * We check for some verifications, then santizie and validate, and finally
   * save or delete the option
   *
   * @return [type] [description]
   */
  function saveFields() {

		if (
      get_current_screen()->id !== 'tools_page_wordpress-jekyll-cpt-filter'
      || ! current_user_can( 'manage_options')
      || ! isset( $_POST['jekyll_cpts_saved'] )
      || wp_verify_nonce('jekyll_cpts_saved', 'jekyll_cpts_saved' ) > 1
    ){
      return;
    }

    // if the jekll cpts option is not set then we delete it from the options table
    if( ! isset( $_POST['jekyll_cpts'] ))
      delete_option('wordpress-jekyll-cpts');

    // just set the fields tot a variable for easier typing
    $fields = $_POST['jekyll_cpts'];

    // sanitize and validate the CPTs
    $fields = self::sanitizeFields($fields);
    $fields = self::validateFields($fields);

    // so long as the values are valid, save the options
    if($fields !== false)
      update_option('wordpress-jekyll-cpts', $fields, false);
	}

  /**
   * Sanitize the fields
   */
  function sanitizeFields( $fields = array() )
  {
    foreach($fields as &$field)
      $field = filter_var($field, FILTER_SANITIZE_STRING);

      return $fields;
  }

  /**
   * Validate that the submitted values exist in the potential CPTs we collected earlier
   */
  function validateFields( $fields = array() )
  {

    if(!array_diff($fields, $this->post_types))
      return $fields;

    return false;
  }

}
$jekyll_cpt_filter = new jekyll_cpt_filter();
