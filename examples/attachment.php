<?php get_header(); ?>

<div id="content">
<div id="posts">
<div class="post">

<h2><?php the_title() ?></h2>

<?php if ( wp_attachment_is_image( $post->id ) ) : $att_image = wp_get_attachment_image_src( $post->id, "large"); ?>
    <div class="wp-caption aligncenter" style="width: <?php echo $att_image[1]; ?>px;">
        <a href="<?php echo wp_get_attachment_url($post->id); ?>" title="<?php the_title(); ?>" rel="attachment">
        <img src="<?php echo $att_image[0];?>" width="<?php echo $att_image[1];?>" height="<?php echo $att_image[2];?>"  class="attachment-large" alt="<?php $post->post_excerpt; ?>" />
        </a>
    </div>
    <?php if ( !empty($post->post_excerpt) ) the_excerpt(); ?>

<?php elseif ($post->post_mime_type == 'application/x-go-sgf') :
    $meta = get_post_custom($post->ID);
    $theme = 'compact';
    if ($meta['_wpeidogo_theme'][0] == 'problem')
        $theme = null;
    echo wpeidogo_embed_attachment($post, 'aligncenter', 'Download SGF', null, $theme);
?>

<?php else : ?>
    <div class="wp-caption aligncenter">
    <a href="<?php echo wp_get_attachment_url($post->ID) ?>"
        title="<?php echo wp_specialchars( get_the_title($post->ID), 1 ) ?>"
        rel="attachment"><?php echo basename($post->guid) ?></a>
    </div>
    <?php if ( !empty($post->post_excerpt) ) the_excerpt(); ?>

<?php endif; ?>

</div>
</div>
</div>

<?php get_sidebar(); ?>

<?php get_footer(); ?>
