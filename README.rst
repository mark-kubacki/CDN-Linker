====================================
CDN Linker - Wordpress Plugin
====================================
:Info: See `github <http://github.com/wmark/CDN-Linker>`_ for the latest source.
:Author: W-Mark Kubacki <wmark@hurrikane.de>
:Tags: CDN,links,cloudfront,simplecdn,media,performance,distribution,accelerator,content,speed,cloud
:Requires at least: 2.7
:Tested up to: 3.1.2

Rewrites links to static files to your own CDN network.

Description
============
Replaces the blog URL by another for all files under `wp-content` and `wp-includes`.
That way static content can be handled by a CDN by origin pull. (The origin is your blog address.)

You could upload your static files to S3, CloudFront or just any site, too.
For S3/Cloudfront, see this script_ to help you with uploading/synchronizing your blog files.

The **lite version** of the plugin works like the **regular version** but is not supported.
You can get the *regular version* at Github_ which:

* Is free of charge, open source and without any ads.
* Supported. Here is `the bug tracker`__ if you encounter any issues.
* Has more and advanced features.
* Is updated more frequently. See downloads_.
* Quality assurance.

.. _script:    http://mark.ossdl.de/2009/09/how-to-copy-your-wordpress-files-to-cloudfront-efficiently/
.. _Github:    http://github.com/wmark/CDN-Linker
.. _issues:    http://github.com/wmark/CDN-Linker/issues
.. _downloads: http://github.com/wmark/CDN-Linker/downloads
__ issues_

License
========
Licensed under the Reciprocal Public License, Version 1.5, for non-commercial
(http://www.opensource.org/licenses/rpl1.5).

Installation
=============

1. Setup your CDN: Either configure an origin pull, mirror bucket or upload your static files somewhere.
2. Upload the plugin to your `/wp-content/plugins/` directory.
3. Activate the plugin through the 'Plugins' menu in WordPress.
4. Provide the URL, where your static files can be found, under `Settings`.

Frequently Asked Questions
===========================

How to uninstall?
  Either deactivate the plugin or delete the plugin's directory.

Why another such plugin?
  As many WP plugins don't correctly include JS and CSS files most of the current CDN plugins cannot
  rewrite links correctly. They rely on the high-level WP API.

  This plugin does its rewriting on the lowest level possible - PHP itself.

How does it work?
  After your blog pages have been rendered but before sending them to the visitor,
  it will rewrite links pointing to `wp-content` and `wp-includes`. That rewriting will simply
  replace your blog URL with that you have entered in settings screen.

  Thus files are pulled from the other site.

Is it compatible to plugin XY?
  Yes, by design it is compatible to all plugins. It hooks into a PHP function ob_start_
  and there does the string replacement. Therefore, no Wordpress function is altered, overwritten or modified in any way.

  Indeed, you could even copy this plugin's rewriter into any arbitrary PHP software.

Where can I see it in action?
  On my own blog, `Mark's blog`__. See the source code of any page.

Will it work with my CDN?
  Yes, **if it supports origin pull** (aka mirror bucket, some sort of caching). Or if you upload your files manually.

What about Amazon's S3?
  Right, S3 doesn't support origin pull. You will have to upload your files by hand.
  I've written a script for this, too, so it is just a matter of running it. It is linked on the bottom of my blog post about
  `copying files to S3 <http://mark.ossdl.de/2009/09/how-to-copy-your-wordpress-files-to-cloudfront-efficiently/>`_.

What other plugins do you recommend?
  Now that you can offload all the files such as images, music or CSS, you should serve your blog posts as static files to
  decrease load on your server. I recommend SuperCache-Plus_ as it will maintain, update and create that static files from
  dynamic content for you. The off-linker is compatible.

Alternatives?
  The experts at `Arcostream` currently develop (or have it ready by now) a plugin which enables you to set up a CDN at their's
  with one click or two. Without any configuration, everything is automatic. Please google their licensed flavour of this plugin. ;-)

I discovered a bug!
  If you are using the `lite` version, please upgrade.
  The following applies only to the `regular` version which you can find at Github_:

  Share it with me! The rarer a species, the more interesting. But I will need its habitat, too.
  Therefore, please send me at least one page with the plugin turned off and on, as attachment.

.. _ob_start:        http://us2.php.net/manual/en/function.ob-start.php
.. _Mark:            http://mark.ossdl.de/
.. _SuperCache-Plus: http://murmatrons.armadillo.homeip.net/features/experimental-eaccelerator-wp-super-cache
__ Mark_