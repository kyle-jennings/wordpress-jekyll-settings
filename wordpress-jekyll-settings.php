<?php
/*
Plugin Name: WordPress to Jekyll Settings
Description: Provides a settings page to select exactly which post types get exported wiith Ben Balter's <a href="https://wordpress.org/plugins/jekyll-exporter/" target="_blank" >Jekyll Exporter</a> plugin
Author: Kyle Jennings
Author URI: https://github.com/kyle-jennings
Version: 0.1
Text Domain: wordpress-jekyll-settings
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

    public $jekyll_cpts;
    public $jekyll_layouts = array(
        'auto',
        'posts',
        'pages',
        'default',
    );

    public $settings = array(
        'jekyll_cpts' => array(
            'heading' => 'Which post types do you want to export into Jekyll?',
            'type'=>'checkbox',
            'choices' => array()
        ),
        'jekyll_layouts' => array(
            'heading' => 'What layouts do you want to use?',
            'type' => 'radio',
            'choices' => array(
                'auto',
                'posts',
                'pages',
                'default',
            )
        )
    );


    // the construct
    function __construct()
    {
        add_action( 'current_screen', array( $this, 'saveFields' ) );
        add_action( 'admin_init', array( $this, 'collectAllPostTypes') );
        add_action( 'admin_menu', array( $this, 'registerMenu' ) );
        add_filter( 'jekyll_export_post_types', array($this, 'jekyll_export_post_types'));
        add_filter( 'jekyll_export_meta', array($this, 'jekyll_export_meta'));
    }


    // hook into the jekyll export post type filter and add CPTs
    function jekyll_export_post_types($defaults)
    {
        return (array) get_option('wordpress-jekyll_cpts', array( 'post', 'page', 'revision' ) );
    }


    // the filter for the meta information, specifically the layout
    function jekyll_export_meta($meta)
    {
        $layout = get_option('wordpress-jekyll_layouts', 'auto' );
        $meta['layout'] = ($layout == 'auto') ? $meta['layout'] : $layout ;

        return $meta;
    }


    // collect all the post types available in the system
    function collectAllPostTypes()
    {
        $post_types = get_post_types();
        $this->settings['jekyll_cpts']['choices'] = $this->jekyll_cpts = $post_types;
    }


    // registers teh settings page
    function registerMenu()
    {
        add_management_page(
            __( 'WordPress to Jekyll Settings', 'wordpress-jekyll-settings' ),
            __( 'WordPress to Jekyll Settings', 'wordpress-jekyll-settings' ),
            'manage_options',
            'wordpress-jekyll-settings',
            array($this, 'settingsPage')
        );
    }


    // the markup for the settings fields
    static public function fieldMarkup($name, $checked, $type = 'checkbox', $setting = 'cpts') {

        $find = array('_', '-');
        $label = ucwords(str_replace($find, ' ', $name));

        $field_name = $type == 'checkbox' ? $setting.'[]' : $setting;

        // the field
        $output = '';
        $output .= '<input class="" id="'.$setting.'-'.$name.'" type="'.$type.'"
             name="'.$field_name.'" value="'.$name.'" '.$checked.'>';

        // the label
        $output .= '<label for="cpt-'.$name.'">' . $label . '</label>';

        return $output;
    }

    // makes the list options
    function choicesMarkup($attrs, $saved, $setting)
    {
        $output = '';
        $output .= '<ul>';
        foreach($attrs['choices'] as $name):
            $checked = in_array( $name, $saved ) ? 'checked="checked"' : '';
            $output .= '<li>';
                $output .= self::fieldMarkup($name, $checked, $attrs['type'], $setting);
            $output .= '</li>';
        endforeach;
        $output .= '</ul>';

        return $output;
    }

    /**
    * The settings page form
    */
    function settingsPage()
    {

        $output = '';
        $output .= '<h1> WordPress to Jekyll export Settings </h1>';

        $output .= '<form method="post">';
        $output .= wp_nonce_field( 'jekyll_settings_saved', 'jekyll_settings_saved', true, false ) ;

        foreach($this->settings as $setting => $attrs) {

            $saved = (array) get_option('wordpress-'.$setting, array( 'post', 'page', 'revision' ) );

            $output .= '<h2>'.$attrs['heading'].'</h2>';
            $output .= self::choicesMarkup($attrs, $saved, $setting);


        }

        // the submit
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
            get_current_screen()->id !== 'tools_page_wordpress-jekyll-settings'
            || ! current_user_can( 'manage_options')
            || ! isset( $_POST['jekyll_settings_saved'] )
            || wp_verify_nonce('jekyll_settings_saved', 'jekyll_settings_saved' ) > 1
        ){
            return;
        }

        // loop through the settings and save, update, or delete things
        foreach($this->settings as $setting => $attrs){
            // if the jekll cpts option is not set then we delete it from the options table
            if( ! isset( $_POST[$setting] ))
                delete_option('wordpress-'.$setting);

            // just set the fields tot a variable for easier typing
            $fields = $_POST[$setting];

            // sanitize and validate the CPTs
            $fields = self::sanitizeFields($fields);
            $fields = self::validateFields($fields, $attrs['choices']);

            // so long as the values are valid, save the options
            if($fields !== false)
                update_option('wordpress-'.$setting, $fields, false);
        }


    }

    /**
    * Sanitize the fields
    */
    function sanitizeFields( $fields = array() )
    {
        if(empty($fields))
            return $fields;


        foreach((array)$fields as &$field)
            $field = filter_var($field, FILTER_SANITIZE_STRING);

        return $fields;
    }

    /**
    * Validate that the submitted values exist in the potential CPTs we collected earlier
    */
    function validateFields( $fields = array(), $check_against = array() )
    {

        if(!array_diff( (array) $fields, $check_against ))
            return $fields;

        return false;
    }


    // helper function for developer
    public function examine($object, $examine_type = 'print_r', $die = 'hard'){
        if(empty($object))
            return;
        echo '<pre>';
        if($examine_type == 'var_dump')
            var_dump($object);
        else
            print_r($object);
        if($die != 'soft')
            die;
    }

}
$jekyll_cpt_filter = new jekyll_cpt_filter();
