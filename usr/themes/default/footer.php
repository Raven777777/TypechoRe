<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>

        </div><!-- end .row -->
    </div>
</div><!-- end #body -->

<footer id="footer" role="contentinfo">
    &copy; <?php echo date('Y'); ?> <a href="<?php $this->options->siteUrl(); ?>"><?php $this->options->title(); ?></a>.
    <?php echo sprintf(
        _t('由 <a href="%s">%s</a> 强力驱动'),
        \Typecho\Common::PROJECT_URL,
        $this->options->software
    ); ?>. <?php echo sprintf(
        _t('(基于 <a href="%s">Typecho</a> 的 Fork 版本)'),
        \Typecho\Common::PROJECT_ORIGIN_URL
    ); ?>
</footer><!-- end #footer -->

<?php $this->footer(); ?>
</body>
</html>
