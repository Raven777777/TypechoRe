<?php
include 'common.php';

if ($user->hasLogin()) {
    $response->redirect($options->adminUrl);
}
$rememberName = htmlspecialchars(\Typecho\Cookie::get('__typecho_remember_name', ''));
\Typecho\Cookie::delete('__typecho_remember_name');

$bodyClass = 'body-100';

include 'header.php';
?>
<div class="typecho-login-wrap">
    <div class="typecho-login">
        <h1><a href="https://github.com/Raven777777/TypechoRe" class="i-logo">TypechoRe</a></h1>
        <form action="<?php $options->loginAction(); ?>" method="post" name="login" role="form">
            <p>
                <label for="name" class="sr-only"><?php _e('用户名或邮箱'); ?></label>
                <input type="text" id="name" name="name" value="<?php echo $rememberName; ?>" placeholder="<?php _e('用户名或邮箱'); ?>" class="text-l w-100" autofocus />
            </p>
            <p>
                <label for="password" class="sr-only"><?php _e('密码'); ?></label>
                <input type="password" id="password" name="password" class="text-l w-100" placeholder="<?php _e('密码'); ?>" required />
            </p>
            <p class="submit">
                <button type="submit" class="btn btn-l w-100 primary"><?php _e('登录'); ?></button>
                <input type="hidden" name="referer" value="<?php echo $request->filter('html')->get('referer'); ?>" />
            </p>
            <p id="passkey-login-row" style="display:none">
                <button type="button" id="passkey-login" class="btn btn-l w-100"><?php _e('使用 Passkey 登录'); ?></button>
            </p>
            <p>
                <label for="remember">
                    <input<?php if(\Typecho\Cookie::get('__typecho_remember_remember')): ?> checked<?php endif; ?> type="checkbox" name="remember" class="checkbox" value="1" id="remember" /> <?php _e('下次自动登录'); ?>
                </label>
            </p>
        </form>
        
        <p class="more-link">
            <a href="<?php $options->siteUrl(); ?>"><?php _e('返回首页'); ?></a>
            <?php if($options->allowRegister): ?>
            &bull;
            <a href="<?php $options->registerUrl(); ?>"><?php _e('用户注册'); ?></a>
            <?php endif; ?>
        </p>
    </div>
</div>
<?php 
include 'common-js.php';
?>
<script>
$(document).ready(function () {
    $('#name').focus();

    if (!window.PublicKeyCredential || !navigator.credentials) {
        return;
    }

    var passkeyUrl = <?php echo json_encode(\Typecho\Common::url('/action/passkey', $options->index)); ?>;
    var button = $('#passkey-login');
    $('#passkey-login-row').show();

    function fromBase64Url(value) {
        value = value.replace(/-/g, '+').replace(/_/g, '/');
        while (value.length % 4) value += '=';
        var raw = atob(value), result = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; i++) result[i] = raw.charCodeAt(i);
        return result.buffer;
    }

    function toBase64Url(buffer) {
        var bytes = new Uint8Array(buffer), value = '';
        for (var i = 0; i < bytes.length; i++) value += String.fromCharCode(bytes[i]);
        return btoa(value).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    }

    function responseData(credential) {
        var response = credential.response;
        return {
            id: credential.id,
            clientDataJSON: toBase64Url(response.clientDataJSON),
            authenticatorData: toBase64Url(response.authenticatorData),
            signature: toBase64Url(response.signature),
            userHandle: response.userHandle ? toBase64Url(response.userHandle) : ''
        };
    }

    button.on('click', function () {
        button.prop('disabled', true).text('<?php _e('请在设备上确认'); ?>');
        fetch(passkeyUrl + '?do=get-options', {credentials: 'same-origin'})
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data.success) throw new Error(data.message || 'Passkey 请求失败');
                var publicKey = data.options.publicKey;
                publicKey.challenge = fromBase64Url(publicKey.challenge);
                return navigator.credentials.get({publicKey: publicKey});
            })
            .then(function (credential) {
                return fetch(passkeyUrl + '?do=process-get', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(Object.assign(responseData(credential), {
                        remember: $('#remember').is(':checked') ? 1 : 0
                    }))
                });
            })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data.success) throw new Error(data.message || 'Passkey 验证失败');
                window.location.href = data.redirect;
            })
            .catch(function (error) {
                alert(error.message || 'Passkey 登录失败');
                button.prop('disabled', false).text('<?php _e('使用 Passkey 登录'); ?>');
            });
    });
});
</script>
<?php
include 'footer.php';
?>
