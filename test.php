<?php
/*
 * These tests need PHP's "zlib" extension.
 *
 * Run with:
 *     php -d phar.readonly=0 mageekguy.atoum.phar --update || \
 *     curl -LOR http://downloads.atoum.org/nightly/mageekguy.atoum.phar
 *     php test.php
 */

namespace blitznote\wp\cdn\tests\units;

require_once('mageekguy.atoum.phar');

include('cdn-linker-base.php');

use \mageekguy\atoum;
use \blitznote\wp\cdn;

class URI_changer extends atoum\test
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

	protected function getInstance() {
		$uri_changer = new cdn\URI_changer(
			'http://test.local',
			cdn\target_url_strategy_for('http://cdn.test.local'),
			'wp-content,wp-includes',
			array('.php'),
			false,
			false,
			true
		);
		$uri_changer->in_unit_test = true;
		return $uri_changer;
	}

	public function testNoModificationIfUrlsMatch() {
		$uri_changer = $this->getInstance();
		$uri_changer->get_target_url = cdn\target_url_strategy_for('http://test.local');
		$input = '<a href="http://test.local/favicon.ico">some text</a>';
		$output = $uri_changer->rewrite($input);
		$this->string($output)->isEqualTo($input);
	}

	public function testModifiesIfUrlsDontMatch() {
		$uri_changer = $this->getInstance();
		$input = '<a href="http://test.local/favicon.ico">some text</a>';
		$expected = '<a href="http://cdn.test.local/favicon.ico">some text</a>';
		$output = $uri_changer->rewrite($input);
		$this->string($output)->isEqualTo($expected);
	}

	public function testRootRelativeLinkDisabled() {
		$uri_changer = $this->getInstance();
		$uri_changer->rootrelative = false;
		$input = '<a href="/favicon.ico">some text</a>';
		$expected = '<a href="/favicon.ico">some text</a>';
		$output = $uri_changer->rewrite($input);
		$this->string($output)->isEqualTo($expected);
	}

	public function testRootRelativeLinkEnabled() {
		$uri_changer = $this->getInstance();
		$uri_changer->rootrelative = true;
		$input = '<a href="/favicon.ico"><img src="http://test.local/favicon.ico" /></a>';
		$expected = '<a href="http://cdn.test.local/favicon.ico"><img src="http://cdn.test.local/favicon.ico" /></a>';
		$output = $uri_changer->rewrite($input);
		$this->string($output)->isEqualTo($expected);
	}

	public function testLinksToPostsAndPagesNotAffected() {
		$uri_changer = $this->getInstance();
		$expected = $input = $this->readCompressedSample('virtual-1.before.gz');

		$uri_changer->rootrelative = false;
		$output = $uri_changer->rewrite($input);
		$this->string($output)->isEqualTo($expected);

		$uri_changer->rootrelative = true;
		$output = $uri_changer->rewrite($input);
		$this->string($output)->isEqualTo($expected);
	}

	public function testExcludes() {
		$uri_changer = $this->getInstance();
		$uri_changer->rootrelative = true;
		$input = '<a href="/favicongetter.php"><img src="/favicon.ico" /></a>';
		$expected = '<a href="/favicongetter.php"><img src="http://cdn.test.local/favicon.ico" /></a>';
		$output = $uri_changer->rewrite($input);
		$this->string($output)->isEqualTo($expected);
	}

	public function testExcludesSomeMore() {
		$uri_changer = $this->getInstance();
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
				$uri_changer->excludes = $excludes;
				$uri_changer->rootrelative = $rootrelative;

				foreach($cases as $input) {
					$expected = $input;
					// Output has to be unmodified.
					$output = $uri_changer->rewrite($input);
					$this->string($output)->isEqualTo($expected);
					// If it were modified, e.g. comments wouldn't work or similar things.
				}
			}
		}
	}

	public function testDisableCDNURISifHTTPS() {
		$uri_changer = $this->getInstance();
		$uri_changer->https_deactivates_rewriting = true;

		// request without HTTPS
		$input = '<img src="http://test.local/favicon.ico" />';
		$expected = '<img src="http://cdn.test.local/favicon.ico" />';
		$output = $uri_changer->rewrite($input);
		$this->string($output)->isEqualTo($expected);

		// request using HTTPS
		$_SERVER["HTTPS"] = 'on';
		$expected = '<img src="http://test.local/favicon.ico" />';
		$output = $uri_changer->rewrite($input);
		$this->string($output)->isEqualTo($expected);
	}

	public function testMultipleCDNs() {
		$uri_changer = $this->getInstance();
		$uri_changer->rootrelative = true;
		$uri_changer->get_target_url = cdn\target_url_strategy_for('http://cdn%4%.test.local');
		$input = '<img src="/me.jpg" /><img src="/favicon.tif" /><a href="/movie.ogg">text</a><a href="/wp-content/file.exe">text</a>';
		$expected = '<img src="http://cdn1.test.local/me.jpg" /><img src="http://cdn2.test.local/favicon.tif" /><a href="http://cdn3.test.local/movie.ogg">text</a><a href="http://cdn4.test.local/wp-content/file.exe">text</a>';

		$output = $uri_changer->rewrite($input);
		$this->string($output)->isEqualTo($expected);
		// another run to make sure it is deterministic
		$output = $uri_changer->rewrite($input);
		$this->string($output)->isEqualTo($expected);
	}

	/** Now come actual sites. */

	protected function comparePair($blog_url, $cdn_url, $root_relative, $fn_before, $fn_after) {
		$uri_changer = $this->getInstance();
		$uri_changer->blog_url = $blog_url;
		$uri_changer->get_target_url = cdn\target_url_strategy_for($cdn_url);
		$uri_changer->rootrelative = $root_relative;

		$input = $this->readCompressedSample($fn_before);
		$expected = $this->readCompressedSample($fn_after);
		$output = $uri_changer->rewrite($input);
		$this->string($output)->isEqualTo($expected);
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
		$uri_changer = $this->getInstance();
		// Howdyargs.com has (in this version) exceptionally ugly code.
		// One plugin, fp-autoconnect (?), breaks w/o the particular excludes.
		$uri_changer->blog_url = 'http://howdyags.com';
		$uri_changer->get_target_url = cdn\target_url_strategy_for('http://cdn.howdyags.com');
		$uri_changer->excludes = array('.php', 'xd_receiver.htm');
		$uri_changer->rootrelative = true;

		$input = $this->readCompressedSample('howdyags.com-before.gz');
		$expected = $this->readCompressedSample('howdyags.com-after.gz');
		$output = $uri_changer->rewrite($input);
		$this->string($output)->isEqualTo($expected);

		$input = $expected = '<a href="http://howdyags.com/groups/site-news/">Site News</a></div> ';
		$output = $uri_changer->rewrite($input);
		$this->string($output)->isEqualTo($expected);
	}

	public function testExcludes3() {
		$uri_changer = $this->getInstance();
		// 刘晖 has reported issue #4 and sent pages to reproce the issue.
		// Let's see what has been configured to reproduce the incorrect output:
		$uri_changer->blog_url = 'http://wangma.me';
		$uri_changer->get_target_url = cdn\target_url_strategy_for('http://cdn.wangma.me');

		$uri_changer->excludes = array('.php'); //<-- this worked fine!
		$input = $this->readCompressedSample('wangma.me-before.gz');
		$expected = $this->readCompressedSample('wangma.me-after.gz');
		$output = $uri_changer->rewrite($input);
		$this->string($output)->isEqualTo($expected);

		$uri_changer->excludes = array(); //<-- this is what actually has been configured and caused the malfunction
		$expected = $this->readCompressedSample('wangma.me-incorrect.gz');
		$output = $uri_changer->rewrite($input);
		$this->string($output)->isEqualTo($expected);
	}

	public function testIncludeDirs() {
		$uri_changer = $this->getInstance();
		// this one has root-relative links to "/extscripts/..."
		$uri_changer->blog_url = 'http://screenrant.com';
		$uri_changer->get_target_url = cdn\target_url_strategy_for('http://cdn.screenrant.com');
		$uri_changer->include_dirs = 'wp-content,wp-includes,extscripts';
		$uri_changer->rootrelative = true;

		$input = $this->readCompressedSample('screenrant.com-before.gz');
		$expected = str_replace('/extscripts/', 'http://cdn.screenrant.com/extscripts/',
					$this->readCompressedSample('screenrant.com-after.gz'));
		$output = $uri_changer->rewrite($input);
		$this->string($output)->isEqualTo($expected);
	}

	public function testMissingWww() {
		$uri_changer = $this->getInstance();
		// http://prettyshinysparkly.com/
		// 2012-01-30: everything under "/images/" doesn't work if root-relative == false
		$uri_changer->blog_url = 'http://www.prettyshinysparkly.com';
		$uri_changer->get_target_url = cdn\target_url_strategy_for('http://cdn.prettyshinysparkly.com');
		$uri_changer->include_dirs = 'images, wp-content, wp-includes, js'; // spaces
		$uri_changer->excludes = array_map('trim', explode(',', trim('.php, .flv, .do')));

		// tests for: rootrelative
		$input = $this->readCompressedSample('prettyshinysparkly.com-before.gz');
		$expected = $this->readCompressedSample('prettyshinysparkly.com-after.gz');
		$uri_changer->rootrelative = false;
		$output = $uri_changer->rewrite($input);
		$this->string($output)->isEqualTo($expected);
		$uri_changer->rootrelative = true;
		$output = $uri_changer->rewrite($input);
		$this->string($output)->isEqualTo($expected);

		// tests for: www_is_optional
		$input = $this->readCompressedSample('prettyshinysparkly.com-no_www-before.gz');
		$uri_changer->www_is_optional = true;
		$uri_changer->rootrelative = false;
		$output = $uri_changer->rewrite($input);
		$this->string($output)->isEqualTo($expected);
		$uri_changer->rootrelative = true;
		$output = $uri_changer->rewrite($input);
		$this->string($output)->isEqualTo($expected);

	}

}
