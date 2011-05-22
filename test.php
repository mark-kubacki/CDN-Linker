<?php
include('cdn-linker-base.php');

class CDNLinkerTest extends PHPUnit_Framework_TestCase
{
	protected function setGlobalRootRelative($root_relative) {
		global $ossdl_off_rootrelative;
		$ossdl_off_rootrelative = !!$root_relative;
	}

	/**
	 * Sets global variables like the plugin for Wordpress does.
	 */
	protected function setGlobals($blog_url = 'http://test.local',
				   $cdn_url = 'http://cdn.test.local',
				   $include_dirs = 'wp-content,wp-includes',
				   array $excludes = array('.php'),
				   $root_relative = false) {
		global $ossdl_off_blog_url, $ossdl_off_cdn_url, $ossdl_off_include_dirs, $arr_of_excludes,
			$ossdl_off_rootrelative;
		
		$ossdl_off_blog_url = $blog_url;
		$ossdl_off_cdn_url = $cdn_url;
		$ossdl_off_include_dirs = $include_dirs;
		$arr_of_excludes = $excludes;
		$ossdl_off_rootrelative = $root_relative;
	}

	public function testNoModificationIfUrlsMatch() {
		$this->setGlobals('http://test.local', 'http://test.local');
		$input = '<a href="http://test.local/favicon.ico">some text</a>';
		$output = ossdl_off_filter($input);
		$this->assertEquals($input, $output);
	}

	public function testModifiesIfUrlsDontMatch() {
		$this->setGlobals();
		$input = '<a href="http://test.local/favicon.ico">some text</a>';
		$expected = '<a href="http://cdn.test.local/favicon.ico">some text</a>';
		$output = ossdl_off_filter($input);
		$this->assertEquals($expected, $output);
	}

	public function testRootRelativeLinkDisabled() {
		$this->setGlobals();
		$this->setGlobalRootRelative(false);
		$input = '<a href="/favicon.ico">some text</a>';
		$expected = '<a href="/favicon.ico">some text</a>';
		$output = ossdl_off_filter($input);
		$this->assertEquals($expected, $output);
	}

	public function testRootRelativeLinkEnabled() {
		$this->setGlobals();
		$this->setGlobalRootRelative(true);
		$input = '<a href="/favicon.ico"><img src="http://test.local/favicon.ico" /></a>';
		$expected = '<a href="http://cdn.test.local/favicon.ico"><img src="http://cdn.test.local/favicon.ico" /></a>';
		$output = ossdl_off_filter($input);
		$this->assertEquals($expected, $output);
	}

}
