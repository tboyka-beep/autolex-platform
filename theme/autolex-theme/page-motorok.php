<?php
/**
 * Engine hierarchy route shell.
 *
 * @package Autolex_Theme
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
while (have_posts()) :
    the_post();
    get_template_part('template-parts/hierarchy-shell', null, array('context' => 'engine'));
endwhile;
get_footer();
