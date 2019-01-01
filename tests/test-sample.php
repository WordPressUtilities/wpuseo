<?php
/**
 * Class WPUSEOTest
 *
 * @package Wpuseo
 */

/**
 * Sample test case.
 */
class WPUSEOTest extends WP_UnitTestCase {

    public $demo_plugin;

    public function setUp() {
        parent::setUp();
        $this->demo_plugin = new WPUSEO;
        $this->demo_plugin->init();
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
}
