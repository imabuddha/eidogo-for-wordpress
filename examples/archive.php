<?php get_header(); ?>

<div id="content">
<div id="posts">

<?php if (have_posts()) : ?>

<?php $post = $posts[0]; // Hack. Set $post so that the_date() works. ?>
<?php /* If this is a category archive */ if (is_category()) { ?>
    <h1 class="pagetitle">Archive for &ldquo;<?php echo single_cat_title(); ?>&rdquo;</h1>

<?php /* If this is a taxonomy archive */ } elseif (is_taxonomy($taxonomy)) {
    $term = get_term_by('slug', get_query_var('term'), $taxonomy);
    ?><h1 class="pagetitle"><?php echo $wp_taxonomies[$taxonomy]->label; ?>: <?php echo $term->name; ?></h1>

<?php /* If this is a daily archive */ } elseif (is_day()) { ?>
    <h1 class="pagetitle">Archive for <?php the_time('F jS, Y'); ?></h1>

<?php /* If this is a monthly archive */ } elseif (is_month()) { ?>
    <h1 class="pagetitle">Archive for <?php the_time('F, Y'); ?></h1>

<?php /* If this is a yearly archive */ } elseif (is_year()) { ?>
    <h1 class="pagetitle">Archive for <?php the_time('Y'); ?></h1>

<?php /* If this is an author archive */ } elseif (is_author()) { ?>
    <h1 class="pagetitle">Author Archive</h1>

<?php /* If this is a paged archive */ } elseif (isset($_GET['paged']) && !empty($_GET['paged'])) { ?>
    <h1 class="pagetitle">Blog Archives</h1>

<?php } ?>

<?php include(TEMPLATEPATH . '/posts.php'); ?>

<?php else : ?>

<?php include(TEMPLATEPATH . '/notfound.php'); ?>

<?php endif; ?>

</div>
</div>

<?php get_sidebar(); ?>

<?php get_footer(); ?>
