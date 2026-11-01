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
<input type="hidden" name="author" id="author" value="<?php $this->remember('author'); ?>">
<input type="hidden" name="mail" id="mail" value="<?php $this->remember('mail'); ?>">
<input type="hidden" name="url" id="url" value="<?php $this->remember('url'); ?>">
<?php endif; ?>
<p><label for="textarea">Comment</label>
<?php $draft = (string) \Typecho\Cookie::get('__typecho_remember_text'); ?>
<textarea rows="8" name="text" id="textarea" required><?php echo htmlspecialchars($draft); ?></textarea></p>
<p><button type="submit">Submit</button></p>
</form>
<script>
(function () {
    var form = document.getElementById('comment-form');
    if (!form || !form.author) return;
    var textarea = form.querySelector('textarea[name=text]'),
        fields = {name: 'author', email: 'mail', website: 'url'},
        identity = [],
        key;
    // 身份行由脚本生成, 无 JS 时评论正文里不会混入姓名/邮箱
    for (key in fields) {
        identity.push(key.charAt(0).toUpperCase() + key.slice(1) + ':' + form[fields[key]].value);
    }
    textarea.value = identity.join('\n') + '\n' + textarea.value;
    form.addEventListener('submit', function () {
        var lines = textarea.value.split('\n'),
            map = {},
            i = 0;
        // 只认前三行的 Name:/Email:/Website:，空值也算已填（不会吃掉下一行）
        for (; i < 3 && i < lines.length; i++) {
            var m = /^\s*(name|email|website)\s*[:：]\s*(.*)$/i.exec(lines[i]);
            if (!m) break;
            map[m[1].toLowerCase()] = m[2].trim();
        }
        if (!i) return;
        textarea.value = lines.slice(i).join('\n');
        for (var field in map) {
            form[fields[field]].value = map[field];
        }
    });
})();
</script>
</div>
<?php else: ?>
<h2>Comments are closed</h2>
<?php endif; ?>
</div>
