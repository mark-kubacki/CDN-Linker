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
			ossdl_off_cdn_strategy_for('http://cdn.test.local'),
			'wp-content,wp-includes',
			array('.php'),
			false,
			false,
			true
		);
		$this->ctx->in_unit_test = true;
	}

	public function testNoModificationIfUrlsMatch() {
		$this->ctx->cdn_url = ossdl_off_cdn_strategy_for('http://test.local');
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

	public function testExcludesSomeMore() {
		// This test is an attempt to reproduce issue #4.
		// It is very verbosely commented because I want link to it whenever someone reports
		// a similar issue. For that it needs to be understood even by non-programmers - or me
		// after a while. ;-)
		$cases = array(
			// "login goes through CDN!"
			'<a href="http://test.local/wp-login.php?action=logout&#038;_wpnonce=71297c3251">',
			'<a href="/wp-login.php?action=logout&#038;_wpnonce=71297c3251">',
			// "pingbacks..." or "external editors don't work!"
			'<a href="http://test.local/xmlrpc.php?rsd">',
			'空格<a href="/xmlrpc.php?rsd">',
			'<a href="http://test.local/xmlrpc.php">',
			'<a href="/xmlrpc.php">',
			// "I/we cannot post nor comment!"
			'<a href="http://test.local/wp-comments-post.php">',
			'<a href="/wp-comments-post.php">'
		);
		$exclude_configurations = array(
			// This is the default:
			array_map('trim', explode(',', trim('.php'))),
			// I will try really hard to cover all cases of misconfiguration here
			// and add additional whitespace and some more stop-strings.
			array_map('trim', explode(',', trim(' .php, .py')))
		);
		foreach($exclude_configurations as $excludes) {
			foreach(array(true, false) as $rootrelative) {
				$this->ctx->excludes = $excludes;
				$this->ctx->rootrelative = $rootrelative;

				foreach($cases as $input) {
					$expected = $input;
					// Output has to be unmodified.
					$output = $this->ctx->rewrite($input);
					$this->assertEquals($expected, $output);
					// If it were modified, e.g. comments wouldn't work or similar things.
				}
			}
		}
	}

	public function testDisableCDNURISifHTTPS() {
		$this->ctx->https_deactivates_rewriting = true;

		// request without HTTPS
		$input = '<img src="http://test.local/favicon.ico" />';
		$expected = '<img src="http://cdn.test.local/favicon.ico" />';
		$output = $this->ctx->rewrite($input);
		$this->assertEquals($expected, $output);

		// request using HTTPS
		$_SERVER["HTTPS"] = 'on';
		$expected = '<img src="http://test.local/favicon.ico" />';
		$output = $this->ctx->rewrite($input);
		$this->assertEquals($expected, $output);
	}

	public function testMultipleCDNs() {
		$this->ctx->rootrelative = true;
		$this->ctx->cdn_url = ossdl_off_cdn_strategy_for('http://cdn%4%.test.local');
		$input = '<img src="/me.jpg" /><img src="/favicon.tif" /><a href="/movie.ogg">text</a><a href="/wp-content/file.exe">text</a>';
		$expected = '<img src="http://cdn1.test.local/me.jpg" /><img src="http://cdn2.test.local/favicon.tif" /><a href="http://cdn3.test.local/movie.ogg">text</a><a href="http://cdn4.test.local/wp-content/file.exe">text</a>';

		$output = $this->ctx->rewrite($input);
		$this->assertEquals($expected, $output);
		// another run to make sure it is deterministic
		$output = $this->ctx->rewrite($input);
		$this->assertEquals($expected, $output);
	}

	/** Now come actual sites. */

	protected function comparePair($blog_url, $cdn_url, $root_relative, $fn_before, $fn_after) {
		$this->ctx->blog_url = $blog_url;
		$this->ctx->cdn_url = ossdl_off_cdn_strategy_for($cdn_url);
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
		$this->ctx->cdn_url = ossdl_off_cdn_strategy_for('http://cdn.howdyags.com');
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

	public function testExcludes3() {
		// 刘晖 has reported issue #4 and sent pages to reproce the issue.
		// Let's see what has been configured to reproduce the incorrect output:
		$this->ctx->blog_url = 'http://wangma.me';
		$this->ctx->cdn_url = ossdl_off_cdn_strategy_for('http://cdn.wangma.me');

		$this->ctx->excludes = array('.php'); //<-- this worked fine!
		$input = $this->readCompressedSample('wangma.me-before.gz');
		$expected = $this->readCompressedSample('wangma.me-after.gz');
		$output = $this->ctx->rewrite($input);
		$this->assertEquals($expected, $output);

		$this->ctx->excludes = array(); //<-- this is what actually has been configured and caused the malfunction
		$expected = $this->readCompressedSample('wangma.me-incorrect.gz');
		$output = $this->ctx->rewrite($input);
		$this->assertEquals($expected, $output);
	}

	public function testIncludeDirs() {
		// this one has root-relative links to "/extscripts/..."
		$this->ctx->blog_url = 'http://screenrant.com';
		$this->ctx->cdn_url = ossdl_off_cdn_strategy_for('http://cdn.screenrant.com');
		$this->ctx->include_dirs = 'wp-content,wp-includes,extscripts';
		$this->ctx->rootrelative = true;

		$input = $this->readCompressedSample('screenrant.com-before.gz');
		$expected = str_replace('/extscripts/', 'http://cdn.screenrant.com/extscripts/',
					$this->readCompressedSample('screenrant.com-after.gz'));
		$output = $this->ctx->rewrite($input);
		$this->assertEquals($expected, $output);
	}

	public function testMissingWww() {
		// http://prettyshinysparkly.com/
		// 2012-01-30: everything under "/images/" doesn't work if root-relative == false
		$this->ctx->blog_url = 'http://www.prettyshinysparkly.com';
		$this->ctx->cdn_url = ossdl_off_cdn_strategy_for('http://cdn.prettyshinysparkly.com');
		$this->ctx->include_dirs = 'images, wp-content, wp-includes, js'; // spaces
		$this->ctx->excludes = array_map('trim', explode(',', trim('.php, .flv, .do')));

		// tests for: rootrelative
		$input = $this->readCompressedSample('prettyshinysparkly.com-before.gz');
		$expected = $this->readCompressedSample('prettyshinysparkly.com-after.gz');
		$this->ctx->rootrelative = false;
		$output = $this->ctx->rewrite($input);
		$this->assertEquals($expected, $output);
		$this->ctx->rootrelative = true;
		$output = $this->ctx->rewrite($input);
		$this->assertEquals($expected, $output);

		// tests for: www_is_optional
		$input = $this->readCompressedSample('prettyshinysparkly.com-no_www-before.gz');
		$this->ctx->www_is_optional = true;
		$this->ctx->rootrelative = false;
		$output = $this->ctx->rewrite($input);
		$this->assertEquals($expected, $output);
		$this->ctx->rootrelative = true;
		$output = $this->ctx->rewrite($input);
		$this->assertEquals($expected, $output);

	}

}
