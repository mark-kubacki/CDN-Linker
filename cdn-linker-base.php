<?php
/**
 * Strategy for creating CDN URIs.
 */
interface ICdnForItemStrategy
{
	/**
	 * Gets the right CDN URL for the given item.
	 *
	 * By modifying or overwriting this method you could, for example,
	 * have images loaded from CDN 1 and videos from CDN 2.
	 *
	 * @param String $item will either be something like 'http://test.local/xyz/zdf.ext' or '/xyz/zdf.ext'
	 * @return String contains the CDN url - e.g. 'http://cdn.test.local'
	 */
	public function get_for(&$item);

}

class OneCdnForAllStrategy implements ICdnForItemStrategy
{
	/** String: URL of the CDN domain */
	var $cdn_url		= null;

	function __construct($cdn_url) {
		$this->cdn_url = $cdn_url;
	}

	public function get_for(&$item) {
		return $this->cdn_url;
	}

}

class MultipleCdnsDeterministic implements ICdnForItemStrategy
{
	/**
	 * String: URL with pattern %d% for the CDN domains.
	 * 'd' is how many variations exist.
	 */
	var $cdn_pattern	= null;
	/** Number of variations. Derived from the above variable. */
	var $variations		= 0;
	/** Fragment, to speed up replacings. */
	var $fragment		= null;

	function __construct($cdn_pattern) {
		preg_match('/%(\d)%/', $cdn_pattern, $m);
		$this->variations = max($m[1], 1);
		$this->cdn_pattern = $cdn_pattern;
		$this->fragment = $m[0];
	}

	public function get_for(&$item) {
		$n = ( hexdec(substr(md5($item), 0, 1)) % $this->variations ) + 1;
		return str_replace($this->fragment, $n, $this->cdn_pattern);
	}

}

/**
 * Gets an implementation of ICdnForItemStrategy.
 */
function ossdl_off_cdn_strategy_for($pattern) {
	if (preg_match('/%(\d)%/', $pattern)) {
		return new MultipleCdnsDeterministic($pattern);
	} else {
		return new OneCdnForAllStrategy($pattern);
	}
}

/**
 * Reperesents the CDN Linker's rewrite logic.
 *
 * 'rewrite' gets the raw HTML as input and returns the final result.
 * It finds all links and runs them through 'rewrite_singe', which prepends the CDN domain.
 *
 * 'CDNLinksRewriter' contains no WP related function calls and can thus be used in testing or in other software.
 */
class CDNLinksRewriter
{
	/** String: the blog's URL ( get_option('siteurl') ) */
	var $blog_url		= null;
	/** ICdnForItemStrategy: results in URL of a CDN domain */
	var $cdn_url		= null;
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
	function __construct($blog_url, ICdnForItemStrategy $cdn_url, $include_dirs, array $excludes, $root_relative, $www_is_optional,
			$https_deactivates_rewriting) {
		$this->blog_url		= $blog_url;
		$this->cdn_url		= $cdn_url;
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
	protected function rewrite_single(&$match) {
		if ($this->exclude_single($match[0])) {
			return $match[0];
		} else {
			$blog_url = $this->blog_url;
			if ($this->www_is_optional && $match[0]{0} != '/' && !strstr($match[0], '//www.')) {
				$blog_url = str_replace('//www.', '//', $blog_url);
			}
			if (!$this->rootrelative || strstr($match[0], $blog_url)) {
				return str_replace($blog_url, $this->cdn_url->get_for($match[0]), $match[0]);
			} else { // obviously $this->rootrelative is true and we got a root-relative link - else that case won't happen
				return $this->cdn_url->get_for($match[0]) . $match[0];
			}
		}
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
		} else {
			return implode('|', array_map('quotemeta', array_map('trim', $input)));
		}
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
		} else {
			return $blog_url;
		}
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

		if ($this->in_unit_test) {
			return $new_content;
		} else {
			return $new_content.'<!-- CDN Linker <https://github.com/wmark/CDN-Linker/tags> active -->';
		}
	}

}

/**
 * The rewrite logic with calls to Wordpress.
 */
class CDNLinksRewriterWordpress extends CDNLinksRewriter
{
	/** Initializes all options calling Wordpress functions. */
	function __construct() {
		$excl_tmp = trim(get_option('ossdl_off_exclude'));
		$excludes = array_map('trim', explode(',', $excl_tmp));

		parent::__construct(
			get_option('siteurl'),
			ossdl_off_cdn_strategy_for(trim(get_option('ossdl_off_cdn_url'))),
			trim(get_option('ossdl_off_include_dirs')),
			$excludes,
			!!trim(get_option('ossdl_off_rootrelative')),
			!!trim(get_option('ossdl_off_www_is_optional')),
			!!trim(get_option('ossdl_off_disable_cdnuris_if_https'))
		);
	}

	/**
	 * Registers the output buffer, if needed.
	 *
	 * This function is called by Wordpress if the plugin was enabled.
	 */
	public function register_as_output_buffer() {
		if ($this->blog_url != trim(get_option('ossdl_off_cdn_url'))) {
			ob_start(array(&$this, 'rewrite'));
		}
	}

}

/**
 * This function actually registers the rewriter.
 * It is called by Wordpress.
 */
function do_ossdl_off_ob_start() {
	$rewriter = new CDNLinksRewriterWordpress();
	$rewriter->register_as_output_buffer();
}
