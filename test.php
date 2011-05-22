<?php
/*
 * These tests need PHP's "zlib" extension.
 *
 * Run with: phpunit test.php
 */

include('cdn-linker-base.php');

class CDNLinkerTest extends PHPUnit_Framework_TestCase
{
	/** Instance of CDNLinksRewriter. */
	var $ctx = null;

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

	protected function setUp() {
		$this->ctx = new CDNLinksRewriter(
			'http://test.local',
			'http://cdn.test.local',
			'wp-content,wp-includes',
			array('.php'),
			false
		);
	}

	public function testNoModificationIfUrlsMatch() {
		$this->ctx->cdn_url = 'http://test.local';
		$input = '<a href="http://test.local/favicon.ico">some text</a>';
		$output = $this->ctx->rewrite($input);
		$this->assertEquals($input, $output);
	}

	public function testModifiesIfUrlsDontMatch() {
		$input = '<a href="http://test.local/favicon.ico">some text</a>';
		$expected = '<a href="http://cdn.test.local/favicon.ico">some text</a>';
		$output = $this->ctx->rewrite($input);
		$this->assertEquals($expected, $output);
	}

	public function testRootRelativeLinkDisabled() {
		$this->ctx->rootrelative = false;
		$input = '<a href="/favicon.ico">some text</a>';
		$expected = '<a href="/favicon.ico">some text</a>';
		$output = $this->ctx->rewrite($input);
		$this->assertEquals($expected, $output);
	}

	public function testRootRelativeLinkEnabled() {
		$this->ctx->rootrelative = true;
		$input = '<a href="/favicon.ico"><img src="http://test.local/favicon.ico" /></a>';
		$expected = '<a href="http://cdn.test.local/favicon.ico"><img src="http://cdn.test.local/favicon.ico" /></a>';
		$output = $this->ctx->rewrite($input);
		$this->assertEquals($expected, $output);
	}

	public function testLinksToPostsAndPagesNotAffected() {
		$expected = $input = $this->readCompressedSample('virtual-1.before.gz');

		$this->ctx->rootrelative = false;
		$output = $this->ctx->rewrite($input);
		$this->assertEquals($expected, $output);

		$this->ctx->rootrelative = true;
		$output = $this->ctx->rewrite($input);
		$this->assertEquals($expected, $output);
	}

	public function testExcludes() {
		$this->ctx->rootrelative = true;
		$input = '<a href="/favicongetter.php"><img src="/favicon.ico" /></a>';
		$expected = '<a href="/favicongetter.php"><img src="http://cdn.test.local/favicon.ico" /></a>';
		$output = $this->ctx->rewrite($input);
		$this->assertEquals($expected, $output);
	}

	/** Now come actual sites. */

	protected function comparePair($blog_url, $cdn_url, $root_relative, $fn_before, $fn_after) {
		$this->ctx->blog_url = $blog_url;
		$this->ctx->cdn_url = $cdn_url;
		$this->ctx->rootrelative = $root_relative;

		$input = $this->readCompressedSample($fn_before);
		$expected = $this->readCompressedSample($fn_after);
		$output = $this->ctx->rewrite($input);
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

	public function testExcludes2() {
		// Howdyargs.com has (in this version) exceptionally ugly code.
		// One plugin, fp-autoconnect (?), breaks w/o the particular excludes.
		$this->ctx->blog_url = 'http://howdyags.com';
		$this->ctx->cdn_url = 'http://cdn.howdyags.com';
		$this->ctx->excludes = array('.php', 'xd_receiver.htm');
		$this->ctx->rootrelative = true;

		$input = $this->readCompressedSample('howdyags.com-before.gz');
		$expected = $this->readCompressedSample('howdyags.com-after.gz');
		$output = $this->ctx->rewrite($input);
		$this->assertEquals($expected, $output);

		$input = $expected = '<a href="http://howdyags.com/groups/site-news/">Site News</a></div> ';
		$output = $this->ctx->rewrite($input);
		$this->assertEquals($expected, $output);
	}

	public function testIncludeDirs() {
		// this one has root-relative links to "/extscripts/..."
		$this->ctx->blog_url = 'http://screenrant.com';
		$this->ctx->cdn_url = 'http://cdn.screenrant.com';
		$this->ctx->include_dirs = 'wp-content,wp-includes,extscripts';
		$this->ctx->rootrelative = true;

		$input = $this->readCompressedSample('screenrant.com-before.gz');
		$expected = str_replace('/extscripts/', 'http://cdn.screenrant.com/extscripts/',
					$this->readCompressedSample('screenrant.com-after.gz'));
		$output = $this->ctx->rewrite($input);
		$this->assertEquals($expected, $output);
	}

}

