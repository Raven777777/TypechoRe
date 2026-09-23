<?php
include 'common.php';
include 'header.php';
include 'menu.php';

$stat = \Widget\Stat::alloc();
?>

<main class="main">
    <div class="body container">
        <?php include 'page-title.php'; ?>
        <div class="row typecho-page-main">
            <div class="col-mb-12 col-tb-3">
                <p><a href="https://gravatar.com/"
                      title="<?php _e('在 Gravatar 上修改头像'); ?>"><?php echo '<img class="profile-avatar" src="' . \Typecho\Common::gravatarUrl($user->mail, 220, 'X', 'mm', $request->isSecure()) . '" alt="' . htmlspecialchars($user->screenName) . '" />'; ?></a>
                </p>
                <h2><?php $user->screenName(); ?></h2>
                <p><?php $user->name(); ?></p>
                <p><?php _e('目前有 <em>%s</em> 篇日志, 并有 <em>%s</em> 条关于你的评论在 <em>%s</em> 个分类中.',
                        $stat->myPublishedPostsNum, $stat->myPublishedCommentsNum, $stat->categoriesNum); ?></p>
                <p><?php
                    if ($user->logged > 0) {
                        $logged = new \Typecho\Date($user->logged);
                        _e('最后登录: %s', $logged->word());
                    }
                    ?></p>
            </div>

            <div class="col-mb-12 col-tb-6 col-tb-offset-1 typecho-content-panel" role="form">
                <section>
                    <h3><?php _e('个人资料'); ?></h3>
                    <?php \Widget\Users\Profile::alloc()->profileForm()->render(); ?>
                </section>

                <?php if ($user->pass('contributor', true)): ?>
                    <br>
                    <section id="writing-option">
                        <h3><?php _e('撰写设置'); ?></h3>
                        <?php \Widget\Users\Profile::alloc()->optionsForm()->render(); ?>
                    </section>
                <?php endif; ?>

                <br>

                <section id="change-password">
                    <h3><?php _e('密码修改'); ?></h3>
                    <?php \Widget\Users\Profile::alloc()->passwordForm()->render(); ?>
                </section>

                <br>
                <section id="passkey-management">
                    <h3><?php _e('Passkey 登录'); ?></h3>
                    <p><?php _e('使用 Windows Hello、手机或安全密钥登录，无需输入密码。'); ?></p>
                    <button type="button" class="btn primary" id="passkey-register"><?php _e('注册 Passkey'); ?></button>
                    <ul id="passkey-list"></ul>
                </section>
                <script>
                (function () {
                    if (!window.PublicKeyCredential || !navigator.credentials) return;
                    var base = <?php echo json_encode(\Typecho\Common::url('/action/passkey', $options->index)); ?>;
                    var token = <?php echo json_encode(\Widget\Security::alloc()->getToken(null)); ?>;
                    var list = document.getElementById('passkey-list');
                    function b64(value) { value = value.replace(/-/g, '+').replace(/_/g, '/'); while (value.length % 4) value += '='; var raw = atob(value), out = new Uint8Array(raw.length); for (var i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i); return out.buffer; }
                    function enc(buffer) { var b = new Uint8Array(buffer), s = ''; for (var i = 0; i < b.length; i++) s += String.fromCharCode(b[i]); return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, ''); }
                    function json(url, options) { return fetch(url, Object.assign({credentials: 'same-origin'}, options || {})).then(function (r) { return r.json(); }); }
                    function refresh() { json(base + '?do=list').then(function (data) { list.innerHTML = ''; (data.items || []).forEach(function (item) { var li = document.createElement('li'); li.textContent = item.name + ' '; var button = document.createElement('button'); button.type = 'button'; button.className = 'btn btn-xs'; button.textContent = '<?php _e('删除'); ?>'; button.onclick = function () { if (confirm('<?php _e('确定删除此 Passkey 吗？'); ?>')) json(base + '?do=delete&id=' + encodeURIComponent(item.id) + '&_=' + encodeURIComponent(token), {method: 'POST'}).then(refresh); }; li.appendChild(button); list.appendChild(li); }); }); }
                    document.getElementById('passkey-register').onclick = function () { var button = this; button.disabled = true; json(base + '?do=create-options&_=' + encodeURIComponent(token)).then(function (data) { if (!data.success) throw Error(data.message); var key = data.options.publicKey; key.challenge = b64(key.challenge); key.user.id = b64(key.user.id); return navigator.credentials.create({publicKey: key}); }).then(function (credential) { return json(base + '?do=process-create&_=' + encodeURIComponent(token), {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({name: prompt('<?php _e('Passkey 名称'); ?>', '我的设备') || 'Passkey', clientDataJSON: enc(credential.response.clientDataJSON), attestationObject: enc(credential.response.attestationObject)})}); }).then(function (data) { if (!data.success) throw Error(data.message); refresh(); }).catch(function (e) { alert(e.message || '<?php _e('Passkey 注册失败'); ?>'); }).finally(function () { button.disabled = false; }); };
                    refresh();
                })();
                </script>

                <?php \Widget\Users\Profile::alloc()->personalFormList(); ?>
            </div>
        </div>
    </div>
</main>

<?php
include 'copyright.php';
include 'common-js.php';
include 'form-js.php';
\Typecho\Plugin::factory('admin/profile.php')->call('bottom');
include 'footer.php';
?>
