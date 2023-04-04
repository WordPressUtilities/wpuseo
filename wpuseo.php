<?php

/*
Plugin Name: WPU SEO
Plugin URI: https://github.com/WordPressUtilities/wpuseo
Update URI: https://github.com/WordPressUtilities/wpuseo
Description: Enhance SEO : Clean title, Nice metas, GDPR friendly Analytics.
Version: 2.20.1
Author: Darklg
Author URI: https://darklg.me/
Text Domain: wpuseo
Domain Path: /lang
Requires at least: 6.0
Requires PHP: 8.0
License: MIT License
License URI: https://opensource.org/licenses/MIT
Contributors: @boiteaweb, @CecileBr
*/

class WPUSEO {

    public $plugin_version = '2.20.1';
    private $active_wp_title = true;
    private $active_metas = true;
    private $fake_txt_files = array('ads', 'robots');
    private $plugin_description;
    private $thumbnail_size;
    private $enable_twitter_metas;
    private $enable_facebook_metas;
    private $boxes_pt;
    private $boxes_pt_with_archive;
    private $twitter_cards;
    private $settings_update;

    public function __construct() {
        add_action('init', array(&$this,
            'init'
        ));
    }

    public function init() {

        $this->active_wp_title = !apply_filters('wpuseo__disable__wp_title', false);
        $this->active_metas = !apply_filters('wpuseo__disable__metas', false);

        $this->check_config();
        $this->load_translation();

        // Ads
        add_action('wpuoptions__post_update', array(&$this,
            'wpuoptions__post_update__update_fake_files'
        ));
        add_action('init', array(&$this,
            'add_rewrite_rule__fake_files'
        ));
        add_filter('redirect_canonical', array(&$this,
            'redirect_canonical_callback'
        ), 100, 2);
        add_action('query_vars', array(&$this,
            'query_vars__fakefiles'
        ));
        add_action('wp', array(&$this,
            'display_fake_file_content'
        ));

        // Filter
        if ($this->active_wp_title) {
            add_filter('wp_title', array(&$this,
                'wp_title'
            ), 999, 2);
            add_filter('pre_get_document_title', array(&$this,
                'wp_title'
            ), 999, 2);
            add_filter('wp_title_rss', array(&$this,
                'rss_wp_title'
            ), 999, 2);
        }

        // Preview
        if ($this->active_wp_title && $this->active_metas) {
            add_filter('wpupostmetas__box_content__after_table', array(&$this,
                'admin_box_preview'
            ), 10, 2);
            add_filter('wputaxometas__box_content__after_field_main', array(&$this,
                'admin_box_preview_taxo'
            ), 10, 6);
        }

        // Actions
        if ($this->active_metas) {
            add_action('wp', array(&$this,
                'add_metas_robots'
            ), 1, 0);
            add_action('wp_head', array(&$this,
                'add_metas'
            ), 1);
        }

        // Native sitemap
        add_filter('wp_sitemaps_posts_query_args', array(&$this,
            'wp_sitemaps_posts_query_args'
        ), 10, 2);

        // Cookies
        add_action('wp_head', array(&$this,
            'wp_head_cookie_helper'
        ), 10, 0);
        add_action('wp_enqueue_scripts', array(&$this,
            'wp_enqueue_scripts'
        ), 10, 0);
        add_action('wp_footer', array(&$this,
            'wp_footer'
        ), 50, 0);
        add_action('rocket_excluded_inline_js_content', array(&$this,
            'rocket_excluded_inline_js_content'
        ), 10, 1);

        add_action('wp_head', array(&$this,
            'display_google_analytics_code'
        ), 99, 0);
        add_action('wp_head', array(&$this,
            'display_facebook_pixel_code'
        ), 99, 0);
        add_action('wp_head', array(&$this,
            'display_custom_tracking'
        ), 99, 0);
        add_action('woocommerce_thankyou', array(&$this,
            'display_facebook_pixel_code__thankyou'
        ), 99, 1);

        // Clean WP Head
        add_action('template_redirect', array(&$this,
            'clean_wordpress_head'
        ));

        // Admin boxes
        add_filter('wpu_options_tabs', array(&$this,
            'add_tabs'
        ), 99, 1);
        add_filter('wpu_options_boxes', array(&$this,
            'add_boxes'
        ), 99, 1);
        add_filter('wpu_options_fields', array(&$this,
            'add_fields'
        ), 99, 1);
        add_filter('admin_head', array(&$this,
            'remove_boxes'
        ), 99);

        // User boxes
        add_filter('wpu_usermetas_sections', array(&$this,
            'add_user_sections'
        ), 10, 3);
        add_filter('wpu_usermetas_fields', array(&$this,
            'add_user_fields'
        ), 10, 3);

        // Post box
        add_filter('wputh_post_metas_boxes', array(&$this,
            'post_meta_boxes'
        ), 10, 3);
        add_filter('wputh_post_metas_fields', array(&$this,
            'post_meta_fields'
        ), 10, 3);
        // Taxo fields
        add_filter('wputaxometas_fields', array(&$this,
            'taxo_fields'
        ), 10, 3);

        $this->thumbnail_size = apply_filters('wpuseo_thumbnail_size', 'full');

        $this->enable_twitter_metas = (get_option('wpu_seo_user_twitter_enable') != '0');
        $this->enable_facebook_metas = (get_option('wpu_seo_user_facebook_enable') != '0');
        $this->boxes_pt = apply_filters('wpuseo_post_types', array(
            'product',
            'post',
            'page'
        ));
        $this->boxes_pt_with_archive = array_filter($this->boxes_pt, function ($a) {
            return !in_array($a, array('post', 'page'));
        });
        $this->twitter_cards = apply_filters('wpuseo_twitter_cards', array(
            'summary' => 'summary',
            'summary_large_image' => 'summary_large_image'
        ));

        require_once dirname(__FILE__) . '/inc/WPUBaseUpdate/WPUBaseUpdate.php';
        $this->settings_update = new \wpuseo\WPUBaseUpdate(
            'WordPressUtilities',
            'wpuseo',
            $this->plugin_version);
    }

    public function load_translation() {
        // Load lang
        $lang_dir = dirname(plugin_basename(__FILE__)) . '/lang/';
        if (!load_plugin_textdomain('wpuseo', false, $lang_dir)) {
            load_muplugin_textdomain('wpuseo', $lang_dir);
        }
        $this->plugin_description = __('Enhance SEO : Clean title, Nice metas, GDPR friendly Analytics.', 'wpuseo');
    }

    /* ----------------------------------------------------------
      Check config
    ---------------------------------------------------------- */

    public function check_config() {

        if (!is_admin()) {
            return;
        }
        include_once ABSPATH . 'wp-admin/includes/plugin.php';

        // Check if WPU Options is active
        if (!is_plugin_active('wpuoptions/wpuoptions.php')) {
            add_action('admin_notices', array(&$this,
                'set_error_missing_wpuoptions'
            ));
        }
    }

    public function set_error_missing_wpuoptions() {
        echo '<div class="error"><p>' . sprintf($this->__('The plugin <b>%s</b> depends on the <b>WPU Options</b> plugin. Please install and activate it.'), 'WPU SEO') . '</p></div>';
    }

    /* ----------------------------------------------------------
      Clean WordPress head
    ---------------------------------------------------------- */

    public function clean_wordpress_head() {
        if (!is_single()) {
            remove_action('wp_head', 'wp_shortlink_wp_head');
            remove_action('wp_head', 'adjacent_posts_rel_link_wp_head');
        }
    }

    /* ----------------------------------------------------------
      Metas
    ---------------------------------------------------------- */

    public function post_meta_boxes($boxes) {
        $boxes['wpuseo_box'] = array(
            'name' => $this->__('SEO Details'),
            'post_type' => $this->boxes_pt
        );
        if ($this->active_metas && $this->enable_twitter_metas) {
            $boxes['wpuseo_box_twitter'] = array(
                'name' => $this->__('SEO Details - Twitter'),
                'post_type' => $this->boxes_pt
            );
        }
        if ($this->active_metas && $this->enable_facebook_metas) {
            $boxes['wpuseo_box_facebook'] = array(
                'name' => $this->__('SEO Details - Facebook'),
                'post_type' => $this->boxes_pt
            );
        }
        return $boxes;
    }

    /* Meta fields
     -------------------------- */

    public function post_meta_fields($fields) {
        if ($this->active_wp_title) {
            $fields['wpuseo_post_title'] = array(
                'box' => 'wpuseo_box',
                'name' => $this->__('Page title'),
                'lang' => true
            );
        }
        if ($this->active_metas) {
            $fields['wpuseo_post_description'] = array(
                'box' => 'wpuseo_box',
                'name' => $this->__('Page description'),
                'type' => 'textarea',
                'lang' => true
            );
            $fields['wpuseo_hide_search'] = array(
                'box' => 'wpuseo_box',
                'name' => $this->__('Hide'),
                'checkbox_label' => $this->__('Hide from search engines'),
                'type' => 'checkbox'
            );
        }
        /* Twitter */
        $fields['wpuseo_post_image_twitter'] = array(
            'box' => 'wpuseo_box_twitter',
            'name' => $this->__('Image'),
            'type' => 'image',
            'lang' => true
        );
        $fields['wpuseo_post_title_twitter'] = array(
            'box' => 'wpuseo_box_twitter',
            'name' => $this->__('Page title'),
            'lang' => true
        );
        $fields['wpuseo_post_description_twitter'] = array(
            'box' => 'wpuseo_box_twitter',
            'name' => $this->__('Page description'),
            'type' => 'textarea',
            'lang' => true
        );
        /* Facebook */
        $fields['wpuseo_post_image_facebook'] = array(
            'box' => 'wpuseo_box_facebook',
            'name' => $this->__('Image'),
            'type' => 'attachment',
            'lang' => true
        );
        $fields['wpuseo_post_title_facebook'] = array(
            'box' => 'wpuseo_box_facebook',
            'name' => $this->__('Page title'),
            'lang' => true
        );
        $fields['wpuseo_post_description_facebook'] = array(
            'box' => 'wpuseo_box_facebook',
            'name' => $this->__('Page description'),
            'type' => 'textarea',
            'lang' => true
        );

        global $WPUPostMetas;
        /* Old version of wpupostmetas : use type attachment */
        if (is_object($WPUPostMetas) && isset($WPUPostMetas->version)) {
            $wpupostmetas_version = floatval($WPUPostMetas->version);
            if (is_float($wpupostmetas_version) && $wpupostmetas_version < 0.26) {
                $fields['wpuseo_post_image_twitter']['type'] = 'attachment';
                $fields['wpuseo_post_image_facebook']['type'] = 'attachment';
            }
        }

        return $fields;
    }

    /* Taxo fields
     -------------------------- */

    public function taxo_fields($fields) {
        $lang = apply_filters('wpuseo_enable_lang_on_taxos', true);
        $taxonomies = apply_filters('wpuseo_taxo_list', array(
            'category',
            'post_tag'
        ));
        $fields['wpuseo_title'] = array(
            'label' => $this->__('SEO Details'),
            'type' => 'title',
            'taxonomies' => $taxonomies
        );
        $fields['wpuseo_taxo_title'] = array(
            'label' => $this->__('Page title'),
            'taxonomies' => $taxonomies,
            'lang' => $lang
        );
        $fields['wpuseo_taxo_description'] = array(
            'label' => $this->__('Page description'),
            'taxonomies' => $taxonomies,
            'type' => 'textarea',
            'lang' => $lang
        );
        $fields['wpuseo_hide_search'] = array(
            'taxonomies' => $taxonomies,
            'label' => $this->__('Hide'),
            'long_label' => $this->__('Hide from search engines'),
            'type' => 'checkbox'
        );
        if ($this->enable_twitter_metas) {
            $fields['wpuseo_title_twitter'] = array(
                'label' => $this->__('SEO Details - Twitter'),
                'type' => 'title',
                'taxonomies' => $taxonomies
            );
            $fields['wpuseo_taxo_title_twitter'] = array(
                'label' => $this->__('Twitter:title'),
                'taxonomies' => $taxonomies,
                'lang' => $lang
            );
            $fields['wpuseo_taxo_description_twitter'] = array(
                'label' => $this->__('Twitter:description'),
                'type' => 'textarea',
                'taxonomies' => $taxonomies,
                'lang' => $lang
            );
            $fields['wpuseo_taxo_image_twitter'] = array(
                'label' => $this->__('Twitter:Image'),
                'type' => 'attachment',
                'taxonomies' => $taxonomies
            );

        }
        if ($this->enable_facebook_metas) {
            $fields['wpuseo_title_facebook'] = array(
                'label' => $this->__('SEO Details - Facebook'),
                'type' => 'title',
                'taxonomies' => $taxonomies
            );
            $fields['wpuseo_taxo_title_facebook'] = array(
                'label' => $this->__('OG:Title'),
                'taxonomies' => $taxonomies,
                'lang' => $lang
            );
            $fields['wpuseo_taxo_description_facebook'] = array(
                'label' => $this->__('OG:Description'),
                'type' => 'textarea',
                'taxonomies' => $taxonomies,
                'lang' => $lang
            );
            $fields['wpuseo_taxo_image_facebook'] = array(
                'label' => $this->__('Og:Image'),
                'type' => 'attachment',
                'taxonomies' => $taxonomies
            );
        }
        return $fields;
    }

    /* ----------------------------------------------------------
      Admin Options
    ---------------------------------------------------------- */

    public function add_tabs($tabs) {
        $tabs['wpu_seo'] = array(
            'name' => 'Options SEO'
        );
        return $tabs;
    }

    public function add_boxes($boxes) {
        $boxes['wpu_seo'] = array(
            'name' => $this->__('Main'),
            'tab' => 'wpu_seo'
        );
        $boxes['wpu_seo_home'] = array(
            'name' => $this->__('Homepage'),
            'tab' => 'wpu_seo'
        );
        $boxes['wpu_seo_bing'] = array(
            'name' => 'Bing',
            'tab' => 'wpu_seo'
        );
        $boxes['wpu_seo_google'] = array(
            'name' => 'Google',
            'tab' => 'wpu_seo'
        );
        $boxes['wpu_seo_custom'] = array(
            'name' => 'Custom',
            'tab' => 'wpu_seo'
        );
        $boxes['wpu_seo_fake_files'] = array(
            'name' => 'Fake files',
            'tab' => 'wpu_seo'
        );
        $boxes['wpu_seo_robots'] = array(
            'name' => 'Robots',
            'tab' => 'wpu_seo'
        );
        $boxes['wpu_seo_facebook'] = array(
            'name' => 'Facebook',
            'tab' => 'wpu_seo'
        );
        $boxes['wpu_seo_twitter'] = array(
            'name' => 'Twitter',
            'tab' => 'wpu_seo'
        );
        $boxes['wpu_seo_cookies'] = array(
            'name' => 'Cookies',
            'tab' => 'wpu_seo'
        );

        foreach ($this->boxes_pt_with_archive as $post_type) {
            $post_type_details = get_post_type_object($post_type);
            if (!$post_type_details) {
                continue;
            }
            $boxes['wpu_seo_pt__' . $post_type] = array(
                'name' => 'Post-type : ' . $post_type_details->label,
                'tab' => 'wpu_seo'
            );
        }

        return $boxes;
    }

    public function add_fields($options) {

        // Various
        if ($this->active_wp_title) {
            $options['wpu_home_title_separator'] = array(
                'label' => $this->__('Title separator'),
                'box' => 'wpu_seo',
                'help' => $this->__('Between site name and site description, or page name and site name')
            );
            $options['wpu_title_order'] = array(
                'label' => $this->__('Site name before page title'),
                'box' => 'wpu_seo',
                'type' => 'select'
            );
        }
        if ($this->active_metas) {
            $options['wpu_meta_keywords_min_len'] = array(
                'label' => $this->__('Meta keyword min length'),
                'box' => 'wpu_seo',
                'type' => 'number'
            );
        }
        $options['wpu_meta_image_default'] = array(
            'label' => $this->__('Default SEO Thumbnail'),
            'type' => 'media',
            'box' => 'wpu_seo',
            'help' => $this->__('If not filled, the current theme screenshot will be used')
        );

        // Home
        if ($this->active_wp_title) {
            $options['wpu_home_title_separator_hide_prefix'] = array(
                'label' => $this->__('Hide title prefix'),
                'type' => 'select',
                'box' => 'wpu_seo_home'
            );
            $options['wpu_home_page_title'] = array(
                'label' => $this->__('Page title'),
                'box' => 'wpu_seo_home'
            );
        }
        if ($this->active_metas) {
            $options['wpu_home_meta_description'] = array(
                'label' => $this->__('Meta description'),
                'type' => 'textarea',
                'box' => 'wpu_seo_home'
            );
            $options['wpu_home_meta_keywords'] = array(
                'label' => $this->__('Meta keywords'),
                'type' => 'textarea',
                'box' => 'wpu_seo_home'
            );
        }

        // Bing
        if ($this->active_metas) {
            $options['wpu_bing_site_verification'] = array(
                'label' => $this->__('Site verification ID'),
                'box' => 'wpu_seo_bing',
                'help' => $this->__('Use the content attribute of the validation meta tag') . ' (&lt;meta name="msvalidate.01" content="THECODE" /&gt;)'
            );
        }

        // Custom
        $options['wputh_custom_tracking_code'] = array(
            'label' => $this->__('Custom JS Code'),
            'box' => 'wpu_seo_custom',
            'type' => 'textarea',
            'help' => $this->__('Custom tracking code : Plugged to cookie notice if enabled. No HTML !')
        );

        // Fake files
        foreach ($this->fake_txt_files as $fake_file) {
            $options['wpu_seo_' . $fake_file . '_txt_content'] = array(
                'label' => sprintf($this->__('%s content'), ucfirst($fake_file) . '.txt'),
                'box' => 'wpu_seo_fake_files',
                'type' => 'textarea',
                'autoload' => false,
                'help' => sprintf($this->__('Content of the %s file'), $fake_file . '.txt')
            );
        }

        // Google
        $options['wpu_google_site_verification'] = array(
            'label' => $this->__('Site verification ID'),
            'box' => 'wpu_seo_google',
            'help' => $this->__('Use the content attribute of the validation meta tag') . ' (&lt;meta name="google-site-verification" content="THECODE" /&gt;)'
        );
        $options['wputh_ua_analytics'] = array(
            'label' => $this->__('Google Analytics ID'),
            'box' => 'wpu_seo_google'
        );
        $options['wputh_analytics_enableloggedin'] = array(
            'label' => $this->__('Enable Analytics for logged-in users'),
            'box' => 'wpu_seo_google',
            'type' => 'select'
        );
        $options['wputh_analytics_enableanonymizeip'] = array(
            'label' => $this->__('Enable anonymizeIp for Analytics'),
            'box' => 'wpu_seo_google',
            'type' => 'select'
        );
        $options['wputh_analytics_cookie_expires'] = array(
            'label' => $this->__('Cookie expiration for Analytics (in sec)'),
            'box' => 'wpu_seo_google',
            'type' => 'number',
            'help' => $this->__('If not filled, will use Google’s default value.')
        );

        // Facebook
        $options['wpu_seo_user_facebook_enable'] = array(
            'label' => $this->__('Enable Facebook metas'),
            'type' => 'select',
            'box' => 'wpu_seo_facebook'
        );
        $options['wputh_fb_domain'] = array(
            'label' => $this->__('Site verification ID'),
            'box' => 'wpu_seo_facebook',
            'help' => $this->__('Use the content attribute of the validation meta tag') . ' (&lt;meta name="facebook-domain-verification" content="THECODE" /&gt;)'
        );
        $options['wputh_fb_admins'] = array(
            'label' => $this->__('FB:Admins ID'),
            'box' => 'wpu_seo_facebook'
        );
        $options['wputh_fb_app'] = array(
            'label' => $this->__('FB:App ID'),
            'box' => 'wpu_seo_facebook'
        );
        $options['wputh_fb_pixel'] = array(
            'label' => $this->__('FB:Pixel ID'),
            'box' => 'wpu_seo_facebook'
        );
        $options['wputh_fbpixel_enableloggedin'] = array(
            'label' => $this->__('Enable FB:Pixel for logged-in users'),
            'box' => 'wpu_seo_facebook',
            'type' => 'select'
        );
        $options['wputh_fb_image'] = array(
            'label' => $this->__('OG:Image'),
            'type' => 'media',
            'box' => 'wpu_seo_facebook'
        );
        $options['wpu_homefb_page_title'] = array(
            'label' => $this->__('OG:Title Home'),
            'box' => 'wpu_seo_facebook'
        );
        $options['wpu_homefb_meta_description'] = array(
            'label' => $this->__('OG:Description Home'),
            'type' => 'textarea',
            'box' => 'wpu_seo_facebook'
        );

        // Twitter
        $options['wpu_seo_user_twitter_enable'] = array(
            'label' => $this->__('Enable Twitter metas'),
            'type' => 'select',
            'box' => 'wpu_seo_twitter'
        );
        $options['wpu_seo_user_twitter_site_username'] = array(
            'label' => $this->__('Twitter site @username'),
            'box' => 'wpu_seo_twitter'
        );
        $options['wpu_seo_user_twitter_account_id'] = array(
            'label' => $this->__('Twitter ads ID'),
            'box' => 'wpu_seo_twitter'
        );
        $options['wputh_twitter_card'] = array(
            'label' => $this->__('Twitter:Card format'),
            'type' => 'select',
            'datas' => $this->twitter_cards,
            'box' => 'wpu_seo_twitter'
        );
        $options['wputh_twitter_image'] = array(
            'label' => $this->__('Twitter:Image'),
            'type' => 'media',
            'box' => 'wpu_seo_twitter'
        );
        $options['wpu_hometwitter_page_title'] = array(
            'label' => $this->__('Title Home'),
            'box' => 'wpu_seo_twitter'
        );
        $options['wpu_hometwitter_meta_description'] = array(
            'label' => $this->__('Description Home'),
            'type' => 'textarea',
            'box' => 'wpu_seo_twitter'
        );

        // Cookies
        $options['wpu_seo_cookies__enable_notice'] = array(
            'label' => $this->__('Enable Cookie Notice'),
            'type' => 'select',
            'box' => 'wpu_seo_cookies'
        );
        $options['wpu_seo_cookies__enable_tracking'] = array(
            'label' => $this->__('Track before cookie check'),
            'type' => 'select',
            'box' => 'wpu_seo_cookies',
            'help' => $this->__('For test purposes only : do not use in production !')
        );
        $options['wpu_seo_cookies__text'] = array(
            'label' => $this->__('Banner text'),
            'type' => 'textarea',
            'lang' => true,
            'box' => 'wpu_seo_cookies'
        );
        $options['wpu_seo_cookies__button_accept'] = array(
            'label' => $this->__('"Accept" button text'),
            'type' => 'text',
            'lang' => true,
            'box' => 'wpu_seo_cookies'
        );
        $options['wpu_seo_cookies__button_refuse'] = array(
            'label' => $this->__('"Refuse" button text'),
            'type' => 'text',
            'lang' => true,
            'box' => 'wpu_seo_cookies'
        );
        $options['wpu_seo_cookies__display_refuse'] = array(
            'label' => $this->__('Display "refuse" button'),
            'type' => 'select',
            'box' => 'wpu_seo_cookies'
        );
        $options['wpu_seo_cookies__duration_choice'] = array(
            'label' => $this->__('Duration of choice (in days)'),
            'type' => 'number',
            'default_value' => 30,
            'box' => 'wpu_seo_cookies'
        );
        $options['wpu_seo_cookies__support_dnt'] = array(
            'label' => $this->__('Support DoNotTrack'),
            'type' => 'select',
            'box' => 'wpu_seo_cookies'
        );

        foreach ($this->boxes_pt_with_archive as $post_type) {

            if ($this->active_wp_title) {
                /* Title */
                $options['wpu_seo_pt__' . $post_type . '__page_title'] = array(
                    'label' => $this->__('Page title'),
                    'lang' => true,
                    'box' => 'wpu_seo_pt__' . $post_type
                );
            }
            if ($this->active_metas) {
                /* Desc */
                $options['wpu_seo_pt__' . $post_type . '__meta_description'] = array(
                    'label' => $this->__('Meta description'),
                    'type' => 'textarea',
                    'lang' => true,
                    'box' => 'wpu_seo_pt__' . $post_type
                );

                /* Twitter */
                if ($this->enable_twitter_metas) {
                    $options['wpu_seo_pt__' . $post_type . '_box_title_twitter'] = array(
                        'box' => 'wpu_seo_pt__' . $post_type,
                        'label' => $this->__('Twitter'),
                        'type' => 'title'
                    );
                    $options['wpu_seo_pt__' . $post_type . '_post_image_twitter'] = array(
                        'box' => 'wpu_seo_pt__' . $post_type,
                        'label' => $this->__('Image'),
                        'type' => 'media',
                        'lang' => true
                    );
                    $options['wpu_seo_pt__' . $post_type . '_post_title_twitter'] = array(
                        'box' => 'wpu_seo_pt__' . $post_type,
                        'label' => $this->__('Page title'),
                        'lang' => true
                    );
                    $options['wpu_seo_pt__' . $post_type . '_post_description_twitter'] = array(
                        'box' => 'wpu_seo_pt__' . $post_type,
                        'label' => $this->__('Page description'),
                        'type' => 'textarea',
                        'lang' => true
                    );
                }
                /* Facebook */
                if ($this->enable_facebook_metas) {
                    $options['wpu_seo_pt__' . $post_type . '_box_title_facebook'] = array(
                        'box' => 'wpu_seo_pt__' . $post_type,
                        'label' => $this->__('Facebook'),
                        'type' => 'title'
                    );
                    $options['wpu_seo_pt__' . $post_type . '_post_image_facebook'] = array(
                        'box' => 'wpu_seo_pt__' . $post_type,
                        'label' => $this->__('Image'),
                        'type' => 'media',
                        'lang' => true
                    );
                    $options['wpu_seo_pt__' . $post_type . '_post_title_facebook'] = array(
                        'box' => 'wpu_seo_pt__' . $post_type,
                        'label' => $this->__('Page title'),
                        'lang' => true
                    );
                    $options['wpu_seo_pt__' . $post_type . '_post_description_facebook'] = array(
                        'box' => 'wpu_seo_pt__' . $post_type,
                        'label' => $this->__('Page description'),
                        'type' => 'textarea',
                        'lang' => true
                    );
                }
            }
        }

        // Multilingual : defined here for retrocompatibility purposes
        if ($this->is_site_multilingual()) {
            if (isset($options['wpu_home_page_title'])) {
                $options['wpu_home_page_title']['lang'] = 1;
            }
            if (isset($options['wpu_home_meta_description'])) {
                $options['wpu_home_meta_description']['lang'] = 1;
            }
            if (isset($options['wpu_home_meta_keywords'])) {
                $options['wpu_home_meta_keywords']['lang'] = 1;
            }
            if (isset($options['wpu_homefb_page_title'])) {
                $options['wpu_homefb_page_title']['lang'] = 1;
            }
            if (isset($options['wpu_homefb_meta_description'])) {
                $options['wpu_homefb_meta_description']['lang'] = 1;
            }
            if (isset($options['wpu_seo_user_twitter_site_username'])) {
                $options['wpu_seo_user_twitter_site_username']['lang'] = 1;
            }
            if (isset($options['wpu_hometwitter_page_title'])) {
                $options['wpu_hometwitter_page_title']['lang'] = 1;
            }
            if (isset($options['wpu_hometwitter_meta_description'])) {
                $options['wpu_hometwitter_meta_description']['lang'] = 1;
            }
        }

        return $options;
    }

    public function remove_boxes() {
        if (!$this->is_admin_preview_home()) {
            return;
        }

        $selectors = array(
            '#wputh_box_wpuseo_box',
            '#wputh_box_wpuseo_box_twitter',
            '#wputh_box_wpuseo_box_facebook'
        );
        $rules = array();

        echo '<style>';
        foreach ($selectors as $selector) {
            echo $selector . ' hr, ' . $selector . ' .inside > table {display:none;}';
            echo $selector . ' .wpuseo-message-post{display:none!important;}';
            echo $selector . ' .wpuseo-message-home{display:block!important;}';
        }
        echo '</style>';
    }

    /* ----------------------------------------------------------
      User Options
    ---------------------------------------------------------- */

    public function add_user_sections($sections) {
        $sections['wpu-seo'] = array(
            'capability' => 'edit_posts',
            'name' => 'WPU SEO'
        );
        return $sections;
    }

    public function add_user_fields($fields) {
        $fields['wpu_seo_user_google_profile'] = array(
            'name' => 'Google+ URL',
            'section' => 'wpu-seo'
        );
        $fields['wpu_seo_user_twitter_account'] = array(
            'name' => '@TwitterUsername',
            'section' => 'wpu-seo'
        );
        return $fields;
    }

    /* ----------------------------------------------------------
      Taxo metas
    ---------------------------------------------------------- */

    public function get_taxo_meta($type) {
        global $q_config;
        $queried_object = $this->get_queried_object();

        $term_id = $queried_object->term_id;
        $metas = array();
        if (function_exists('get_taxonomy_metas')) {
            $metas = get_taxonomy_metas($term_id);
        }
        $seo_title = '';
        if (is_array($metas)) {
            if (isset($metas['wpuseo_taxo_' . $type])) {
                $seo_title = $metas['wpuseo_taxo_' . $type];
            }
            if (isset($q_config['language']) && isset($metas[$q_config['language'] . '__wpuseo_taxo_' . $type]) && !empty($metas[$q_config['language'] . '__wpuseo_taxo_' . $type])) {
                $seo_title = $metas[$q_config['language'] . '__wpuseo_taxo_' . $type];
            }
        }
        return $seo_title;
    }

    public function get_queried_object() {
        if (!is_admin() || !isset($_GET['tag_ID'], $_GET['taxonomy']) || !is_numeric($_GET['tag_ID'])) {
            return get_queried_object();
        }
        return get_term($_GET['tag_ID'], $_GET['taxonomy']);
    }

    /* ----------------------------------------------------------
      Sitemap
    ---------------------------------------------------------- */

    function wp_sitemaps_posts_query_args($args, $post_type) {

        /* Remove hidden posts from sitemap */
        $args['meta_query'] = isset($args['meta_query']) ? $args['meta_query'] : array();
        $args['meta_query'][] = array(
            'key' => 'wpuseo_hide_search',
            'value' => '1',
            'compare' => '!='
        );
        return $args;
    }

    /* ----------------------------------------------------------
      Page Title
    ---------------------------------------------------------- */

    public function rss_wp_title($content, $sep) {
        return $this->wp_title($content, $sep);
    }

    public function wp_title($title, $sep = '|') {

        $custom_sep = trim(get_option('wpu_home_title_separator'));
        if (!empty($custom_sep)) {
            $sep = htmlentities($custom_sep);
        }

        $wpu_title_order_before = get_option('wpu_title_order') == '1' ? '1' : '0';
        $bloginfo_name = apply_filters('wpuseo_bloginfo_name', get_bloginfo('name'));

        $spaced_sep = ' ' . $sep . ' ';
        $new_title = '';

        // Home : Exception for order
        if (is_home() || is_front_page() || is_feed() || $this->is_admin_preview_home()) {
            $wpu_title = trim($this->get_option_custom('wpu_home_page_title'));
            if (empty($wpu_title)) {
                $wpu_title = get_bloginfo('description');
            }
            $hide_prefix = get_option('wpu_home_title_separator_hide_prefix');
            if ($hide_prefix != '1') {
                $wpu_title = $bloginfo_name . $spaced_sep . $wpu_title;
            }

            $wpu_title = strip_tags($wpu_title);

            return apply_filters('wpuseo_title_after_settings', $wpu_title);
        }

        $new_title = $this->get_displayed_title();

        if (is_post_type_archive() && in_array(get_post_type(), $this->boxes_pt_with_archive)) {
            $post_type = get_post_type();
            $wpuseo_post_title = wputh_l18n_get_option('wpu_seo_pt__' . $post_type . '__page_title');
            if (!empty($wpuseo_post_title)) {
                $new_title = $wpuseo_post_title;
            }
        }

        if (is_singular() || $this->is_shop() || $this->is_admin_preview()) {
            $post_id = get_the_ID();
            if ($this->is_shop()) {
                $post_id = get_option('woocommerce_shop_page_id');
            }

            $wpuseo_post_title = trim($this->get_post_meta_custom($post_id, 'wpuseo_post_title', 1));
            if (!empty($wpuseo_post_title)) {
                $new_title = $wpuseo_post_title;
            }
        }

        if (is_category() || is_tax() || is_tag() || $this->is_admin_preview('term')) {
            $taxo_meta_title = $this->get_taxo_meta('title');
            if (!empty($taxo_meta_title)) {
                $new_title = $taxo_meta_title;
            }
        }

        $new_title = strip_tags($new_title);

        // Return new title with site name at the end
        if ($wpu_title_order_before) {
            $wpu_title = $bloginfo_name . $spaced_sep . $new_title;
        } else {
            $wpu_title = $new_title . $spaced_sep . $bloginfo_name;
        }

        return apply_filters('wpuseo_title_after_settings', $wpu_title);
    }

    public function get_displayed_title($prefix = true) {
        global $post;
        $displayed_title = $this->__('404 Error');
        $queried_object = $this->get_queried_object();
        if (is_search()) {
            $displayed_title = sprintf($this->__('Search results for "%s"'), get_search_query());
        }
        if (is_archive()) {
            $displayed_title = $this->__('Archive');
        }
        if (is_tax() || $this->is_admin_preview('term')) {
            $displayed_title = $queried_object->name;
        }
        if (is_tag() || $this->is_admin_preview('term')) {
            $displayed_title = ($prefix ? $this->__('Tag:') . ' ' : '') . $queried_object->name;
        }
        if (is_category() || $this->is_admin_preview('term')) {
            $displayed_title = ($prefix ? $this->__('Category:') . ' ' : '') . $queried_object->name;
        }
        if (is_post_type_archive()) {
            $displayed_title = post_type_archive_title('', false);
        }
        if (is_author()) {
            global $author;
            $author_name = get_query_var('author_name');
            $curauth = !empty($author_name) ? get_user_by('slug', $author_name) : get_userdata(intval($author));
            $displayed_title = $this->__('Author:') . ' ' . $curauth->nickname;
        }
        if (is_year()) {
            $displayed_title = ($prefix ? $this->__('Year:') . ' ' : '') . get_the_time($this->__('Y'));
        }
        if (is_month()) {
            $displayed_title = ($prefix ? $this->__('Month:') . ' ' : '') . get_the_time($this->__('F Y'));
        }
        if (is_day()) {
            $displayed_title = ($prefix ? $this->__('Day:') . ' ' : '') . get_the_time($this->__('F j, Y'));
        }
        if (is_singular() || $this->is_admin_preview()) {
            $displayed_title = get_the_title();
            if (property_exists($post, 'post_password') && !empty($post->post_password)) {
                if (post_password_required()) {
                    $displayed_title = apply_filters('wpuseo_protectedposthidden_title', $displayed_title, $post);
                } else {
                    $displayed_title = apply_filters('wpuseo_protectedpostvisible_title', $displayed_title, $post);
                }
            }
        }
        if (is_404()) {
            $displayed_title = $this->__('404 Error');
        }
        return apply_filters('wpuseo_displayed_title', $displayed_title);
    }

    /* ----------------------------------------------------------
      Meta content & open graph
    ---------------------------------------------------------- */

    public function get_metas() {
        global $post;
        $metas = apply_filters('wpuseo_metas_before_settings', array());
        $metas_json = array();
        $links = array();

        if ($this->enable_facebook_metas) {
            $metas['og_sitename'] = array(
                'property' => 'og:site_name',
                'content' => get_bloginfo('name')
            );

            $metas['og_type'] = array(
                'property' => 'og:type',
                'content' => 'website'
            );
        }

        /* Get screenshot */
        $screenshot = false;
        $current_theme = wp_get_theme();
        if ($current_theme && $current_theme->get_screenshot()) {
            $screenshot = $current_theme->get_screenshot();
        }
        if (!$screenshot) {
            $themes = wp_get_themes();
            foreach ($themes as $theme) {
                if ($current_theme->get_screenshot()) {
                    $screenshot = $theme->get_screenshot();
                    break;
                }
            }
        }

        $og_image = false;
        $og_image_id = false;
        if ($screenshot) {
            $og_image = $screenshot;
        }

        // Default image
        $og_image = apply_filters('wpuseo_default_image', $og_image);
        $default_image_id = get_option('wpu_meta_image_default');
        if ($default_image_id && is_numeric($default_image_id)) {
            $default_image_src = wp_get_attachment_image_src($default_image_id, $this->thumbnail_size, true);
            $og_image = $default_image_src[0];
            $og_image_id = $default_image_id;
        }

        // Default Facebook og:image
        if ($this->enable_facebook_metas) {
            $opt_wputh_fb_image = get_option('wputh_fb_image');
            if (is_numeric($opt_wputh_fb_image)) {
                $wputh_fb_image = wp_get_attachment_image_src($opt_wputh_fb_image, $this->thumbnail_size, true);
                if ($opt_wputh_fb_image != false && isset($wputh_fb_image[0])) {
                    $og_image = $wputh_fb_image[0];
                    $og_image_id = $opt_wputh_fb_image;
                }
            }
            $metas['og_image'] = array(
                'property' => 'og:image',
                'imgid' => $og_image_id,
                'content' => $og_image
            );
        }

        // Default Twitter twitter:image
        if ($this->enable_twitter_metas) {
            $opt_wputh_twitter_image = get_option('wputh_twitter_image');
            if (is_numeric($opt_wputh_twitter_image)) {
                $wputh_twitter_image = wp_get_attachment_image_src($opt_wputh_twitter_image, $this->thumbnail_size, true);
                if ($opt_wputh_twitter_image != false && isset($wputh_twitter_image[0])) {
                    $og_image = $wputh_twitter_image[0];
                }
            }
            $metas['twitter_image'] = array(
                'name' => 'twitter:image',
                'imgid' => $opt_wputh_twitter_image,
                'content' => $og_image
            );
        }
        $metas['image'] = array(
            'hidden' => 1,
            'imgid' => $og_image_id,
            'content' => $og_image
        );

        if ($this->enable_twitter_metas) {
            $twitter_card_format = get_option('wputh_twitter_card');
            if (!$twitter_card_format) {
                $twitter_card_format = 'summary';
            }

            /* Twitter : Summary card */
            $metas['twitter_card'] = array(
                'name' => 'twitter:card',
                'content' => $twitter_card_format
            );
        }

        $wpu_seo_user_twitter_site_username = trim($this->get_option_custom('wpu_seo_user_twitter_site_username'));
        if (!empty($wpu_seo_user_twitter_site_username) && $this->testTwitterUsername($wpu_seo_user_twitter_site_username) && $this->enable_twitter_metas) {
            $metas['twitter_site'] = array(
                'name' => 'twitter:site',
                'content' => $wpu_seo_user_twitter_site_username
            );
            $metas['twitter_creator'] = array(
                'name' => 'twitter:creator',
                'content' => $wpu_seo_user_twitter_site_username
            );
        }

        $wpu_seo_user_twitter_account_id = trim(get_option('wpu_seo_user_twitter_account_id'));
        if (!empty($wpu_seo_user_twitter_account_id) && $this->enable_twitter_metas) {
            $metas['twitter_account_id'] = array(
                'property' => 'twitter:account_id',
                'content' => $wpu_seo_user_twitter_account_id
            );
        }

        if (is_post_type_archive() && in_array(get_post_type(), $this->boxes_pt_with_archive)) {

            /* Default values */
            $post_type = get_post_type();
            $post_type_obj = get_post_type_object($post_type);
            $post_type_label = $post_type_obj->label;
            $title = wputh_l18n_get_option('wpu_seo_pt__' . $post_type . '__page_title');
            $description = wputh_l18n_get_option('wpu_seo_pt__' . $post_type . '__meta_description');

            /* Meta description */
            $title = $title ? $title : $post_type_label;
            $description = $description ? $description : $title;
            $metas['description'] = array(
                'name' => 'description',
                'content' => $this->prepare_text($description)
            );

            if ($this->enable_twitter_metas) {

                /* Default values */
                $twitter_title = trim(wputh_l18n_get_option('wpu_seo_pt__' . $post_type . '_post_title_twitter'));
                $twitter_description = trim(wputh_l18n_get_option('wpu_seo_pt__' . $post_type . '_post_description_twitter'));
                $twitter_image = trim(wputh_l18n_get_option('wpu_seo_pt__' . $post_type . '_post_image_twitter'));

                /* Title */
                $twitter_title = $twitter_title ? $twitter_title : $title;
                $metas['twitter_title'] = array(
                    'name' => 'twitter:title',
                    'content' => $twitter_title
                );

                /* Description */
                $twitter_description = $twitter_description ? $twitter_description : $description;
                $metas['twitter_description'] = array(
                    'name' => 'twitter:description',
                    'content' => $this->prepare_text($twitter_description)
                );

                /* Image */
                if (is_numeric($twitter_image)) {
                    $thumb_url = wp_get_attachment_image_src($twitter_image, $this->thumbnail_size, true);
                    if (isset($thumb_url[0])) {
                        $metas['twitter_image'] = array(
                            'name' => 'twitter:image',
                            'imgid' => $twitter_image,
                            'content' => $thumb_url[0]
                        );
                    }
                }
            }

            if ($this->enable_facebook_metas) {

                /* Default values */
                $facebook_title = trim(wputh_l18n_get_option('wpu_seo_pt__' . $post_type . '_post_title_facebook'));
                $facebook_description = trim(wputh_l18n_get_option('wpu_seo_pt__' . $post_type . '_post_description_facebook'));
                $facebook_image = trim(wputh_l18n_get_option('wpu_seo_pt__' . $post_type . '_post_image_facebook'));

                /* Title */
                $facebook_title = $facebook_title ? $facebook_title : $title;
                $metas['og_title'] = array(
                    'property' => 'og:title',
                    'content' => $facebook_title
                );

                /* Description */
                $facebook_description = $facebook_description ? $facebook_description : $description;
                $metas['og_description'] = array(
                    'property' => 'og:description',
                    'content' => $this->prepare_text($facebook_description)
                );

                /* Image */
                if (is_numeric($facebook_image)) {
                    $thumb_url = wp_get_attachment_image_src($facebook_image, $this->thumbnail_size, true);
                    if (isset($thumb_url[0])) {
                        $metas['og_image'] = array(
                            'property' => 'og:image',
                            'imgid' => $facebook_image,
                            'content' => $thumb_url[0]
                        );
                    }
                }
            }

        }

        if (is_category() || is_tax() || is_tag() || $this->is_admin_preview('term')) {
            $queried_object = $this->get_queried_object();
            $description = $queried_object->description;
            $taxo_meta_title = $this->get_taxo_meta('title');
            if (empty($taxo_meta_title)) {
                $taxo_meta_title = $queried_object->name;
            }
            $taxo_meta_description = $this->get_taxo_meta('description');
            if (!empty($taxo_meta_description)) {
                $description = $taxo_meta_description;
            }
            if (empty($description)) {
                $description = $taxo_meta_title;
            }

            $metas['description'] = array(
                'name' => 'description',
                'content' => $this->prepare_text($description)
            );

            if ($this->enable_twitter_metas) {

                /* Title */
                $twitter_title = trim($this->get_taxo_meta('title_twitter'));
                if (empty($twitter_title)) {
                    $twitter_title = $taxo_meta_title;
                }
                $metas['twitter_title'] = array(
                    'name' => 'twitter:title',
                    'content' => $twitter_title
                );
                /* Description */
                $twitter_description = $this->prepare_text($this->get_taxo_meta('description_twitter'));
                if (empty($twitter_description)) {
                    $twitter_description = $description;
                }
                $metas['twitter_description'] = array(
                    'name' => 'twitter:description',
                    'content' => $twitter_description
                );
                $custom_twitter_image = $this->get_taxo_meta('image_twitter');
                if (is_numeric($custom_twitter_image)) {
                    $thumb_url = wp_get_attachment_image_src($custom_twitter_image, $this->thumbnail_size, true);
                    if (isset($thumb_url[0])) {
                        $metas['twitter_image'] = array(
                            'name' => 'twitter:image',
                            'imgid' => $custom_twitter_image,
                            'content' => $thumb_url[0]
                        );
                    }
                }
            }

            if ($this->enable_facebook_metas) {
                $facebook_title = $this->prepare_text($this->get_taxo_meta('title_facebook'));
                if (empty($facebook_title)) {
                    $facebook_title = $taxo_meta_title;
                }
                $metas['og_title'] = array(
                    'property' => 'og:title',
                    'content' => $facebook_title
                );
                /* Description */
                $facebook_description = trim($this->get_taxo_meta('description_facebook'));
                if (empty($facebook_description)) {
                    $facebook_description = $description;
                }
                $metas['og_description'] = array(
                    'property' => 'og:description',
                    'content' => $facebook_description
                );

                $custom_og_image = $this->get_taxo_meta('image_facebook');
                if (is_numeric($custom_og_image)) {
                    $thumb_url = wp_get_attachment_image_src($custom_og_image, $this->thumbnail_size, true);
                    if (isset($thumb_url[0])) {
                        $metas['og_image'] = array(
                            'property' => 'og:image',
                            'imgid' => $custom_og_image,
                            'content' => $thumb_url[0]
                        );
                    }
                }
            }

        }

        if (!is_home() && !is_front_page() && (is_single() || is_page() || is_singular() || $this->is_shop() || $this->is_admin_preview())) {

            $post_infos = $post;
            $post_type = get_post_type($post);
            $post_id = get_the_ID();

            if ($this->is_shop()) {
                $post_id = get_option('woocommerce_shop_page_id');
                $post_infos = get_post($post_id);
            }

            $description = apply_filters('wpuseo_post_description_main', $post_infos->post_excerpt);
            if (empty($description)) {
                $description = $post_infos->post_content;
            }

            $wpuseo_post_description = trim($this->get_post_meta_custom($post_id, 'wpuseo_post_description', 1));

            if (!empty($wpuseo_post_description)) {
                $description = $wpuseo_post_description;
            }

            $description = $this->prepare_text($description);

            if ($this->enable_twitter_metas) {
                /* Title */
                $twitter_title = trim($this->get_post_meta_custom($post_id, 'wpuseo_post_title_twitter', 1));
                if (empty($twitter_title)) {
                    $twitter_title = get_the_title();
                }
                $metas['twitter_title'] = array(
                    'name' => 'twitter:title',
                    'content' => $twitter_title
                );
                /* Description */
                $twitter_description = $this->prepare_text($this->get_post_meta_custom($post_id, 'wpuseo_post_description_twitter', 1));
                if (empty($twitter_description)) {
                    $twitter_description = $description;
                }
                $metas['twitter_description'] = array(
                    'name' => 'twitter:description',
                    'content' => $twitter_description
                );
            }

            if ($this->enable_facebook_metas) {

                $og_type = 'article';
                if ($post_type == 'product') {
                    $og_type = 'product';
                }

                /* Facebook : Open Graph */
                $metas['og_type']['content'] = $og_type;

            }

            // Description
            $metas['description'] = array(
                'name' => 'description',
                'content' => $description
            );

            $keywords = $this->get_post_keywords($post_id);
            if (!empty($keywords)) {
                $keywords_txt = implode(', ', $keywords);
                $metas['keywords'] = array(
                    'name' => 'keywords',
                    'content' => $this->prepare_text($keywords_txt)
                );
            }

            if ($this->enable_facebook_metas) {
                $facebook_title = $this->prepare_text($this->get_post_meta_custom($post_id, 'wpuseo_post_title_facebook', 1));
                if (empty($facebook_title)) {
                    $facebook_title = get_the_title($post_id);
                }
                $metas['og_title'] = array(
                    'property' => 'og:title',
                    'content' => $facebook_title
                );
                /* Description */
                $facebook_description = trim($this->get_post_meta_custom($post_id, 'wpuseo_post_description_facebook', 1));
                if (empty($facebook_description)) {
                    $facebook_description = $description;
                }
                $metas['og_description'] = array(
                    'property' => 'og:description',
                    'content' => $facebook_description
                );
                $metas['og_url'] = array(
                    'property' => 'og:url',
                    'content' => get_permalink($post_id)
                );
            }
            $post_thumbnail_id = apply_filters('wpuseo_post_image_main', get_post_thumbnail_id($post_id), $post_infos);
            $thumb_url = !is_numeric($post_thumbnail_id) ? array() : wp_get_attachment_image_src($post_thumbnail_id, $this->thumbnail_size, true);
            if ($post_thumbnail_id && isset($thumb_url[0])) {
                $metas['image'] = array(
                    'hidden' => 1,
                    'imgid' => $post_thumbnail_id,
                    'content' => $thumb_url[0]
                );
                if ($this->enable_facebook_metas) {
                    $metas['og_image'] = array(
                        'property' => 'og:image',
                        'imgid' => $post_thumbnail_id,
                        'content' => $thumb_url[0]
                    );
                }
                if ($this->enable_twitter_metas) {
                    $metas['twitter_image'] = array(
                        'name' => 'twitter:image',
                        'imgid' => $post_thumbnail_id,
                        'content' => $thumb_url[0]
                    );
                }

            }

            // Product details
            if ($this->enable_facebook_metas && $post_type == 'product' && function_exists('wc_get_product')) {
                $metas['product_price_currency'] = array(
                    'property' => 'product:price:currency',
                    'content' => get_woocommerce_currency()
                );
                $_product = wc_get_product($post_infos->ID);
                if (is_object($_product)) {
                    $metas['product_price_amount'] = array(
                        'property' => 'product:price:amount',
                        'content' => $post_id
                    );
                }
            }

            // Custom og:image
            if ($this->enable_facebook_metas) {
                $custom_og_image = get_post_meta($post_id, 'wpuseo_post_image_facebook', 1);
                if (is_numeric($custom_og_image)) {
                    $thumb_url = wp_get_attachment_image_src($custom_og_image, $this->thumbnail_size, true);
                    if (isset($thumb_url[0])) {
                        $metas['og_image'] = array(
                            'property' => 'og:image',
                            'imgid' => $custom_og_image,
                            'content' => $thumb_url[0]
                        );
                    }
                }
            }

            // Custom twitter:image
            if ($this->enable_twitter_metas) {
                $custom_twitter_image = get_post_meta($post_id, 'wpuseo_post_image_twitter', 1);
                if (is_numeric($custom_twitter_image)) {
                    $thumb_url = wp_get_attachment_image_src($custom_twitter_image, $this->thumbnail_size, true);
                    if (isset($thumb_url[0])) {
                        $metas['twitter_image'] = array(
                            'name' => 'twitter:image',
                            'imgid' => $custom_twitter_image,
                            'content' => $thumb_url[0]
                        );
                    }
                }
            }

            // Author informations
            $wpu_seo_user_google_profile = get_user_meta($post_infos->post_author, 'wpu_seo_user_google_profile', 1);
            if (filter_var($wpu_seo_user_google_profile, FILTER_VALIDATE_URL)) {
                $links['google_author'] = array(
                    'rel' => 'author',
                    'href' => $wpu_seo_user_google_profile
                );
            }

            $wpu_seo_user_twitter_account = get_user_meta($post_infos->post_author, 'wpu_seo_user_twitter_account', 1);
            if (!empty($wpu_seo_user_twitter_account) && preg_match('/^@([A-Za-z0-9_]+)$/', $wpu_seo_user_twitter_account) && $this->enable_twitter_metas) {
                $metas['twitter_creator'] = array(
                    'name' => 'twitter:creator',
                    'content' => $wpu_seo_user_twitter_account
                );
            }
        }

        if (is_home() || is_front_page() || $this->is_admin_preview_home()) {

            // Main values
            $wpu_description = trim($this->get_option_custom('wpu_home_meta_description'));
            $wpu_keywords = trim($this->get_option_custom('wpu_home_meta_keywords'));

            // Meta description
            $home_meta_description = trim(get_bloginfo('description'));
            if (!empty($wpu_description)) {
                $home_meta_description = $wpu_description;
            }
            $metas['description'] = array(
                'name' => 'description',
                'content' => $this->prepare_text($home_meta_description, 200)
            );

            // Meta keywords
            if (empty($wpu_keywords)) {
                $categories = get_categories();
                if (count($categories) > 1) {
                    $tmp_keywords = array();
                    foreach ($categories as $category) {
                        $tmp_keywords[] = $category->name;
                    }
                    $wpu_keywords = implode(', ', $tmp_keywords);
                }
            }
            if (!empty($wpu_keywords)) {
                $metas['keywords'] = array(
                    'name' => 'keywords',
                    'content' => $this->prepare_text($wpu_keywords, 200)
                );
            }

            // Twitter
            if ($this->enable_twitter_metas) {
                $wpu_hometwitter_page_title = trim($this->get_option_custom('wpu_hometwitter_page_title'));
                if (empty($wpu_hometwitter_page_title)) {
                    $wpu_hometwitter_page_title = get_bloginfo('name');
                }
                $wpu_hometwitter_meta_description = trim($this->get_option_custom('wpu_hometwitter_meta_description'));
                if (empty($wpu_hometwitter_meta_description)) {
                    $wpu_hometwitter_meta_description = $home_meta_description;
                }

                $metas['twitter_title'] = array(
                    'name' => 'twitter:title',
                    'content' => $wpu_hometwitter_page_title
                );
                $metas['twitter_description'] = array(
                    'name' => 'twitter:description',
                    'content' => $this->prepare_text($wpu_hometwitter_meta_description, 200)
                );
            }

            // Facebook
            if ($this->enable_facebook_metas) {
                $homefb_page_title = trim($this->get_option_custom('wpu_homefb_page_title'));
                if (empty($homefb_page_title)) {
                    $homefb_page_title = get_bloginfo('name');
                }
                $homefb_meta_description = trim($this->get_option_custom('wpu_homefb_meta_description'));
                if (empty($homefb_meta_description)) {
                    $homefb_meta_description = $home_meta_description;
                }
                $metas['og_title'] = array(
                    'property' => 'og:title',
                    'content' => $homefb_page_title
                );
                $metas['og_description'] = array(
                    'property' => 'og:description',
                    'content' => $homefb_meta_description
                );
                $metas['og_url'] = array(
                    'property' => 'og:url',
                    'content' => $this->get_home_url()
                );
            }

            // Metas JSON
            $metas_json["@type"] = "WebSite";
            $metas_json["url"] = site_url();
            $metas_json["name"] = get_bloginfo('name');

        }

        // Bing Site
        $wpu_bing_site_verification = trim(get_option('wpu_bing_site_verification'));
        if (!empty($wpu_bing_site_verification)) {
            $metas['bing_site_verification'] = array(
                'name' => 'msvalidate.01',
                'content' => $wpu_bing_site_verification
            );
        }

        // Google Site
        $wpu_google_site_verification = trim(get_option('wpu_google_site_verification'));
        if (!empty($wpu_google_site_verification)) {
            $metas['google_site_verification'] = array(
                'name' => 'google-site-verification',
                'content' => $wpu_google_site_verification
            );
        }

        // FB Domain
        $wputh_fb_domain = trim(get_option('wputh_fb_domain'));
        if ($this->enable_facebook_metas && !empty($wputh_fb_domain)) {
            $metas['fb_domain'] = array(
                'name' => 'facebook-domain-verification',
                'content' => $wputh_fb_domain
            );
        }

        // FB Admins
        $wputh_fb_admins = trim(get_option('wputh_fb_admins'));
        if ($this->enable_facebook_metas && !empty($wputh_fb_admins)) {
            $wputh_fb_admins_multiple = explode(',', $wputh_fb_admins);
            foreach ($wputh_fb_admins_multiple as $k => $admin) {
                $metas['fb_admins_' . $k] = array(
                    'property' => 'fb:admins',
                    'content' => trim($admin)
                );
            }
        }

        // FB App
        $wputh_fb_app = trim(get_option('wputh_fb_app'));
        if ($this->enable_facebook_metas && !empty($wputh_fb_app)) {
            $metas['fb_app'] = array(
                'property' => 'fb:app_id',
                'content' => $wputh_fb_app
            );
        }

        // Metas JSON : Single
        if ((is_single() || is_singular()) && !is_singular('product') && !is_front_page() && !is_home()) {
            $queried_object = get_queried_object();
            $metas_json = array(
                "@type" => "NewsArticle",
                "author" => get_the_author_meta('display_name', $queried_object->post_author),
                "datePublished" => get_the_time('Y-m-d', $queried_object->ID),
                "dateModified" => get_the_modified_time('Y-m-d', $queried_object->ID),
                "mainEntityOfPage" => get_permalink(),
                "url" => get_permalink(),
                "headline" => get_the_title()
            );

            $metas_keywords = '';
            if (isset($metas['keywords']['content'])) {
                $metas_keywords = $metas['keywords']['content'];
            }
            $metas_json['keywords'] = apply_filters('wpuseo_metasldjson_single', $metas_keywords);
        }

        $metas = apply_filters('wpuseo_metas_after_settings', $metas);
        $metas_json = apply_filters('wpuseo_metas_json_after_settings', $metas_json, $metas);

        return array(
            'links' => $links,
            'metas' => $metas,
            'metas_json' => $metas_json
        );
    }

    public function add_metas() {
        $metas = $this->get_metas();
        echo $this->special_convert_array_html($metas['metas']);
        echo $this->special_convert_array_html($metas['links'], 'link');

        if (!empty($metas['metas_json'])) {
            echo $this->set_metas_ld_json($metas['metas'], $metas['metas_json']);
        }
    }

    public function set_metas_ld_json($metas, $metas_json) {

        $metas_json = array_merge(array("@context" => "http://schema.org"), $metas_json);

        if (!empty($metas['description'])) {
            $metas_json['description'] = $metas['description']['content'];
        }

        if (isset($metas['image'])) {
            $metas_json['image'] = array(
                "@type" => "ImageObject",
                "url" => $metas['image']['content']
            );
            $image_id = isset($metas['image']['imgid']) && is_numeric($metas['image']['imgid']) ? $metas['image']['imgid'] : false;
            if (is_numeric($image_id)) {
                $image_obj = wp_get_attachment_image_src($image_id, $this->thumbnail_size);
                if (is_array($image_obj) && $image_obj[0] == $metas['image']['content']) {
                    $metas_json['image']['height'] = $image_obj[1];
                    $metas_json['image']['width'] = $image_obj[2];
                }
            }
        }

        $metas_json['publisher'] = array(
            "@type" => "Organization",
            "name" => get_bloginfo('name'),
            "url" => site_url()
        );

        if (has_header_image()) {
            $header = get_custom_header();
            if (is_object($header) && property_exists($header, 'url')) {
                $metas_json['publisher']['logo'] = array(
                    "@type" => "ImageObject",
                    "url" => $header->url,
                    "width" => is_numeric($header->width) ? $header->width : 0,
                    "height" => is_numeric($header->height) ? $header->height : 0
                );
            }
        }

        $metas_json = apply_filters('wpuseo_metas_json_before_display', $metas_json, $metas);

        return '<script type="application/ld+json">' . str_replace('\/', '/', json_encode($metas_json)) . '</script>';
    }

    /* ----------------------------------------------------------
      Hide from search
    ---------------------------------------------------------- */

    public function is_hidden_from_search() {
        if (is_singular()) {
            $wpuseo_hide_search = get_post_meta(get_the_ID(), 'wpuseo_hide_search', 1);
            if ($wpuseo_hide_search == '1') {
                return true;
            }
        }
        if (is_category() || is_tax() || is_tag()) {
            $wpuseo_hide_search = $this->get_taxo_meta('wpuseo_hide_search');
            if ($wpuseo_hide_search == '1') {
                return true;
            }
        }
        return false;
    }

    /* ----------------------------------------------------------
      Robots tag
    ---------------------------------------------------------- */

    public function add_metas_robots() {

        // Disable indexation for archives pages after page 1 OR 404 page OR paginated comments
        if ((is_paged() && (is_category() || is_tag() || is_author() || is_tax())) || is_404() || (is_single() && comments_open() && (int) get_query_var('cpage') > 0)) {
            add_filter('wp_robots', 'wp_robots_no_robots');
        }

        // Disable indexation for some pages marked as sensitive
        if ($this->is_hidden_from_search()) {
            add_filter('wp_robots', 'wp_robots_sensitive_page');
        }
    }

    /* ----------------------------------------------------------
      Cookies
    ---------------------------------------------------------- */

    public function wp_head_cookie_helper() {
        $fb_pixel = $this->get_pixel_id();
        $ua_analytics = $this->get_analytics_id();
        $custom_tracking = $this->get_custom_tracking_code();
        if (!$fb_pixel && !$ua_analytics && !$custom_tracking) {
            return;
        }
        $wpu_seo_cookies__enable_notice = (get_option('wpu_seo_cookies__enable_notice') == 1);
        if (!$wpu_seo_cookies__enable_notice) {
            echo '<script>window.addEventListener("DOMContentLoaded",function(){';
            echo 'if(typeof(jQuery)=="function"){jQuery("body").trigger("wpuseo-cookie-notice-not-needed");}';
            echo '});</script>';
            return;
        }

        $wpuseo_cookies_name = site_url();
        $wpuseo_cookies_name = str_replace(array('http://', 'https://'), '', $wpuseo_cookies_name);
        $wpuseo_cookies_name = 'wpuseo_cookies_' . sanitize_title($wpuseo_cookies_name);

        /* Thanks to https://gist.github.com/wpsmith/6cf23551dd140fb72ae7 */

        echo '<script data-cfasync="false">';
        echo 'window.wpuseo_cookies_name="' . $wpuseo_cookies_name . '";';
        echo 'function wpuseo_getcookie(name){';
        echo 'var value="; "+document.cookie;';
        echo 'var parts=value.split("; "+name+"=");';
        echo 'if(parts.length==2)return parts.pop().split(";").shift();';
        echo '}</script>';
    }

    /* Scripts
    -------------------------- */

    public function wp_enqueue_scripts() {
        $wpu_seo_cookies__enable_notice = (get_option('wpu_seo_cookies__enable_notice') == 1);
        if ($wpu_seo_cookies__enable_notice) {
            wp_enqueue_style('wpuseo_cookies_style', plugins_url('assets/cookies.css', __FILE__), array(), $this->plugin_version);
            wp_enqueue_script('wpuseo_cookies_script', plugins_url('assets/cookies.js', __FILE__), array('jquery'), $this->plugin_version, false);
            wp_localize_script('wpuseo_cookies_script', 'wpuseo_obj', array(
                'duration_choice' => get_option('wpu_seo_cookies__duration_choice')
            ));
        }
    }

    public function rocket_excluded_inline_js_content($inline_js) {
        $inline_js[] = 'wpuseo_getcookie';
        return $inline_js;
    }

    /* Footer
    -------------------------- */

    public function wp_footer() {
        $wpu_seo_cookies__enable_notice = (get_option('wpu_seo_cookies__enable_notice') == 1);
        if ($wpu_seo_cookies__enable_notice) {
            $this->display__cookie_notice();
        }
    }

    public function display__cookie_notice() {
        $cookie_text = $this->__('This website uses cookies to ensure you get the best experience on our website.');
        $opt_cookie_text = $this->get_option_custom('wpu_seo_cookies__text');
        if ($opt_cookie_text) {
            $cookie_text = $opt_cookie_text;
        }

        $cookie_button_accept = $this->__('Accept');
        $opt_cookie_button_accept = $this->get_option_custom('wpu_seo_cookies__button_accept');
        if ($opt_cookie_button_accept) {
            $cookie_button_accept = esc_html($opt_cookie_button_accept);
        }

        $cookie_button_refuse = $this->__('Refuse');
        $opt_cookie_button_refuse = $this->get_option_custom('wpu_seo_cookies__button_refuse');
        if ($opt_cookie_button_accept) {
            $cookie_button_refuse = esc_html($opt_cookie_button_refuse);
        }

        /* Hide refuse button */
        $cookie_button_refuse_visible = true;
        $opt_cookie_button_refuse_visible = $this->get_option_custom('wpu_seo_cookies__display_refuse');
        if (!$opt_cookie_button_refuse_visible) {
            $cookie_button_refuse_visible = false;
        }

        $cookie_content = wpautop($cookie_text);

        $add_wrapper_buttons = apply_filters('wpuseo__display_cookie_notice__add_wrapper', false);

        if ($add_wrapper_buttons) {
            $cookie_content .= '<div class="cookie-actions">';
        }
        $cookie_content .= ' <a href="#" data-cookie-action="1" class="accept"><span>' . $cookie_button_accept . '</span></a>';
        if ($cookie_button_refuse_visible) {
            $cookie_content .= ' <a href="#" data-cookie-action="-1" class="refuse"><span>' . $cookie_button_refuse . '</span></a>';
        }
        if ($add_wrapper_buttons) {
            $cookie_content .= '</div>';
        }
        $wrapper_classname = 'cookie-notice__wrapper ' . apply_filters('wpuseo__display_cookie_notice__wrapper_extra_classname', '');
        $cookie_content = apply_filters('wpuseo__display_cookie_notice__cookie_content', $cookie_content);

        /* Content */
        echo '<div class="cookie-notice" data-nosnippet>';
        echo '<div class="cookie-notice__overlay"></div>';
        echo '<div class="' . esc_attr(trim($wrapper_classname)) . '">';
        echo '<a href="#" class="close" data-cookie-action="-1" title="' . esc_attr($cookie_button_refuse) . '"><span>&times;</span></a>';
        /* Content */
        echo '<div class="cookie-notice__inner">';
        echo $cookie_content;
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    /* ----------------------------------------------------------
      Custom tracking
    ---------------------------------------------------------- */

    public function display_custom_tracking() {

        $hook_ajaxready = apply_filters('wpuseo_ajaxready_hook', 'vanilla-pjax-ready');
        $cookie_notice = get_option('wpu_seo_cookies__enable_notice');

        $code = $this->get_custom_tracking_code();
        if (!$code) {
            return;
        }

        $custom_code = '';
        $custom_code .= '<script>function wpuseo_init_custom_tracking(){';
        $custom_code .= $this->get_js_conditions_tracking();
        $custom_code .= '(function(){';
        $custom_code .= 'function _trigger(){jQuery("body").trigger("wpuseo-cookie-notice-accepted");}';
        $custom_code .= 'if(document.readyState==="complete"||document.readyState==="loaded"){_trigger();}';
        $custom_code .= 'else{window.addEventListener("DOMContentLoaded",_trigger);}';
        $custom_code .= '}());';
        $custom_code .= $code;
        $custom_code .= '};wpuseo_init_custom_tracking();</script>';

        echo apply_filters('wpuseo__display_custom_tracking', $custom_code);

    }

    public function get_custom_tracking_code() {
        $code = get_option('wputh_custom_tracking_code');
        $code = apply_filters('wpuseo_wputh_custom_tracking_code', $code);
        $code = strip_tags($code);
        if (!$code) {
            return;
        }
        return $code;
    }

    /* ----------------------------------------------------------
      Google Analytics
    ---------------------------------------------------------- */

    public function display_google_analytics_code() {
        $ua_analytics = $this->get_analytics_id();
        if (!$ua_analytics) {
            return;
        }

        $base_ua_analytics = strtolower($ua_analytics);

        $ga_type = substr($base_ua_analytics, 0, 2) == 'g-' ? 'ga4' : 'ga3';

        // Tracked logged in users
        $analytics_enableloggedin = (get_option('wputh_analytics_enableloggedin') == '1');
        if (is_user_logged_in() && !$analytics_enableloggedin) {
            return;
        }
        $hook_ajaxready = apply_filters('wpuseo_ajaxready_hook', 'vanilla-pjax-ready');
        $enableanonymizeip = (get_option('wputh_analytics_enableanonymizeip') == '1');
        $analyticsCookieExpires = (get_option('wputh_analytics_cookie_expires'));
        if ($analyticsCookieExpires) {
            $analyticsCookieExpires = intval($analyticsCookieExpires, 10);
        }

        $analytics_code = '';
        $cookie_notice = get_option('wpu_seo_cookies__enable_notice');

        if ($cookie_notice != '1') {
            $analytics_code .= '<link rel="dns-prefetch" href="//www.google-analytics.com">';
        }

        $analytics_code .= '<script type="text/javascript">';
        $analytics_code .= "function wpuseo_init_analytics(){";
        $analytics_code .= apply_filters('wpuseo__display_google_analytics_code__before', '');

        /* Cookie notice */
        $analytics_code .= $this->get_js_conditions_tracking();

        if ($ga_type == 'ga4') {
            $analytics_code .= 'var gtag4 = document.createElement("script");';
            $analytics_code .= 'gtag4.type = "text/javascript";';
            $analytics_code .= 'gtag4.setAttribute("async", "true");';
            $analytics_code .= 'gtag4.setAttribute("src", "https://www.googletagmanager.com/gtag/js?id=' . $ua_analytics . '");';
            $analytics_code .= 'document.documentElement.firstChild.appendChild(gtag4);';
            $analytics_code .= "window.dataLayer = window.dataLayer || [];";
            $analytics_code .= "function gtag(){dataLayer.push(arguments);}";
            $analytics_code .= "gtag('js', new Date());";
            $analytics_code .= "gtag('config', '" . $ua_analytics . "');";

        } else {
            /* Calls GA */
            $analytics_code .= "(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)})(window,document,'script','https://www.google-analytics.com/analytics.js','ga');";
            $analytics_code .= "if (typeof(ga) == 'function') {";
            $analytics_code .= "\nga('create','" . $ua_analytics . "','auto');";
            if (is_user_logged_in() && apply_filters('wpuseo_analytics_track_user_id', true)) {
                $user = wp_get_current_user();
                if (is_object($user)) {
                    $analytics_code .= "\nga('set','userId','" . $user->ID . "');";
                }
            }
            if ($enableanonymizeip) {
                $analytics_code .= "\nga('set', 'anonymizeIp', true);";
            }
            if ($analyticsCookieExpires) {
                $analytics_code .= "\nga('set', 'cookie_expires', " . $analyticsCookieExpires . ");";
            }
            $analytics_code .= "\nga('set','dimension1','visitorloggedin-" . (is_user_logged_in() ? '1' : '0') . "');";
            $analytics_code .= "\nga('send','pageview');";
            $analytics_code .= "\n}\n";
        }

        $analytics_code .= apply_filters('wpuseo__display_google_analytics_code__after', '');
        $analytics_code .= "} wpuseo_init_analytics();";
        /* End of calls */

        if (!empty($hook_ajaxready)) {
            $analytics_code .= "function wpuseo_callback_ajaxready(){ga('set','page',window.location.pathname);ga('send','pageview');}";
            $analytics_code .= "if (typeof(jQuery) == 'undefined') {window.addEventListener('" . esc_attr($hook_ajaxready) . "',wpuseo_callback_ajaxready,1);}";
            $analytics_code .= "else {jQuery(window).on('" . esc_attr($hook_ajaxready) . "',wpuseo_callback_ajaxready);}";
        }
        $analytics_code .= '</script>';

        echo apply_filters('wpuseo__display_google_analytics_code', $analytics_code);
    }

    public function get_analytics_id() {
        $ua_analytics = get_option('wputh_ua_analytics');

        // Invalid ID
        if ($ua_analytics === false || empty($ua_analytics) || in_array($ua_analytics, array(
            'UA-XXXXX-X'
        ))) {
            return false;
        }

        return $ua_analytics;
    }

    /* ----------------------------------------------------------
      Facebook Pixel code
    ---------------------------------------------------------- */

    public function get_pixel_id() {
        $fb_pixel = get_option('wputh_fb_pixel');

        // Invalid ID
        if (empty($fb_pixel)) {
            return false;
        }
        // Tracked logged in users
        $fbpixel_enableloggedin = (get_option('wputh_fbpixel_enableloggedin') == '1');
        if (is_user_logged_in() && !$fbpixel_enableloggedin) {
            return false;
        }

        return $fb_pixel;
    }

    public function display_facebook_pixel_code() {

        $fb_pixel = $this->get_pixel_id();
        if (!$fb_pixel) {
            return false;
        }

        $pixel_code = '';
        $cookie_notice = get_option('wpu_seo_cookies__enable_notice');

        if ($cookie_notice != '1') {
            $pixel_code .= '<link rel="dns-prefetch" href="//connect.facebook.net">';
        }

        $pixel_code .= '<script>';
        $pixel_code .= "function wpuseo_init_fbpixel(){";
        $pixel_code .= apply_filters('wpuseo__display_facebook_pixel_code__before', '');
        $pixel_code .= $this->get_js_conditions_tracking();
        $pixel_code .= '!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
n.push=n;n.loaded=!0;n.version=\'2.0\';n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
document,\'script\',\'https://connect.facebook.net/en_US/fbevents.js\');';
        $pixel_code .= "fbq('init', '" . $fb_pixel . "');";
        $pixel_code .= "fbq('track', 'PageView');";
        $pixel_code .= apply_filters('wpuseo__display_facebook_pixel_code__after', '');
        $pixel_code .= '} wpuseo_init_fbpixel();';
        $pixel_code .= '</script>';

        echo apply_filters('wpuseo__display_facebook_pixel_code', $pixel_code);

    }

    public function display_facebook_pixel_code__thankyou($order_id) {

        $fb_pixel = $this->get_pixel_id();
        if (!$fb_pixel) {
            return false;
        }

        $order = wc_get_order($order_id);

        echo "<script>";
        echo "if(typeof fbq == 'function'){";
        echo "fbq('track', 'Purchase', {value: '" . $order->get_total() . "',currency: '" . $order->get_currency() . "'});";
        echo "}";
        echo "</script>";
    }

    /* ----------------------------------------------------------
      Get post keywords
    ---------------------------------------------------------- */

    public function get_post_keywords($id) {
        global $post;

        $wpu_meta_keywords_min_len = get_option('wpu_meta_keywords_min_len');
        if (!is_numeric($wpu_meta_keywords_min_len)) {
            $wpu_meta_keywords_min_len = 3;
        }

        // Keywords
        $keywords_raw = array();

        $title = explode(' ', mb_strtolower(get_the_title($id)));
        foreach ($title as $word) {
            if (strlen($word) >= $wpu_meta_keywords_min_len) {
                $keywords_raw = $this->check_keywords_value(sanitize_title($word), $word, $keywords_raw);
            }
        }

        $keywords_raw = $this->add_terms_to_keywords(get_the_category($id), $keywords_raw);
        $keywords_raw = $this->add_terms_to_keywords(get_the_tags($id), $keywords_raw);

        // Sort keywords by score
        usort($keywords_raw, array($this, 'order_keywords_values'));

        // Set keywords
        $keywords = array();
        foreach ($keywords_raw as $keyword) {
            $keywords[] = $keyword[1];
        }
        return $keywords;
    }

    public function add_terms_to_keywords($terms, $keywords_raw) {
        if (is_array($terms)) {
            foreach ($terms as $term) {
                $keywords_raw = $this->check_keywords_value($term->slug, $term->name, $keywords_raw);
            }
        }

        return $keywords_raw;
    }

    public function check_keywords_value($slug, $word, $keywords_raw) {
        $word = str_replace(array(
            ','
        ), ' ', $word);
        if (array_key_exists($slug, $keywords_raw)) {
            $keywords_raw[$slug][0]++;
        } else {
            $keywords_raw[$slug] = array(
                1,
                $word
            );
        }
        return $keywords_raw;
    }

    public function order_keywords_values($a, $b) {
        if ($a[0] == $b[0]) {
            return 0;
        }
        return ($a[0] < $b[0]) ? -1 : 1;
    }

    /* ----------------------------------------------------------
      Get common JS tracking conditions
    ---------------------------------------------------------- */

    function get_js_conditions_tracking() {

        $cookie_notice = get_option('wpu_seo_cookies__enable_notice');
        $enable_tracking = get_option('wpu_seo_cookies__enable_tracking');
        $support_dnt = get_option('wpu_seo_cookies__support_dnt');

        $js = '';

        /* DNT */
        if ($support_dnt == '1') {
            $js .= 'if(navigator.doNotTrack==1){console.log("DNT is enabled");return;}';
        }

        /* Cookie notice */
        if ($cookie_notice == '1' && $enable_tracking != '1') {
            $js .= 'if(wpuseo_getcookie(window.wpuseo_cookies_name) != "1"){return;};';
        }

        return $js;

    }

    /* ----------------------------------------------------------
      Admin preview
    ---------------------------------------------------------- */

    public function admin_box_preview($content, $boxid) {
        $boxes = array(
            'wpuseo_box',
            'wpuseo_box_twitter',
            'wpuseo_box_facebook'
        );

        if (!in_array($boxid, $boxes)) {
            return $content;
        }

        $metas = $this->get_metas();

        $data = array();
        if ($boxid == 'wpuseo_box') {
            if ($this->active_wp_title) {
                $data[] = array(
                    'th' => $this->__('Title tag content'),
                    'td' => '<pre>' . $this->wp_title('') . '</pre>'
                );
            }
            if (isset($metas['metas']['description']['content'])) {
                $data[] = array(
                    'th' => $this->__('Meta description content'),
                    'td' => '<pre>' . $metas['metas']['description']['content'] . '</pre>'
                );
            }
        }
        if ($boxid == 'wpuseo_box_twitter') {
            if (isset($metas['metas']['twitter_title']['content'])) {
                $data[] = array(
                    'th' => $this->__('Twitter Title'),
                    'td' => '<pre>' . $metas['metas']['twitter_title']['content'] . '</pre>'
                );
            }
            if (isset($metas['metas']['twitter_description']['content'])) {
                $data[] = array(
                    'th' => $this->__('Twitter Description'),
                    'td' => '<pre>' . $metas['metas']['twitter_description']['content'] . '</pre>'
                );
            }
            if (isset($metas['metas']['twitter_image']['content'])) {
                $data[] = array(
                    'th' => $this->__('Twitter Image'),
                    'td' => '<img src="' . $metas['metas']['twitter_image']['content'] . '" style="width:96px;height:54px;object-fit:cover;background-color:#ccc;" loading="lazy" alt="" />'
                );
            }
        }
        if ($boxid == 'wpuseo_box_facebook') {
            if (isset($metas['metas']['og_title']['content'])) {
                $data[] = array(
                    'th' => $this->__('Facebook og:title'),
                    'td' => '<pre>' . $metas['metas']['og_title']['content'] . '</pre>'
                );
            }
            if (isset($metas['metas']['og_description']['content'])) {
                $data[] = array(
                    'th' => $this->__('Facebook og:description'),
                    'td' => '<pre>' . $metas['metas']['og_description']['content'] . '</pre>'
                );
            }
            if (isset($metas['metas']['og_image']['content'])) {
                $data[] = array(
                    'th' => $this->__('Facebook og:image'),
                    'td' => '<img src="' . $metas['metas']['og_image']['content'] . '" style="width:103px;height:54px;object-fit:cover;background-color:#ccc;" loading="lazy" alt="" />'
                );
            }
        }

        /* Display box content */
        $html = '<hr /><details><summary>' . $this->__('Preview') . '</summary>';
        $html .= '<table class="wpupostmetas-table" style="width:100%"><tbody>';
        foreach ($data as $item) {
            $html .= '<tr><th>' . $item['th'] . '</th><td>' . $item['td'] . '</td></tr>';
        }
        $html .= '</tbody></table>';
        $html .= '<div class="wpuseo-message-post">' . wpautop($this->__('Save post to display updated values')) . '</div>';
        $html .= '<div style="display:none;" class="wpuseo-message-home">' . wpautop('<a href="' . admin_url('admin.php?page=wpuoptions-settings&tab=wpu_seo#box-wpu_seo_home') . '">' . $this->__('Edit in site options to display updated values') . '</a>') . '</div>';
        $html .= '</details>';

        return $content . $html;
    }

    /* Taxo
    -------------------------- */

    public function admin_box_preview_taxo($content, $id, $field, $term_meta, $id_lang, $mode) {
        if ($mode != 'edit') {
            return $content;
        }

        $content = '';

        switch ($id) {
        case 'wpuseo_taxo_image_facebook':
            $content = '<tr><td colspan="2">' . $this->admin_box_preview('', 'wpuseo_box_facebook') . '</td></tr>';
            break;
        case 'wpuseo_taxo_image_twitter':
            $content = '<tr><td colspan="2">' . $this->admin_box_preview('', 'wpuseo_box_twitter') . '</td></tr>';
            break;
        case 'wpuseo_hide_search':
            $content = '<tr><td colspan="2">' . $this->admin_box_preview('', 'wpuseo_box') . '</td></tr>';
            break;
        default:

        }

        return $content;
    }

    /* Checks for admin preview
    -------------------------- */

    public function is_admin_preview_home() {
        if (!is_admin()) {
            return false;
        }
        $screen = get_current_screen();
        /* Only on post page */
        if (!isset($_GET['post']) || !is_object($screen) || $screen->base != 'post' || $screen->id != 'page') {
            return false;
        }
        $show_on_front = get_option('show_on_front');
        if ($show_on_front != 'page') {
            return false;
        }
        $home_ids = $this->get_home_page_ids();
        if (!in_array($_GET['post'], $home_ids)) {
            return false;
        }
        return true;
    }

    public function is_admin_preview($base = 'post') {
        if (!is_admin()) {
            return false;
        }

        $screen = get_current_screen();
        if (!$screen) {
            return false;
        }

        return $screen->base == $base;
    }

    /* ----------------------------------------------------------
      Ads
    ---------------------------------------------------------- */

    function wpuoptions__post_update__update_fake_files() {
        $need_rewrite = false;
        foreach ($this->fake_txt_files as $fake_file) {
            $opt_val = trim(get_option('wpu_seo_' . $fake_file . '_txt_content'));
            update_option('wpu_seo_' . $fake_file . '_txt_content_has_val', $opt_val ? '1' : '0', true);
            if ($opt_val) {
                $this->add_rewrite_rule__fake_files();
                $need_rewrite = true;
            }
        }

        if ($need_rewrite) {
            flush_rewrite_rules();
        }
    }

    function add_rewrite_rule__fake_files() {
        foreach ($this->fake_txt_files as $fake_file) {
            $opt_val = get_option('wpu_seo_' . $fake_file . '_txt_content_has_val');
            if ($opt_val == '1') {
                add_rewrite_rule('^' . $fake_file . '\.txt$', 'index.php?wpuseovirtualfile=' . $fake_file, 'top');
            }
        }
    }

    function redirect_canonical_callback($redirect_url, $requested_url) {
        foreach ($this->fake_txt_files as $fake_file) {
            $opt_val = get_option('wpu_seo_' . $fake_file . '_txt_content_has_val');
            if ($opt_val && str_replace(site_url(), '', $requested_url) == '/' . $fake_file . '.txt') {
                return $requested_url;
            }
        }
        return $redirect_url;
    }

    function query_vars__fakefiles($query_vars) {
        $query_vars[] = 'wpuseovirtualfile';
        return $query_vars;
    }

    function display_fake_file_content() {
        global $wp_query;
        if (!is_object($wp_query) || !isset($wp_query->query_vars, $wp_query->query_vars['wpuseovirtualfile'])) {
            return;
        }
        $type = $wp_query->query_vars['wpuseovirtualfile'];
        $opt_val = get_option('wpu_seo_' . $type . '_txt_content_has_val');
        if ($opt_val != '1') {
            return;
        }
        $opt_val = get_option('wpu_seo_' . $type . '_txt_content');
        if ($opt_val) {
            header('Content-Type: text/plain; charset=utf-8');
            echo $opt_val;
            die;
        }
    }

    /* ----------------------------------------------------------
      Utilities
    ---------------------------------------------------------- */

    /* Translate
     -------------------------- */

    private function __($string) {
        return __($string, 'wpuseo');
    }

    private function get_option_custom($name) {
        $opt = get_option($name);

        /* Prevent a weird encoding bug with c/c content */
        $opt = htmlentities($opt);
        $opt = html_entity_decode($opt);

        if ($this->is_site_multilingual() && function_exists('wputh_l18n_get_option')) {
            $opt = wputh_l18n_get_option($name);
        }
        return $opt;
    }

    private function get_post_meta_custom($post_id, $name, $single = 1) {
        $post_meta = trim(get_post_meta($post_id, $name, $single));
        if ($this->is_site_multilingual() && function_exists('wputh_l10n_get_post_meta')) {
            $post_meta = trim(wputh_l10n_get_post_meta($post_id, $name, $single));
        }
        return $post_meta;
    }

    /* Test a twitter username
     -------------------------- */

    public function testTwitterUsername($username) {
        return !!preg_match('/^\@([a-zA-Z_0-9]+)$/', $username);
    }

    /* Prepare meta description
     -------------------------- */

    public function prepare_text( $text, $max_length = 200 ) {
        $text        = strip_shortcodes( $text );
        $text        = strip_tags( $text );
        $text        = preg_replace( "/\s+/", ' ', $text );
        $text        = trim( $text );
        $words       = explode( ' ', $text );
        $final_text  = '';
        $$max_length = (int) apply_filters( 'WPUSEO/prepare_text/max_length', $max_length, $text );
        $hellip      = apply_filters( 'WPUSEO/prepare_text/hellip', ' ...' );
        foreach ( $words as $word ) {
            if ( ( strlen( $final_text ) + strlen( ' ' . $word ) ) > $max_length) {
                return trim( $final_text ) . $hellip;
            } else {
                $final_text .= ' ' . $word;
            }
        }
        return trim( $final_text );
    }

    /* Convert an array of metas to HTML
     -------------------------- */

    public function special_convert_array_html($metas, $tag = 'meta') {
        $_excluded_attrs = array('imgid');
        $html = '';
        if (!is_array($metas)) {
            return '';
        }
        foreach ($metas as $values) {
            if (isset($values['hidden'])) {
                continue;
            }
            $html .= '<' . $tag;
            foreach ($values as $name => $value) {
                if (in_array($name, $_excluded_attrs)) {
                    continue;
                }
                if ($name == 'id' && !$value) {
                    continue;
                }
                $_value = esc_attr(trim($value));
                $html .= sprintf(' %s="%s"', $name, $_value);
            }
            $html .= ' />' . "\n";
        }
        return $html;
    }

    /* Check if site is multilingual
     -------------------------- */

    public function is_site_multilingual() {
        if (function_exists('qtrans_getSortedLanguages')) {
            return true;
        }
        if (function_exists('qtranxf_getSortedLanguages')) {
            return true;
        }
        if (function_exists('pll_the_languages')) {
            return true;
        }
        if (function_exists('icl_get_languages')) {
            return true;
        }
        return false;
    }

    /* Home
    -------------------------- */

    public function get_home_url() {
        $url = home_url();
        if (function_exists('pll_home_url')) {
            $url = pll_home_url();
        }
        return $url;
    }

    public function get_home_page_ids() {
        $page_id = get_option('page_on_front');
        $ids = array($page_id);
        if (function_exists('pll_get_post_translations')) {
            $ids = pll_get_post_translations($page_id);
        }
        if (function_exists('icl_get_languages')) {
            $langs = array_keys(icl_get_languages());
            $ids = array();
            foreach ($langs as $lang) {
                $ids[] = icl_object_id($page_id, 'page', false, $lang);
            }
        }
        return $ids;
    }

    /* WooCommerce helpers
    -------------------------- */

    public function is_shop() {
        if (!function_exists('is_shop') || !function_exists('woocommerce_content')) {
            return false;
        }
        return is_shop();
    }

}

$WPUSEO = new WPUSEO();
