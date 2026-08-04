<?php
/**
 * Site footer.
 *
 * @package Autolex_Theme
 */
?>
</main>
<footer class="alx-site-footer">
    <div class="alx-container alx-footer-grid">
        <section>
            <a class="alx-brand" href="<?php echo esc_url(home_url('/')); ?>"><span class="alx-brand-mark" aria-hidden="true">A</span><span>AUTOLEX</span></a>
            <p><?php esc_html_e('Megbízható járműadatok, átlátható forrásokkal.', 'autolex-theme'); ?></p>
        </section>
        <section><h2 class="alx-footer-title"><?php esc_html_e('Katalógus', 'autolex-theme'); ?></h2><ul class="alx-footer-links"><li><a href="<?php echo esc_url(home_url('/autok/')); ?>"><?php esc_html_e('Márkák és modellek', 'autolex-theme'); ?></a></li><li><a href="<?php echo esc_url(home_url('/osszehasonlitas/')); ?>"><?php esc_html_e('Összehasonlítás', 'autolex-theme'); ?></a></li></ul></section>
        <section><h2 class="alx-footer-title"><?php esc_html_e('Biztonság', 'autolex-theme'); ?></h2><ul class="alx-footer-links"><li><a href="<?php echo esc_url(home_url('/visszahivasok/')); ?>"><?php esc_html_e('Visszahívások', 'autolex-theme'); ?></a></li><li><a href="<?php echo esc_url(home_url('/forrasok/')); ?>"><?php esc_html_e('Források', 'autolex-theme'); ?></a></li></ul></section>
        <section><h2 class="alx-footer-title"><?php esc_html_e('Tudástár', 'autolex-theme'); ?></h2><ul class="alx-footer-links"><li><a href="<?php echo esc_url(home_url('/tudastar/')); ?>"><?php esc_html_e('Útmutatók', 'autolex-theme'); ?></a></li><li><a href="<?php echo esc_url(home_url('/rolunk/')); ?>"><?php esc_html_e('Rólunk', 'autolex-theme'); ?></a></li></ul></section>
    </div>
    <div class="alx-container alx-footer-meta">&copy; <?php echo esc_html(wp_date('Y')); ?> Autolex.</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
