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
        <div id="<?php $this->respondId(); ?>" class="respond card mt-8 p-5 sm:p-6">
            <div class="mb-4 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <h2 id="response" class="text-base font-semibold tracking-tight"><?php _e('添加新评论'); ?></h2>
                <div class="cancel-comment-reply ml-auto text-xs text-zinc-500 dark:text-zinc-400"><?php $comments->cancelReply(); ?></div>
            </div>

            <div id="notice-box" class="mb-4 hidden rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm whitespace-pre-line text-amber-800 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200" hidden></div>

            <?php /* data-respond-id: 评论框容器 id (respond-XXX) 的完整值, app.js 的
                     回复/取消回复靠它定位容器, 不要去拼 cid —— theId 不是纯数字。 */ ?>
            <form id="comment-form" class="space-y-3" method="post" action="<?php $this->commentUrl(); ?>" data-respond-id="<?php echo themeEsc($this->respondId); ?>" data-comment-anchor="<?php echo themeEsc($this->theId); ?>" data-submit-failed="<?php echo themeEsc(_t('提交失败, 请稍后再试。')); ?>" data-waiting-text="<?php echo themeEsc(_t('评论已提交, 通过审核后显示。')); ?>" data-comment-submit>
                <?php if ($this->user->hasLogin()): ?>
                    <div class="comment-identity">
                        <?php $this->user->gravatar(24, 'X', null, 'comment-avatar'); ?>
                        <span class="min-w-0 truncate">
                            <a class="font-medium text-zinc-700 transition-colors hover:text-accent dark:text-zinc-200" href="<?php $this->options->profileUrl(); ?>"><?php $this->user->screenName(); ?></a><span class="mx-1 text-zinc-300 dark:text-zinc-600">·</span><a class="transition-colors hover:text-accent" href="<?php $this->options->logoutUrl(); ?>"><?php _e('退出'); ?></a>
                        </span>
                    </div>
                <?php else: ?>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div>
                            <label class="field-label" for="author"><?php _e('称呼'); ?></label>
                            <input class="field field-soft" id="author" name="author" type="text" value="<?php $this->remember('author'); ?>" required>
                        </div>
                        <div>
                            <label class="field-label" for="mail"><?php _e('Email'); ?></label>
                            <input class="field field-soft" id="mail" name="mail" type="email" value="<?php $this->remember('mail'); ?>"<?php if ($this->options->commentsRequireMail): ?> required<?php endif; ?>>
                        </div>
                        <div>
                            <label class="field-label" for="url"><?php _e('网站'); ?></label>
                            <input class="field field-soft" id="url" name="url" type="url" placeholder="https://" value="<?php $this->remember('url'); ?>"<?php if ($this->options->commentsRequireUrl): ?> required<?php endif; ?>>
                        </div>
                    </div>
                <?php endif; ?>

                <div>
                    <label class="sr-only" for="textarea"><?php _e('内容'); ?></label>
                    <textarea class="field field-soft min-h-[100px] max-h-96 resize-y py-2.5 pr-10 leading-relaxed" id="textarea" name="text" rows="3" placeholder="<?php echo themeEsc(_t('写下你的想法…')); ?>" required><?php $this->remember('text'); ?></textarea>
                </div>

                <div class="comment-actions">
                    <?php /* Markdown 工具条只在站点开启「在评论中使用 Markdown 语法」时出现,
                             否则会提示一堆用不了的语法 (渲染时也会被服务端过滤掉)。 */ ?>
                    <div class="comment-toolbar">
                        <details class="emoji-picker">
                            <summary class="tool-btn" title="<?php echo themeEsc(_t('插入表情')); ?>" aria-label="<?php echo themeEsc(_t('插入表情')); ?>"><span aria-hidden="true">😀</span></summary>
                            <div class="emoji-panel" role="group" aria-label="<?php echo themeEsc(_t('常用表情')); ?>">
                                <?php foreach (['😀', '😄', '😅', '😂', '🙂', '😉', '😍', '🤔', '😳', '😭', '😡', '👍', '👎', '🙏', '👏', '🎉', '❤️', '🔥', '✨', '💡'] as $emojiChar): ?>
                                    <button type="button" class="emoji-item" data-emoji="<?php echo themeEsc($emojiChar); ?>"><?php echo $emojiChar; ?></button>
                                <?php endforeach; ?>
                            </div>
                        </details>
                        <?php if ($this->options->commentsMarkdown): ?>
                            <button type="button" class="tool-btn" data-comment-format="bold" title="<?php echo themeEsc(_t('加粗')); ?>" aria-label="<?php echo themeEsc(_t('加粗')); ?>"><span aria-hidden="true" class="font-semibold">B</span></button>
                            <button type="button" class="tool-btn" data-comment-format="italic" title="<?php echo themeEsc(_t('斜体')); ?>" aria-label="<?php echo themeEsc(_t('斜体')); ?>"><span aria-hidden="true" class="font-serif italic">I</span></button>
                            <button type="button" class="tool-btn" data-comment-format="link" title="<?php echo themeEsc(_t('插入链接')); ?>" aria-label="<?php echo themeEsc(_t('插入链接')); ?>"><?php echo themeIcon('link', 'size-4'); ?></button>
                            <button type="button" class="tool-btn" data-comment-format="code" title="<?php echo themeEsc(_t('行内代码')); ?>" aria-label="<?php echo themeEsc(_t('行内代码')); ?>"><span aria-hidden="true" class="font-mono text-xs">&lt;/&gt;</span></button>
                            <button type="button" class="tool-btn" data-comment-format="quote" title="<?php echo themeEsc(_t('引用')); ?>" aria-label="<?php echo themeEsc(_t('引用')); ?>"><?php echo themeIcon('quote', 'size-4'); ?></button>
                        <?php endif; ?>
                        <span class="toolbar-sep" aria-hidden="true"></span>
                        <span class="comment-hint"><?php
                            echo $this->options->commentsMarkdown
                                ? themeEsc(_t('支持 Markdown · 提交后可能需要审核'))
                                : themeEsc(_t('提交后可能需要审核'));
                        ?></span>
                    </div>

                    <button class="btn btn-ghost comment-submit" type="submit"><?php _e('提交评论'); ?></button>
                </div>
            </form>
        </div>
    <?php else: ?>
        <p class="card mt-8 p-6 text-sm text-zinc-500 dark:text-zinc-400"><?php _e('评论已关闭'); ?></p>
    <?php endif; ?>
</div>
