<?php

namespace blitznote\wp\cdn;

/**
 * Strategy for creating CDN URIs.
 */
interface Target_URL_Strategy
{
	/**
	 * Gets the right CDN URL for the given item.
	 *
	 * By modifying or overwriting this method you could, for example,
	 * have images loaded from CDN 1 and videos from CDN 2.
	 *
	 * @param String $url will either be something like 'http://test.local/xyz/zdf.ext' or '/xyz/zdf.ext'
	 * @return String contains the CDN url - e.g. 'http://cdn.test.local'
	 */
	public function for_source(&$url);

}

class Target_single_host implements Target_URL_Strategy
{
	/** String: URL of the CDN domain */
	var $cdn_url		= null;

	function __construct($cdn_url) {
		$this->cdn_url = $cdn_url;
	}

	public function for_source(&$url) {
		return $this->cdn_url;
	}

}

class Target_multiple_hosts implements Target_URL_Strategy
{
	/**
	 * String: URL with pattern %d% for the CDN domains.
	 * 'd' is how many variations exist.
	 */
	var $cdn_pattern	= null;
	/** Number of variations. Derived from the above variable. */
	var $variations		= 0;
	/** Fragment, to speed up replacings. */
	protected $fragment	= null;

	function __construct($cdn_pattern) {
		preg_match('/%(\d)%/', $cdn_pattern, $m);
		$this->variations = max($m[1], 1);
		$this->cdn_pattern = $cdn_pattern;
		$this->fragment = $m[0];
	}

	public function for_source(&$url) {
		$n = ( hexdec(substr(md5($url), 0, 1)) % $this->variations ) + 1;
		return str_replace($this->fragment, $n, $this->cdn_pattern);
	}

}

/**
 * Gets an implementation of Target_URL_Strategy.
 */
function target_url_strategy_for($pattern) {
	if (preg_match('/%(\d)%/', $pattern)) {
		return new Target_multiple_hosts($pattern);
	}
	return new Target_single_host($pattern);
}

/**
 * Reperesents the CDN Linker's rewrite logic.
 *
 * 'rewrite' gets the raw HTML as input and returns the final result.
 * It finds all links and runs them through 'rewrite_singe', which prepends the CDN domain.
 *
 * 'URI_changer' contains no WP related function calls and can thus be used in testing or in other software.
 */
class URI_changer
{
	/** String: the blog's URL ( get_option('siteurl') ) */
	var $blog_url		= null;
	/** Target_URL_Strategy: results in URL of a CDN domain */
	var $get_target_url	= null;
	/** String: directories to include in static file matching, comma-delimited list */
	var $include_dirs	= null;
	/** Array: strings which indicate that a given element should not be rewritten (i.e., ".php") */
	var $excludes		= array();
	/** Boolean: if true, modifies root-relative links */
	var $rootrelative	= false;
	/** Boolean: if true, missing subdomain 'www' will still result in a match*/
	var $www_is_optional	= false;
	/** Boolean: HTTPS accesses deactivate rewriting */
	var $https_deactivates_rewriting	= true;
	/** Boolean: will skip some matches in JS scripts if set to true */
	var $skip_on_trailing_semicolon = false;
	/** Boolean: only set in unit tests */
	var $in_unit_test	= false;


	/** Constructor. */
	function __construct($blog_url, Target_URL_Strategy $get_target_url, $include_dirs,
			array $excludes, $root_relative, $www_is_optional,
			$https_deactivates_rewriting) {
		$this->blog_url		= $blog_url;
		$this->get_target_url	= $get_target_url;
		$this->include_dirs	= $include_dirs;
		$this->excludes		= $excludes;
		$this->rootrelative	= $root_relative;
		$this->www_is_optional	= $www_is_optional;
		$this->https_deactivates_rewriting = $https_deactivates_rewriting;
	}

	/**
	 * Determines whether to exclude a match.
	 *
	 * @param String $match URI to examine
	 * @return Boolean true if to exclude given match from rewriting
	 */
	protected function exclude_single(&$match) {
		foreach ($this->excludes as $badword) {
			if (!!$badword && stristr($match, $badword) != false) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Rewriter of URLs, used as callback for rewriting in {@link ossdl_off_filter}.
	 *
	 * @param String $match An URI as candidate for rewriting
	 * @return String the unmodified URI if it is not to be rewritten, otherwise a modified one pointing to CDN
	 */
	protected function rewrite_single($match) {
		if ($this->exclude_single($match[0])) {
			return $match[0];
		}

		$blog_url = $this->blog_url;
		if ($this->www_is_optional && $match[0]{0} != '/' && !strstr($match[0], '//www.')) {
			$blog_url = str_replace('//www.', '//', $blog_url);
		}
		if (!$this->rootrelative || strstr($match[0], $blog_url)) {
			return str_replace($blog_url, $this->get_target_url->for_source($match[0]), $match[0]);
		}
		// obviously $this->rootrelative is true and we got a root-relative link - else that case won't happen
		return $this->get_target_url->for_source($match[0]) . $match[0];
	}

	/**
	 * Creates a regexp-compatible pattern from the list of relevant directories.
	 *
	 * @return String regexp pattern for those directories, or empty if none are given
	 */
	protected function include_dirs_to_pattern() {
		$input = explode(',', $this->include_dirs);
		if ($this->include_dirs == '' || count($input) < 1) {
			return 'wp\-content|wp\-includes';
		}
		return implode('|', array_map('quotemeta', array_map('trim', $input)));
	}

	/**
	 * Takes care of an optional 'www' subdomain and an optional domain name.
	 *
	 * @return String regexp pattern such as {@code '(?:http://(?:www\.)?example\.com)?'}
	 */
	protected function blog_url_to_pattern() {
		$blog_url = quotemeta($this->blog_url);
		$max_occurences =  1; // due to PHP's stupidity this must be a variable
		if ($this->www_is_optional && strstr($blog_url, '//www\.')) {
			$blog_url = str_replace('//www\.', '//(?:www\.)?', $blog_url, $max_occurences);
		}
		if ($this->rootrelative) {
			return '(?:'.$blog_url.')?';
		}
		return $blog_url;
	}

	/**
	 * Output filter which runs the actual plugin logic.
	 *
	 * Gets the page's HTML and runs {@link rewrite_single} on every found substring.
	 *
	 * @param String $content the raw HTML of the page from Wordpress, meant to be returned to the requester but intercepted here
	 * @return String modified HTML with replaced links - will be served by the HTTP server to the requester
	 */
	public function rewrite(&$content) {
		if ($this->https_deactivates_rewriting && isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == 'on') {
			return $content;
		}

		$dirs = $this->include_dirs_to_pattern();
		// string has to start with a quotation mark or parentheses
		$regex = '#(?<=[(\"\'])';
		// ... optionally followed by the blog url
		$regex .= $this->blog_url_to_pattern();
		// ... after that by a single dash,
		//     (followed by a directory and some chars
		//      or a filename (which we spot by the dot in its filename))
		$regex .= '/(?:((?:'.$dirs.')[^\"\')]+)|([^/\"\']+\.[^/\"\')]+))';
		// ... finally ending in an enclosing quotation mark or parentheses
		if ($this->skip_on_trailing_semicolon) {
			$regex .= '(?=[\"\'][^;]|\))#';
		} else {
			$regex .= '(?=[\"\')])#';
		}

		$new_content = preg_replace_callback($regex, array(&$this, 'rewrite_single'), $content);
		return $new_content;
	}

}

/**
 * This is called by Wordpress.
 */
function register_as_output_buffer_handler() {
	if (get_option('siteurl') == trim(get_option('ossdl_off_cdn_url'))) {
		return;
	}

	$excludes = array_map('trim', explode(',', get_option('ossdl_off_exclude')));
	$rewriter = new URI_changer(
		get_option('siteurl'),
		target_url_strategy_for(trim(get_option('ossdl_off_cdn_url'))),
		trim(get_option('ossdl_off_include_dirs')),
		$excludes,
		!!trim(get_option('ossdl_off_rootrelative')),
		!!trim(get_option('ossdl_off_www_is_optional')),
		!!trim(get_option('ossdl_off_disable_cdnuris_if_https'))
	);

	ob_start(array(&$rewriter, 'rewrite'));
}
