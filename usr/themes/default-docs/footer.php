<?php
/**
 * 主题模板作用域变量
 *
 * @var Widget\Archive $this
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
?>
<script>
(function () {
    var m = document.cookie.match(/(?:^|;\s*)([^;]*__typecho_notice)=([^;]*)/);
    if (!m) return;
    var box = document.getElementById('notice-box');
    if (!box) return;
    try {
        box.textContent = JSON.parse(decodeURIComponent(m[2])).join('\n');
        box.hidden = false;
    } catch (e) { return; }
    document.cookie = m[1] + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path='
        + <?php echo json_encode(\Typecho\Common::url(null, parse_url((string) $this->options->rootUrl, PHP_URL_PATH))); ?>;
})();
</script>
<?php $this->footer(); ?>
</body>
</html>
