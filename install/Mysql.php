<?php if(!defined('__TYPECHO_ROOT_DIR__')) exit; ?>

<ul class="typecho-option">
    <li>
        <label class="typecho-label" for="dbHost"><?php _e('数据库地址'); ?></label>
        <input type="text" class="text" name="dbHost" id="dbHost" value="localhost"/>
        <p class="description"><?php _e('对于本地环境，通常使用 "%s"，其本质是指向本地服务器的域名，MySQL 连接时采用 Socket 方式，性能更佳；而 “127.0.0.1” 则是本地回环 IP 地址，通过 TCP/IP 方式连接', 'localhost'); ?></p>
    </li>
</ul>

<ul class="typecho-option">
    <li>
        <label class="typecho-label" for="dbUser"><?php _e('数据库用户名'); ?></label>
        <input type="text" class="text" name="dbUser" id="dbUser" value="" />
        <p class="description"><?php _e('为保障数据库安全，不推荐使用 "%s"，建议您为当前项目创建专属的数据库账户并使用', 'root'); ?></p>
    </li>
</ul>

<ul class="typecho-option">
    <li>
        <label class="typecho-label" for="dbPassword"><?php _e('数据库密码'); ?></label>
        <input type="password" class="text" name="dbPassword" id="dbPassword" value="" />
    </li>
</ul>
<ul class="typecho-option">
    <li>
        <label class="typecho-label" for="dbDatabase"><?php _e('数据库名'); ?></label>
        <input type="text" class="text" name="dbDatabase" id="dbDatabase" value="" />
        <p class="description"><?php _e('请您指定数据库名称'); ?></p>
    </li>

</ul>

<details>
    <summary>
        <strong><?php _e('高级选项'); ?></strong>
    </summary>
    <ul class="typecho-option">
        <li>
            <label class="typecho-label" for="dbPort"><?php _e('数据库端口'); ?></label>
            <input type="text" class="text" name="dbPort" id="dbPort" value="3306"/>
            <p class="description"><?php _e('若不确定，请保持默认选项'); ?></p>
        </li>
    </ul>

    <ul class="typecho-option">
        <li>
            <label class="typecho-label" for="dbCharset"><?php _e('数据库编码'); ?></label>
            <select name="dbCharset" id="dbCharset">
                <option value="utf8mb4">utf8mb4</option>
                <option value="utf8">utf8</option>
            </select>
            <p class="description"><?php _e('推荐保持默认选项，除非您的 MySQL 低于 5.6 版本'); ?></p>
        </li>
    </ul>

    <ul class="typecho-option">
        <li>
            <label class="typecho-label" for="dbEngine"><?php _e('数据库引擎'); ?></label>
            <select name="dbEngine" id="dbEngine">
                <option value="InnoDB">InnoDB</option>
                <option value="MyISAM">MyISAM</option>
            </select>
            <p class="description"><?php _e('推荐保持默认选项，除非您的 MySQL 低于 5.7 版本'); ?></p>
        </li>
    </ul>

    <ul class="typecho-option">
        <li>
            <label class="typecho-label" for="dbSslCa"><?php _e('数据库 SSL 证书'); ?></label>
            <input type="text" class="text" name="dbSslCa" id="dbSslCa"/>
            <p class="description"><?php _e('如果您的数据库启用了 SSL，请填写 CA 证书路径，否则请留空'); ?></p>
        </li>
    </ul>

    <ul class="typecho-option">
        <li>
            <label class="typecho-label" for="dbSslVerify"><?php _e('启用数据库 SSL 服务端证书验证'); ?></label>
            <select name="dbSslVerify" id="dbSslVerify">
                <option value="off"><?php _e('不启用'); ?></option>
                <option value="on"><?php _e('启用'); ?></option>
            </select>
        </li>
    </ul>
</details>
