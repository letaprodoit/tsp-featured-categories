<?php
    /**
     * TSP_Easy_Dev_Options_Featured_Categories - Extends the TSP_Easy_Dev_Options Class
     * @package TSP_Easy_Dev
     * @author sharrondenice, letaprodoit
     * @author Sharron Denice, Let A Pro Do IT!
     * @copyright 2021 Let A Pro Do IT!
     * @license APACHE v2.0 (http://www.apache.org/licenses/LICENSE-2.0)
     * @version $Id: [FILE] [] [DATE] [TIME] [USER] $
     */

    class TSP_Easy_Dev_Options_Featured_Categories extends TSP_Easy_Dev_Options
    {
        /**
         * Implements the settings_page to display settings specific to this plugin
         *
         * @since 1.1.0
         *
         * @param void
         *
         * @return output to screen
         */
        function display_plugin_options_page()
        {
            $message = "";

            $error = "";

            // get settings from database
            $shortcode_fields = get_option( $this->get_value('shortcode-fields-option-name') );

            $defaults = new TSP_Easy_Dev_Data ( $shortcode_fields, 'shortcode' );

            $form = null;
            if ( array_key_exists( $this->get_value('name') . '_form_submit', $_REQUEST ))
            {
                $form = $_REQUEST[ $this->get_value('name') . '_form_submit'];
            }//endif

            // Save data for settings page
            if( isset( $form ) && check_admin_referer( $this->get_value('name'), $this->get_value('name') . '_nonce_name' ) )
            {
                $defaults->set_values( $_POST );
                $shortcode_fields = $defaults->get();

                update_option( $this->get_value('shortcode-fields-option-name'), $shortcode_fields );

                $message = __( "Options saved.", $this->get_value('name') );
            }

            $form_fields = $defaults->get_values( true );

            // Display settings to screen
            $smarty = new TSP_Easy_Dev_Smarty( $this->get_value('smarty_template_dirs'),
                $this->get_value('smarty_cache_dir'),
                $this->get_value('smarty_compiled_dir'), true );

            global $featured_categories;

            $smarty->assign( 'plugin_title',			TSPFC_PLUGIN_TITLE);
            $smarty->assign( 'plugin_links',			implode(' | ', $featured_categories->get_meta_links()));
            $smarty->assign( 'EASY_DEV_SETTINGS_UI',	$this->get_value('name') . '_child-page-instructions.tpl');

            $smarty->assign( 'form_fields',				$form_fields);
            $smarty->assign( 'message',					$message);
            $smarty->assign( 'error',					$error);
            $smarty->assign( 'form',					$form);
            $smarty->assign( 'plugin_name',				$this->get_value('name'));
            $smarty->assign( 'nonce_name',				wp_nonce_field( $this->get_value('name'), $this->get_value('name').'_nonce_name' ));

            $smarty->display( 'easy-dev-child-page-default.tpl');

        }//end settings_page

    }//end TSP_Easy_Dev_Options_Featured_Categories


    /**
     * TSP_Easy_Dev_Widget_Featured_Categories - Extends the TSP_Easy_Dev_Widget Class
     * @package TSPEasyPlugin
     * @author sharrondenice, letaprodoit
     * @author Sharron Denice, Let A Pro Do IT!
     * @copyright 2021 Let A Pro Do IT!
     * @license APACHE v2.0 (http://www.apache.org/licenses/LICENSE-2.0)
     * @version $Id: [FILE] [] [DATE] [TIME] [USER] $
     */

    /**
     * Extends the TSP_Easy_Dev_Widget_Facepile Class
     *
     * original author: Sharron Denice
     */
    class TSP_Easy_Dev_Widget_Featured_Categories extends TSP_Easy_Dev_Widget
    {
        /**
         * Constructor
         */
        public function __construct()
        {
            add_filter( get_class()  .'-init', 	array( $this, 'init'), 10, 1 );
            add_action( 'admin_init', 			array( $this, 'copy_term_metadata' ));
        }//end __construct


        /**
         * Function added to filter to allow initialization of widget
         *
         * @since 1.1.0
         *
         * @param object $options Required - pass in reference to options class
         *
         * @return void
         */
        public function init( $options )
        {
            // Create the widget
            parent::__construct( $options );
        }//end init

        /**
         * Override required of form function to display widget information
         *
         * @since 1.1.0
         *
         * @param array $fields Required - array of current values
         *
         * @return void - display to widget box
         *
         */
        public function display_form( $fields )
        {
            if (!empty($this->options))
            {
                $smarty = new TSP_Easy_Dev_Smarty( $this->options->get_value('smarty_template_dirs'),
                    $this->options->get_value('smarty_cache_dir'),
                    $this->options->get_value('smarty_compiled_dir'), true );

                $smarty->assign( 'shortcode_fields', $fields );
                $smarty->assign( 'class', 'widefat' );
                $smarty->display( 'easy-dev-shortcode-form.tpl' );
            }
        }//end form

        /**
         * Implementation (required) to print widget & shortcode information to screen
         *
         * @since 1.1.0
         *
         * @param array $fields  - the settings to display
         * @param boolean $echo Optional - if false returns output instead of displaying to screen
         * @param string $tag Optional - the name of the shortcode being processed
         *
         * @return string $output if echo is true displays to screen else returns string
         *
         * @throws SmartyException
         */
        public function display_widget( $fields, $echo = true, $tag = null )
        {
            extract ( $fields );

            $return_HTML = "";

            // If there is a title insert before/after title tags
            if (!empty($fields['title'])) {
                $return_HTML .= $fields['before_title'] . $fields['title'] . $fields['after_title'];
            }

            $queried_categories = array();

            $pro_term = $this->options->get_pro_term();

            $all_term_data = $pro_term->get_term_metadata();

            // remove unnecessary spaces from cat_ids #FC-13
            if (!empty($fields['cat_ids']))
            {
                $fields['cat_ids'] = preg_replace( "/\s+/", " ", $fields['cat_ids'] ); //remove extra spaces
                $fields['cat_ids'] = preg_replace( "/\,(\s+)/", ",", $fields['cat_ids'] ); // remove comma's with extra spaces
                $fields['cat_ids'] = preg_replace( "/(\s+)/", ",", $fields['cat_ids'] ); // replace spaces with commas
            }

            // If the user wants to display only featured categories then add only featured categories to the array
            // else add them all
            if ($fields['cat_type'] == 'featured')
            {
                // Return all categories
                $cat_args = array(
                    'taxonomy' => $fields['taxonomy'],
                    'orderby' => $fields['order_by'],
                    'child_of' => $fields['parent_cat'],
                    'include' => $fields['cat_ids'],
                    'hide_empty' => ($fields['hide_empty'] == 'Y') ? 1 : 0
                );
                $all_categories = get_terms( $cat_args );

                $cat_cnt = 1;

                // Add only featured categories
                foreach ($all_categories as $category)
                {
                    $term_ID = $category->term_id;
                    $featured = null;

                    if (!empty( $all_term_data ))
                    {
                        // Determine if the category is featured
                        if ( array_key_exists( $term_ID, $all_term_data ) )
                        {
                            if ( array_key_exists( 'featured', $all_term_data[$term_ID] ) )
                            {
                                $featured   = $all_term_data[$term_ID]['featured'];
                            }//end if
                        }//end if

                    }

                    if ($featured && $cat_cnt <= $fields['number_cats'])
                    {
                        $queried_categories[] = $category;
                    }//end if

                    $cat_cnt++;
                }//endforeach
            }//endif
            else
            {
                // Return all categories with a limit of $number_cats categories
                $cat_args = array(
                    'taxonomy' => $fields['taxonomy'],
                    'orderby' => $fields['order_by'],
                    'number' => $fields['number_cats'],
                    'child_of' => $fields['parent_cat'],
                    'include' => $fields['cat_ids'],
                    'hide_empty' => ($fields['hide_empty'] == 'Y') ? 1 : 0
                );
                $queried_categories = get_terms( $cat_args );
            }//endelse

            // Now display the category
            $media = "";
            $cat_cnt = 0;
            $num_cats = sizeof($queried_categories);

            $cat_width = '100%';

            if ( $num_cats > 1 )
            {
                // Fix for ticket #FC-11
                $cat_width = round(95 / $num_cats); //divide category with by number of categories

                if ($cat_width < 20)
                    $cat_width = 20;

                $cat_width .= '%';
            }//end if

            foreach ($queried_categories as $category)
            {
                $term_ID = $category->term_id;

                // get the fields stored in the database for this post
                $term_fields = $pro_term->get_term_fields( $term_ID, $category->taxonomy );

                $url = site_url()."/".$category->slug;
                $title = $category->name;

                $desc = $category->description;

                if (strlen($category->description) > $fields['max_desc'] && $fields['layout'] != 2)
                {
                    $chop_desc = substr($category->description, 0, $fields['max_desc']);
                    $desc = $chop_desc."...";
                }//end if

                $cat_cnt++;

                $smarty = new TSP_Easy_Dev_Smarty( $this->options->get_value('smarty_template_dirs'),
                    $this->options->get_value('smarty_cache_dir'),
                    $this->options->get_value('smarty_compiled_dir'), true );

                // Store values into Smarty
                foreach ($fields as $key => $val)
                {
                    $smarty->assign("$key", $val, true);
                }//end foreach

                // Store values into Smarty
                foreach ($term_fields as $key => $val)
                {
                    if ($key == "image")
                    {
                        if (empty($val))
                        {
                            global $wpdb, $table_prefix;

                            $termdata = $wpdb->get_results( "SELECT `meta_value` FROM {$table_prefix}termmeta WHERE `meta_key` LIKE '%thumbnail_id' AND `term_id` = {$term_ID}" );

                            if (!empty($termdata))
                            {
                                $image = wp_get_attachment_image_src( $termdata[0]->meta_value, array(
                                    'width'     => $fields['thumb_width'],
                                    'height'    => $fields['thumb_height'],
                                ));

                                if (!empty($image))
                                    $media = $image[0];
                            }
                        }
                        else
                            $media = $val;

                        $smarty->assign("image", $media, true);
                    }
                    else
                    {
                        $smarty->assign("$key", $val, true);
                    }
                }//end foreach


                // Only show categories that have associated images if $show_text_categories is set to 'Y' and
                // $show_text_categories is 'N' and there are at least a video or image
                if ( $fields['show_text_categories'] == 'N' && empty( $media ) )
                    continue;


                $smarty->assign("title", 			$title, true);
                $smarty->assign("url", 				$url, true);
                $smarty->assign("desc", 			$desc, true);
                $smarty->assign("cat_term", 		$category->term_id, true);
                $smarty->assign("adj_thumb_height",	round($fields['thumb_height'] / 2), true);
                $smarty->assign("cat_width",		$cat_width, true);
                $smarty->assign("first_cat",		($cat_cnt == 1) ? true : null, true);
                $smarty->assign("last_cat",			($cat_cnt == $num_cats) ? true : null, true);

                $return_HTML .= $smarty->fetch( $this->options->get_value('name') . '_layout'.$fields['layout'].'.tpl' );

            }//endforeach

            if ($echo)
                echo $return_HTML;

            return $return_HTML;
        }//end display

        /**
         * Copy the data from the category metadata to the options table
         *
         * @ignore - Must be public, used by WordPress hooks
         *
         * @since 1.0
         *
         * @return void
         */
        public function copy_term_metadata()
        {
            global $wpdb;

            $tables_copied = true;

            $old_tables = array( $wpdb->prefix . 'termsmeta', $wpdb->prefix . 'tspfc_termsmeta');

            $all_term_data = $this->options->get_pro_term()->get_term_metadata();

            foreach ( $old_tables as $table_name )
            {
                // if the category table exists then this is the first time we are copying the data
                if ($wpdb->get_var("show tables like '{$table_name}'") == $table_name )
                {
                    $tables_copied = false;
                }//end if

                // if old table exists and this is a new install, copy its contents into the new table
                if ( !$tables_copied )
                {
                    $sql     = "SELECT * FROM `$table_name` WHERE `meta_key` = 'featured' OR `meta_key` = 'image';";
                    $entries = $wpdb->get_results($sql, ARRAY_A);

                    foreach ( $entries as $e )
                    {

                        $id = $e['terms_id'];
                        $key = $e['meta_key'];

                        $all_term_data[$id][$key] = $e['meta_value'];
                    }//endforeach

                    $this->drop_table( $table_name );
                }//end if
            }//end foreach

            if (! $tables_copied )
            {
                update_option( $this->options->get_value('term-data-option-name'), $all_term_data );
            }//end if
        }//end copy_term_metadata_table


        /**
         *  Implementation to remove category metadata tables
         *
         * @since 1.0
         *
         * @param void
         *
         * @return void
         */
        private function drop_table ( $table_name )
        {
            global $wpdb;

            $wpdb->query( 'DROP TABLE IF EXISTS ' . $table_name );
        }//end remove_term_table
    }//end TSP_Easy_Dev_Widget_Featured_Categories