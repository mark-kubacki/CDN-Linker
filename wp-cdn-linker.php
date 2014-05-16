<?php
/*
Plugin Name: CDN Linker
Plugin URI: https://github.com/wmark/CDN-Linker
Description: Modifies links pointing to <code>wp-content</code> and/or <code>wp-includes</code> (or whatever you configure) by replacing your ‘blog_url’ with a custom one. Enables you to pull static files, such as images, CSS or JS, from a different host, mirror or CDN.
Version: 1.4.2
Author: W-Mark Kubacki
Author URI: http://mark.ossdl.de/
License: RPL 1.5, for Personal Use
*/

namespace blitznote\wp;

if ( @include_once('cdn-linker-base.php') ) {
	add_action('template_redirect', 'blitznote\wp\cdn\register_as_output_buffer_handler');
}

/********** WordPress Administrative ********/

function on_plugin_activation() {
	add_option('ossdl_off_cdn_url', get_option('siteurl'));
	add_option('ossdl_off_include_dirs', 'wp-content,wp-includes');
	add_option('ossdl_off_exclude', '.php');
	add_option('ossdl_off_rootrelative', '');
	add_option('ossdl_off_www_is_optional', '');
	add_option('ossdl_off_disable_cdnuris_if_https', '1');
}
register_activation_hook( __FILE__, 'blitznote\wp\on_plugin_activation');

/********** WordPress Interface ********/
add_action('admin_menu', 'blitznote\wp\add_to_admin_sidebar_menu');
add_filter('plugin_action_links', 'blitznote\wp\add_to_plugin_actions', 10, 2);

function add_to_admin_sidebar_menu() {
	add_options_page('CDN Linker', 'CDN Linker', 'manage_options', __FILE__, 'blitznote\wp\on_handle_admin_page');
}

function add_to_plugin_actions($links, $file) {
	static $this_plugin;
	if (!$this_plugin) {
		$this_plugin = plugin_basename(__FILE__);
	}

	if ($file == $this_plugin && is_plugin_active($file)) {
		$settings_link = '<a href="options-general.php?page='. $this_plugin .'">' . __('Settings') . '</a>';
		array_unshift($links, $settings_link); // before other links
	}
	return $links;
}

function on_handle_admin_page() {
	if (!empty($_POST) && check_admin_referer('save-options', 'ossdl-nonce')) {
		// Yes, this URL is not subject to esc_url() or esc_url_raw() (please don’t report it), because:
		// 1) Only the owner of the installation can change this setting — if he/she wants something odd here (javascript:…; HTML fragments), we allow for it!
		update_option('ossdl_off_cdn_url', $_POST['ossdl_off_cdn_url']);
		update_option('ossdl_off_include_dirs', $_POST['ossdl_off_include_dirs'] == '' ? 'wp-content,wp-includes' : $_POST['ossdl_off_include_dirs']);
		if(strstr($_POST['ossdl_off_exclude'], '.php')) {
			update_option('ossdl_off_exclude', $_POST['ossdl_off_exclude']);
		} else {
			// this forces '.php' being part of the list
			$excludes = array_map('trim', explode(',', $_POST['ossdl_off_exclude']));
			$excludes[] = '.php';
			update_option('ossdl_off_exclude', implode(',', $excludes));
		}
		// checkboxes which are not checked are sometimes not sent, hence the additional calls to isset($_POST(…))
		$ossdl_off_rootrelative = isset($_POST['ossdl_off_rootrelative']) ? !!$_POST['ossdl_off_rootrelative'] : false;
		$ossdl_off_www_is_optional = isset($_POST['ossdl_off_www_is_optional']) ? !!$_POST['ossdl_off_www_is_optional'] : false;
		$ossdl_off_disable_cdnuris_if_https = isset($_POST['ossdl_off_disable_cdnuris_if_https']) ? !!$_POST['ossdl_off_disable_cdnuris_if_https'] : false;
		update_option('ossdl_off_rootrelative', $ossdl_off_rootrelative);
		update_option('ossdl_off_www_is_optional', $ossdl_off_www_is_optional);
		update_option('ossdl_off_disable_cdnuris_if_https', $ossdl_off_disable_cdnuris_if_https);
	}

	$example_file_rr = '/wp-includes/images/rss.png';
	if (get_option('ossdl_off_cdn_url') == get_option('siteurl')) {
		$example_cdn_uri = str_replace('http://', 'http://cdn.', str_replace('www.', '', get_option('siteurl')))
				. $example_file_rr;
	} else {
		$example_uri = get_option('siteurl') . $example_file_rr;
		$get_target_url = cdn\target_url_strategy_for(trim(get_option('ossdl_off_cdn_url')));
		$example_cdn_uri = $get_target_url->for_source($example_uri) . $example_file_rr;
	}

	?><div class="wrap" xml:lang="en">
		<h2>CDN Linker</h2>
		<p>Many Wordpress plugins misbehave when linking to their JS or CSS files by not calling the appropriate functions from Wordpress.
		This results in you not being able to pull static files, such as images, CSS or JS, from a different host, mirror or CDN.<p>
		<p>Therefore this plugin modifies links pointing to <code>wp-content</code> and/or <code>wp-includes</code> by replacing your <code>blog_url</code> with the URL you provide below.
		You will be able to lessen the load on machines running your WP installation,
		and utilize CDNs providing <q>origin pull</q> or hosts dedicated to serving static files.</p>
		<p><strong style="color: red">WARNING:</strong> Test some static urls e.&thinsp;g., <code><a href="<?php echo($example_cdn_uri); ?>" target="_blank"><?php echo($example_cdn_uri); ?></a></code> to ensure your CDN service is fully working before saving changes.</p>
		<form method="post" action="">
		<table class="form-table"><tbody>
			<tr valign="top">
				<th scope="row"><label for="ossdl_off_cdn_url">CDN URL</label></th>
				<td>
					<input type="text" name="ossdl_off_cdn_url" value="<?php echo(esc_attr(get_option('ossdl_off_cdn_url'))); ?>" size="64" class="regular-text code" />
					<span class="description">The new URL to be used in place of <?php echo(get_option('siteurl')); ?> for rewriting. No trailing <code>/</code> please. E.&thinsp;g. <code><?php echo($example_cdn_uri); ?></code>.
					&mdash;
					You can use <code>%4%</code> (a number between 1 and 9, surrounded by percent signs) to use that many hostname variations.
					Should be between 2 and 4, with 4 being beyond an universal optimum.
					If you are going to use 3 or more then make sure they have different IPs or
					<a href="http://statichtml.com/2010/use-unique-ips-for-sharded-asset-hosts.html">some routers will block requests</a> to them.
					</span>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="ossdl_off_rootrelative">rewrite root-relative refs</label></th>
				<td>
					<input type="checkbox" name="ossdl_off_rootrelative" <?php echo(!!get_option('ossdl_off_rootrelative') ? 'checked="1" ' : '') ?>value="true" />
					<span class="description">Check this if you want to have links like <code><em>/</em>wp-content/xyz.png</code> rewritten - i.&thinsp;e. without your blog’s domain as prefix.</span>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="ossdl_off_www_is_optional">subdomain <q>www</q> is optional</label></th>
				<td>
					<input type="checkbox" name="ossdl_off_www_is_optional" <?php echo(!!get_option('ossdl_off_www_is_optional') ? 'checked="1" ' : '') ?>value="true" />
					<span class="description">Check this if your blog can be accessed without a <q>www</q> in front of its domain name. If unchecked links without a <q>www</q> won’t be modified.
					Safe to say <q>yes</q> here.</span>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="ossdl_off_disable_cdnuris_if_https">skip CDN if HTTPS</label></th>
				<td>
					<input type="checkbox" name="ossdl_off_disable_cdnuris_if_https" <?php echo(!!get_option('ossdl_off_disable_cdnuris_if_https') ? 'checked="1" ' : '') ?>value="true" />
					<span class="description">Skips linking to your CDN if the page has been visited using HTTPS. This option will not affect caching.
					If in doubt say <q>yes</q>. Say <q>no</q> if your CDN supports HTTPS.</span>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="ossdl_off_include_dirs">include dirs</label></th>
				<td>
					<input type="text" name="ossdl_off_include_dirs" value="<?php echo(esc_attr(get_option('ossdl_off_include_dirs'))); ?>" size="64" class="regular-text code" />
					<span class="description">Directories to include in static file matching.
					Use a comma as delimiter. Default is <code>wp-content, wp-includes</code>, which will be enforced if this field is left empty.</span>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="ossdl_off_exclude">exclude if substring</label></th>
				<td>
					<input type="text" name="ossdl_off_exclude" value="<?php echo(esc_attr(get_option('ossdl_off_exclude'))); ?>" size="64" class="regular-text code" />
					<span class="description">Excludes something from being rewritten if one of the above strings is found in the match.
					Use a comma as delimiter. E.&thinsp;g. <code>.php, .flv, .do</code>.
					Always include <code>.php</code>, which is the default. (Will be set to <code>.php</code> if left empty.)</span>
				</td>
			</tr>
		</tbody></table>
		<?php wp_nonce_field('save-options', 'ossdl-nonce'); ?>
		<p class="submit"><input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" /></p>
		</form>
	</div><?php
}
