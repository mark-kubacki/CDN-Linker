<?php
/*
 * These tests need PHP's "zlib" extension.
 *
 * Run with: phpunit test.php
 */

include('cdn-linker-base.php');

class CDNLinkerTest extends PHPUnit_Framework_TestCase
{
	public function readCompressedSample($filename) {
		$fp = gzopen('test/samples/'.$filename, 'r') or die("can't open: $php_errormsg");
		$content = gzread($fp, 128000);
		gzclose($fp);
		return $content;
	}

	public function writeCompressedSample($filename, $content) {
		$fp = gzopen('test/samples/'.$filename, 'w9') or die("can't open: $php_errormsg");
		if (-1 == gzwrite($fp, $content)) {
			die("can't write: $php_errormsg");
		}
		gzclose($fp);
	}

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

	public function testLinksToPostsAndPagesNotAffected() {
		$this->setGlobals();
		$expected = $input = $this->readCompressedSample('virtual-1.before.gz');

		$this->setGlobalRootRelative(false);
		$output = ossdl_off_filter($input);
		$this->assertEquals($expected, $output);

		$this->setGlobalRootRelative(true);
		$output = ossdl_off_filter($input);
		$this->assertEquals($expected, $output);
	}

	public function testExcludes() {
		$this->setGlobals();
		$this->setGlobalRootRelative(true);
		$input = '<a href="/favicongetter.php"><img src="/favicon.ico" /></a>';
		$expected = '<a href="/favicongetter.php"><img src="http://cdn.test.local/favicon.ico" /></a>';
		$output = ossdl_off_filter($input);
		$this->assertEquals($expected, $output);
	}

	/** Now come actual sites. */

	protected function comparePair($blog_url, $cdn_url, $root_relative, $fn_before, $fn_after) {
		$this->setGlobals($blog_url, $cdn_url);
		$this->setGlobalRootRelative($root_relative);
		$input = $this->readCompressedSample($fn_before);
		$expected = $this->readCompressedSample($fn_after);
		$output = ossdl_off_filter($input);
		$this->assertEquals($expected, $output);
	}

	public function testImagesAtRootLevel() {
		// the Screenrant.com sample doesn't use root-relative links
		$this->comparePair('http://screenrant.com', 'http://cdn.screenrant.com',
				   false, 'screenrant.com-before.gz', 'screenrant.com-after.gz');
	}

	public function testImagesAtRootLevel2() {
		// the mark.ossdl.de landing page has root-relative links and images on root level
		$this->comparePair('http://mark.ossdl.de', 'http://cdn.ww.ossdl.de',
				   true, 'mark.ossdl.de-before.gz', 'mark.ossdl.de-after.gz');
	}

	public function testIncludeDirs() {
		// this one has root-relative links to "/extscripts/..."
		$this->setGlobals('http://screenrant.com', 'http://cdn.screenrant.com',
				  'wp-content,wp-includes,extscripts');
		$this->setGlobalRootRelative(true);
		$input = $this->readCompressedSample('screenrant.com-before.gz');
		$expected = str_replace('/extscripts/', 'http://cdn.screenrant.com/extscripts/',
					$this->readCompressedSample('screenrant.com-after.gz'));
		$output = ossdl_off_filter($input);
		$this->assertEquals($expected, $output);
	}

}
