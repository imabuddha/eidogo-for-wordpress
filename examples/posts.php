<?php while (have_posts()) : the_post(); ?>

    <div class="post" id="post-<?php the_ID(); ?>">

    <div class="title">
    <h2><a href="<?php the_permalink() ?>" rel="bookmark" title="Permanent Link to <?php the_title(); ?>"><?php the_title(); ?></a></h2>
    <?php if (!$quell_postinfo) { ?>
        <p class="date"><span class="author">Posted by <?php the_author(); ?></span> <span class="clock"> on <?php the_time('l, F j, Y'); ?></span></p>
    <?php } ?>
    </div>

    <div class="entry">
        <?php if ($post->post_type == 'attachment' && $post->post_mime_type == 'application/x-go-sgf') {

            $meta = get_post_custom($post->ID);
            $theme = 'compact';
            if ($meta['_wpeidogo_theme'][0] == 'problem')
                $theme = null;
            echo wpeidogo_embed_attachment($post, 'aligncenter', 'Download SGF', null, $theme);

        } ?>

        <?php the_content('Read the rest of this entry &raquo;'); ?>
    </div>

    <?php if (!$quell_postinfo && $post->post_type == 'post') { ?>
    <div class="postinfo">
        <div class="category">Filed under: <?php the_category(', '); ?> </div>
        <div class="com"><?php comments_popup_link('Add comments', '1 comment', '% comments'); ?></div>
    </div>
    <?php } elseif ($post->post_type == 'attachment' && $post->post_mime_type == 'application/x-go-sgf') { ?>
    <div class="postinfo">
        <div class="problem-category"><?php echo get_the_term_list($post->ID, 'problem_category', 'Category: ', ', ', ''); ?> </div>
        <div class="problem-difficulty"><?php echo get_the_term_list($post->ID, 'problem_difficulty', 'Difficulty: ', ', ', ''); ?></div>
    </div>
    <?php } ?>

    </div>

<?php endwhile; ?>

<?php if (!$quell_postinfo) { ?>
<div class="navigation">
    <div class="alignleft"><?php next_posts_link('&laquo; Previous Entries') ?></div>
    <div class="alignright"><?php previous_posts_link('Next Entries &raquo;') ?></div>
</div>
<?php } ?>
