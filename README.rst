====================================
CDN Linker - Wordpress Plugin
====================================
:Info: See `github <http://github.com/wmark/CDN-Linker>`_ for the latest source.
:Author: W-Mark Kubacki <wmark@hurrikane.de>
:Tags: CDN,links,cloudfront,simplecdn,media,performance,distribution,accelerator,content,speed,cloud
:min. WP: 2.7
:Tested up to: 3.9

Rewrites links to static files to your own CDN network.

Description
============
Modifies links pointing to ‘wp-content’ and/or ‘wp-includes’ (or whatever you configure) 
by replacing your ‘blog_url’ with a custom one.
Enables you to pull static files, such as images, CSS or JS, from a different host, mirror or CDN.

You could upload your files to S3, CloudFront or a host dedicated to serving static files.
For S3/Cloudfront, see this script_ to help you with uploading/synchronizing your blog files.

.. _script:    http://mark.ossdl.de/2009/09/how-to-copy-your-wordpress-files-to-cloudfront-efficiently/

License
========
Licensed under the Reciprocal Public License, Version 1.5, for Personal Use as defined in 1.11 therein
(http://www.opensource.org/licenses/rpl1.5).

Else, please contact me by email for an individual license.

Installation
=============

1. Setup your CDN: Either configure “origin pull”, “mirror bucket” or upload your static files to a dedicated host.
2. Upload the plugin to your `/wp-content/plugins/` directory.
3. Activate the plugin through the ‘Plugins’ menu in WordPress.
4. Provide the URL, where your static files can be found, under ‘Settings’.

Frequently Asked Questions
===========================

Is it free of charge to use?
  Yes, for personal use. ‘Personal use’ excludes any commercial or commercialized sites or blogs.
  Although ‘personal use’ excludes hosting companies you are free to upload the plugin to your WP installation individually, though,
  as long as your “WP installation” is covered by the term ‘personal use’. ;-)

  You can obtain a generous license for other use-cases for a tip.
  By ‘generous’ I mean that one license will cover all domains serving the same content no matter which TLD
  (myblog.com, myblog.de, myblog.us — all covered) and the such.
  Just drop me an email.

  Contributors will receive a license free of charge.

How to uninstall?
  Either deactivate the plugin or delete the plugin’s directory.

Why another such plugin?
  Most plugins for WP don’t correctly include JS and CSS files by using Wordpress’ functions.
  This results in you not being able to pull static files, such as images, CSS or JS, from a different host, mirror or CDN.

  This plugin does its rewriting on the lowest level possible — PHP itself —
  providing a global work-around.

How does it work?
  After your blog pages have been rendered, but before WP is sending them to the visitor,
  CDN Linker will modify links pointing to `wp-content` and `wp-includes` by
  replacing your blog URL.

Is it compatible to plugin XY?
  Yes, by design it is compatible with all plugins. It hooks into PHP function ob_start_
  and does the string replacement there. No Wordpress function is altered, overwritten or modified in any way.

  Indeed, you could even copy this plugin’s rewriter into any arbitrary PHP software.

Will it work with my CDN?
  Yes, **if it supports “origin pull”** (aka “mirror bucket”, some sort of caching). Or if you upload your files manually.

What about Amazon’s S3?
  Right, S3 doesn’t support “origin pull”. You will have to upload your files by hand.
  I’ve written a script for this, too, so it is just a matter of running it. It is linked on the bottom of my blog post about
  `copying files to S3 <http://mark.ossdl.de/2009/09/how-to-copy-your-wordpress-files-to-cloudfront-efficiently/>`_.

  Amazon’s CloudFront does support origin pull. Just create a “distribution” as usual and set your blog’s URL as “origin”.

So my blog is fully served by a CDN?
  No. Wordpress will still have to run on the host to which your address resolves to.
  “www.myblog.blog” will still have to be “www.myblog.blog”, only your (for example) images will be loaded
  from “cdn.myblog.blog”. Most CDNs do not support fast-changing content or passthrough of HTTP POST requests (→ comments).

There is more than one “CDN Linker”?
  Yes, this is the regular version. There is a “professional” and a “multisite” version, too.

What other plugins do you recommend?
  Now that you can offload all the files such as images, music or CSS, you should serve your blog posts as static files to
  decrease load on your server. I recommend SuperCache-Plus_ as it will maintain, update and create that static files from
  dynamic content for you. The off-linker is compatible.

I discovered a bug!
  If you are using the `lite` version, please upgrade.
  The following applies only to the `regular` version which you can find at Github_:

  Share it with me! The rarer a species, the more interesting. But I will need its habitat, too.
  Therefore, please send me at least one page with the plugin turned off and on, as attachment.
  For that please click at `view source` in your browser and copy the contents to `notepad` and
  send me the two resulting text files.

.. _ob_start:        http://us2.php.net/manual/en/function.ob-start.php
.. _Mark:            http://mark.ossdl.de/
.. _SuperCache-Plus: http://murmatrons.armadillo.homeip.net/features/experimental-eaccelerator-wp-super-cache

Troubleshooting
================

Disqus
  Either uncheck `rewrite root-relative refs` or add `count.js` to `exclude if substring`.

Livefyre, IntenseDebate, and Juvia
  No issues.

__ Mark_