=== Custom Pagination Permalinks ===
Contributors: blogestudio, pauiglesias
Tags: pagination, paginated, paging, permalinks, custom, customization, seo, url, urls, custom url, custom urls
Requires at least: 3.3.2
Tested up to: 4.3
Stable tag: 1.0
License: GPLv2 or later

Custom listing pagination URLs instead default WordPress permalinks like "[..]/page/[number]/"

== Description ==

If you want to customize pagination URLs you can see that there are no options to change the URL suffix "[..]/page/[number]/" that WordPress implements in paging context: from home page, in category or tag navigation, search results pages, etc.

This plugin allows you to define a new URL suffix to replace the usual "[..]/page/[number]/" and define custom pagination URLs.

To do this make sure that you have activated the pretty permalinks options of WordPress under menu Settings > Permalinks.

About the previous URLs, this plugin does automatically redirects from old classic URLs to the defined new ones.

Also there is another feature where you can indicate to search engines that the current page is part of a listing. This option adds the tags &lt;link&gt; with attributes rel="prev" and/or rel="next" into the head section.

== Installation ==

1. Unzip and upload custom-pagination-permalinks folder to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Go to menú Settings > Custom Pagination Permalinks to configure and activate the custom permalinks

== Frequently Asked Questions ==

= This plugin generates a pagination bar? =

No, this plugin only works with URLs and it's independent of navigation display, you can use any of these plugins that display a navigation links items and should not be conflicts.

= It works with paginated/splitted posts or paged comments? =

No, this plugin only replaces the URL suffixes in posts listings, it doesn't change anything in single post contexts.

= Why change the default URL suffix used by WordPress? =

For SEO purposes per example, defining another suffix for pagination URLs with only one level of directories like [..]/page-[number].html instead of [..]/page/[number]/.

= This plugin changes the canonical URL? =

Yes. By default WordPress doesn't add the tag &lt;link rel="canonical"..&gt; in posts listings, but some plugins do it. For the moment we only added support to All in One SEO Park plugin.

== Screenshots ==

1. Custom Pagination Permalinks administration options.

== Changelog ==

= 1.0 =
Release Date: April 10th, 2015

* First and tested released until WordPress 4.2
* Tested code from WordPress 3.3.2 version.

== Upgrade Notice ==

= 1.0 =
Initial Release.