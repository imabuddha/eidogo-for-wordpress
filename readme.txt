=== EidoGo for WordPress ===
Contributors: fwiffo
Donate link: href="http://www.amazon.com/gp/redirect.html?ie=UTF8&location=http%3A%2F%2Fwww.amazon.com%2Fgp%2Fregistry%2Fwishlist%2F3ETA9NVNRTZ4P&tag=fomygocl-20&linkCode=ur2&camp=1789&creative=390957
Tags: widget, go, weiqi, baduk, sgf, EidoGo
Requires at least: 2.8
Tested up to: 2.9.1
Stable tag: 0.8.8

EidoGo for WordPress makes it easy to embed SGF files in your WordPress-powered
blog with the EidoGo SGF viewer and editor.

== Description ==

EidoGo for WordPress makes it easy to embed SGF files in your
WordPress-powered blog with the [EidoGo SGF viewer and
editor](http://eidogo.com/). If you write a blog about go (baduk, wéiqí, 碁,
etc.) this plugin will let you easily post go diagrams, game records,
problems, joseki dictionaries, etc. PHP 5 is required. Imagemagick is
recommended but not required.

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

EidoGo for WordPress integrates with the WordPress media library thingy, so
you can embed an SGF file exactly the same way as you would an image - the
plugin will insert the necessary tags for you automatically.

You can also embed an SGF file manually, for instance:

    `[sgf sgfUrl="/wp-content/uploads/2009/11/example.sgf"][/sgf]`

By default EidoGo will be inserted with the `"compact"` theme. If your blog
has a wide enough layout, you can use the `"full"` theme which adds nice
things like a variation tree, larger comment box, etc.

    `[sgf theme="full" sgfUrl="/wp-content/uploads/2009/11/example.sgf"][/sgf]`

You can also specify the "compact" theme explicitly. By default, EidoGo
instances will be embedded in an iframe to allow keyboard shortcuts to be used
to navigate the game without stealing them from the user's browser. If you don't
want to use iframes for some reason, you can specify themes of `"compact-inline"`
or `"full-inline"` to embed the EidoGo instance directly.  Keyboard shortcuts
will be disabled, however.

Because SGF is a text based format you can also embed the SGF data directly in
your post without uploading it as a separate file. Just open the SGF file in
something like notepad, then copy and paste the SGF data between `[sgf][/sgf]`
tags in a post, page or comment.

For example, this will embed a blank 19x19 board:

    `[sgf](;GM[1]FF[4]CA[UTF-8]SZ[19])[/sgf]`

= How do I add a problem? =

Specify `[sgf theme="problem"]` to activate EidoGo's problem mode. In problem
mode iframes are not necessary, nor are used. For maximum usefullness I
recommend putting comments at the end of each branch indicating if the branch
is correct or incorrect, otherwise EidoGo gives no indication.

It's possible that EidoGo won't correctly determine which color should be
automatically played (it tries to look for a `PL[]` entry in the SGF file),
but you can override it by specifing, e.g. `[sgf problemColor="B"]`
explicitly.

= Can I embed as a static diagram image? =

Yep, add the parameter `image="true"`. This is also what gets inserted in RSS
feeds since EidoGo obviously won't work there. Note: imagemagick needs to be
installed on the server and in the path for this to work. If the plugin can't
find imagemagick, it will insert static text instead.

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

The included styles will align EidoGo instances with the `alignleft`,
`aligncenter` and `alignright` classes the same as one would expect for
images, but you may want to add a few lines to your theme's stylesheet to
match things like margins with the rest of your layout or to change how
instances are laid out by default when no alignment has been specified.

= I want to customize the appearance of EidoGo and need to load a stylesheet from within the iframe -- can I do that? =

Yes. The plugin will check for the existence of a file called "wp-eidogo.css"
within your theme's directory and will load it as necessary.

= What other stuff can you do? =

Try adding the random problem widget to your sidebar. It'll pull a random
problem from any of the uploaded SGF problem files. Note: it only chooses from
those in your media library (attachements). It won't include those embedded
inline.

There's also a widget that lets you browse problems by category or difficulty,
and one to browse games by category. For this to work nicely, you'll want to
make sure your theme does a good job of handling WordPress's custom taxonomies
(which is what the plugin uses to categorize problems) and can invoke eidogo
to display SGF attachments. Check out the `eidogo-for-wordpress/examples`
directory, and also the next FAQ question.

= Can I make SGF files show up nicely on the attachment page? =

Yep, a convenience function is included; you'll just have to edit your theme's
attachment.php. Right now, the main part of it probably looks something like:

    ...
    <?php if ( wp_attachment_is_image($post->id) ) ... ?>
        ...
    <?php else : ?>
        ...
    <?php endif; ?>
    ...

You'll want to add some code to treat SGF files in a special way.

    ...
    <?php if ( wp_attachment_is_image($post->id) ) ... ?>
        ...
    <?php elseif ($post->post_mime_type == 'application/x-go-sgf') :
        echo wpeidogo_embed_attachment($post);
    ?>
    <?php else : ?>
        ...
    <?php endif; ?>
    ...

By default, `wpeidogo_embed_attachment()` will use the same options as are
saved with the SGF file in your media library, but it also takes parameters...

    wpeidogo_embed_attachment($post, $class, $caption, $href, $theme, $method)

All except the first (the attachment iself) are optional. So if you want to
center the SGF file, pass `'aligncenter'` for `$class`. By default, `$class`
is `null`, `$href` is a direct link to download the SGF file (the other
parameters use the saved values.)

Take a look in the `eidogo-for-wordpress/examples/` directory to see some
files from my theme that handle this.

= How did you get the really compact layout with tiny stones in that screenshot of the random problem widget? =

As of version 0.8.7 of the plugin, this should be handled automatically.

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
3. Uploading SGF files
4. Random problem widget
5. Administration for random problem widget

== Changelog ==

= 0.8.8 =
* Minor bug fixes in EidoGo
* Improved appearance of EidoGo (using black/white text and markers as
  appropriate instead of hard-to-read red)
* Allow loading of custom stylesheet within iframes

= 0.8.7 =
* Showing problem category and difficulty in random problem widget
* Make showing/hiding unpublished problems a global setting
* Integrating tiny widget theme with default plugin styles

= 0.8.6 =
* Style tweaks including styles for 9x9 and 13x13 boards in tiny mode
* Added Problem Category, Problem Difficulty and Game Category using
  wordpress taxonomies
* Widget to browse problems and games by category and difficulty
* Option to exclude unpublished problems from random problem widget
* Select problems for random problem widget by category and/or difficulty

= 0.8.5 =
* Changed `embed_attachment()` to not work by invoking filters, which had
  weird side effects like getting sociable stuck inside the problem widget
* Somewhat improved SGF parser and storing additional SGF metadata
* Putting static images in RSS feed instead of trying to embed javascript
* Adding new sgf2svg script to generate static diagrams
* Added option to embed static images instead of EidoGo instances (imagemagick
  required for this feature!)

= 0.8.4 =
* Using latest EidoGo from github instead of 1.2 release
* Fix bug in EidoGo; replace unreliable `instanceof Array` with new, more
  robust `eidogo.util.isArray`
* Adding `current_user_can()` check when saving SGF metadata
* Storing some additional metadata about SGF files
* Created "Random Go Problem" widget
* Fixed crooked board coordinates in EidoGo

= 0.8.3 =
* sgfUrl is set relative to site root by default
* Make it easy to allow SGF files to show up nicely in your theme's attachment
  page
* Some IE7 and IE8 fixes
* Added styles to indicate right/wrong answers to problems

= 0.8.2 =
* Embedding preferences are now saved with SGF attachments
* Media form for SGF files is nicer

= 0.8.1 =
* Adding SGF icon
* More options for embedding
* Adding aligment option (works like image alignment)

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

= 0.8.3 =
Lots of polish-type enhancements and some Internet Explorer fixes.

= 0.8.7 =
Browse problems by type or difficulty and, enhanced random problem widget,
improved display in RSS feeds.

== Roadmap ==

Some stuff I plan to do in the future:

* Add a configuration screen for tweaking the default EidoGo
  parameters
* Add EidoGo's backend stuff like position search, progressive load, save to
  server, etc.
* Expose more of EidoGo's options to the embed tag syntax
* More browser testing
* Allow on-server editing of uploaded SGF files from admin screen with EidoGo
  (it'll be cool!)
* Pull information from SGF file to set the default title and summary
  information on upload

== Legal ==

Copyright &copy; 2009-2010 Thomas Schumm

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
