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
        <section class="alx-footer-brand-block">
            <a class="alx-brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="<?php esc_attr_e('Autolex főoldal', 'autolex-theme'); ?>">
                <span class="alx-brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 38 38" role="presentation" focusable="false"><path d="M6.1 28.9 16.5 7.3c.7-1.5 2.2-2.4 3.8-2.4h7.5l-3.4 7.2h-3.5l-2.8 6h5.3l4.8-10.1 5.7 11.7c.8 1.7-.4 3.7-2.3 3.7h-7.1l3.2 6.6H15.4l-3.2-6.6-2.4 5.5H6.1Z" fill="#1769e8"/><path d="m27.7 4.9 4.6 4.4-4.4 4.7-4.7-4.5 4.5-4.6Z" fill="#9fc1ff"/></svg>
                </span>
                <span class="alx-brand-word">AUTOLEX</span>
            </a>
            <p><?php esc_html_e('Megbízható autós adatok. Okos döntésekhez.', 'autolex-theme'); ?></p>
            <div class="alx-footer-social" aria-label="<?php esc_attr_e('Közösségi csatornák', 'autolex-theme'); ?>"><span aria-hidden="true">f</span><span aria-hidden="true">in</span><span aria-hidden="true">▶</span></div>
        </section>

        <section><h2 class="alx-footer-title"><?php esc_html_e('Katalógus', 'autolex-theme'); ?></h2><ul class="alx-footer-links"><li><a href="<?php echo esc_url(home_url('/markak/')); ?>"><?php esc_html_e('Márkák', 'autolex-theme'); ?></a></li><li><a href="<?php echo esc_url(home_url('/modellek/')); ?>"><?php esc_html_e('Modellek', 'autolex-theme'); ?></a></li><li><a href="<?php echo esc_url(home_url('/autok/')); ?>"><?php esc_html_e('Jármű adatok', 'autolex-theme'); ?></a></li></ul></section>
        <section><h2 class="alx-footer-title"><?php esc_html_e('Források', 'autolex-theme'); ?></h2><ul class="alx-footer-links"><li><a href="<?php echo esc_url(home_url('/tudastar/')); ?>"><?php esc_html_e('Tudástár', 'autolex-theme'); ?></a></li><li><a href="<?php echo esc_url(home_url('/forrasok/')); ?>"><?php esc_html_e('Útmutatók', 'autolex-theme'); ?></a></li><li><a href="<?php echo esc_url(home_url('/forrasok/')); ?>"><?php esc_html_e('GYIK', 'autolex-theme'); ?></a></li></ul></section>
        <section><h2 class="alx-footer-title"><?php esc_html_e('Jogi', 'autolex-theme'); ?></h2><ul class="alx-footer-links"><li><a href="<?php echo esc_url(home_url('/adatvedelem/')); ?>"><?php esc_html_e('Adatvédelem', 'autolex-theme'); ?></a></li><li><a href="<?php echo esc_url(home_url('/felhasznalasi-feltetelek/')); ?>"><?php esc_html_e('Felhasználási feltételek', 'autolex-theme'); ?></a></li><li><a href="<?php echo esc_url(home_url('/impresszum/')); ?>"><?php esc_html_e('Impresszum', 'autolex-theme'); ?></a></li></ul></section>
        <section><h2 class="alx-footer-title"><?php esc_html_e('Vállalat', 'autolex-theme'); ?></h2><ul class="alx-footer-links"><li><a href="<?php echo esc_url(home_url('/rolunk/')); ?>"><?php esc_html_e('Rólunk', 'autolex-theme'); ?></a></li><li><a href="<?php echo esc_url(home_url('/kapcsolat/')); ?>"><?php esc_html_e('Kapcsolat', 'autolex-theme'); ?></a></li><li><a href="<?php echo esc_url(home_url('/karrier/')); ?>"><?php esc_html_e('Karrier', 'autolex-theme'); ?></a></li></ul></section>

        <section class="alx-footer-newsletter" aria-labelledby="alx-footer-newsletter-title">
            <h2 id="alx-footer-newsletter-title" class="alx-footer-title"><?php esc_html_e('Iratkozzon fel hírlevelünkre', 'autolex-theme'); ?></h2>
            <p><?php esc_html_e('Friss hírek, adatok és útmutatók közvetlenül az e-mail fiókjába.', 'autolex-theme'); ?></p>
            <div class="alx-footer-subscribe" aria-label="<?php esc_attr_e('Hírlevél feliratkozás hamarosan', 'autolex-theme'); ?>">
                <span><?php esc_html_e('E-mail cím', 'autolex-theme'); ?></span>
                <strong><?php esc_html_e('Hamarosan', 'autolex-theme'); ?></strong>
            </div>
        </section>
    </div>
    <div class="alx-container alx-footer-meta"><span>&copy; <?php echo esc_html(wp_date('Y')); ?> Autolex. <?php esc_html_e('Minden jog fenntartva.', 'autolex-theme'); ?></span><span><?php esc_html_e('Kövess minket:', 'autolex-theme'); ?> <span class="alx-footer-meta-social" aria-hidden="true">f · in · ▶</span></span></div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
