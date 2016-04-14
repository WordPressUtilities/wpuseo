<?php

/*
Plugin Name: WPU SEO
Plugin URI: https://github.com/WordPressUtilities/wpuseo
Description: Enhance SEO : Clean title, nice metas.
Version: 1.10
Author: Darklg
Author URI: http://darklg.me/
License: MIT License
License URI: http://opensource.org/licenses/MIT
Contributor: @boiteaweb
*/

class WPUSEO {

    public function init() {

        add_action('init', array(&$this,
            'check_config'
        ));
        add_action('plugins_loaded', array(&$this,
            'load_translation'
        ));

        // Filter
        add_filter('wp_title', array(&$this,
            'wp_title'
        ), 999, 2);
        add_filter('pre_get_document_title', array(&$this,
            'wp_title'
        ), 999, 2);
        add_filter('wp_title_rss', array(&$this,
            'rss_wp_title'
        ), 999, 2);

        // Actions
        add_action('wp_head', array(&$this,
            'add_metas'
        ), 1);
        add_action('wp_head', array(&$this,
            'add_metas_robots'
        ), 1, 0);
        add_action('wp_head', array(&$this,
            'display_google_analytics_code'
        ), 99, 0);

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
    }

    public function load_translation() {

        // Load lang
        load_plugin_textdomain('wpuseo', false, dirname(plugin_basename(__FILE__)) . '/lang/');
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
            'post_type' => array(
                'post',
                'page'
            )
        );
        return $boxes;
    }

    /* Meta fields
     -------------------------- */

    public function post_meta_fields($fields) {
        $fields['wpuseo_post_title'] = array(
            'box' => 'wpuseo_box',
            'name' => $this->__('Page title'),
            'lang' => true
        );
        $fields['wpuseo_post_description'] = array(
            'box' => 'wpuseo_box',
            'name' => $this->__('Page description'),
            'type' => 'textarea',
            'lang' => true
        );
        return $fields;
    }

    /* Taxo fields
     -------------------------- */

    public function taxo_fields($fields) {
        $taxonomies = apply_filters('wpuseo_taxo_list', array(
            'category',
            'post_tag'
        ));
        $fields['wpuseo_taxo_title'] = array(
            'label' => $this->__('Page title'),
            'taxonomies' => $taxonomies,
            'lang' => 1
        );
        $fields['wpuseo_taxo_description'] = array(
            'label' => $this->__('Page description'),
            'taxonomies' => $taxonomies,
            'type' => 'textarea',
            'lang' => 1
        );
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
        $boxes['wpu_seo_google'] = array(
            'name' => 'Google',
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
        return $boxes;
    }

    public function add_fields($options) {
        $is_multi = $this->is_site_multilingual() !== false;

        // Various
        $options['wpu_home_meta_description'] = array(
            'label' => $this->__('Main meta description'),
            'type' => 'textarea',
            'box' => 'wpu_seo'
        );
        $options['wpu_home_meta_keywords'] = array(
            'label' => $this->__('Main meta keywords'),
            'type' => 'textarea',
            'box' => 'wpu_seo'
        );
        $options['wpu_home_title_separator'] = array(
            'label' => $this->__('Title separator'),
            'box' => 'wpu_seo'
        );

        /* Home */
        $options['wpu_home_page_title'] = array(
            'label' => $this->__('Page title'),
            'type' => 'textarea',
            'box' => 'wpu_seo_home'
        );
        $options['wpu_home_title_separator_hide_prefix'] = array(
            'label' => $this->__('Hide title prefix'),
            'type' => 'select',
            'box' => 'wpu_seo_home'
        );

        if ($is_multi) {
            $options['wpu_home_meta_description']['lang'] = 1;
            $options['wpu_home_meta_keywords']['lang'] = 1;
            $options['wpu_home_page_title']['lang'] = 1;
        }

        // Google
        $options['wpu_google_site_verification'] = array(
            'label' => $this->__('Site verification ID'),
            'box' => 'wpu_seo_google'
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

        // Facebook
        $options['wpu_seo_user_facebook_enable'] = array(
            'label' => $this->__('Enable Facebook metas'),
            'type' => 'select',
            'box' => 'wpu_seo_facebook'
        );
        $options['wputh_fb_admins'] = array(
            'label' => $this->__('FB:Admins ID'),
            'box' => 'wpu_seo_facebook'
        );
        $options['wputh_fb_app'] = array(
            'label' => $this->__('FB:App ID'),
            'box' => 'wpu_seo_facebook'
        );
        $options['wputh_fb_image'] = array(
            'label' => $this->__('FB:Image'),
            'box' => 'wpu_seo_facebook',
            'type' => 'media'
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

        return $options;
    }

    /* ----------------------------------------------------------
      User Options
    ---------------------------------------------------------- */

    public function add_user_sections($sections) {
        $sections['wpu-seo'] = array(
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
        $queried_object = get_queried_object();
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

    /* ----------------------------------------------------------
      Page Title
    ---------------------------------------------------------- */

    public function rss_wp_title($content, $sep) {
        return $this->wp_title($content, $sep);
    }

    public function wp_title($title, $sep = '|') {

        $wpu_home_title_separator = trim(get_option('wpu_home_title_separator'));
        if (!empty($wpu_home_title_separator)) {
            $sep = htmlentities($wpu_home_title_separator);
        }

        $spaced_sep = ' ' . $sep . ' ';
        $new_title = '';

        // Home : Exception for order
        if (is_home() || is_front_page() || is_feed()) {
            $is_multi = $this->is_site_multilingual() !== false;
            $hide_prefix = get_option('wpu_home_title_separator_hide_prefix');
            $wpu_title = trim(get_option('wpu_home_page_title'));
            if ($is_multi && function_exists('wputh_l18n_get_option')) {
                $wpu_title = trim(wputh_l18n_get_option('wpu_home_page_title'));
            }
            if (empty($wpu_title)) {
                $wpu_title = get_bloginfo('description');
            }
            if ($is_multi && function_exists('wputh_l18n_get_option')) {
                $hide_prefix = trim(wputh_l18n_get_option('wpu_home_title_separator_hide_prefix'));
            }

            if ($hide_prefix != '1') {
                $wpu_title = get_bloginfo('name') . $spaced_sep . $wpu_title;
            }

            return $wpu_title;
        }

        $new_title = $this->get_displayed_title();

        if (is_singular()) {
            $wpuseo_post_title = trim(get_post_meta(get_the_ID(), 'wpuseo_post_title', 1));
            if (function_exists('wputh_l10n_get_post_meta')) {
                $wpuseo_post_title = trim(wputh_l10n_get_post_meta(get_the_ID(), 'wpuseo_post_title', 1));
            }

            if (!empty($wpuseo_post_title)) {
                $new_title = $wpuseo_post_title;
            }
        }

        if (is_category() || is_tax() || is_tag()) {
            $taxo_meta_title = $this->get_taxo_meta('title');
            if (!empty($taxo_meta_title)) {
                $new_title = $taxo_meta_title;
            }
        }

        // Return new title with site name at the end
        return $new_title . $spaced_sep . get_bloginfo('name');
    }

    public function get_displayed_title($prefix = true) {
        global $post;
        $displayed_title = $this->__('404 Error');
        if (is_search()) {
            $displayed_title = sprintf($this->__('Search results for "%s"'), get_search_query());
        }
        if (is_archive()) {
            $displayed_title = $this->__('Archive');
        }
        if (is_tax()) {
            $displayed_title = single_cat_title("", false);
        }
        if (is_tag()) {
            $displayed_title = ($prefix ? $this->__('Tag:') . ' ' : '') . single_tag_title("", false);
        }
        if (is_category()) {
            $displayed_title = ($prefix ? $this->__('Category:') . ' ' : '') . single_cat_title("", false);
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
        if (is_singular()) {
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
        return $displayed_title;
    }

    /* ----------------------------------------------------------
      Meta content & open graph
    ---------------------------------------------------------- */

    public function add_metas() {
        global $post;
        $metas = array();
        $links = array();
        $enable_twitter_metas = (get_option('wpu_seo_user_twitter_enable') != '0');
        $enable_facebook_metas = (get_option('wpu_seo_user_facebook_enable') != '0');

        if ($enable_facebook_metas) {
            $metas['og_sitename'] = array(
                'property' => 'og:site_name',
                'content' => get_bloginfo('name')
            );

            $metas['og_type'] = array(
                'property' => 'og:type',
                'content' => 'website'
            );
        }

        $wpu_seo_user_twitter_site_username = trim(get_option('wpu_seo_user_twitter_site_username'));
        if (!empty($wpu_seo_user_twitter_site_username) && $this->testTwitterUsername($wpu_seo_user_twitter_site_username) && $enable_twitter_metas) {
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
        if (!empty($wpu_seo_user_twitter_account_id) && $enable_twitter_metas) {
            $metas['twitter_account_id'] = array(
                'property' => 'twitter:account_id',
                'content' => $wpu_seo_user_twitter_account_id
            );
        }

        if (is_category() || is_tax() || is_tag()) {
            $queried_object = get_queried_object();
            $description = $queried_object->description;

            $taxo_meta_description = $this->get_taxo_meta('description');
            if (!empty($taxo_meta_description)) {
                $description = $taxo_meta_description;
            }
            if (!empty($description)) {

                // Description
                $metas['description'] = array(
                    'name' => 'description',
                    'content' => $this->prepare_text($description)
                );
            }
        }

        if (is_single() || is_page()) {

            $description = $post->post_excerpt;
            if (empty($post->post_excerpt)) {
                $description = $post->post_content;
            }

            $wpuseo_post_description = trim(get_post_meta(get_the_ID(), 'wpuseo_post_description', 1));
            if (function_exists('wputh_l10n_get_post_meta')) {
                $wpuseo_post_description = trim(wputh_l10n_get_post_meta(get_the_ID(), 'wpuseo_post_description', 1));
            }

            if (!empty($wpuseo_post_description)) {
                $description = $wpuseo_post_description;
            }

            $description = $this->prepare_text($description);

            if ($enable_twitter_metas) {

                /* Twitter : Summary card */
                $metas['twitter_card'] = array(
                    'name' => 'twitter:card',
                    'content' => 'summary'
                );
                $metas['twitter_title'] = array(
                    'name' => 'twitter:title',
                    'content' => get_the_title()
                );
                $metas['twitter_description'] = array(
                    'name' => 'twitter:description',
                    'content' => $description
                );
            }

            if ($enable_facebook_metas) {

                /* Facebook : Open Graph */
                $metas['og_type']['content'] = 'article';
            }

            // Description
            $metas['description'] = array(
                'name' => 'description',
                'content' => $description
            );

            $keywords = $this->get_post_keywords(get_the_ID());
            if (!empty($keywords)) {
                $keywords_txt = implode(', ', $keywords);
                $metas['keywords'] = array(
                    'name' => 'keywords',
                    'content' => $this->prepare_text($keywords_txt)
                );
            }

            if ($enable_facebook_metas) {

                $metas['og_title'] = array(
                    'property' => 'og:title',
                    'content' => get_the_title()
                );
                $metas['og_url'] = array(
                    'property' => 'og:url',
                    'content' => get_permalink()
                );
            }
            $thumb_url = wp_get_attachment_image_src(get_post_thumbnail_id(), $this->thumbnail_size, true);
            if (isset($thumb_url[0])) {
                if ($enable_facebook_metas) {
                    $metas['og_image'] = array(
                        'property' => 'og:image',
                        'content' => $thumb_url[0]
                    );
                }
                if ($enable_twitter_metas) {
                    $metas['twitter_image'] = array(
                        'name' => 'twitter:image',
                        'content' => $thumb_url[0]
                    );
                }
            }

            // Author informations
            $wpu_seo_user_google_profile = get_user_meta($post->post_author, 'wpu_seo_user_google_profile', 1);
            if (filter_var($wpu_seo_user_google_profile, FILTER_VALIDATE_URL)) {
                $links['google_author'] = array(
                    'rel' => 'author',
                    'href' => $wpu_seo_user_google_profile
                );
            }

            $wpu_seo_user_twitter_account = get_user_meta($post->post_author, 'wpu_seo_user_twitter_account', 1);
            if (!empty($wpu_seo_user_twitter_account) && preg_match('/^@([A-Za-z0-9_]+)$/', $wpu_seo_user_twitter_account) && $enable_twitter_metas) {
                $metas['twitter_creator'] = array(
                    'name' => 'twitter:creator',
                    'content' => $wpu_seo_user_twitter_account
                );
            }
        }

        if (is_home() || is_front_page()) {

            $is_multi = $this->is_site_multilingual() !== false;

            // Main values
            $wpu_description = trim(get_option('wpu_home_meta_description'));
            $wpu_keywords = trim(get_option('wpu_home_meta_keywords'));
            if ($is_multi && function_exists('wputh_l18n_get_option')) {
                $wpu_description = trim(wputh_l18n_get_option('wpu_home_meta_description'));
                $wpu_keywords = trim(wputh_l18n_get_option('wpu_home_meta_keywords'));
            }

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
            if ($enable_twitter_metas) {
                $metas['twitter_title'] = array(
                    'name' => 'twitter:title',
                    'content' => get_bloginfo('name')
                );
                $metas['twitter_description'] = array(
                    'name' => 'twitter:description',
                    'content' => $this->prepare_text($home_meta_description, 200)
                );
            }

            // Facebook
            if ($enable_facebook_metas) {
                $metas['og_title'] = array(
                    'property' => 'og:title',
                    'content' => get_bloginfo('name')
                );
                $metas['og_url'] = array(
                    'property' => 'og:url',
                    'content' => home_url()
                );
            }
            $og_image = get_stylesheet_directory_uri() . '/screenshot.png';
            $opt_wputh_fb_image = get_option('wputh_fb_image');
            $wputh_fb_image = wp_get_attachment_image_src($opt_wputh_fb_image, $this->thumbnail_size, true);
            if ($opt_wputh_fb_image != false && isset($wputh_fb_image[0])) {
                $og_image = $wputh_fb_image[0];
            }
            if ($enable_facebook_metas) {
                $metas['og_image'] = array(
                    'property' => 'og:image',
                    'content' => $og_image
                );
            }

        }

        // Google Site
        $wpu_google_site_verification = trim(get_option('wpu_google_site_verification'));
        if (!empty($wpu_google_site_verification)) {
            $metas['google_site_verification'] = array(
                'name' => 'google-site-verification',
                'content' => $wpu_google_site_verification
            );
        }

        // FB Admins
        $wputh_fb_admins = trim(get_option('wputh_fb_admins'));
        if ($enable_facebook_metas && !empty($wputh_fb_admins)) {
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
        if ($enable_facebook_metas && !empty($wputh_fb_app)) {
            $metas['fb_app'] = array(
                'property' => 'fb:app',
                'content' => $wputh_fb_app
            );
        }

        echo $this->special_convert_array_html($metas);
        echo $this->special_convert_array_html($links, 'link');
    }

    /* ----------------------------------------------------------
      Robots tag
    ---------------------------------------------------------- */

    public function add_metas_robots() {

        // Disable indexation for archives pages after page 1 OR 404 page OR paginated comments
        if ((is_paged() && (is_category() || is_tag() || is_author() || is_tax())) || is_404() || (is_single() && comments_open() && (int) get_query_var('cpage') > 0)) {
            wp_no_robots();
        }
    }

    /* ----------------------------------------------------------
      Google Analytics
    ---------------------------------------------------------- */

    public function display_google_analytics_code() {
        $ua_analytics = get_option('wputh_ua_analytics');
        // Invalid ID
        if ($ua_analytics === false || empty($ua_analytics) || in_array($ua_analytics, array(
            'UA-XXXXX-X'
        ))) {
            return;
        }

        // Tracked logged in users
        $analytics_enableloggedin = (get_option('wputh_analytics_enableloggedin') == '1');
        if (is_user_logged_in() && !$analytics_enableloggedin) {
            return;
        }
        $hook_ajaxready = apply_filters('wpuseo_ajaxready_hook', '');

        echo '<script type="text/javascript">';
        echo "(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)})(window,document,'script','//www.google-analytics.com/analytics.js','ga');";
        echo "ga('create', '" . $ua_analytics . "', 'auto');";
        echo "ga('send', 'pageview');";
        if (!empty($hook_ajaxready)) {
            echo "function wpuseo_callback_ajaxready(){ga('send','pageview');}";
            echo "if (typeof(jQuery) == 'undefined') {document.addEventListener('" . esc_attr($hook_ajaxready) . "',wpuseo_callback_ajaxready,1);}";
            echo "else {jQuery(document).on('" . esc_attr($hook_ajaxready) . "',wpuseo_callback_ajaxready);}";
        }
        echo '</script>';
    }

    /* ----------------------------------------------------------
      Get post keywords
    ---------------------------------------------------------- */

    public function get_post_keywords($id) {
        global $post;

        // Keywords
        $keywords_raw = array();

        $title = explode(' ', strtolower(get_the_title($id)));
        foreach ($title as $word) {
            if (strlen($word) > 3) {
                $keywords_raw = $this->check_keywords_value(sanitize_title($word), $word, $keywords_raw);
            }
        }

        $keywords_raw = $this->add_terms_to_keywords(get_the_category($id), $keywords_raw);
        $keywords_raw = $this->add_terms_to_keywords(get_the_tags($id), $keywords_raw);

        // Sort keywords by score
        usort($keywords_raw, array(
            'WPUSEO',
            'order_keywords_values'
        ));

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
        return $a[0] < $b[0];
    }

    /* ----------------------------------------------------------
      Utilities
    ---------------------------------------------------------- */

    /* Translate
     -------------------------- */

    private function __($string) {
        return __($string, 'wpuseo');
    }

    /* Test a twitter username
     -------------------------- */

    public function testTwitterUsername($username) {
        return preg_match('/^\@([a-zA_Z_0-9]+)$/', $username) !== false;
    }

    /* Prepare meta description
     -------------------------- */

    public function prepare_text($text, $max_length = 200) {
        $text = strip_shortcodes($text);
        $text = strip_tags($text);
        $text = preg_replace("/\s+/", ' ', $text);
        $text = trim($text);
        $words = explode(' ', $text);
        $final_text = '';
        foreach ($words as $word) {
            if ((strlen($final_text) + strlen(' ' . $word)) > $max_length) {
                return $final_text . ' ...';
            } else {
                $final_text .= ' ' . $word;
            }
        }
        return trim($final_text);
    }

    /* Convert an array of metas to HTML
     -------------------------- */

    public function special_convert_array_html($metas, $tag = 'meta') {
        $html = '';
        foreach ($metas as $values) {
            $html .= '<' . $tag;
            foreach ($values as $name => $value) {
                $html .= sprintf(' %s="%s"', $name, esc_attr($value));
            }
            $html .= ' />';
        }
        return $html;
    }

    /* Check if site is multilingual
     -------------------------- */

    public function is_site_multilingual() {
        $is_multi = false;
        if (function_exists('qtrans_getSortedLanguages')) {
            $is_multi = qtrans_getSortedLanguages();
        }
        if (function_exists('qtranxf_getSortedLanguages')) {
            $is_multi = qtranxf_getSortedLanguages();
        }
        return $is_multi;
    }

    /* Get languages
     -------------------------- */

    private function get_languages() {
        global $q_config, $polylang;
        $languages = array();

        // Obtaining from Qtranslate
        if (isset($q_config['enabled_languages'])) {
            foreach ($q_config['enabled_languages'] as $lang) {
                if (!in_array($lang, $languages) && isset($q_config['language_name'][$lang])) {
                    $languages[$lang] = $q_config['language_name'][$lang];
                }
            }
        }

        // Obtaining from Polylang
        if (function_exists('pll_the_languages') && is_object($polylang)) {
            $poly_langs = $polylang->model->get_languages_list();
            foreach ($poly_langs as $lang) {
                $languages[$lang->slug] = $lang->name;
            }
        }
        return $languages;
    }
}

$WPUSEO = new WPUSEO();
$WPUSEO->init();
