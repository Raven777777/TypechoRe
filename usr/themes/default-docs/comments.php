<?php
/**
 * 评论模板
 *
 * @var Widget\Archive $this
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
?>
<div id="comments">
<?php $this->comments()->to($comments); ?>
<?php if ($comments->have()): ?>
<h2><?php $this->commentsNum('No comments', '1 comment', '%d comments'); ?></h2>
<?php $comments->listComments(); ?>
<?php $comments->pageNav('Prev', 'Next'); ?>
<?php endif; ?>

<?php if ($this->allow('comment')): ?>
<div id="<?php $this->respondId(); ?>" class="respond">
<?php $comments->cancelReply(); ?>
<div id="notice-box" class="notice-box" role="alert" hidden></div>
<form method="post" action="<?php $this->commentUrl(); ?>" id="comment-form">
<?php if ($this->user->hasLogin()): ?>
<p>Logged in as <a href="<?php $this->options->profileUrl(); ?>"><?php $this->user->screenName(); ?></a>. <a href="<?php $this->options->logoutUrl(); ?>">Logout</a></p>
<?php else: ?>
<p><label for="author">Name</label>
<input type="text" name="author" id="author" value="<?php $this->remember('author'); ?>"></p>
<p><label for="mail">Email</label>
<input type="text" name="mail" id="mail" value="<?php $this->remember('mail'); ?>"></p>
<p><label for="url">Website</label>
<input type="text" name="url" id="url" value="<?php $this->remember('url'); ?>"></p>
<?php endif; ?>
<p><label for="textarea">Comment</label>
<textarea rows="8" name="text" id="textarea" required></textarea></p>
<p><button type="submit">Submit</button></p>
</form>
</div>
<?php else: ?>
<h2>Comments are closed</h2>
<?php endif; ?>
</div>
