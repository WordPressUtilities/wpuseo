<?php
/**
 * Class test_WPUSEO
 *
 * @package Wpuseo
 */

/**
 * Sample test case.
 */
class test_WPUSEO extends WP_UnitTestCase {

    public $demo_plugin;

    /* Post */
    public $post_title = 'My Post Title';
    public $post_content = 'The world needs dreamers and the world needs doers. But above all, the world needs dreamers who do — Sarah Ban Breathnach. é!"';
    public $post_item = false;

    /* Category */
    public $cat_title = 'My Category Title';
    public $cat_content = 'The world needs dreamers and the world needs doers. But above all, the world needs dreamers who do — Sarah Ban Breathnach. é!"';
    public $cat_item = false;

    /* CPT */
    public $cpt_name = 'My WPUSEO CPT';
    public $cpt_id = 'wpuposttype';

    public function setUp(): void{
        $this->demo_plugin = new WPUSEO;
        $this->demo_plugin->init();

        /* Create post item */
        $this->post_item = wp_insert_post(array(
            'post_status' => 'publish',
            'post_type' => 'post',
            'post_title' => $this->post_title,
            'post_content' => $this->post_content
        ));

        /* Post type */
        register_post_type($this->cpt_id, array(
            'public' => true,
            'has_archive' => true,
            'label' => $this->cpt_name
        ));
        wp_insert_post(array(
            'post_status' => 'publish',
            'post_type' => $this->cpt_id,
            'post_title' => 'Post Title',
            'post_content' => 'Post Content'
        ));
        flush_rewrite_rules();
    }

    // Test meta conversion
    public function test_convert_metas() {
        do_action('init');
        $out = $this->demo_plugin->special_convert_array_html(array(
            array('name' => 'test')
        ));
        $out = trim($out);
        $this->assertEquals('<meta name="test" />', $out);
    }

    // Test Twitter Username Validity
    public function test_twitter_username() {
        $this->assertFalse($this->demo_plugin->testTwitterUsername('@<az>'));
        $this->assertTrue($this->demo_plugin->testTwitterUsername('@Darklg'));
    }

    /* ----------------------------------------------------------
      Home
    ---------------------------------------------------------- */

    public function test_home_metas() {
        $this->go_to('/');
        $blogname = get_option('blogname');

        /* Check format */
        $metas = $this->demo_plugin->get_metas();
        $this->assertTrue(is_array($metas));
        $this->assertTrue(isset($metas['metas']['description']));

        /* Description
        -------------------------- */
        /* Empty desc */
        update_option('blogdescription', '');
        $metas = $this->demo_plugin->get_metas();
        $this->assertTrue(empty($metas['metas']['description']['content']));

        /* Social */

        /* Default desc */
        $test_str = 'Lorem ipsum';
        update_option('blogdescription', $test_str);
        $metas = $this->demo_plugin->get_metas();
        $this->assertEquals($metas['metas']['description']['content'], $test_str);

        /* Long description should be cutted */
        $test_str = str_repeat('a ', 150);
        $test_str2 = str_repeat('a ', 100) . '...';
        update_option('blogdescription', $test_str);
        $metas = $this->demo_plugin->get_metas();
        $this->assertEquals($metas['metas']['description']['content'], $test_str2);

        /* Home meta desc should be used */
        $test_str3 = 'Lorem ipsum';
        update_option('wpu_home_meta_description', $test_str3);
        $metas = $this->demo_plugin->get_metas();
        $this->assertEquals($metas['metas']['description']['content'], $test_str3);

        /* HTML Should be removed */
        $test_str4 = 'Lorem <strong>ipsum</strong>';
        update_option('wpu_home_meta_description', $test_str4);
        $metas = $this->demo_plugin->get_metas();
        $this->assertEquals($metas['metas']['description']['content'], $test_str3);

        /* Social : Facebook
        -------------------------- */

        /* Disabled by default */
        $this->assertFalse(isset($metas['metas']['og_description']));

        /* Enabled */
        $this->demo_plugin->enable_facebook_metas = true;
        $metas = $this->demo_plugin->get_metas();
        $this->assertTrue(isset($metas['metas']['og_description']));
        $this->assertTrue(isset($metas['metas']['og_title']));

        /* Value loaded from  */
        $this->assertEquals($metas['metas']['og_description']['content'], $test_str3);
        $this->assertEquals($metas['metas']['og_title']['content'], $blogname);

        /* Social : Twitter
        -------------------------- */

        /* Disabled by default */
        $this->assertFalse(isset($metas['metas']['twitter_description']));

        /* Enabled */
        $this->demo_plugin->enable_twitter_metas = true;
        $metas = $this->demo_plugin->get_metas();
        $this->assertTrue(isset($metas['metas']['twitter_description']));
        $this->assertTrue(isset($metas['metas']['twitter_title']));

        /* Value loaded from  */
        $this->assertEquals($metas['metas']['twitter_description']['content'], $test_str3);
        $this->assertEquals($metas['metas']['twitter_title']['content'], $blogname);

        /* Test hooks
        -------------------------- */

        add_filter('wpuseo__prepare_text__before', function ($text, $context) {
            if (!is_home()) {
                return $text;
            }
            if ($context == 'facebook_description') {
                return 'fbdesc';
            }
            if ($context == 'facebook_title') {
                return 'fbtitle';
            }
            if ($context == 'twitter_description') {
                return 'twdesc';
            }
            if ($context == 'twitter_title') {
                return 'twtitle';
            }
            return $text;
        }, 10, 2);
        $metas = $this->demo_plugin->get_metas();
        $this->assertEquals($metas['metas']['og_title']['content'], 'fbtitle');
        $this->assertEquals($metas['metas']['og_description']['content'], 'fbdesc');
        $this->assertEquals($metas['metas']['twitter_title']['content'], 'twtitle');
        $this->assertEquals($metas['metas']['twitter_description']['content'], 'twdesc');

    }

    function test_single_metas() {
        $this->go_to(get_permalink($this->post_item));

        /* Default metas
        -------------------------- */

        $this->demo_plugin->enable_facebook_metas = true;
        $this->demo_plugin->enable_twitter_metas = true;
        $metas = $this->demo_plugin->get_metas();

        /* Default description */
        $this->assertEquals($metas['metas']['description']['content'], $this->post_content);

        /* Social */
        $this->assertEquals($metas['metas']['og_title']['content'], $this->post_title);
        $this->assertEquals($metas['metas']['og_description']['content'], $this->post_content);
        $this->assertEquals($metas['metas']['twitter_title']['content'], $this->post_title);
        $this->assertEquals($metas['metas']['twitter_description']['content'], $this->post_content);

        /* Hooks */
        add_filter('wpuseo__prepare_text__before', function ($text, $context) {
            if (!is_single()) {
                return $text;
            }
            if ($context == 'facebook_description') {
                return 'fbdesc';
            }
            if ($context == 'facebook_title') {
                return 'fbtitle';
            }
            if ($context == 'twitter_description') {
                return 'twdesc';
            }
            if ($context == 'twitter_title') {
                return 'twtitle';
            }
            return $text;
        }, 10, 2);
        $metas = $this->demo_plugin->get_metas();
        $this->assertEquals($metas['metas']['og_title']['content'], 'fbtitle');
        $this->assertEquals($metas['metas']['og_description']['content'], 'fbdesc');
        $this->assertEquals($metas['metas']['twitter_title']['content'], 'twtitle');
        $this->assertEquals($metas['metas']['twitter_description']['content'], 'twdesc');
    }

    function test_category_metas() {

        $this->cat_title = $this->cat_title . ' ' . time();

        /* Create category */
        $this->cat_item = wp_insert_category(array(
            'cat_name' => $this->cat_title,
            'category_description' => $this->cat_content
        ));

        /* Assign category */
        wp_set_post_categories($this->post_item, array($this->cat_item));

        $this->go_to(get_category_link($this->cat_item));

        /* Default metas
            -------------------------- */

        $this->demo_plugin->enable_facebook_metas = true;
        $this->demo_plugin->enable_twitter_metas = true;
        $metas = $this->demo_plugin->get_metas();

        /* Default description */
        $this->assertEquals($metas['metas']['description']['content'], $this->cat_content);

        /* Social */
        $this->assertEquals($metas['metas']['og_title']['content'], $this->cat_title);
        $this->assertEquals($metas['metas']['og_description']['content'], $this->cat_content);
        $this->assertEquals($metas['metas']['twitter_title']['content'], $this->cat_title);
        $this->assertEquals($metas['metas']['twitter_description']['content'], $this->cat_content);

        /* Hooks */
        add_filter('wpuseo__prepare_text__before', function ($text, $context) {
            if (!is_category()) {
                return $text;
            }
            if ($context == 'facebook_description') {
                return 'fbdesc';
            }
            if ($context == 'facebook_title') {
                return 'fbtitle';
            }
            if ($context == 'twitter_description') {
                return 'twdesc';
            }
            if ($context == 'twitter_title') {
                return 'twtitle';
            }
            return $text;
        }, 10, 2);
        $metas = $this->demo_plugin->get_metas();
        $this->assertEquals($metas['metas']['og_title']['content'], 'fbtitle');
        $this->assertEquals($metas['metas']['og_description']['content'], 'fbdesc');
        $this->assertEquals($metas['metas']['twitter_title']['content'], 'twtitle');
        $this->assertEquals($metas['metas']['twitter_description']['content'], 'twdesc');

    }

    function test_cpt_metas() {
        $this->go_to(get_post_type_archive_link($this->cpt_id));

        $this->demo_plugin->enable_facebook_metas = true;
        $this->demo_plugin->enable_twitter_metas = true;

        /* Not enabled by default */
        $metas = $this->demo_plugin->get_metas();
        $this->assertFalse(isset($metas['metas']['description']));

        /* Enabled */
        $this->demo_plugin->boxes_pt_with_archive[] = $this->cpt_id;
        $metas = $this->demo_plugin->get_metas();

        /* Default description */
        $this->assertEquals($metas['metas']['description']['content'], $this->cpt_name);

        /* Social */
        $this->assertEquals($metas['metas']['og_title']['content'], $this->cpt_name);
        $this->assertEquals($metas['metas']['og_description']['content'], $this->cpt_name);
        $this->assertEquals($metas['metas']['twitter_title']['content'], $this->cpt_name);
        $this->assertEquals($metas['metas']['twitter_description']['content'], $this->cpt_name);

        /* Hooks */
        add_filter('wpuseo__prepare_text__before', function ($text, $context) {
            if (!is_post_type_archive($this->cpt_id)) {
                return $text;
            }
            if ($context == 'facebook_description') {
                return 'fbdesc';
            }
            if ($context == 'facebook_title') {
                return 'fbtitle';
            }
            if ($context == 'twitter_description') {
                return 'twdesc';
            }
            if ($context == 'twitter_title') {
                return 'twtitle';
            }
            return $text;
        }, 10, 2);
        $metas = $this->demo_plugin->get_metas();
        $this->assertEquals($metas['metas']['og_title']['content'], 'fbtitle');
        $this->assertEquals($metas['metas']['og_description']['content'], 'fbdesc');
        $this->assertEquals($metas['metas']['twitter_title']['content'], 'twtitle');
        $this->assertEquals($metas['metas']['twitter_description']['content'], 'twdesc');

    }
}
