<?php if(!defined('__TYPECHO_ADMIN__')) exit; ?>
<?php
/**
 * 后台模板作用域变量: 由 admin/common.php (以及 header.php / menu.php) 通过 include 注入。
 * PHPStan 无法跨 include 传播局部变量, 这里按实际作用域显式声明。
 *
 * @var Widget\Options $options
 * @var Widget\Security $security
 */
?>
<?php \Typecho\Plugin::factory('admin/write-js.php')->call('write'); ?>

<script src="<?php $options->adminStaticUrl('js', 'timepicker.js'); ?>"></script>
<script>
$(document).ready(function() {
    // 日期时间控件
    $('#date').mask('9999-99-99 99:99').datetimepicker({
        currentText     :   <?php echo _je('现在'); ?>,
        prevText        :   <?php echo _je('上一月'); ?>,
        nextText        :   <?php echo _je('下一月'); ?>,
        monthNames      :   [<?php echo _je('一月'); ?>, <?php echo _je('二月'); ?>, <?php echo _je('三月'); ?>, <?php echo _je('四月'); ?>,
            <?php echo _je('五月'); ?>, <?php echo _je('六月'); ?>, <?php echo _je('七月'); ?>, <?php echo _je('八月'); ?>,
            <?php echo _je('九月'); ?>, <?php echo _je('十月'); ?>, <?php echo _je('十一月'); ?>, <?php echo _je('十二月'); ?>],
        dayNames        :   [<?php echo _je('星期日'); ?>, <?php echo _je('星期一'); ?>, <?php echo _je('星期二'); ?>,
            <?php echo _je('星期三'); ?>, <?php echo _je('星期四'); ?>, <?php echo _je('星期五'); ?>, <?php echo _je('星期六'); ?>],
        dayNamesShort   :   [<?php echo _je('周日'); ?>, <?php echo _je('周一'); ?>, <?php echo _je('周二'); ?>, <?php echo _je('周三'); ?>,
            <?php echo _je('周四'); ?>, <?php echo _je('周五'); ?>, <?php echo _je('周六'); ?>],
        dayNamesMin     :   [<?php echo _je('日'); ?>, <?php echo _je('一'); ?>, <?php echo _je('二'); ?>, <?php echo _je('三'); ?>,
            <?php echo _je('四'); ?>, <?php echo _je('五'); ?>, <?php echo _je('六'); ?>],
        closeText       :   <?php echo _je('完成'); ?>,
        timeOnlyTitle   :   <?php echo _je('选择时间'); ?>,
        timeText        :   <?php echo _je('时间'); ?>,
        hourText        :   <?php echo _je('时'); ?>,
        amNames         :   [<?php echo _je('上午'); ?>, 'A'],
        pmNames         :   [<?php echo _je('下午'); ?>, 'P'],
        minuteText      :   <?php echo _je('分'); ?>,
        secondText      :   <?php echo _je('秒'); ?>,

        dateFormat      :   'yy-mm-dd',
        timezone        :   <?php $options->timezone(); ?> / 60,
        hour            :   (new Date()).getHours(),
        minute          :   (new Date()).getMinutes()
    });

    // 聚焦
    $('#title').select();

    // text 自动拉伸
    Typecho.editorResize('text', '<?php $security->index('/action/ajax?do=editorResize'); ?>');

    // 标签速选面板
    const picker = $('#tag-picker');

    if (picker.length > 0) {
        const hidden = $('#tags'),
            search = $('#tag-search'),
            cloud = $('.tag-picker-cloud', picker),
            emptyText = <?php echo _je('没有匹配的标签, 回车创建'); ?>;

        const sanitize = function (v) {
            // 逗号是隐藏字段的分隔符, 名称内不允许出现; 同时剥离 HTML 特殊字符
            return String(v).replace(/[,，]/g, '').replace(/[<>&"']/g, '').replace(/^\s+|\s+$/g, '');
        };

        const current = function () {
            return (hidden.val() || '').split(/[,，]/).map(function (s) {
                return s.replace(/^\s+|\s+$/g, '');
            }).filter(function (s) {
                return s;
            });
        };

        const render = function () {
            const items = current();

            cloud.children('.tag-picker-item').each(function () {
                $(this).toggleClass('tag-picker-active', $.inArray($(this).attr('data-name'), items) > -1);
            });
        };

        const add = function (name) {
            const items = current();
            if ($.inArray(name, items) < 0) {
                items.push(name);
                hidden.val(items.join(','));
                ensureCloudItem(name);
                render();
            }
        };

        const remove = function (name) {
            const items = $.grep(current(), function (n) {
                return n !== name;
            });
            hidden.val(items.join(','));
            render();
        };

        const ensureCloudItem = function (name) {
            if (cloud.children('.tag-picker-item[data-name="' + $.escapeSelector(name) + '"]').length < 1) {
                const item = $('<button type="button" class="tag-picker-item tag-picker-new"></button>')
                    .attr('data-name', name).text(name);
                const empty = $('.tag-picker-empty', cloud);

                if (empty.length > 0) {
                    empty.before(item);
                } else {
                    cloud.append(item);
                }
            }
        };

        const filter = function () {
            const key = (search.val() || '').toLowerCase(),
                empty = $('.tag-picker-empty', picker);
            let shown = 0;

            cloud.children('.tag-picker-item').each(function () {
                const match = !key || $(this).attr('data-name').toLowerCase().indexOf(key) > -1;

                $(this).toggle(match);
                if (match) shown++;
            });

            if (empty.length < 1) {
                cloud.append($('<span class="tag-picker-empty"></span>').text(emptyText));
            }

            empty.toggle(shown < 1);
        };

        const toggle = function (name) {
            if ($.inArray(name, current()) > -1) {
                remove(name);
            } else {
                add(name);
            }
        };

        cloud.on('click', '.tag-picker-item', function (e) {
            e.preventDefault();
            toggle($(this).attr('data-name'));
        });

        $('#tag-clear').click(function (e) {
            e.preventDefault();
            hidden.val('');
            render();
        });

        search.on('input', filter).on('keydown', function (e) {
            // 中文输入法组合中的回车是确认候选词, 不应触发选签/新建
            if (e.originalEvent && e.originalEvent.isComposing) {
                return;
            }

            if (13 === e.keyCode || 188 === e.keyCode) {
                e.preventDefault();

                // 输入中含逗号时视为批量提交
                const names = search.val().split(/[,，]/).map(function (v) {
                    return sanitize(v);
                }).filter(function (v) {
                    return v;
                });

                if (names.length > 1) {
                    names.forEach(add);
                    search.val('');
                    filter();
                    return;
                }

                const exact = cloud.children(
                    '.tag-picker-item[data-name="' + $.escapeSelector(names[0] || '\u0000') + '"]:visible');

                if (exact.length > 0) {
                    toggle(exact.attr('data-name'));
                } else {
                    const first = cloud.children('.tag-picker-item:visible').first();

                    if (first.length > 0 && !search.val()) {
                        toggle(first.attr('data-name'));
                    } else {
                        const name = sanitize(search.val());
                        if (name) {
                            add(name);
                        }
                    }
                }

                search.val('');
                filter();
            } else if (8 === e.keyCode && !search.val()) {
                const items = current();
                if (items.length > 0) {
                    remove(items[items.length - 1]);
                }
            } else if (27 === e.keyCode) {
                search.val('');
                filter();
            }
        });

        if (cloud.children('.tag-picker-empty').length < 1) {
            cloud.append($('<span class="tag-picker-empty"></span>').text(emptyText));
        }

        render();
        filter();
    }

    // 缩略名自适应宽度
    const slug = $('#slug');

    if (slug.length > 0) {
        const wrap = $('<div />').css({
            'position'  :   'relative',
            'display'   :   'inline-block'
        }),
        justifySlug = $('<pre />').css({
            'display'   :   'block',
            'visibility':   'hidden',
            'height'    :   slug.height(),
            'padding'   :   '0 2px',
            'margin'    :   0
        }).insertAfter(slug.wrap(wrap).css({
            'left'      :   0,
            'top'       :   0,
            'minWidth'  :   '5px',
            'position'  :   'absolute',
            'width'     :   '100%'
        }));

        function justifySlugWidth() {
            const val = slug.val();
            justifySlug.text(val.length > 0 ? val : '     ');
        }

        slug.bind('input propertychange', justifySlugWidth);
        justifySlugWidth();
    }

    // 处理保存文章的逻辑
    const form = $('form[name=write_post],form[name=write_page]'),
        idInput = $('input[name=cid]'),
        draft = $('input[name=draft]'),
        btnPreview = $('#btn-preview'),
        autoSave = $('<span id="auto-save-message"></span>').prependTo('.left');

    let cid = idInput.val(),
        draftId = draft.length > 0 ? draft.val() : 0,
        changed = false,
        written = false,
        lastSaveTime = null;

    form.on('write', function () {
        written = true;
        form.trigger('datachange');
    });

    form.on('change', function () {
        if (written) {
            form.trigger('datachange');
        }
    });

    $('button[name=do]').click(function () {
        $('input[name=do]').val($(this).val());
    });

    // 自动检测离开页
    $(window).bind('beforeunload', function () {
        if (changed && !form.hasClass('submitting')) {
            return <?php echo _je('内容已经改变尚未保存, 您确认要离开此页面吗?'); ?>;
        }
    });

    // 发送保存请求
    Typecho.savePost = function(cb) {
        if (!changed) {
            cb && cb();
            return;
        }

        const callback = function (o) {
            lastSaveTime = o.time;
            cid = o.cid;
            draftId = o.draftId;
            idInput.val(cid);
            autoSave.text(<?php echo _je('已保存'); ?> + ' (' + o.time + ')').effect('highlight', 1000);

            cb && cb();
        };

        changed = false;
        autoSave.text(<?php echo _je('正在保存'); ?>);

        const data = new FormData(form.get(0));
        data.append('do', 'save');
        form.triggerHandler('submit');

        $.ajax({
            url: form.attr('action'),
            processData: false,
            contentType: false,
            type: 'POST',
            data: data,
            success: callback,
            error: function () {
                autoSave.text(<?php echo _je('保存失败, 请重试'); ?>);
            },
            complete: function () {
                form.trigger('submitted');
            }
        });
    };

    <?php if ($options->autoSave): ?>
    // 自动保存
    let saveTimer = null;
    let stopAutoSave = false;

    form.on('datachange', function () {
        changed = true;
        autoSave.text(<?php echo _je('尚未保存'); ?> + (lastSaveTime ? ' (<?php echo _js('上次保存时间'); ?>: ' + lastSaveTime + ')' : ''));

        if (saveTimer) {
            clearTimeout(saveTimer);
        }

        saveTimer = setTimeout(function () {
            !stopAutoSave && Typecho.savePost();
        }, 3000);
    }).on('submit', function () {
        stopAutoSave = true;
    }).on('submitted', function () {
        stopAutoSave = false;
    });
    <?php else: ?>
    form.on('datachange', function () {
        changed = true;
    });
    <?php endif; ?>

    // 计算夏令时偏移
    const dstOffset = (function () {
        const d = new Date(),
            jan = new Date(d.getFullYear(), 0, 1),
            jul = new Date(d.getFullYear(), 6, 1),
            stdOffset = Math.max(jan.getTimezoneOffset(), jul.getTimezoneOffset());

        return stdOffset - d.getTimezoneOffset();
    })();
    
    if (dstOffset > 0) {
        $('<input name="dst" type="hidden" />').appendTo(form).val(dstOffset);
    }

    // 时区
    $('<input name="timezone" type="hidden" />').appendTo(form).val(- (new Date).getTimezoneOffset() * 60);

    // 预览功能
    let isFullScreen = false;

    function previewData(cid) {
        isFullScreen = $(document.body).hasClass('fullscreen');
        $(document.body).addClass('fullscreen preview');

        const frame = $('<iframe frameborder="0" class="preview-frame preview-loading"></iframe>')
            .attr('src', './preview.php?cid=' + cid)
            .attr('sandbox', 'allow-same-origin allow-scripts')
            .appendTo(document.body);

        frame.load(function () {
            frame.removeClass('preview-loading');
        });

        frame.height($(window).height() - 53);
    }

    function cancelPreview() {
        if (!isFullScreen) {
            $(document.body).removeClass('fullscreen');
        }

        $(document.body).removeClass('preview');
        $('.preview-frame').remove();
    }

    $('#btn-cancel-preview').click(cancelPreview);

    $(window).bind('message', function (e) {
        if (e.originalEvent.data === 'cancelPreview') {
            cancelPreview();
        }
    });

    btnPreview.click(function () {
        if (changed) {
            if (confirm(<?php echo _je('修改后的内容需要保存后才能预览, 是否保存?'); ?>)) {
                Typecho.savePost(function () {
                    previewData(draftId);
                });
            }
        } else if (!!draftId) {
            previewData(draftId);
        } else if (!!cid) {
            previewData(cid);
        }
    });

    // 控制选项和附件的切换
    $('#edit-secondary .typecho-option-tabs li').click(function() {
        $('#edit-secondary .typecho-option-tabs li.active').removeClass('active');
        $('#edit-secondary .tab-content').addClass('hidden');

        const activeTab = $(this).addClass('active').find('a').attr('href');
        $(activeTab).removeClass('hidden');

        return false;
    });

    // 自动隐藏密码框
    $('#visibility').change(function () {
        const val = $(this).val(), password = $('#post-password');

        if ('password' === val) {
            password.removeClass('hidden');
        } else {
            password.addClass('hidden');
        }
    });
    
    // 草稿删除确认
    $('.edit-draft-notice a').click(function () {
        if (confirm(<?php echo _je('您确认要删除这份草稿吗?'); ?>)) {
            window.location.href = $(this).attr('href');
        }

        return false;
    });
});
</script>

