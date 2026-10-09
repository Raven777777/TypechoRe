<?php
/**
 * 后台模板作用域变量: 由 admin/common.php (以及 header.php / menu.php) 通过 include 注入。
 * PHPStan 无法跨 include 传播局部变量, 这里按实际作用域显式声明。
 *
 * @var Widget\Options $options
 * @var Typecho\Widget\Response $response
 * @var Widget\User $user
 */

include 'common.php';

/** 获取内容 Widget */
\Widget\Archive::alloc('type=single&checkPermalink=0&preview=1')->to($content);

/** 检测是否存在 */
if (!$content->have()) {
    $response->redirect($options->adminUrl);
}

/** 检测权限 */
if (!$user->pass('editor', true) && $content->authorId != $user->uid) {
    $response->redirect($options->adminUrl);
}

/** 输出内容 */
$content->render();
?>
<script>
    window.onbeforeunload = function () {
        if (!!window.parent) {
            window.parent.postMessage('cancelPreview', '<?php $options->rootUrl(); ?>');
        }
    }
</script>
