<?php if(!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<?php
// 128 位 [0-9a-zA-Z] 随机文件名 (CSPRNG), 防止猜解或枚举 web 根目录内的数据库文件
$defaultDir = __TYPECHO_ROOT_DIR__ . '/usr/' . install_random_db_name() . '.db';
?>
<ul class="typecho-option">
    <li>
        <label class="typecho-label" for="dbFile"><?php _e('数据库文件路径'); ?></label>
        <input type="text" class="text" name="dbFile" id="dbFile" value="<?php echo htmlspecialchars($defaultDir, ENT_QUOTES); ?>"/>
        <p class="description"><?php _e('"%s" 是我们为您自动生成的地址', $defaultDir); ?></p>
    </li>
</ul>
