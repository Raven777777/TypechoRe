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
<div id="notice-box" class="notice-box" hidden></div>
<form method="post" action="<?php $this->commentUrl(); ?>" id="comment-form">
<?php if ($this->user->hasLogin()): ?>
<p>Logged in as <a href="<?php $this->options->profileUrl(); ?>"><?php $this->user->screenName(); ?></a>. <a href="<?php $this->options->logoutUrl(); ?>">Logout</a></p>
<?php else: ?>
<?php
$defaultText = 'Name：' . (string) $this->remember('author', true) . "\n"
    . 'Email：' . (string) $this->remember('mail', true) . "\n"
    . 'Website：' . (string) $this->remember('url', true);
?>
<input type="hidden" name="author" id="author" value="<?php $this->remember('author'); ?>">
<input type="hidden" name="mail" id="mail" value="<?php $this->remember('mail'); ?>">
<input type="hidden" name="url" id="url" value="<?php $this->remember('url'); ?>">
<?php endif; ?>
<p><textarea rows="8" name="text" id="textarea" required><?php
    echo htmlspecialchars($this->user->hasLogin() ? '' : $defaultText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?></textarea></p>
<p><button type="submit">Submit</button></p>
</form>
</div>
<?php if (!$this->user->hasLogin()): ?>
<script>
(function () {
    var form = document.getElementById('comment-form');
    if (!form) return;
    form.addEventListener('submit', function () {
        var textarea = form.querySelector('#textarea');
        var lines = textarea.value.split(/\r?\n/);
        var values = {Name: '', Email: '', Website: ''};
        var start = 0;
        while (start < lines.length) {
            var match = lines[start].match(/^\s*(Name|Email|Website)\s*[:：]\s*(.*)$/);
            if (!match) {
                break;
            }
            values[match[1]] = match[2].trim();
            start++;
        }
        textarea.value = lines.slice(start).join('\n').replace(/^\n+/, '');
        form.querySelector('#author').value = values.Name;
        form.querySelector('#mail').value = values.Email;
        form.querySelector('#url').value = values.Website;
    });
})();
</script>
<?php endif; ?>
<?php else: ?>
<h2>Comments are closed</h2>
<?php endif; ?>
</div>
