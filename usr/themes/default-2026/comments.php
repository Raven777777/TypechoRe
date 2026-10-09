<?php
/**
 * 评论区模板
 *
 * @var Widget\Archive $this
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$comments = $this->comments();
?>
<div id="comments" class="no-print mt-10">
    <?php if ($comments->have()): ?>
        <h2 class="widget-title"><?php echo themeIcon('message-circle', 'size-4'); ?><?php $this->commentsNum(_t('暂无评论'), _t('1 条评论'), _t('%d 条评论')); ?></h2>

        <?php $comments->listComments([
            'avatarSize' => 40,
            'dateFormat' => 'Y-m-d H:i'
        ]); ?>

        <?php $comments->pageNav('&laquo;', '&raquo;', 1, '...', [
            'wrapTag'   => 'nav',
            'wrapClass' => 'page-navigator'
        ]); ?>
    <?php endif; ?>

    <?php if ($this->allow('comment')): ?>
        <div id="<?php $this->respondId(); ?>" class="respond card mt-8 p-6 sm:p-8">
            <div class="mb-5 flex flex-wrap items-center gap-3">
                <h2 id="response" class="text-lg font-semibold"><?php _e('添加新评论'); ?></h2>
                <div class="cancel-comment-reply ml-auto text-xs text-zinc-500 dark:text-zinc-400"><?php $comments->cancelReply(); ?></div>
            </div>

            <div id="notice-box" class="mb-5 hidden rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm whitespace-pre-line text-amber-800 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200" hidden></div>

            <form id="comment-form" class="space-y-4" method="post" action="<?php $this->commentUrl(); ?>">
                <?php if ($this->user->hasLogin()): ?>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                        <?php _e('登录身份'); ?>:
                        <a class="font-medium text-accent" href="<?php $this->options->profileUrl(); ?>"><?php $this->user->screenName(); ?></a>
                        ·
                        <a class="transition-colors hover:text-accent" href="<?php $this->options->logoutUrl(); ?>"><?php _e('退出'); ?></a>
                    </p>
                <?php else: ?>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label class="field-label" for="author"><?php _e('称呼'); ?></label>
                            <input class="field" id="author" name="author" type="text" value="<?php $this->remember('author'); ?>" required>
                        </div>
                        <div>
                            <label class="field-label" for="mail"><?php _e('Email'); ?></label>
                            <input class="field" id="mail" name="mail" type="email" value="<?php $this->remember('mail'); ?>"<?php if ($this->options->commentsRequireMail): ?> required<?php endif; ?>>
                        </div>
                        <div>
                            <label class="field-label" for="url"><?php _e('网站'); ?></label>
                            <input class="field" id="url" name="url" type="url" placeholder="https://" value="<?php $this->remember('url'); ?>"<?php if ($this->options->commentsRequireUrl): ?> required<?php endif; ?>>
                        </div>
                    </div>
                <?php endif; ?>

                <div>
                    <label class="field-label" for="textarea"><?php _e('内容'); ?></label>
                    <textarea class="field min-h-32 resize-y" id="textarea" name="text" rows="6" required><?php $this->remember('text'); ?></textarea>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <button class="btn btn-primary" type="submit"><?php _e('提交评论'); ?></button>
                    <span class="text-xs text-zinc-400 dark:text-zinc-500"><?php _e('提交后可能需要审核才会显示'); ?></span>
                </div>
            </form>
        </div>
    <?php else: ?>
        <p class="card mt-8 p-6 text-sm text-zinc-500 dark:text-zinc-400"><?php _e('评论已关闭'); ?></p>
    <?php endif; ?>
</div>
