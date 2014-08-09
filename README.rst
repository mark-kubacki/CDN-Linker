====================================
CDN Linker - Wordpress Plugin
====================================
.. image:: https://drone.io/github.com/wmark/CDN-Linker/status.png
   :target: https://drone.io/github.com/wmark/CDN-Linker/latest
.. image:: https://coveralls.io/repos/wmark/CDN-Linker/badge.png?branch=master
   :target: https://coveralls.io/r/wmark/CDN-Linker?branch=master

*Info*
  See `github <http://github.com/wmark/CDN-Linker>`_ for the latest source.

*Author*
  W. Mark Kubacki <wmark@hurrikane.de>

*Tags*
  CDN,links,cloudfront,simplecdn,media,performance,distribution,accelerator,content,speed,cloud

*Requirements*
  Wordpress 2.7 – 3.9.1, PHP >= 5.3 or HHVM >= 3.0.0

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

Support
========

Before asking for support please upgrade to the latest non-beta version of CDN Linker!

* If something doesn't work and you don't know why, please ask on StackExchange_.
  Feel free to email me a link to the question.
* … Only if you know the cause, down to the line, function or concept, please open a ticket on Github_.
  Please add to it a list of your installed plugins and the URL of your blog and the CDN host.
  You can provide the latter URLs by separate email, if you are not comfortable with publishing it.
* I will happily discuss your feature requests and ideas for further development if you send me an email.

Support you expect to receive free of charge is never urgent.

.. _StackExchange:  http://wordpress.stackexchange.com/questions/tagged/plugins
.. _Github:         https://github.com/wmark/CDN-Linker/issues

Frequently Asked Questions
===========================

Is it any good?
  Yes.

Is it free of charge to use?
  Yes, for personal use. Not for commercial use or hosting companies.

  ‘Personal use’ excludes any commercial or commercialized sites or blogs.
  Although ‘personal use’ excludes hosting companies you are free to upload the plugin to your WP installation individually, though,
  as long as your “WP installation” is covered by the term ‘personal use’. ;-)

  You can obtain a generous license for other use-cases for a tip.
  By ‘generous’ I mean that one license will cover all domains serving the same content no matter which TLD
  (myblog.com, myblog.de, myblog.us — all covered) and the such.
  Contact me by email with a list of the relevant domain names.

  Contributors will receive a gratis license.

How do I uninstall it?
  Go to Wordpress' plugin listing and click on “delete”.

Will it work with my CDN?
  Yes, **if it supports “origin pull”** (aka “mirror bucket”, some sort of caching). Or if you upload your files manually.

What about Amazon’s S3?
  Right, S3 doesn’t support “origin pull”. You will have to upload your files by hand.
  I’ve written a script for this, too, so it is just a matter of running it. It is linked on the bottom of my blog post about
  `copying files to S3 <http://mark.ossdl.de/2009/09/how-to-copy-your-wordpress-files-to-cloudfront-efficiently/>`_.

  Amazon’s CloudFront does support origin pull. Just create a “distribution” as usual and set your blog’s URL as “origin”.

So, my blog is fully served by a CDN?
  No. Wordpress will still have to run on the original host.
  “www.myblog.blog” will still have to be “www.myblog.blog”, only your (for example) images will be loaded
  from “cdn.myblog.blog”. Most CDNs do not support fast-changing content or passthrough of HTTP POST requests (→ comments).

There is more than one “CDN Linker”?
  Yes. This is the regular version. A “professional” version exists, too, and is available to paying customers.
  And there are forks for CDN providers.

What other plugins do you recommend?
  Now that you can offload all the files such as images, music or CSS, you should serve your blog posts as static files to
  decrease load on your server. I recommend SuperCache-Plus_ as it will maintain, update and create that static files from
  dynamic content for you. The CDN Linker is compatible.

.. _Mark:            http://mark.ossdl.de/
.. _SuperCache-Plus: http://murmatrons.armadillo.homeip.net/features/experimental-eaccelerator-wp-super-cache

Troubleshooting
================

Disqus
  Either uncheck `rewrite root-relative refs` or add `count.js` and `embed.js` to `exclude if substring`.

Livefyre, IntenseDebate, and Juvia
  No issues.

HHVM
  Please note issue `1854 <//github.com/facebook/hhvm/issues/1854>`_.
  For CDN Linker’s *post-processing* feature you will have to install `hhvm-zmq <//github.com/duxet/hhvm-zmq>`_
  (monitor issue `1214 <//github.com/facebook/hhvm/issues/1214>`_) or resort to Redis.

__ Mark_