=== EidoGo for WordPress ===
Contributors: fwiffo
Tags: widget, go, weiqi, baduk
Requires at least: 2.5 (maybe?)
Tested up to: 2.8.6
Stable tag: 0.8

EidoGo for WordPress makes it easy to embed SGF files in your WordPress-powered
blog with the EidoGo SGF viewer and editor.

== Description ==

EidoGo for WordPress makes it easy to embed SGF files in your
WordPress-powered blog with the [EidoGo SGF viewer and
editor](http://eidogo.com/). If you write a blog about go (baduk, wéiqí, 碁,
etc.) this plugin will let you easily post go diagrams, game records,
problems, joseki dictionaries, etc.

== Installation ==

1. Unzip the archive into your `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress

That's it! No additional hooks or configuration is required. You can start
embedding SGF files immediately.

The included stylesheet should provide a reasonable layout for your EidoGo
instances. However, you may want to add some styles to your theme to tweak the
layout. See the FAQ section for details.

== Frequently Asked Questions ==

= So, how do I embed an SGF File? =

SGF is a text based format, so it's easy. Just open the SGF file in something
like notepad, then copy and paste the SGF data between `[sgf][/sgf]` tags in a
post, page or comment.

For example, this will embed a blank 19x19 board:

    `[sgf](;GM[1]FF[4]CA[UTF-8]SZ[19])[/sgf]`

You can also upload the SGF file to your blog, and then embed it by URL:

    `[sgf sgfUrl="/wp-content/uploads/2009/11/example.sgf"][/sgf]`

Those examples embed EidoGo with the `"compact"` theme. If your blog has a wide
enough layout, you can use the `"full"` theme which adds nice things like a
variation tree, larger comment box, etc.

    `[sgf theme="full" sgfUrl="/wp-content/uploads/2009/11/example.sgf"][/sgf]`

You can also specify the "compact" theme explicitly. By default, EidoGo
instances will be embedded in an iframe to allow keyboard shortcuts to be used
to navigate the game without stealing them from the user's browser. If you don't
want to use iframes for some reason, you can specify themes of `"compact-inline"`
or `"full-inline"` to embed the EidoGo instance directly.  Keyboard shortcuts
will be disabled, however.

= How do I add a problem? =

Specify `[sgf theme="problem"]` to activate EidoGo's problem mode. In problem
mode iframes are not necessary, nor are used. For maximum usefullness I
recommend putting comments at the end of each branch indicating if the branch
is correct or incorrect, otherwise EidoGo gives no indication.

It's possible that EidoGo won't correctly determine which color should be
automatically played (it tries to look for a `PL[]` entry in the SGF file),
but you can override it by specifing, e.g. [`sgf problemColor="B"]` explicitly.

= What other parameters are there? =

You can specify a caption for the EidoGo instance with `caption="Caption"`.
You can link the caption to some url with `href="http://www.example.com/"` if
you, for example, want to link to some discussion of the game.

You can specify the `loadPath` parameter for EidoGo with `loadPath="something"`
if you understand how to use that. As a shortcut for jumping to a move number
in the main branch, you can specify `moveNumber="72"` or some such.

You can specify `class="className"` if you want to add a CSS class to the
containing element (useful if you want to, for example, align a problem to the
left of the screen instead of the right or something).

= Can I see an example in action? =

[Yep.](http://www.fortmyersgo.org/eidogo-for-wordpress/)

= I want to tweak the layout within my theme; what's the markup look like? =

It's pretty simple:

    <div class="wp-eidogo wp-eidogo-[theme]">
        <div id="player-container-[id]" class="player-container">
            ...iframe or EidoGo instance...
        </div>
        <script type="text/javascript"><!--
            ...some javascript to invoke EidoGo...
        --></script>
        <p class="wp-caption-text">[caption]</p>
    </div>

The default styles will center full and compact EidoGo instances and will
float problems to the right, but you'll probably want to add a couple lines to
your theme's stylesheet to match things like margins with the rest of your
layout.

= I'm getting an error message in Internet Explorer 6 (or older) telling me to upgrade. =

Yeah, the plugin doesn't work with IE 6 or older. EidoGo can be made to work
with IE 6, but it's not something I'm going to waste *my* time on. If you
really need IE 6 support, I can e-mail you more details on the exact nature of
the problem and will accept patches, but you should really just upgrade to a
real web browser.

I intend to support current or reasonably recent versions of Firefox, Google
Chrome, Opera, Safari and Internet Explorer (which means pretty much any Gecko
or KHTML browsers are probably covered). I've tested the plugin with various
recent versions of Firefox, Chrome, IE 7 and IE 8 so far.

= I'm getting an error message and I have PHP 4 =

Right now, PHP 5 is required and I don't have a PHP 4 setup to test with.
Sorry. If you feel like writing a patch, I'll take a look.

= I'm having some other problem =

Oh, maybe I screwed up. [Send me an e-mail](http://www.fortmyersgo.org/about/).

== Screenshots ==

1. EidoGo embedded with the "full" theme
2. A couple tsumego

== Changelog ==

= 0.8 =
* Preparing strings for i18n
* Closer conformance to official WordPress coding style
* Adding SGF embedding support to media library

= 0.7.1 =
* Adding copyright and license information

= 0.7 =
* Initial public release.

== Upgrade Notice ==

= 0.7 =
This is the first public release.

= 0.8 =
SGF support is now integrated with the media library so adding SGF files is
now much easier.

== Roadmap ==

Some stuff I plan to do in the future:

* Add a configuration screen for tweaking the default EidoGo
  parameters
* Allow custom EidoGo themes and loading of custom stylesheets inside
  iframes
* Add EidoGo's backend stuff like position search, progressive load, save to
  server, etc.
* Expose more of EidoGo's options to the embed tag syntax
* More browser testing
* Error checking for old versions of PHP or Wordpress
* Allow on-server editing of uploaded SGF files from admin screen with EidoGo
  (it'll be so cool!)

== Legal ==

Copyright &copy; 2009 Thomas Schumm

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU Affero General Public License as published by the Free
Software Foundation, either version 3 of the License, or (at your option) any
later version.

This program is distributed in the hope that it will be useful, but WITHOUT
ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more
details.

You should have received a copy of the GNU Affero General Public License along
with this program. If not, see <http://www.gnu.org/licenses/>.
