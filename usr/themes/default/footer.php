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
<script>
(function () {
    // 读取 Notice 组件写入的 __typecho_notice cookie (JSON 数组), 在评论区显示后清除
    var m = document.cookie.match(/(?:^|;\s*)([^;]*__typecho_notice)=([^;]*)/);
    if (!m) return;
    var box = document.getElementById('notice-box');
    if (!box) return;
    try {
        var list = JSON.parse(decodeURIComponent(m[2]));
        box.textContent = list.join('\n');
        box.hidden = false;
    } catch (e) { return; }
    document.cookie = m[1] + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
})();
</script>
</body>
</html>
