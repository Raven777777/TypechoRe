<?php
include 'common.php';
include 'header.php';
include 'menu.php';

\Widget\Metas\Tag\Admin::alloc()->to($tags);
?>

<main class="main">
    <div class="body container">
        <?php include 'page-title.php'; ?>
        <div class="row typecho-page-main manage-metas">

            <div class="col-mb-12 col-tb-8" role="main">

                <form method="post" name="manage_tags" class="operate-form">
                    <div class="typecho-list-operate">
                        <div class="operate">
                            <label><i class="sr-only"><?php _e('全选'); ?></i><input type="checkbox"
                                                                                   class="typecho-table-select-all"/></label>
                            <div class="btn-group btn-drop">
                                <button class="btn dropdown-toggle btn-s" type="button"><i
                                        class="sr-only"><?php _e('操作'); ?></i><?php _e('选中项'); ?> <i
                                        class="i-caret-down"></i></button>
                                <ul class="dropdown-menu">
                                    <li><a lang="<?php _e('你确认要删除这些标签吗?'); ?>"
                                           href="<?php $security->index('/action/metas-tag-edit?do=delete'); ?>"><?php _e('删除'); ?></a>
                                    </li>
                                    <li><a lang="<?php _e('刷新标签可能需要等待较长时间, 你确认要刷新这些标签吗?'); ?>"
                                           href="<?php $security->index('/action/metas-tag-edit?do=refresh'); ?>"><?php _e('刷新'); ?></a>
                                    </li>
                                    <li class="multiline">
                                        <button type="button" class="btn btn-s merge"
                                                rel="<?php $security->index('/action/metas-tag-edit?do=merge'); ?>"><?php _e('合并到'); ?></button>
                                        <input type="text" name="merge" class="text-s"/>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <?php if ($tags->have()): ?>
                        <ul class="typecho-list-notable tag-list">
                            <?php while ($tags->next()): ?>
                                <li class="size-<?php $tags->split(5, 10, 20, 30); ?>" id="<?php $tags->theId(); ?>">
                                    <input type="checkbox" value="<?php $tags->mid(); ?>" name="mid[]"/>
                                    <span
                                        rel="<?php echo $request->makeUriByRequest('mid=' . $tags->mid); ?>"><?php $tags->name(); ?></span>
                                    <a class="tag-edit-link"
                                       href="<?php echo $request->makeUriByRequest('mid=' . $tags->mid); ?>"><i
                                            class="i-edit"></i></a>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                    <?php else: ?>
                        <ul class="tag-list">
                            <li class="none"><?php _e('没有任何标签'); ?></li>
                        </ul>
                    <?php endif; ?>
                    <input type="hidden" name="do" value="delete"/>
                </form>

            </div>
            <div class="col-mb-12 col-tb-4" role="complementary">
                <?php \Widget\Metas\Tag\Edit::alloc()->form()->render(); ?>

                <section class="typecho-option tag-picker-settings">
                    <form method="post" name="tag-picker-settings"
                          action="<?php $security->index('/action/tag-picker'); ?>">
                        <?php $tagPicker = \Widget\Options\TagPicker::config(); ?>
                        <ul>
                            <li class="typecho-option">
                                <label class="typecho-label"><?php _e('速选面板设置'); ?></label>
                            </li>
                            <li class="typecho-option">
                                <label class="typecho-label"><?php _e('排序方式'); ?></label>
                                <select name="sort">
                                    <option value="count"<?php if ('count' == $tagPicker['sort']): ?> selected<?php endif; ?>><?php _e('按文章数量（由多到少）'); ?></option>
                                    <option value="name"<?php if ('name' == $tagPicker['sort']): ?> selected<?php endif; ?>><?php _e('按标签名称'); ?></option>
                                </select>
                            </li>
                            <li class="typecho-option">
                                <label class="typecho-label"><?php _e('载入的标签数量'); ?></label>
                                <input type="number" name="limit" value="<?php echo $tagPicker['limit']; ?>"
                                       class="text-s num" min="0" max="1000"/>
                                <p class="description"><?php _e('填写 0 表示载入全部标签'); ?></p>
                            </li>
                            <li class="typecho-option">
                                <label><input type="checkbox" name="ignoreZeroCount[]" value="1"
                                    <?php if ($tagPicker['ignoreZeroCount']): ?>checked<?php endif; ?>/> <?php _e('隐藏没有被任何文章使用的标签'); ?></label>
                            </li>
                            <li class="typecho-option">
                                <label><input type="checkbox" name="showCount[]" value="1"
                                    <?php if ($tagPicker['showCount']): ?>checked<?php endif; ?>/> <?php _e('在标签后面显示关联的文章数量'); ?></label>
                            </li>
                            <li class="typecho-option">
                                <button type="submit" class="btn primary"><?php _e('保存设置'); ?></button>
                            </li>
                        </ul>
                    </form>
                </section>
            </div>
        </div>
    </div>
</main>

<?php
include 'copyright.php';
include 'common-js.php';
?>

<script type="text/javascript">
    (function () {
        $(document).ready(function () {

            $('.typecho-list-notable').tableSelectable({
                checkEl: 'input[type=checkbox]',
                rowEl: 'li',
                selectAllEl: '.typecho-table-select-all',
                actionEl: '.dropdown-menu a'
            });

            $('.btn-drop').dropdownMenu({
                btnEl: '.dropdown-toggle',
                menuEl: '.dropdown-menu'
            });

            $('.dropdown-menu button.merge').click(function () {
                var btn = $(this);
                btn.parents('form').attr('action', btn.attr('rel')).submit();
            });

            <?php if (isset($request->mid)): ?>
            $('.typecho-mini-panel').effect('highlight', '#AACB36');
            <?php endif; ?>
        });
    })();
</script>
<?php include 'footer.php'; ?>

