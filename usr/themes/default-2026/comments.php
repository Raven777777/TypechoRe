<?php
/**
 * 评论区模板
 *
 * 版面是「引用式」: 每条评论左边一条竖线, 竖线就是这条评论的边界。
 * 不做卡片底色、也不用大缩进堆层级 (缩进堆到第 4 层正文只剩三分之一宽度),
 * 竖线的深浅 + 半格缩进一起表示嵌套。
 *
 * @var Widget\Archive $this
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$comments = $this->comments();
/* ?replyTo=<coid>: 内核会给表单 action 补上 parent 字段 (无 JS 也能回复), 同时把
   「取消回复」链接显示出来; 回复框跟着默认展开, 两边保持一致。 */
$replyTo = (int) $this->request->filter('int')->get('replyTo');
$hasComments = $comments->have();
$commentCount = (int) $this->commentsNum;
?>
<div id="comments" class="no-print mt-8">
    <?php if ($hasComments): ?>
        <h2 class="section-title mb-1 flex items-center gap-2.5">
            <?php _e('评论'); ?>
            <span class="section-count"><?php echo $commentCount; ?></span>
        </h2>
        <p class="meta-text mb-5"><?php _e('说点什么, 不用太正式。'); ?></p>

        <?php /* avatarSize 取 64: CSS 把头像缩到 32px, 高分辨率屏上不糊。
                 before / after 同时包住根列表与子列表 (内核两处共用同一份配置),
                 子列表那一层用 CSS 摊平, 所以这里统一用一个类名。 */ ?>
        <?php $comments->listComments([
            'avatarSize' => 64,
            'before'     => '<div class="thread-list">',
            'after'      => '</div>'
        ]); ?>

        <?php $comments->pageNav('&laquo;', '&raquo;', 1, '...', [
            'wrapTag'   => 'nav',
            'wrapClass' => 'page-navigator'
        ]); ?>
    <?php endif; ?>

    <?php if ($this->allow('comment')): ?>
        <?php if (!$hasComments): ?>
            <div class="mb-6 border-t border-line pt-6">
                <h2 class="section-title"><?php _e('评论'); ?></h2>
                <p class="meta-text mt-1"><?php _e('还没有人说话, 第一条归你。'); ?></p>
            </div>
        <?php endif; ?>

        <?php /* 回复框默认收起成一颗按钮 (<details>): 点它或点某条评论的「回复」才展开成
                 表单 (后者由 app.js / 内核脚本展开); 无 JS 时 <details> 自己也能展开。 */ ?>
        <details id="<?php $this->respondId(); ?>" class="respond"<?php if ($replyTo > 0): ?> open<?php endif; ?>>
            <summary class="respond-summary">
                <?php echo themeIcon('message-circle', 'size-4'); ?>
                <span><?php _e('写下你的想法'); ?></span>
                <?php echo themeIcon('chevron-down', 'respond-chevron size-4'); ?>
            </summary>

            <div class="mb-4 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <h2 id="response" class="sr-only"><?php _e('写下你的想法'); ?></h2>
                <div class="cancel-comment-reply ml-auto text-xs text-ink-3"><?php $comments->cancelReply(); ?></div>
            </div>

            <?php /* data-respond-id: 评论框容器 id (respond-XXX) 的完整值, app.js 的
                     回复/取消回复靠它定位容器, 不要去拼 cid —— theId 不是纯数字。 */ ?>
            <form id="comment-form" class="space-y-3" method="post" action="<?php $this->commentUrl(); ?>" data-respond-id="<?php echo themeEsc($this->respondId); ?>" data-comment-anchor="<?php echo themeEsc($this->theId); ?>" data-submit-failed="<?php echo themeEsc(_t('提交失败, 请稍后再试。')); ?>" data-waiting-text="<?php echo themeEsc(_t('评论已提交, 通过审核后显示。')); ?>" data-posted-text="<?php echo themeEsc(_t('评论已发布, 谢谢。')); ?>" data-comment-submit>
                <?php if ($this->user->hasLogin()): ?>
                    <div class="comment-identity">
                        <?php /* 后台关掉 Gravatar 头像服务后不该再请求头像: Comments::gravatar() 会
                                 自己看 commentsAvatar 选项, 但 Users::gravatar() 不看, 所以这里判一次。 */ ?>
                        <?php if ($this->options->commentsAvatar): ?>
                            <?php $this->user->gravatar(48, 'X', null, 'comment-avatar'); ?>
                        <?php endif; ?>
                        <span class="min-w-0 truncate">
                            <a class="font-medium text-ink-1 transition-colors hover:text-accent-ink" href="<?php $this->options->profileUrl(); ?>"><?php echo themeText(fn () => $this->user->screenName()); ?></a><span class="mx-1 text-ink-3">·</span><a class="transition-colors hover:text-accent-ink" href="<?php $this->options->logoutUrl(); ?>"><?php _e('退出'); ?></a>
                        </span>
                    </div>
                <?php else: ?>
                    <?php /* 三个字段横排时每格只有 90px 宽, 手机上更是挤成一条缝:
                             窄屏逐行堆叠, 只在 ≥40rem 之后才排成三列。 */ ?>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div>
                            <label class="field-label" for="author"><?php _e('称呼'); ?></label>
                            <input class="field field-soft" id="author" name="author" type="text" autocomplete="nickname" value="<?php $this->remember('author'); ?>" required>
                        </div>
                        <div>
                            <label class="field-label" for="mail"><?php _e('Email'); ?></label>
                            <input class="field field-soft" id="mail" name="mail" type="email" autocomplete="email" inputmode="email" value="<?php $this->remember('mail'); ?>"<?php if ($this->options->commentsRequireMail): ?> required<?php endif; ?>>
                        </div>
                        <div>
                            <label class="field-label" for="url"><?php _e('网站'); ?></label>
                            <input class="field field-soft" id="url" name="url" type="url" autocomplete="url" inputmode="url" placeholder="https://" value="<?php $this->remember('url'); ?>"<?php if ($this->options->commentsRequireUrl): ?> required<?php endif; ?>>
                        </div>
                    </div>
                <?php endif; ?>

                <div>
                    <label class="sr-only" for="textarea"><?php _e('内容'); ?></label>
                    <textarea class="field field-soft max-h-96 min-h-[104px] resize-none py-2.5 leading-relaxed" id="textarea" name="text" rows="3" data-autogrow placeholder="<?php echo themeEsc(_t('写下你的想法…')); ?>" required></textarea>
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

                    <?php /* 提交是这一屏最重的动作: 用实心主按钮, 不用描边幽灵按钮
                             (以前最重的动作配上最轻的样式, 视线找不到落点)。 */ ?>
                    <button class="btn btn-primary comment-submit" type="submit"><?php _e('提交评论'); ?></button>
                </div>
            </form>
        </details>

        <?php /* 提交回执放在回复框外面: 收起回复框后依然看得见。
                 注意这里只能有 hidden 属性, 不能同时挂 .hidden 类 —— app.js 只把
                 hidden 属性设回 false, 而 .hidden{display:none} 会继续盖住它。 */ ?>
        <div id="notice-box" class="mt-4 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm whitespace-pre-line text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-100" role="status" aria-live="polite" hidden></div>
    <?php else: ?>
        <p class="card mt-8 p-6 text-sm text-ink-2"><?php _e('评论已关闭'); ?></p>
    <?php endif; ?>
</div>
