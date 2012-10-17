<?php
require_once('redisent.php');

/**
 * Constitutes a caching engine.
 *
 * A cache-hit results in PHP and Wordpress not being run at all.
 * On a cache-miss a new entry is written to cache;
 * and new posts, comments and other visible changes result in something being removed from cache.
 */
abstract class ACacheStrategy
{
	/**
	 * Returns true if the page is intended for the "general audience"; i.e. free of any special markup.
	 *
	 * @return Boolean true if the page is without links to 'edit', 'delete' and the such
	 */
	public function cache_this_request() {
		return	($_SERVER['REQUEST_METHOD'] == 'GET' || $_SERVER['REQUEST_METHOD'] == 'HEAD')
			&& !$_COOKIE['comment_author_' . COOKIEHASH]
			&& !is_user_logged_in();
	}

	/**
	 * Deterministically generates an unique key for the current request.
	 *
	 * This will be some serialized form of SERVER_NAME, REQUEST_URI and perhaps other variables.
	 * The key-structure is expected to correspond with permalink structure or else
	 * removing/pruning changed pages won't reliably work.
	 *
	 * @return String to be used as key for the content to be cached
	 */
	public function generate_key() {
		$key = $_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'];
		return $key;
	}

	/**
	 * Writes the content to the cache.
	 */
	abstract public function set($key, $content);

	/**
	 * Removes entries for the given keys.
	 * Stars * are used as wildcards.
	 *
	 * Please note that any removal might not be effective immediately.
	 *
	 * @param array $keys array of keys to be removed
	 */
	abstract public function remove(array $keys);

	/**
	 * Removes everything from cache.
	 *
	 * Please note that any removal might not be effective immediately.
	 */
	abstract public function empty_cache();

}

/**
 * Redis is an memory-only data store similar to Memcached.
 */
class RedisCache extends ACacheStrategy
{
	var $redis		= null;

	function __construct() {
		$this->redis = new redisent\Redis('localhost'); // XXX
	}

	public function set($key, $content) {
		$this->redis->pipeline()
			->set($key, $content)
			->expire($key, 14400) // XXX
			->uncork();
	}

	protected function expand_wildcards(array $keys) {
		$new_keys = array();
		$this->redis->pipeline();
		foreach($keys as $key) {
			if(strstr($key, '*')) {
				$this->redis->keys($key); // The results will be fetched…
			} else {
				array_push($new_keys, $key);
			}
		}
		// collects all leaves
		array_walk_recursive($this->redis->uncork(), // … here!
			function(&$value, &$idx) use (&$new_keys) {array_push($new_keys, $value);});
		return array_unique($new_keys);
	}

	public function remove(array $keys) {
		$keys_to_be_removed = $this->expand_wildcards($keys);
		$this->redis->pipeline();
		call_user_func_array(array($this->redis, "del"), $keys_to_be_removed);
		$this->redis->uncork();
	}

	public function empty_cache() {
		if($_SERVER['SERVER_NAME']) {
			$this->remove(array($_SERVER['SERVER_NAME'].'/*'));
		}
	}

}

/**
 * Null-object. Blackhole. Does nothing.
 */
class NullCache extends ACacheStrategy
{
	public function generate_key() {
		return '';
	}

	public function set($key, $content) {
		; // do nothing
	}

	public function remove(array $keys) {
		; // do nothing
	}

	public function empty_cache() {
		; // do nothing
	}

}

/**
 * Factory for ICacheStrategy.
 */
function ossdl_get_cache_strategy_for($connection_string) {
	if(strstr($connection_string, 'redis')) {
		return new RedisCache();
	} else {
		return new NullCache();
	}
}

/****************************************************************************
 ** Now come helper-functions.
 **/

/**
 * Removes any scheme such as http:// from the given string.
 *
 * @param String $str URI
 * @return String URI without scheme
 */
function ossdl_cache_remove_scheme($str) {
	if(strstr($str, '://')) {
		$arr = explode('://', $str, 2);
		return $arr[1]; // http://myblog/sth -> myblog/sth
	} else {
		return $str;
	}
}

/**
 * Conditional array_push.
 */
function ossdl_cache_array_push(&$arr, $str, $append = null) {
	if($str && !is_null($str) && $str != '') {
		array_push($arr, !!$append ? ossdl_cache_remove_scheme($str).$append : ossdl_cache_remove_scheme($str));
	}
}

/**
 * Constructs an array of URLs (w/o scheme) of all objects which are affected by content or existence of a given post.
 *
 * XXX: all feed types
 */
function ossdl_cache_get_affected_by($post_id) {
	$what = array();
	$post = &get_post($post_id);

	// by URL
	$permalink = get_permalink($post_id);
	if($permalink) {
		$ltr = '';
		foreach(explode('/', ossdl_cache_remove_scheme($permalink)) as $fragment) { // first one is the host!
			if(strlen($fragment) < 1) { // usually at the end
				continue;
			}
			if(strlen($ltr) < 1) {
				$ltr = $fragment;
			} else {
				$ltr .= '/'.$fragment;
			}
			array_push($what, $ltr.'/');
			array_push($what, $ltr.'/page/*');
			array_push($what, $ltr.'/feed/');
		}
	}

	// feed and author feed
	ossdl_cache_array_push($what, get_author_feed_link($post->post_author));
	ossdl_cache_array_push($what, get_post_comments_feed_link($post_id));

	// other
	foreach(get_ancestors($post_id) as $ancestor_id) {
		ossdl_cache_array_push($what, get_permalink($ancestor_id));
	}

	foreach(wp_get_post_categories($post_id) as $category_id) {
		ossdl_cache_array_push($what, get_category_link($category_id));
		ossdl_cache_array_push($what, get_category_feed_link($category_id));
	}

	foreach(wp_get_post_tags($post_id) as $tag_id) {
		ossdl_cache_array_push($what, get_tag_link($tag_id), '*');
		// ossdl_cache_array_push($what, get_tag_link($tag_id)); // corresponding function does not exist!
	}

	foreach(wp_get_post_terms($post_id) as $term_id) {
		ossdl_cache_array_push($what, get_term_link($term_id), '*');
		// ossdl_cache_array_push($what, get_term_link($term_id)); // corresponding function does not exist!
	}

	return array_unique($what);
}

/****************************************************************************
 ** Now come wrapper-functinos for Wordpress actions.
 **/

/**
 * Removes everything that might have changed along with the post with the given ID.
 *
 * For example:
 * - the actual post
 * - archives for its day, week, month, year
 * - front page
 * - feeds
 * - archives of the post's category and tags
 *
 * Pruning is done the naive way. Otherwise we would've to track every link on every page
 * and match those links to pages. Furthermore we would need to estimate for every page
 * how it affects other pages if an entry disappears.
 *
 * @param post_id Integer provided by Wordpress
 */
function ossdl_cache_remove_by_postId($post_id) {
	$cache = ossdl_get_cache_strategy_for('redis'); // XXX
	$cache->remove(ossdl_cache_get_affected_by($post_id));
}

/**
 * Removes the cache entry of the corresponding page.
 *
 * Adapted from {@link http://wpscp.trac.armadillo.homeip.net/browser/branches/memcached-alt/wp-cache-phase2.php}.
 */
function ossdl_cache_remove_by_commentId($comment_id) {
	$comment = get_comment($comment_id, ARRAY_A);
	$postid = $comment['comment_post_ID'];

	if( !preg_match('/wp-admin\//', $_SERVER['REQUEST_URI']) ) {
		if($comment['comment_approved'] == 'spam' || $comment['comment_approved'] == '0') {
			; // do nothing
		} else {
			if($postid > 0) {
				ossdl_cache_remove_by_postId($postid);
			}
		}
	} elseif($postid > 0) {
		ossdl_cache_remove_by_postId($postid);
	} else {
		ossdl_cache_clean();
	}
}

/**
 * Figures out the relevant post_id(s) and removes some or all pages from cache.
 *
 * Copied from {@link http://wpscp.trac.armadillo.homeip.net/browser/branches/memcached-alt/wp-cache-phase2.php}.
 */
function ossdl_cache_clean() {
	global $posts, $comment_post_ID, $post_ID;

	if ($post_ID > 0 ) ossdl_cache_remove_by_postId($post_ID);
	if ($comment_post_ID > 0 ) ossdl_cache_remove_by_postId($comment_post_ID);
	if (is_single() || is_page()) ossdl_cache_remove_by_postId($posts[0]->ID);
	if (isset( $_GET[ 'p' ] ) && $_GET['p'] > 0) ossdl_cache_remove_by_postId($_GET['p']);
	if (isset( $_POST[ 'p' ] ) && $_POST['p'] > 0) ossdl_cache_remove_by_postId($_POST['p']);
}

/**
 * Wrapper to cache->empty_cache.
 */
function ossdl_cache_reset() {
	$cache = ossdl_get_cache_strategy_for('redis'); // XXX
	$cache->empty_cache();
}
