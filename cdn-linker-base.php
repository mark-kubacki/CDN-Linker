<?php
/*
Data flow of this plugin is as follows:
Final (raw) HTML --> intercepted in PHP's ob_start() --> ossdl_off_filter() --> PHP --> HTTP server

Control flow is this (begin reading with ossdl_off_filter()):
ossdl_off_additional_directories <-- ossdl_off_filter --> ossdl_off_rewriter --> ossdl_off_exclude_match

As this plugin hooks into the PHP output buffer "ossdl_off_filter" cannot have any parameters (beyond the obvious one, that is.)
Therefore these global variables are used:
 - $ossdl_off_blog_url		String: the blog's URL ( get_option('siteurl') )
 - $ossdl_off_cdn_url		String: URL of the CDN domain
 - $ossdl_off_include_dirs	String: directories to include in static file matching, comma-delimited list
 - $arr_of_excludes		Array: strings which indicate that a given element should not be rewritten (i.e., ".php")

 - $ossdl_off_rootrelative	Boolean: if true, modifies root-relative links (default is false)
*/

/**
 * Determines whether to exclude a match.
 *
 * @param String $match URI to examine
 * @param Array $excludes array of "badwords"
 * @return Boolean true if to exclude given match from rewriting
 */
function ossdl_off_exclude_match(&$match, &$excludes) {
	foreach ($excludes as $badword) {
		if (stristr($match, $badword) != false) {
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
function ossdl_off_rewriter(&$match) {
	global $ossdl_off_blog_url, $ossdl_off_cdn_url, $arr_of_excludes, $ossdl_off_rootrelative;
	if (ossdl_off_exclude_match($match[0], $arr_of_excludes)) {
		return $match[0];
	} else {
		if (!$ossdl_off_rootrelative || strstr($match[0], $ossdl_off_blog_url)) {
			return str_replace($ossdl_off_blog_url, $ossdl_off_cdn_url, $match[0]);
		} else { // obviously $ossdl_off_rootrelative is true aand we got a root-relative link - else that case won't happen
			return $ossdl_off_cdn_url . $match[0];
		}
	}
}

/**
 * Creates a regexp compatible pattern from the directories to be included in matching.
 *
 * @return String regexp pattern for those directories, or empty if none are given
 */
function ossdl_off_additional_directories() {
	global $ossdl_off_include_dirs;
	$input = explode(',', $ossdl_off_include_dirs);
	if ($ossdl_off_include_dirs == '' || count($input) < 1) {
		return 'wp\-content|wp\-includes';
	} else {
		return implode('|', array_map('quotemeta', array_map('trim', $input)));
	}
}

/**
 * Output filter which runs the actual plugin logic.
 *
 * @param String $content the raw HTML of the page from Wordpress, meant to be returned to the requester but intercepted here
 * @return String modified HTML with replaced links - will be served by the HTTP server to the requester
 */
function ossdl_off_filter(&$content) {
	global $ossdl_off_blog_url, $ossdl_off_cdn_url, $ossdl_off_rootrelative;
	if ($ossdl_off_blog_url == $ossdl_off_cdn_url) { // no rewrite needed
		return $content;
	} else {
		$dirs = ossdl_off_additional_directories();
		$regex = '#(?<=[(\"\'])';
		$regex .= $ossdl_off_rootrelative
			? ('(?:'.quotemeta($ossdl_off_blog_url).')?')
			: quotemeta($ossdl_off_blog_url);
		$regex .= '/(?:((?:'.$dirs.')[^\"\')]+)|([^/\"\']+\.[^/\"\')]+))(?=[\"\')])#';
		return preg_replace_callback($regex, 'ossdl_off_rewriter', $content);
	}
}

/**
 * Registers ossdl_off_filter as output buffer, if needed.
 *
 * This function is called by Wordpress if the plugin was enabled.
 */
function do_ossdl_off_ob_start() {
	global $ossdl_off_blog_url, $ossdl_off_cdn_url;
	if ($ossdl_off_blog_url != $ossdl_off_cdn_url) {
		ob_start('ossdl_off_filter');
	}
}
