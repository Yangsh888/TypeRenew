<?php

if (!file_exists(dirname(__FILE__) . '/config.inc.php')) {
    define('__TYPECHO_ROOT_DIR__', dirname(__FILE__));
    define('__TYPECHO_PLUGIN_DIR__', '/usr/plugins');
    define('__TYPECHO_THEME_DIR__', '/usr/themes');
    define('__TYPECHO_ADMIN_DIR__', '/admin/');
    require_once __TYPECHO_ROOT_DIR__ . '/var/Typecho/Common.php';
    \Typecho\Common::init();
} else {
    require_once dirname(__FILE__) . '/config.inc.php';
    $installDb = \Typecho\Db::get();
}

/**
 * get lang
 *
 * @return string
 */
function install_get_lang(): string
{
    return \Utils\Defaults::language();
}

/**
 * detect cli mode
 *
 * @return bool
 */
function install_is_cli(): bool
{
    return \Typecho\Request::getInstance()->isCli();
}

function install_default_db_prefix(): string
{
    return 'typerenew_';
}

function install_default_sqlite_file(): string
{
    return __TYPECHO_ROOT_DIR__ . '/usr/' . uniqid() . '.db';
}

/**
 * list all default options
 *
 * @return array
 */
function install_get_default_options(): array
{
    static $options;

    if (empty($options)) {
        $options = \Utils\Defaults::installSeedOptions([
            'lang' => install_get_lang(),
            'siteUrl' => \Utils\Defaults::siteUrl(),
        ]);
    }

    return $options;
}

/**
 * get database driver type
 *
 * @param string $driver
 * @return string
 */
function install_get_db_type(string $driver): string
{
    $parts = explode('_', $driver);
    return $driver == 'Mysqli' ? 'Mysql' : array_pop($parts);
}

/**
 * list all available database drivers
 *
 * @return array
 */
function install_get_db_drivers(): array
{
    $drivers = [];

    if (\Typecho\Db\Adapter\Pdo\Mysql::isAvailable()) {
        $drivers['Pdo_Mysql'] = _t('Pdo 驱动 Mysql 适配器');
    }

    if (\Typecho\Db\Adapter\Pdo\SQLite::isAvailable()) {
        $drivers['Pdo_SQLite'] = _t('Pdo 驱动 SQLite 适配器');
    }

    if (\Typecho\Db\Adapter\Pdo\Pgsql::isAvailable()) {
        $drivers['Pdo_Pgsql'] = _t('Pdo 驱动 PostgreSql 适配器');
    }

    if (\Typecho\Db\Adapter\Mysqli::isAvailable()) {
        $drivers['Mysqli'] = _t('Mysql 原生函数适配器');
    }

    if (\Typecho\Db\Adapter\SQLite::isAvailable()) {
        $drivers['SQLite'] = _t('SQLite 原生函数适配器');
    }

    if (\Typecho\Db\Adapter\Pgsql::isAvailable()) {
        $drivers['Pgsql'] = _t('Pgsql 原生函数适配器');
    }

    return $drivers;
}

/**
 * get current db driver
 *
 * @return string
 */
function install_get_current_db_driver(): string
{
    global $installDb;

    if (empty($installDb)) {
        $driver = \Typecho\Request::getInstance()->get('driver');
        $drivers = install_get_db_drivers();

        if (empty($driver) || !isset($drivers[$driver])) {
            return key($drivers);
        }

        return $driver;
    } else {
        return $installDb->getAdapterName();
    }
}

/**
 * generate config file
 *
 * @param string $adapter
 * @param string $dbPrefix
 * @param array $dbConfig
 * @param bool $return
 * @return string
 */
function install_config_file(string $adapter, string $dbPrefix, array $dbConfig, bool $return = false): string
{
    global $configWritten;

    $code = "<" . "?php
// site root path
define('__TYPECHO_ROOT_DIR__', dirname(__FILE__));

// plugin directory (relative path)
define('__TYPECHO_PLUGIN_DIR__', '/usr/plugins');

// theme directory (relative path)
define('__TYPECHO_THEME_DIR__', '/usr/themes');

// admin directory (relative path)
define('__TYPECHO_ADMIN_DIR__', '/admin/');

require_once __TYPECHO_ROOT_DIR__ . '/var/Typecho/Common.php';

\Typecho\Common::init();

// config db
\$db = new \Typecho\Db('{$adapter}', '{$dbPrefix}');
\$db->addServer(" . (var_export($dbConfig, true)) . ", \Typecho\Db::READ | \Typecho\Db::WRITE);
\Typecho\Db::set(\$db);
";

    $configWritten = false;

    if (!$return) {
        $configPath = __TYPECHO_ROOT_DIR__ . '/config.inc.php';
        $configWritten = is_writable(__TYPECHO_ROOT_DIR__)
            && file_put_contents($configPath, $code) !== false;
    }

    return $code;
}

function install_remove_config_file()
{
    global $configWritten;

    if ($configWritten) {
        unlink(__TYPECHO_ROOT_DIR__ . '/config.inc.php');
    }
}

function install_check(string $type): bool
{
    switch ($type) {
        case 'config':
            return file_exists(__TYPECHO_ROOT_DIR__ . '/config.inc.php');
        case 'db_structure':
        case 'db_data':
            global $installDb;

            if (empty($installDb)) {
                return false;
            }

            try {
                $installed = $installDb->fetchRow($installDb->select()->from('table.options')
                    ->where('user = 0 AND name = ?', 'installed'));

                if ($type == 'db_data' && empty($installed['value'])) {
                    return false;
                }
            } catch (\Typecho\Db\Adapter\ConnectionException) {
                return false;
            } catch (\Typecho\Db\Adapter\SQLException) {
                return false;
            }

            return true;
        default:
            return false;
    }
}

function install_raise_error($error, $config = null)
{
    if (install_is_cli()) {
        if (is_array($error)) {
            foreach ($error as $key => $value) {
                echo (is_int($key) ? '' : $key . ': ') . $value . "\n";
            }
        } else {
            echo $error . "\n";
        }

        exit(1);
    } else {
        install_throw_json([
            'success' => 0,
            'message' => $error,
            'config' => $config
        ]);
    }
}

function install_success($step, ?array $config = null)
{
    global $installDb;

    if (install_is_cli()) {
        if ($step == 3) {
            \Typecho\Db::set($installDb);
        }

        if ($step > 0) {
            $method = 'install_step_' . $step . '_perform';
            $method();
        }

        if (!empty($config)) {
            [$userName, $userPassword] = $config;
            echo _t('安装成功') . "\n";
            echo _t('您的用户名是') . " {$userName}\n";
            echo _t('您的密码是') . " {$userPassword}\n";
        }

        exit(0);
    } else {
        install_throw_json([
            'success' => 1,
            'message' => $step,
            'config'  => $config
        ]);
    }
}

function install_throw_json($data)
{
    \Typecho\Response::getInstance()->setContentType('application/json')
        ->addResponder(function () use ($data) {
            echo json_encode($data);
        })
        ->respond();
}

function install_redirect(string $url)
{
    \Typecho\Response::getInstance()->setStatus(302)
        ->setHeader('Location', $url)
        ->respond();
}

function install_js_support()
{
    ?>
    <div id="success" class="row typecho-page-main hidden">
        <div class="col-mb-12 col-tb-8 col-tb-offset-2">
            <div class="typecho-page-title">
                <h2><?php _e('安装成功'); ?></h2>
            </div>
            <div id="typecho-welcome">
                <p class="keep-word">
                    <?php _e('恭喜！您的 TypeRenew 安装已完成'); ?>
                </p>
                <p class="fresh-word">
                    <?php _e('您的用户名是'); ?>：<strong class="warning" id="success-user"></strong><br>
                    <?php _e('您的密码是'); ?>：<strong class="warning" id="success-password"></strong>
                </p>
                <ul>
                    <li><a id="login-url" href=""><?php _e('访问后台控制面板'); ?></a></li>
                    <li><a id="site-url" href=""><?php _e('查看您的 Blog'); ?></a></li>
                </ul>
                <p><?php _e('希望您能尽情享用 TypeRenew 带来的乐趣!'); ?></p>
            </div>
        </div>
    </div>
    <script>
        let form = $('form'), errorBox = $('<div></div>');

        errorBox.addClass('message error')
            .prependTo(form);

        function showError(error) {
            if (typeof error == 'string') {
                $(window).scrollTop(0);

                errorBox
                    .text(error)
                    .addClass('fade');
            } else {
                for (let k in error) {
                    let input = $('#' + k), msg = error[k], p = $('<p></p>');

                    p.addClass('message error')
                        .text(msg)
                        .insertAfter(input);

                    input.on('input', function () {
                        p.remove();
                    });
                }
            }

            return errorBox;
        }

        form.submit(function (e) {
            e.preventDefault();

            errorBox.removeClass('fade');
            $('button', form).attr('disabled', 'disabled');
            $('.typecho-option .error', form).remove();

            $.ajax({
                url: form.attr('action'),
                processData: false,
                contentType: false,
                type: 'POST',
                data: new FormData(this),
                success: function (data) {
                    $('button', form).removeAttr('disabled');

                    if (data.success) {
                        if (data.message) {
                            location.href = '?step=' + data.message;
                        } else {
                            let success = $('#success').removeClass('hidden');

                            form.addClass('hidden');

                            if (data.config) {
                                success.addClass('fresh');

                                $('.typecho-page-main:first').addClass('hidden');
                                $('#success-user').text(data.config[0]);
                                $('#success-password').text(data.config[1]);

                                $('#login-url').attr('href', data.config[2]);
                                $('#site-url').attr('href', data.config[3]);
                            } else {
                                success.addClass('keep');
                            }
                        }
                    } else {
                        let el = showError(data.message);

                        if (typeof configError == 'function' && data.config) {
                            configError(form, data.config, el);
                        }
                    }
                },
                error: function (xhr, error) {
                    showError(error)
                }
            });
        });
    </script>
    <?php
}

/**
 * @param string[] $extensions
 * @return string|null
 */
function install_check_extension(array $extensions): ?string
{
    foreach ($extensions as $extension) {
        if (extension_loaded($extension)) {
            return null;
        }
    }

    return _n('缺少PHP扩展', '请在服务器上安装以下PHP扩展中的至少一个', count($extensions))
        . ': ' . implode(', ', $extensions);
}

function install_step_1()
{
    $langs = \Widget\Options\General::getLangs();
    $lang = install_get_lang();
    ?>
    <div class="row typecho-page-main">
        <div class="col-mb-12 col-tb-8 col-tb-offset-2">
            <div class="typecho-page-title">
                <h2><?php _e('欢迎使用 TypeRenew'); ?></h2>
            </div>
            <div id="typecho-welcome">
                <form autocomplete="off" method="post" action="install.php">
                    <p class="warning">
                        <strong><?php _e('TypeRenew 脱胎于经典博客程序 <a href="https://typecho.org/">Typecho</a>，完整继承其干净、克制、高效的内核基因，并优化对现代主流运行环境的适配，修复遗留的兼容性问题，原生集成更多实用特性，让跨越十余年的经典，在当下重焕新生。'); ?></strong>
                    </p>
                    <h3><?php _e('安装说明'); ?></h3>
                    <ul>
                        <li><?php _e('本安装程序将自动检测您的服务器环境是否符合 TypeRenew 最低运行配置要求。'); ?></li>
                        <li><?php _e('若环境不满足运行标准，页面顶部将出现明确提示，请您参照提示检查并调整主机配置。'); ?></li>
                        <li><?php _e('若环境符合全部要求，页面下方将出现「开始下一步」按钮，点击即可快速完成程序安装。'); ?></li>
                    </ul>
                    <h3><?php _e('许可及协议'); ?></h3>
                    <ul>
                        <li><?php _e('本程序 TypeRenew 基于原项目 <a href="https://typecho.org/">Typecho</a> 进行二次开发，完整继承并严格遵守 GPL v2 开源许可协议。您可以在 <a href="https://www.gnu.org/copyleft/gpl.html">GPL</a> 协议允许的范围内，自由使用、拷贝、修改和分发本程序，无论是用于商业还是非商业目的。'); ?></li>
                        <li><?php _e('TypeRenew 由开源社区驱动维护，旨在继承 <a href="https://typecho.org/">Typecho</a> 的轻量与优雅，并为其注入新的活力。'); ?></li>
                        <li><?php _e('若在使用中遇到问题或是有新功能建议，欢迎在 <a href="https://github.com/Yangsh888/TypeRenew">GitHub</a> 中交流反馈，也可直接向项目提交代码贡献。'); ?></li>
                        <li><?php _e('欢迎所有开发者、设计师和用户的反馈、建议与贡献，每一份力量都将帮助 TypeRenew 更好地成长。'); ?></li>
                    </ul>

                    <p class="submit">
                        <button class="btn primary" type="submit"><?php _e('我已阅读并同意上述协议内容，开始下一步 &raquo;'); ?></button>
                        <input type="hidden" name="step" value="1">

                        <?php if (count($langs) > 1) : ?>
                            <select style="float: right" onchange="location.href='?lang=' + this.value">
                                <?php foreach ($langs as $key => $val) : ?>
                                    <option value="<?php echo $key; ?>"<?php if ($lang == $key) :
                                        ?> selected<?php
                                                   endif; ?>><?php echo $val; ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </p>
                </form>
            </div>
        </div>
    </div>
    <?php
}

/**
 * check dependencies before install
 */
function install_step_1_perform()
{
    $errors = [];
    if (version_compare(PHP_VERSION, '8.0.0', '<')) {
        $errors[] = _t('当前 PHP 版本为 %s，TypeRenew 最低要求 PHP 8.0，请升级后继续安装。', PHP_VERSION);
    }
    $checks = [
        'mbstring',
        'json',
        'Reflection',
        ['mysqli', 'sqlite3', 'pgsql', 'pdo_mysql', 'pdo_sqlite', 'pdo_pgsql']
    ];

    foreach ($checks as $check) {
        $error = install_check_extension(is_array($check) ? $check : [$check]);

        if (!empty($error)) {
            $errors[] = $error;
        }
    }

    $uploadDir = '/usr/uploads';
    $realUploadDir = \Typecho\Common::url($uploadDir, __TYPECHO_ROOT_DIR__);
    $writeable = true;
    if (is_dir($realUploadDir)) {
        if (!is_writable($realUploadDir) || !is_readable($realUploadDir)) {
            set_error_handler(static function (): bool {
                return true;
            });
            try {
                $chmodOk = chmod($realUploadDir, 0755);
            } finally {
                restore_error_handler();
            }

            if (!$chmodOk && (!is_writable($realUploadDir) || !is_readable($realUploadDir))) {
                $writeable = false;
            }
        }
    } else {
        $parent = dirname($realUploadDir);
        if (!is_dir($parent) || !is_writable($parent) || (!mkdir($realUploadDir, 0755) && !is_dir($realUploadDir))) {
            $writeable = false;
        }
    }

    if (!$writeable) {
        $errors[] = _t('上传目录暂无写入权限，请先检查您的站点是否存在 %s 目录；若存在，请将该目录权限设置为可写状态，完成后再继续安装操作。', $uploadDir);
    }

    if (empty($errors)) {
        install_success(2);
    } else {
        install_raise_error(implode("\n", $errors));
    }
}

function install_assert_mysql_compatibility(\Typecho\Db $db): void
{
    $rawVersion = $db->getVersion(\Typecho\Db::READ);
    $version = \Utils\DbInfo::extractVersion($rawVersion);

    if ($version === '') {
        install_raise_error(_t('无法识别当前 MySQL 服务端版本：%s', $rawVersion));
    }

    $minimum = \Utils\DbInfo::minimumMysqlVersion($rawVersion);
    $label = \Utils\DbInfo::mysqlLabel($rawVersion);

    if (version_compare($version, $minimum, '<')) {
        install_raise_error(_t('当前 %s 版本为 %s，安装器最低要求 %s %s，请升级后继续安装。', $label, $rawVersion, $label, $minimum));
    }
}

function install_resolve_mysql_collation(\Typecho\Db $db, string $charset): string
{
    $rawVersion = $db->getVersion(\Typecho\Db::READ);
    return \Utils\DbInfo::resolveMysqlCollation($charset, $rawVersion);
}

function install_split_sql_statements(string $sql): array
{
    $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql) ?? $sql;
    $length = strlen($sql);
    $statements = [];
    $buffer = '';
    $quote = null;
    $lineComment = false;
    $blockComment = false;

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $i + 1 < $length ? $sql[$i + 1] : '';

        if ($lineComment) {
            if ($char === "\n") {
                $lineComment = false;
            }
            continue;
        }

        if ($blockComment) {
            if ($char === '*' && $next === '/') {
                $blockComment = false;
                $i++;
            }
            continue;
        }

        if ($quote === null) {
            if ($char === '-' && $next === '-') {
                $after = $i + 2 < $length ? $sql[$i + 2] : '';
                if ($after === '' || $after === ' ' || $after === "\t" || $after === "\r" || $after === "\n") {
                    $lineComment = true;
                    $i++;
                    continue;
                }
            }

            if ($char === '#') {
                $lineComment = true;
                continue;
            }

            if ($char === '/' && $next === '*') {
                $blockComment = true;
                $i++;
                continue;
            }

            if ($char === '\'' || $char === '"' || $char === '`') {
                $quote = $char;
                $buffer .= $char;
                continue;
            }

            if ($char === ';') {
                $statement = trim($buffer);
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $buffer = '';
                continue;
            }

            $buffer .= $char;
            continue;
        }

        $buffer .= $char;

        if (($quote === '\'' || $quote === '"') && $char === '\\') {
            if ($i + 1 < $length) {
                $buffer .= $sql[++$i];
            }
            continue;
        }

        if ($char !== $quote) {
            continue;
        }

        if (($quote === '\'' || $quote === '"') && $next === $quote) {
            $buffer .= $sql[++$i];
            continue;
        }

        $quote = null;
    }

    $statement = trim($buffer);
    if ($statement !== '') {
        $statements[] = $statement;
    }

    return $statements;
}

/**
 * display step 2
 */
function install_step_2()
{
    global $installDb;

    $drivers = install_get_db_drivers();
    $adapter = install_get_current_db_driver();
    $type = install_get_db_type($adapter);

    if (!empty($installDb)) {
        $config = $installDb->getConfig(\Typecho\Db::WRITE)->toArray();
        $config['prefix'] = $installDb->getPrefix();
        $config['adapter'] = $adapter;
    }
    ?>
    <div class="row typecho-page-main">
        <div class="col-mb-12 col-tb-8 col-tb-offset-2">
            <div class="typecho-page-title">
                <h2><?php _e('站点配置初始化'); ?></h2>
            </div>
            <form autocomplete="off" action="install.php" method="post">
                <ul class="typecho-option">
                    <li>
                        <label for="dbAdapter" class="typecho-label"><?php _e('数据库适配器'); ?></label>
                        <select name="dbAdapter" id="dbAdapter" onchange="location.href='?step=2&driver=' + this.value">
                            <?php foreach ($drivers as $driver => $name) : ?>
                                <option value="<?php echo $driver; ?>"<?php if ($driver == $adapter) :
                                    ?> selected="selected"<?php
                                               endif; ?>><?php echo $name; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php _e('请根据您使用的数据库类型选择对应的适配器；若不确定，请保持默认选项'); ?></p>
                        <input type="hidden" id="dbNext" name="dbNext" value="none">
                    </li>
                </ul>
                <ul class="typecho-option">
                    <li>
                        <label class="typecho-label" for="dbPrefix"><?php _e('数据库前缀'); ?></label>
                        <input type="text" class="text" name="dbPrefix" id="dbPrefix" value="<?php echo install_default_db_prefix(); ?>" />
                        <p class="description"><?php _e('默认前缀是 "%s"，您也可以根据自己的博客名称自定义设置', install_default_db_prefix()); ?></p>
                    </li>
                </ul>
                <?php require_once './install/' . $type . '.php'; ?>

                <ul class="typecho-option typecho-option-submit">
                    <li>
                        <button id="confirm" type="submit" class="btn primary"><?php _e('确认配置并开始安装 &raquo;'); ?></button>
                        <input type="hidden" name="step" value="2">
                    </li>
                </ul>
            </form>
        </div>
    </div>
    <script>
        function configError(form, config, errorBox) {
            let next = $('#dbNext'),
                line = $('<p></p>');

            if (config.code) {
                let text = $('<textarea></textarea>'),
                    btn = $('<button></button>');

                btn.html('<?php _e('创建完毕, 继续安装 &raquo;'); ?>')
                    .attr('type', 'button')
                    .addClass('btn btn-s primary');

                btn.click(function () {
                    next.val('config');
                    form.trigger('submit');
                });

                text.val(config.code)
                    .addClass('mono')
                    .attr('readonly', 'readonly');

                errorBox.append(text)
                    .append(btn);
                return;
            }

            errorBox.append(line);

            for (let key in config) {
                let word = config[key],
                    btn = $('<button></button>');

                btn.html(word)
                    .attr('type', 'button')
                    .addClass('btn btn-s primary')
                    .click(function () {
                        next.val(key);
                        form.trigger('submit');
                    });

                line.append(btn);
            }
        }

        $('#confirm').click(function () {
            $('#dbNext').val('none');
        });

        <?php if (!empty($config)) : ?>
        function fillInput(config) {
            for (let k in config) {
                let value = config[k],
                    key = 'db' + k.charAt(0).toUpperCase() + k.slice(1),
                    input = $('#' + key)
                        .attr('readonly', 'readonly')
                        .val(value);

                $('option:not(:selected)', input).attr('disabled', 'disabled');
            }
        }

        fillInput(<?php echo json_encode($config); ?>);
        <?php endif; ?>
    </script>
    <?php
}

/**
 * perform install step 2
 */
function install_step_2_perform()
{
    global $installDb;

    $request = \Typecho\Request::getInstance();
    $drivers = install_get_db_drivers();

    $configMap = [
        'Mysql' => [
            'dbHost' => 'localhost',
            'dbPort' => 3306,
            'dbUser' => null,
            'dbPassword' => null,
            'dbCharset' => 'utf8mb4',
            'dbDatabase' => null,
            'dbEngine' => 'InnoDB',
            'dbSslCa' => null,
            'dbSslVerify' => 'off',
        ],
        'Pgsql' => [
            'dbHost' => 'localhost',
            'dbPort' => 5432,
            'dbUser' => null,
            'dbPassword' => null,
            'dbCharset' => 'utf8',
            'dbDatabase' => null,
            'dbSslVerify' => 'off',
        ],
        'SQLite' => [
            'dbFile' => install_default_sqlite_file()
        ]
    ];

    if (install_is_cli()) {
        $config = [
            'dbHost' => $request->getServer('TYPECHO_DB_HOST'),
            'dbUser' => $request->getServer('TYPECHO_DB_USER'),
            'dbPassword' => $request->getServer('TYPECHO_DB_PASSWORD'),
            'dbCharset' => $request->getServer('TYPECHO_DB_CHARSET'),
            'dbPort' => $request->getServer('TYPECHO_DB_PORT'),
            'dbDatabase' => $request->getServer('TYPECHO_DB_DATABASE'),
            'dbFile' => $request->getServer('TYPECHO_DB_FILE'),
            'dbDsn' => $request->getServer('TYPECHO_DB_DSN'),
            'dbEngine' => $request->getServer('TYPECHO_DB_ENGINE'),
            'dbPrefix' => $request->getServer('TYPECHO_DB_PREFIX', install_default_db_prefix()),
            'dbAdapter' => $request->getServer('TYPECHO_DB_ADAPTER', install_get_current_db_driver()),
            'dbNext' => $request->getServer('TYPECHO_DB_NEXT', 'none'),
            'dbSslCa' => $request->getServer('TYPECHO_DB_SSL_CA'),
            'dbSslVerify' => $request->getServer('TYPECHO_DB_SSL_VERIFY', 'off'),
        ];
    } else {
        $config = $request->from([
            'dbHost',
            'dbUser',
            'dbPassword',
            'dbCharset',
            'dbPort',
            'dbDatabase',
            'dbFile',
            'dbDsn',
            'dbEngine',
            'dbPrefix',
            'dbAdapter',
            'dbNext',
            'dbSslCa',
            'dbSslVerify',
        ]);
    }

    $error = (new \Typecho\Validate())
        ->addRule('dbPrefix', 'required', _t('数据库前缀不能为空'))
        ->addRule('dbPrefix', 'minLength', _t('数据库前缀至少 1 个字符'), 1)
        ->addRule('dbPrefix', 'maxLength', _t('数据库前缀不能超过 16 个字符'), 16)
        ->addRule('dbPrefix', 'regexp', _t('数据库前缀仅允许字母、数字和下划线'), '/^[_a-z0-9]+$/i')
        ->addRule('dbAdapter', 'required', _t('请选择数据库适配器'))
        ->addRule('dbAdapter', 'enum', _t('数据库适配器无效'), array_keys($drivers))
        ->addRule('dbNext', 'required', _t('安装流程状态无效'))
        ->addRule('dbNext', 'enum', _t('安装流程状态无效'), ['none', 'delete', 'keep', 'config'])
        ->run($config);

    if (!empty($error)) {
        install_raise_error($error);
    }

    $type = install_get_db_type($config['dbAdapter']);
    $dbConfig = [];

    foreach ($configMap[$type] as $key => $value) {
        $config[$key] = !isset($config[$key]) ? (install_is_cli() ? $value : null) : $config[$key];
    }

    switch ($type) {
        case 'Mysql':
            $error = (new \Typecho\Validate())
                ->addRule('dbHost', 'required', _t('数据库地址不能为空'))
                ->addRule('dbPort', 'required', _t('数据库端口不能为空'))
                ->addRule('dbPort', 'isInteger', _t('数据库端口必须是整数'))
                ->addRule('dbUser', 'required', _t('数据库用户名不能为空'))
                ->addRule('dbCharset', 'required', _t('数据库字符集不能为空'))
                ->addRule('dbCharset', 'enum', _t('数据库字符集仅支持 utf8mb4'), ['utf8mb4'])
                ->addRule('dbDatabase', 'required', _t('数据库名不能为空'))
                ->addRule('dbEngine', 'required', _t('数据表引擎不能为空'))
                ->addRule('dbEngine', 'enum', _t('数据表引擎仅支持 InnoDB'), ['InnoDB'])
                ->addRule('dbSslCa', static function (?string $path): bool {
                    return empty($path) || file_exists((string) $path);
                }, _t('SSL CA 证书路径无效'))
                ->addRule('dbSslVerify', 'enum', _t('SSL 校验选项无效'), ['on', 'off'])
                ->run($config);
            break;
        case 'Pgsql':
            $error = (new \Typecho\Validate())
                ->addRule('dbHost', 'required', _t('数据库地址不能为空'))
                ->addRule('dbPort', 'required', _t('数据库端口不能为空'))
                ->addRule('dbPort', 'isInteger', _t('数据库端口必须是整数'))
                ->addRule('dbUser', 'required', _t('数据库用户名不能为空'))
                ->addRule('dbCharset', 'required', _t('数据库字符集不能为空'))
                ->addRule('dbCharset', 'enum', _t('PostgreSQL 字符集仅支持 utf8'), ['utf8'])
                ->addRule('dbDatabase', 'required', _t('数据库名不能为空'))
                ->addRule('dbSslVerify', 'enum', _t('SSL 校验选项无效'), ['on', 'off'])
                ->run($config);
            break;
        case 'SQLite':
            $error = (new \Typecho\Validate())
                ->addRule('dbFile', 'required', _t('数据库文件路径不能为空'))
                ->addRule('dbFile', function (string $path) {
                    $path = trim($path);

                    if ($path === '' || str_contains($path, "\0")) {
                        return false;
                    }

                    $normalized = str_replace('\\', '/', $path);
                    if ($normalized === '' || str_ends_with($normalized, '/')) {
                        return false;
                    }

                    if (preg_match('/^[a-zA-Z]:\//', $normalized)) {
                        $normalized = substr($normalized, 3);
                    } elseif (str_starts_with($normalized, '//')) {
                        $normalized = preg_replace('/^\/\/[^\/]+\/[^\/]+\/?/u', '', $normalized, 1, $count);
                        if (!is_string($normalized) || ($count ?? 0) === 0) {
                            return false;
                        }
                    } elseif (str_starts_with($normalized, '/')) {
                        $normalized = substr($normalized, 1);
                    }

                    if ($normalized === '' || preg_match('/[<>:"|?*\r\n]/u', $normalized)) {
                        return false;
                    }

                    $segments = array_values(array_filter(explode('/', $normalized), static fn(string $segment) => $segment !== ''));
                    if (empty($segments)) {
                        return false;
                    }

                    $fileName = array_pop($segments);
                    $blocked = ['htaccess', 'htpasswd', 'gitignore', 'env', 'dockerenv', 'editorconfig', 'gitattributes', 'gitmodules'];
                    if (in_array(strtolower(pathinfo($fileName, PATHINFO_FILENAME)), $blocked, true)) {
                        return false;
                    }

                    return preg_match('/\.[^\.\/\\\\]+$/u', $fileName) === 1;
                }, _t('数据库文件路径格式不正确'))
                ->run($config);
            break;
        default:
            install_raise_error(_t('数据库配置无效'));
            break;
    }

    if (!empty($error)) {
        install_raise_error($error);
    }

    foreach ($configMap[$type] as $key => $value) {
        $dbConfig[lcfirst(substr($key, 2))] = $config[$key];
    }

    if (isset($dbConfig['port'])) {
        if (strpos($dbConfig['host'], '/') !== false && $type == 'Mysql') {
            $dbConfig['port'] = null;
        } else {
            $dbConfig['port'] = intval($dbConfig['port']);
        }
    }

    if (isset($dbConfig['sslVerify'])) {
        $dbConfig['sslVerify'] = $dbConfig['sslVerify'] == 'on' || !empty($dbConfig['sslCa']);
    }

    if (isset($dbConfig['file']) && preg_match("/^[a-z0-9]+\.[a-z0-9]{2,}$/i", $dbConfig['file'])) {
        $dbConfig['file'] = __DIR__ . '/usr/' . $dbConfig['file'];
    }

    if ($config['dbNext'] == 'config' && !install_check('config')) {
        $code = install_config_file($config['dbAdapter'], $config['dbPrefix'], $dbConfig, true);
        install_raise_error(_t('没有检测到您手动创建的配置文件, 请检查后再次创建'), ['code' => $code]);
    } elseif (empty($installDb)) {
        try {
            $installDb = new \Typecho\Db($config['dbAdapter'], $config['dbPrefix']);
            $installDb->addServer($dbConfig, \Typecho\Db::READ | \Typecho\Db::WRITE);
            $installDb->query('SELECT 1=1');
            if ($type === 'Mysql') {
                install_assert_mysql_compatibility($installDb);
            }
        } catch (\Typecho\Db\Adapter\ConnectionException $e) {
            $code = $e->getCode();
            if (('Mysql' == $type && 1049 == $code) || ('Pgsql' == $type && 7 == $code)) {
                install_raise_error(_t('数据库: "%s"不存在，请手动创建后重试', $config['dbDatabase']));
            } else {
                install_raise_error(_t('对不起, 无法连接数据库, 请先检查数据库配置再继续进行安装: "%s"', $e->getMessage()));
            }
        } catch (\Typecho\Db\Exception $e) {
            install_raise_error(_t('安装程序捕捉到以下错误: "%s". 程序被终止, 请检查您的配置信息.', $e->getMessage()));
        }

        $code = install_config_file($config['dbAdapter'], $config['dbPrefix'], $dbConfig);

        if (!install_check('config')) {
            install_raise_error(
                _t('安装程序无法自动创建 <strong>config.inc.php</strong> 文件') . "\n" .
                _t('您可以在网站根目录下手动创建 <strong>config.inc.php</strong> 文件, 并复制如下代码至其中'),
                [
                'code' => $code
                ]
            );
        }
    }

    if ($config['dbNext'] == 'delete') {
        $tables = [
            $config['dbPrefix'] . 'comments',
            $config['dbPrefix'] . 'contents',
            $config['dbPrefix'] . 'fields',
            $config['dbPrefix'] . 'mail_queue',
            $config['dbPrefix'] . 'mail_unsub',
            $config['dbPrefix'] . 'metas',
            $config['dbPrefix'] . 'options',
            $config['dbPrefix'] . 'password_resets',
            $config['dbPrefix'] . 'relationships',
            $config['dbPrefix'] . 'users'
        ];

        try {
            foreach ($tables as $table) {
                switch ($type) {
                    case 'Mysql':
                        $installDb->query("DROP TABLE IF EXISTS `{$table}`");
                        break;
                    case 'Pgsql':
                    case 'SQLite':
                        $installDb->query("DROP TABLE IF EXISTS {$table}");
                        break;
                }
            }
        } catch (\Typecho\Db\Exception $e) {
            install_raise_error(_t('安装程序捕捉到以下错误: "%s"，程序被终止，请检查您的配置信息。', $e->getMessage()));
        }
    }

    try {
        $scripts = file_get_contents(__TYPECHO_ROOT_DIR__ . '/install/' . $type . '.sql');
        if (!is_string($scripts) || $scripts === '') {
            install_raise_error(_t('安装程序无法读取数据库初始化脚本，请检查 install/%s.sql 文件是否存在且可读。', $type));
        }
        $scripts = str_replace('typecho_', $config['dbPrefix'], $scripts);

        if (isset($dbConfig['charset'])) {
            $scripts = str_replace('%charset%', $dbConfig['charset'], $scripts);
            $scripts = str_replace('%collate%', install_resolve_mysql_collation($installDb, (string) $dbConfig['charset']), $scripts);
        }

        if (isset($dbConfig['engine'])) {
            $scripts = str_replace('%engine%', $dbConfig['engine'], $scripts);
        }

        foreach (install_split_sql_statements($scripts) as $script) {
            $script = trim($script);
            if ($script) {
                $installDb->query($script, \Typecho\Db::WRITE);
            }
        }

        \Utils\Schema::ensureCoreIndexes($installDb);
        \Utils\Schema::ensureMailInfra($installDb);
    } catch (\Typecho\Db\Exception $e) {
        $code = $e->getCode();

        if (
            ('Mysql' == $type && (1050 == $code || '42S01' == $code)) ||
            ('SQLite' == $type && ('HY000' == $code || 1 == $code)) ||
            ('Pgsql' == $type && '42P07' == $code)
        ) {
            if ($config['dbNext'] == 'keep') {
                if (install_check('db_data')) {
                    install_success(0);
                } else {
                    install_success(3);
                }
            } elseif ($config['dbNext'] == 'none') {
                install_remove_config_file();

                install_raise_error(_t('安装程序检查到原有数据表已经存在.'), [
                    'delete' => _t('删除原有数据'),
                    'keep' => _t('使用原有数据')
                ]);
            }
        } else {
            install_remove_config_file();

            install_raise_error(_t('安装程序捕捉到以下错误: "%s". 程序被终止, 请检查您的配置信息.', $e->getMessage()));
        }
    }

    install_success(3);
}

/**
 * display step 3
 */
function install_step_3()
{
    $options = \Widget\Options::alloc();
    ?>
    <div class="row typecho-page-main">
        <div class="col-mb-12 col-tb-8 col-tb-offset-2">
            <div class="typecho-page-title">
                <h2><?php _e('创建您的管理员帐号'); ?></h2>
            </div>
            <form autocomplete="off" action="install.php" method="post">
                <ul class="typecho-option">
                    <li>
                        <label class="typecho-label" for="userUrl"><?php _e('网站地址'); ?></label>
                        <input autocomplete="new-password" type="text" name="userUrl" id="userUrl" class="text" value="<?php $options->rootUrl(); ?>" />
                        <p class="description"><?php _e('当前为程序自动匹配的网站根目录路径，若与实际不符，请手动调整'); ?></p>
                    </li>
                </ul>
                <ul class="typecho-option">
                    <li>
                        <label class="typecho-label" for="userName"><?php _e('用户名'); ?></label>
                        <input autocomplete="new-password" type="text" name="userName" id="userName" class="text" />
                        <p class="description"><?php _e('请填写您的用户名'); ?></p>
                    </li>
                </ul>
                <ul class="typecho-option">
                    <li>
                        <label class="typecho-label" for="userPassword"><?php _e('登录密码'); ?></label>
                        <input type="password" name="userPassword" id="userPassword" class="text" />
                        <p class="description"><?php _e('请设置 %d-%d 位登录密码。若留空，系统将自动生成随机密码（不推荐）', \Utils\Password::minLength(), \Utils\Password::maxLength()); ?></p>
                    </li>
                </ul>
                <ul class="typecho-option">
                    <li>
                        <label class="typecho-label" for="userMail"><?php _e('邮件地址'); ?></label>
                        <input autocomplete="new-password" type="text" name="userMail" id="userMail" class="text" />
                        <p class="description"><?php _e('请填写您的邮箱地址'); ?></p>
                    </li>
                </ul>
                <ul class="typecho-option typecho-option-submit">
                    <li>
                        <button type="submit" class="btn primary"><?php _e('继续安装 &raquo;'); ?></button>
                        <input type="hidden" name="step" value="3">
                    </li>
                </ul>
            </form>
        </div>
    </div>
    <?php
}

/**
 * perform step 3
 */
function install_step_3_perform()
{
    global $installDb;

    $request = \Typecho\Request::getInstance();
    $defaultPassword = \Typecho\Common::randString(8);
    $options = \Widget\Options::alloc();

    if (install_is_cli()) {
        $config = [
            'userUrl' => $request->getServer('TYPECHO_SITE_URL'),
            'userName' => $request->getServer('TYPECHO_USER_NAME', 'typecho'),
            'userPassword' => $request->getServer('TYPECHO_USER_PASSWORD'),
            'userMail' => $request->getServer('TYPECHO_USER_MAIL', 'admin@localhost.local')
        ];
    } else {
        $config = $request->from([
            'userUrl',
            'userName',
            'userPassword',
            'userMail',
        ]);
    }

    $error = (new \Typecho\Validate())
        ->addRule('userUrl', 'required', _t('请填写站点地址'))
        ->addRule('userUrl', 'url', _t('请填写一个合法的URL地址'))
        ->addRule('userName', 'required', _t('必须填写用户名称'))
        ->addRule('userName', 'xssCheck', _t('请不要在用户名中使用特殊字符'))
        ->addRule('userName', 'maxLength', _t('用户名长度超过限制, 请不要超过 32 个字符'), 32)
        ->addRule('userMail', 'required', _t('必须填写电子邮箱'))
        ->addRule('userMail', 'email', _t('电子邮箱格式错误'))
        ->addRule('userMail', 'maxLength', _t('邮箱长度超过限制, 请不要超过 200 个字符'), 200)
        ->addRule(
            'userPassword',
            [\Utils\Password::class, 'validateLength'],
            _t('密码长度需在 %d-%d 位之间', \Utils\Password::minLength(), \Utils\Password::maxLength())
        )
        ->run($config);

    if (!empty($error)) {
        install_raise_error($error);
    }

    if (empty($config['userPassword'])) {
        $config['userPassword'] = $defaultPassword;
    }

    try {
        $installDb->query(
            $installDb->insert('table.users')->rows([
                'name' => $config['userName'],
                'password' => \Utils\Password::hash($config['userPassword']),
                'mail' => $config['userMail'],
                'url' => $config['userUrl'],
                'screenName' => $config['userName'],
                'group' => 'administrator',
                'created' => \Typecho\Date::time()
            ])
        );

        $installDb->query(
            $installDb->insert('table.metas')
                ->rows([
                    'name' => _t('默认分类'),
                    'slug' => 'default',
                    'type' => 'category',
                    'description' => _t('只是一个默认分类'),
                    'count' => 1
                ])
        );

        $installDb->query($installDb->insert('table.relationships')->rows(['cid' => 1, 'mid' => 1]));

        $installDb->query(
            $installDb->insert('table.contents')->rows([
                'title' => _t('欢迎使用 TypeRenew'),
                'slug' => 'start', 'created' => \Typecho\Date::time(),
                'modified' => \Typecho\Date::time(),
                'text' => '<!--markdown-->' . _t('本程序由 TypeRenew 焕新呈现，基于 Typecho 开发，为现代化而生'),
                'authorId' => 1,
                'type' => 'post',
                'status' => 'publish',
                'commentsNum' => 1,
                'allowComment' => 1,
                'allowPing' => 1,
                'allowFeed' => 1,
                'parent' => 0
            ])
        );

        $installDb->query(
            $installDb->insert('table.contents')->rows([
                'title' => _t('关于'),
                'slug' => 'start-page',
                'created' => \Typecho\Date::time(),
                'modified' => \Typecho\Date::time(),
                'text' => '<!--markdown-->' . _t('本页面由 TypeRenew 创建, 这只是个测试页面.'),
                'authorId' => 1,
                'order' => 0,
                'type' => 'page',
                'status' => 'publish',
                'commentsNum' => 0,
                'allowComment' => 1,
                'allowPing' => 1,
                'allowFeed' => 1,
                'parent' => 0
            ])
        );

        $installDb->query(
            $installDb->insert('table.comments')->rows([
                'cid' => 1, 'created' => \Typecho\Date::time(),
                'author' => 'TypeRenew',
                'ownerId' => 1,
                'url' => 'https://github.com/Yangsh888/TypeRenew',
                'ip' => '127.0.0.1',
                'agent' => $options->generator,
                'text' => _t('感恩相遇，为你而来.'),
                'type' => 'comment',
                'status' => 'approved',
                'parent' => 0
            ])
        );

        foreach (install_get_default_options() as $key => $value) {
            if ($key == 'installed') {
                $value = 1;
            }

            $installDb->query(
                $installDb->insert('table.options')->rows(['name' => $key, 'user' => 0, 'value' => $value])
            );
        }
    } catch (\Typecho\Db\Exception $e) {
        install_raise_error($e->getMessage());
    }

    \Typecho\Cookie::set('__typecho_remember_name', $config['userName']);
    $loginUrl = \Typecho\Common::url(
        'login.php?referer=' . urlencode((string) $options->adminUrl),
        $options->adminUrl
    );

    install_success(0, [
        $config['userName'],
        $config['userPassword'],
        $loginUrl,
        $config['userUrl']
    ]);
}

/**
 * dispatch install action
 *
 */
function install_dispatch()
{
    if (install_is_cli()) {
        define('__TYPECHO_ROOT_URL__', 'http://localhost');
    }

    $options = \Widget\Options::alloc(\Utils\Defaults::bootstrapOptions([
        'lang' => install_get_lang(),
        'siteUrl' => \Utils\Defaults::siteUrl(),
    ]));
    \Widget\Init::alloc();

    if (install_is_cli()) {
        echo $options->generator . "\n";
        echo 'PHP ' . PHP_VERSION . "\n";
    }

    if (
        install_check('config')
        && install_check('db_structure')
        && install_check('db_data')
    ) {
        if (!install_is_cli()) {
            install_redirect($options->siteUrl);
        }

        exit(1);
    }

    if (install_is_cli()) {
        install_step_1_perform();
    } else {
        $request = \Typecho\Request::getInstance();
        $step = $request->get('step');

        $action = 1;

        switch (true) {
            case $step == 2:
                if (!install_check('db_structure')) {
                    $action = 2;
                } else {
                    install_redirect('install.php?step=3');
                }
                break;
            case $step == 3:
                if (install_check('db_structure')) {
                    $action = 3;
                } else {
                    install_redirect('install.php?step=2');
                }
                break;
            default:
                break;
        }

        $method = 'install_step_' . $action;

        if ($request->isPost()) {
            $method .= '_perform';
            $method();
            exit;
        }
        ?>
<!DOCTYPE HTML>
<html>
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title><?php _e('TypeRenew 安装程序'); ?></title>
    <link rel="stylesheet" type="text/css" href="<?php $options->adminUrl('css/normalize.css'); ?>" />
    <link rel="stylesheet" type="text/css" href="<?php $options->adminUrl('css/grid.css'); ?>" />
    <link rel="stylesheet" type="text/css" href="<?php $options->adminUrl('css/style.css'); ?>" />
    <link rel="stylesheet" type="text/css" href="<?php $options->adminUrl('css/install.css'); ?>" />
    <script src="<?php $options->adminUrl('js/jquery.js'); ?>"></script>
</head>
<body>
    <div class="body container">
        <h1><a href="https://www.typerenew.com/" target="_blank"><img src="<?php $options->adminUrl('img/typerenew-logo.svg'); ?>" alt="TypeRenew" style="height:40px;display:inline-block;"></a></h1>
        <?php $method(); ?>
        <?php install_js_support(); ?>
    </div>
</body>
</html>
        <?php
    }
}

install_dispatch();
