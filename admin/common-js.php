<?php if(!defined('__TYPECHO_ADMIN__')) exit; ?>
<script src="<?php $options->adminStaticUrl('js', 'jquery.js'); ?>"></script>
<script src="<?php $options->adminStaticUrl('js', 'jquery-ui.js'); ?>"></script>
<script src="<?php $options->adminStaticUrl('js', 'typecho.js'); ?>"></script>
<script>
    (function () {
        $(document).ready(function() {
            // 处理消息机制
            (function () {
                var prefix = '<?php echo \Typecho\Cookie::getPrefix(); ?>',
                    cookies = {
                        notice      :   $.cookie(prefix + '__typecho_notice'),
                        noticeType  :   $.cookie(prefix + '__typecho_notice_type'),
                        highlight   :   $.cookie(prefix + '__typecho_notice_highlight')
                    },
                    path = '<?php echo \Typecho\Cookie::getPath(); ?>',
                    domain = '<?php echo \Typecho\Cookie::getDomain(); ?>',
                    secure = <?php echo json_encode(\Typecho\Cookie::getSecure()); ?>;

                if (!!cookies.notice && 'success|notice|error'.indexOf(cookies.noticeType) >= 0) {
                    var head = $('.typecho-head-nav'), p, offset = 0, ul = $('<ul>').appendTo($('<div>'));
                    var noticeType = /^(success|notice|error)$/.exec(cookies.noticeType) ? cookies.noticeType : 'notice';

                    // 逐条构建节点, 使用 text() 转义内容, 防止 Cookie 中包含的用户数据注入 DOM
                    var items = null;
                    try { items = JSON.parse(cookies.notice); } catch (e) { items = null; }
                    if (!$.isArray(items)) { items = []; }

                    ul = $('<ul>');
                    $.each(items, function (i, item) {
                        $('<li>').text(String(item)).appendTo(ul);
                    });

                    p = $('<div class="message popup">').addClass(noticeType).append(ul);

                    if (head.length > 0) {
                        p.insertAfter(head);
                    } else {
                        p.prependTo(document.body);
                    }

                    p.slideDown(function () {
                        var t = $(this), color = '#C6D880';
                        
                        if (t.hasClass('error')) {
                            color = '#FBC2C4';
                        } else if (t.hasClass('notice')) {
                            color = '#FFD324';
                        }

                        t.effect('highlight', {color : color})
                            .delay(5000).fadeOut(function () {
                            $(this).remove();
                        });
                    });

                    $.cookie(prefix + '__typecho_notice', null, {path : path, domain: domain, secure: secure});
                    $.cookie(prefix + '__typecho_notice_type', null, {path : path, domain: domain, secure: secure});
                }

                if (cookies.highlight) {
                    // highlight 仅允许合法 id 字符, 防止选择器注入
                    var highlight = cookies.highlight.replace(/[^_0-9a-zA-Z-]/g, '');
                    if (highlight) {
                        $('#' + highlight).effect('highlight', 1000);
                    }
                    $.cookie(prefix + '__typecho_notice_highlight', null, {path : path, domain: domain, secure: secure});
                }
            })();

            if ($('.typecho-login').length == 0) {
                $('a').each(function () {
                    var t = $(this), href = t.attr('href');

                    if ((href && href[0] == '#')
                        || /^<?php echo preg_quote($options->adminUrl, '/'); ?>.*$/.exec(href) 
                            || /^<?php echo substr(preg_quote(\Typecho\Common::url('s', $options->index), '/'), 0, -1); ?>action\/[_a-zA-Z0-9\/]+.*$/.exec(href)) {
                        return;
                    }

                    t.attr('target', '_blank')
                        .attr('rel', 'noopener noreferrer');
                });
            }
        });
    })();
</script>
