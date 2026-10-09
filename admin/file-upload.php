<?php if(!defined('__TYPECHO_ADMIN__')) exit; ?>
<?php
/**
 * 后台模板作用域变量: 由 admin/common.php (以及 header.php / menu.php) 通过 include 注入。
 * PHPStan 无法跨 include 传播局部变量, 这里按实际作用域显式声明。
 *
 * @var Widget\Options $options
 * @var Widget\Security $security
 */
?>

<?php
// $post / $page 由 write-post.php / write-page.php 注入; 两者都不存在时退回未归档附件列表,
// 保证 $attachment 一定有值 (旧实现在这种情况下会使用未定义变量)
$cid = isset($post) ? (int) $post->cid : (isset($page) ? (int) $page->cid : 0);

if ($cid) {
    \Widget\Contents\Attachment\Related::alloc(['parentId' => $cid])->to($attachment);
} else {
    \Widget\Contents\Attachment\Unattached::alloc()->to($attachment);
}
?>

<div id="upload-panel" class="p">
    <div class="upload-area" data-url="<?php $security->index('/action/upload'); ?>">
        <?php _e('拖放文件到这里<br>或者 %s选择文件上传%s', '<a href="###" class="upload-file">', '</a>'); ?>
    </div>
    <ul id="file-list">
    <?php while ($attachment->next()): ?>
        <li data-cid="<?php $attachment->cid(); ?>" data-url="<?php echo $attachment->attachment->url; ?>" data-image="<?php echo $attachment->attachment->isImage ? 1 : 0; ?>"><input type="hidden" name="attachment[]" value="<?php $attachment->cid(); ?>" />
            <a class="insert" title="<?php _e('点击插入文件'); ?>" href="###"><?php $attachment->title(); ?></a>
            <div class="info">
                <?php echo number_format(ceil($attachment->attachment->size / 1024)); ?> Kb
                <a class="file" target="_blank" href="<?php $options->adminUrl('media.php?cid=' . $attachment->cid); ?>" title="<?php _e('编辑'); ?>"><i class="i-edit"></i></a>
                <a href="###" class="delete" title="<?php _e('删除'); ?>"><i class="i-delete"></i></a>
            </div>
        </li>
    <?php endwhile; ?>
    </ul>
</div>

