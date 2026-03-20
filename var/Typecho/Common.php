<?php

namespace {

    use Typecho\I18n;

    /**
     * I18n function
     *
     * @param string $string 需要翻译的文字
     * @param mixed ...$args 参数
     *
     * @return string
     */
    function _t(string $string, ...$args): string
    {
        if (empty($args)) {
            return I18n::translate($string);
        } else {
            return vsprintf(I18n::translate($string), $args);
        }
    }

    /**
     * I18n function, translate and echo
     *
     * @param string $string 需要翻译的文字
     * @param mixed ...$args 参数
     */
    function _e(string $string, ...$args)
    {
        array_unshift($args, $string);
        echo call_user_func_array('_t', $args);
    }

    /**
     * 针对复数形式的翻译函数
     *
     * @param string $single 单数形式的翻译
     * @param string $plural 复数形式的翻译
     * @param integer $number 数字
     *
     * @return string
     */
    function _n(string $single, string $plural, int $number): string
    {
        return str_replace('%d', $number, I18n::ngettext($single, $plural, $number));
    }
}

namespace Typecho {
    const PLUGIN_NAMESPACE = 'TypechoPlugin';

    spl_autoload_register(function (string $className) {
        $isDefinedAlias = defined('__TYPECHO_CLASS_ALIASES__');
        $isNamespace = strpos($className, '\\') !== false;
        $isAlias = $isDefinedAlias && isset(__TYPECHO_CLASS_ALIASES__[$className]);
        $isPlugin = false;

        // detect if class is predefined
        if ($isNamespace) {
            $isPlugin = strpos(ltrim($className, '\\'), PLUGIN_NAMESPACE . '\\') !== false;

            if ($isPlugin) {
                $realClassName = substr($className, strlen(PLUGIN_NAMESPACE) + 1);
                $alias = Common::nativeClassName($realClassName);
                $path = str_replace('\\', '/', $realClassName);
            } else {
                if ($isDefinedAlias) {
                    $alias = array_search('\\' . ltrim($className, '\\'), __TYPECHO_CLASS_ALIASES__);
                }

                $alias = empty($alias) ? Common::nativeClassName($className) : $alias;
                $path = str_replace('\\', '/', $className);
            }
        } elseif (strpos($className, '_') !== false || $isAlias) {
            $isPlugin = !$isAlias && !preg_match("/^(Typecho|Widget|IXR)_/", $className);

            if ($isPlugin) {
                $alias = '\\TypechoPlugin\\' . str_replace('_', '\\', $className);
                $path = str_replace('_', '/', $className);
            } else {
                $alias = $isAlias ? __TYPECHO_CLASS_ALIASES__[$className]
                    : '\\' . str_replace('_', '\\', $className);

                $path = str_replace('\\', '/', $alias);
            }
        } else {
            $path = $className;
        }

        if (
            isset($alias)
            && (class_exists($alias, false)
                || interface_exists($alias, false)
                || trait_exists($alias, false))
        ) {
            class_alias($alias, $className, false);
            return;
        }

        $path .= '.php';
        $defaultFile = __TYPECHO_ROOT_DIR__ . '/var/' . $path;

        if (file_exists($defaultFile) && !$isPlugin) {
            include_once $defaultFile;
        } else {
            $pluginFile = __TYPECHO_ROOT_DIR__ . __TYPECHO_PLUGIN_DIR__ . '/' . $path;

            if (file_exists($pluginFile)) {
                include_once $pluginFile;
            } else {
                return;
            }
        }

        if (isset($alias)) {
            $classLoaded = class_exists($className, false)
                || interface_exists($className, false)
                || trait_exists($className, false);

            $aliasLoaded = class_exists($alias, false)
                || interface_exists($alias, false)
                || trait_exists($alias, false);

            if ($classLoaded && !$aliasLoaded) {
                class_alias($className, $alias);
            } elseif ($aliasLoaded && !$classLoaded) {
                class_alias($alias, $className);
            }
        }
    });

    /**
     * Typecho公用方法
     *
     * @category typecho
     * @package Common
     * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
     * @license GNU General Public License 2.0
     */
    class Common
    {
        /** 程序版本 */
        public const VERSION = '1.3.1';

        /**
         * 将路径转化为链接
         *
         * @param string|null $path 路径
         * @param string|null $prefix 前缀
         * @return string
         */
        public static function url(?string $path, ?string $prefix): string
        {
            $path = $path ?? '';
            $path = (0 === strpos($path, './')) ? substr($path, 2) : $path;
            return rtrim($prefix ?? '', '/') . '/'
                . str_replace('//', '/', ltrim($path, '/'));
        }

        /**
         * 程序初始化方法
         */
        public static function init()
        {
            Response::getInstance()->enableAutoSendHeaders(false);

            ob_start(function ($content) {
                Response::getInstance()->sendHeaders();
                return $content;
            });

            set_exception_handler(function (\Throwable $exception) {
                echo '<pre><code>';
                echo '<h1>' . htmlspecialchars($exception->getMessage()) . '</h1>';
                echo htmlspecialchars($exception->__toString());
                echo '</code></pre>';
                exit;
            });
        }

        /**
         * 输出错误页面
         *
         * @param \Throwable $exception 错误信息
         */
        public static function error(\Throwable $exception)
        {
            $code = $exception->getCode() ?: 500;
            $message = $exception->getMessage();

            if ($exception instanceof \Typecho\Db\Exception) {
                $code = 500;

                $message = 'Database Server Error';

                if ($exception instanceof \Typecho\Db\Adapter\ConnectionException) {
                    $code = 503;
                    $message = 'Error establishing a database connection';
                } elseif ($exception instanceof \Typecho\Db\Adapter\SQLException) {
                    $message = 'Database Query Error';
                }
            } elseif ($exception instanceof \Typecho\Widget\Exception) {
                $message = $exception->getMessage();
            } else {
                $message = 'Server Error';
            }

            if (is_numeric($code) && $code > 200) {
                Response::getInstance()->setStatus($code);
            }

            $message = nl2br($message);

            if (defined('__TYPECHO_EXCEPTION_FILE__')) {
                require_once __TYPECHO_EXCEPTION_FILE__;
            } else {
                echo
                <<<EOF
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>{$code}</title>
        <style>
            html {
                padding: 50px 10px;
                font-size: 16px;
                line-height: 1.4;
                color: #666;
                background: #F6F6F3;
                -webkit-text-size-adjust: 100%;
                -ms-text-size-adjust: 100%;
            }

            html,
            input { font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; }
            body {
                max-width: 500px;
                _width: 500px;
                padding: 30px 20px;
                margin: 0 auto;
                background: #FFF;
            }
            ul {
                padding: 0 0 0 40px;
            }
            .container {
                max-width: 380px;
                _width: 380px;
                margin: 0 auto;
            }
        </style>
    </head>
    <body>
        <div class="container">
            {$message}
        </div>
    </body>
</html>
EOF;
            }

            exit(1);
        }

        /**
         * @param string $className
         * @return string
         */
        public static function nativeClassName(string $className): string
        {
            return trim(str_replace('\\', '_', $className), '_');
        }

        /**
         * 根据count数目来输出字符
         * <code>
         * echo splitByCount(20, 10, 20, 30, 40, 50);
         * </code>
         *
         * @param int $count
         * @param int ...$sizes
         * @return int
         */
        public static function splitByCount(int $count, int ...$sizes): int
        {
            foreach ($sizes as $size) {
                if ($count < $size) {
                    return $size;
                }
            }

            return 0;
        }

        /**
         * 自闭合html修复函数
         * 使用方法:
         * <code>
         * $input = '这是一段被截断的html文本<a href="#"';
         * echo Common::fixHtml($input);
         * //output: 这是一段被截断的html文本
         * </code>
         *
         * @param string|null $string 需要修复处理的字符串
         * @return string|null
         */
        public static function fixHtml(?string $string): ?string
        {
            if (empty($string)) {
                return $string;
            }

            $startPos = strrpos($string, "<");

            if (false == $startPos) {
                return $string;
            }

            $trimString = substr($string, $startPos);

            if (false === strpos($trimString, ">")) {
                $string = substr($string, 0, $startPos);
            }

            //非自闭合html标签列表
            preg_match_all("/<([_0-9a-zA-Z-:]+)\s*([^>]*)>/is", $string, $startTags);
            preg_match_all("/<\/([_0-9a-zA-Z-:]+)>/is", $string, $closeTags);

            if (!empty($startTags[1]) && is_array($startTags[1])) {
                krsort($startTags[1]);
                $closeTagsIsArray = is_array($closeTags[1]);
                foreach ($startTags[1] as $key => $tag) {
                    $attrLength = strlen($startTags[2][$key]);
                    if ($attrLength > 0 && "/" == trim($startTags[2][$key][$attrLength - 1])) {
                        continue;
                    }

                    // 白名单
                    if (
                        preg_match(
                            "/^(area|base|br|col|embed|hr|img|input|keygen|link|meta|param|source|track|wbr)$/i",
                            $tag
                        )
                    ) {
                        continue;
                    }

                    if (!empty($closeTags[1]) && $closeTagsIsArray) {
                        if (false !== ($index = array_search($tag, $closeTags[1]))) {
                            unset($closeTags[1][$index]);
                            continue;
                        }
                    }
                    $string .= "</{$tag}>";
                }
            }

            return preg_replace("/<br\s*\/>\s*<\/p>/is", '</p>', $string);
        }

        /**
         * 去掉字符串中的html标签
         * 使用方法:
         * <code>
         * $input = '<a href="http://test/test.php" title="example">hello</a>';
         * $output = Common::stripTags($input, <a href="">);
         * echo $output;
         * //display: '<a href="http://test/test.php">hello</a>'
         * </code>
         *
         * @param string|null $html 需要处理的字符串
         * @param string|null $allowableTags 需要忽略的html标签
         * @return string
         */
        public static function stripTags(?string $html, ?string $allowableTags = null): string
        {
            $normalizeTags = '';
            $allowableAttributes = [];

            if (!empty($allowableTags) && preg_match_all("/<([_a-z0-9-]+)([^>]*)>/is", $allowableTags, $tags)) {
                $normalizeTags = '<' . implode('><', array_map('strtolower', $tags[1])) . '>';
                $attributes = array_map('trim', $tags[2]);
                foreach ($attributes as $key => $val) {
                    $allowableAttributes[strtolower($tags[1][$key])] =
                        array_map('strtolower', array_keys(self::parseAttrs($val)));
                }
            }

            $html = strip_tags($html, $normalizeTags);
            return preg_replace_callback(
                "/<([_a-z0-9-]+)(\s+[^>]+)?>/is",
                function ($matches) use ($allowableAttributes) {
                    if (!isset($matches[2])) {
                        return $matches[0];
                    }

                    $str = trim($matches[2]);

                    if (empty($str)) {
                        return $matches[0];
                    }

                    $attrs = self::parseAttrs($str);
                    $parsedAttrs = [];
                    $tag = strtolower($matches[1]);

                    foreach ($attrs as $key => $val) {
                        if (in_array($key, $allowableAttributes[$tag])) {
                            $parsedAttrs[] = " {$key}" . (empty($val) ? '' : "={$val}");
                        }
                    }

                    return '<' . $tag . implode('', $parsedAttrs) . '>';
                },
                $html
            );
        }

        /**
         * 过滤用于搜索的字符串
         *
         * @param string|null $query 搜索字符串
         * @return string
         */
        public static function filterSearchQuery(?string $query): string
        {
            return isset($query) ? str_replace('-', ' ', self::slugName($query) ?? '') : '';
        }

        /**
         * 生成缩略名
         *
         * @param string|null $str 需要生成缩略名的字符串
         * @param string $default 默认的缩略名
         * @param integer $maxLength 缩略名最大长度
         * @return string
         */
        public static function slugName(?string $str, string $default = '', int $maxLength = 128): string
        {
            $str = trim($str ?? '');

            if (!strlen($str)) {
                return $default;
            }

            mb_regex_encoding('UTF-8');
            mb_ereg_search_init($str, "[\w" . preg_quote('_-') . "]+");
            $result = mb_ereg_search();
            $return = '';

            if ($result) {
                $regs = mb_ereg_search_getregs();
                $pos = 0;
                do {
                    $return .= ($pos > 0 ? '-' : '') . $regs[0];
                    $pos++;
                } while ($regs = mb_ereg_search_regs());
            }

            $str = trim($return, '-_');
            $str = !strlen($str) ? $default : $str;
            return substr($str, 0, $maxLength);
        }

        /**
         * 将url中的非法字符串
         *
         * @param string|null $url 需要过滤的url
         *
         * @return string
         */
        public static function safeUrl(?string $url): string
        {
            if (empty($url)) {
                return '/';
            }
            $params = parse_url(str_replace(["\r", "\n", "\t", ' '], '', $url));

            /** 禁止非法的协议跳转 */
            if (isset($params['scheme'])) {
                if (!in_array($params['scheme'], ['http', 'https'])) {
                    return '/';
                }
            }

            $params = array_map(function ($string) {
                $string = str_replace(['%0d', '%0a'], '', strip_tags($string));
                $string = preg_replace([
                    "/\(\s*([\"'])/i",           //函数开头
                    "/([\"'])\s*\)/i",           //函数结尾
                ], '', $string);
                $string = str_replace(['"', "'", '<', '>'], '', $string);
                return $string;
            }, $params);

            return self::buildUrl($params);
        }

        /**
         * 根据parse_url的结果重新组合url
         *
         * @param array $params 解析后的参数
         *
         * @return string
         */
        public static function buildUrl(array $params): string
        {
            return (isset($params['scheme']) ? $params['scheme'] . '://' : null)
                . (isset($params['user']) ? $params['user']
                    . (isset($params['pass']) ? ':' . $params['pass'] : null) . '@' : null)
                . ($params['host'] ?? null)
                . (isset($params['port']) ? ':' . $params['port'] : null)
                . ($params['path'] ?? null)
                . (isset($params['query']) ? '?' . $params['query'] : null)
                . (isset($params['fragment']) ? '#' . $params['fragment'] : null);
        }

        /**
         * 清理十六进制字符，用于 XSS 过滤
         *
         * @param string $val
         * @return string
         */
        public static function cleanHex(string $val): string
        {
            $search = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890!@#$%^&*()~`";:?+/={}[]-_|\'\\';
            for ($i = 0; $i < strlen($search); $i++) {
                $val = preg_replace('/(&#[xX]0{0,8}' . dechex(ord($search[$i])) . ';?)/i', $search[$i], $val);
                $val = preg_replace('/(&#0{0,8}' . ord($search[$i]) . ';?)/', $search[$i], $val);
            }
            return $val;
        }

        /**
         * 处理XSS跨站攻击的过滤函数
         *
         * @param string|null $val 需要处理的字符串
         * @return string
         */
        public static function removeXSS(?string $val): string
        {
            $val = preg_replace('/([\x00-\x08]|[\x0b-\x0c]|[\x0e-\x19])/', '', $val);
            $val = self::cleanHex($val);

            $ra1 = ['javascript', 'vbscript', 'expression', 'applet', 'meta', 'xml', 'blink', 'link', 'style', 'script',
                    'embed', 'object', 'iframe', 'frame', 'frameset', 'ilayer', 'layer', 'bgsound', 'title', 'base'];
            $ra2 = [
                'onabort', 'onactivate', 'onafterprint', 'onafterupdate', 'onbeforeactivate', 'onbeforecopy',
                'onbeforecut', 'onbeforedeactivate', 'onbeforeeditfocus', 'onbeforepaste', 'onbeforeprint',
                'onbeforeunload', 'onbeforeupdate', 'onblur', 'onbounce', 'oncellchange', 'onchange', 'onclick',
                'oncontextmenu', 'oncontrolselect', 'oncopy', 'oncut', 'ondataavailable', 'ondatasetchanged',
                'ondatasetcomplete', 'ondblclick', 'ondeactivate', 'ondrag', 'ondragend', 'ondragenter', 'ondragleave',
                'ondragover', 'ondragstart', 'ondrop', 'onerror', 'onerrorupdate', 'onfilterchange', 'onfinish',
                'onfocus', 'onfocusin', 'onfocusout', 'onhelp', 'onkeydown', 'onkeypress', 'onkeyup',
                'onlayoutcomplete', 'onload', 'onlosecapture', 'onmousedown', 'onmouseenter',
                'onmouseleave', 'onmousemove', 'onmouseout', 'onmouseover', 'onmouseup', 'onmousewheel',
                'onmove', 'onmoveend', 'onmovestart', 'onpaste', 'onpropertychange', 'onreadystatechange',
                'onreset', 'onresize', 'onresizeend', 'onresizestart', 'onrowenter', 'onrowexit', 'onrowsdelete',
                'onrowsinserted', 'onscroll', 'onselect', 'onselectionchange', 'onselectstart', 'onstart', 'onstop',
                'onsubmit', 'onunload'
            ];
            $ra = array_merge($ra1, $ra2);

            $found = true; // keep replacing as long as the previous round replaced something
            while ($found == true) {
                $val_before = $val;
                for ($i = 0; $i < sizeof($ra); $i++) {
                    $pattern = '/';
                    for ($j = 0; $j < strlen($ra[$i]); $j++) {
                        if ($j > 0) {
                            $pattern .= '(';
                            $pattern .= '(&#[xX]0{0,8}([9ab]);)';
                            $pattern .= '|';
                            $pattern .= '|(&#0{0,8}([9|10|13]);)';
                            $pattern .= ')*';
                        }
                        $pattern .= $ra[$i][$j];
                    }
                    $pattern .= '/i';
                    $replacement = substr($ra[$i], 0, 2) . '<x>' . substr($ra[$i], 2); // add in <> to nerf the tag
                    $val = preg_replace($pattern, $replacement, $val); // filter out the hex tags

                    if ($val_before == $val) {
                        // no replacements were made, so exit the loop
                        $found = false;
                    }
                }
            }

            return $val;
        }

        /**
         * 宽字符串截字函数
         *
         * @param string $str 需要截取的字符串
         * @param integer $start 开始截取的位置
         * @param integer $length 需要截取的长度
         * @param string $trim 截取后的截断标示符
         *
         * @return string
         */
        public static function subStr(string $str, int $start, int $length, string $trim = "..."): string
        {
            if (!strlen($str)) {
                return '';
            }

            $iLength = self::strLen($str) - $start;
            $tLength = $length < $iLength ? ($length - self::strLen($trim)) : $length;
            $str = mb_substr($str, $start, $tLength, 'UTF-8');

            return $length < $iLength ? ($str . $trim) : $str;
        }

        /**
         * 判断两个字符串是否为空并依次返回
         *
         * @param string|null $a
         * @param string|null $b
         * @return string|null
         */
        public static function strBy(?string $a, ?string $b = null): ?string
        {
            return isset($a) && $a !== '' ? $a : $b;
        }

        /**
         * 获取宽字符串长度函数
         *
         * @param string $str 需要获取长度的字符串
         * @return integer
         */
        public static function strLen(string $str): int
        {
            return mb_strlen($str, 'UTF-8');
        }

        /**
         * 判断hash值是否相等
         *
         * @param string|null $from 源字符串
         * @param string|null $to 目标字符串
         * @return boolean
         */
        public static function hashValidate(?string $from, ?string $to): bool
        {
            if (!isset($from) || !isset($to)) {
                return false;
            }

            if ('$T$' == substr($to, 0, 3)) {
                $salt = substr($to, 3, 9);
                return self::hash($from, $salt) === $to;
            } else {
                return md5($from) === $to;
            }
        }

        /**
         * 对字符串进行hash加密
         *
         * @param string|null $string 需要hash的字符串
         * @param string|null $salt 扰码
         * @return string
         */
        public static function hash(?string $string, ?string $salt = null): string
        {
            if (!isset($string)) {
                return '';
            }

            $salt = empty($salt) ? self::randString(9) : $salt;
            $length = strlen($string);

            if ($length == 0) {
                return '';
            }

            $hash = '';
            $last = ord($string[$length - 1]);
            $pos = 0;

            if (strlen($salt) != 9) {
                return '';
            }

            while ($pos < $length) {
                $asc = ord($string[$pos]);
                $last = ($last * ord($salt[($last % $asc) % 9]) + $asc) % 95 + 32;
                $hash .= chr($last);
                $pos++;
            }

            return '$T$' . $salt . md5($hash);
        }

        /**
         * 生成随机字符串
         *
         * @param integer $length 字符串长度
         * @param boolean $specialChars 是否有特殊字符
         * @return string
         */
        public static function randString(int $length, bool $specialChars = false): string
        {
            $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            if ($specialChars) {
                $chars .= '!@#$%^&*()';
            }

            $result = '';
            $max = strlen($chars) - 1;
            try {
                for ($i = 0; $i < $length; $i++) {
                    $result .= $chars[random_int(0, $max)];
                }
                return $result;
            } catch (\Throwable $e) {
                for ($i = 0; $i < $length; $i++) {
                    $result .= $chars[mt_rand(0, $max)];
                }
            }
            return $result;
        }

        /**
         * 创建一个会过期的Token
         *
         * @param string $secret
         * @return string
         */
        public static function timeToken(string $secret): string
        {
            $ts = time();
            try {
                $nonce = bin2hex(random_bytes(6));
            } catch (\Throwable $e) {
                $nonce = self::randString(12, false);
            }
            $sig = hash_hmac('sha256', $ts . '|' . $nonce, $secret);
            return 'v2:' . $ts . ':' . $nonce . ':' . $sig;
        }

        /**
         * 在时间范围内验证token
         *
         * @param string $token
         * @param string $secret
         * @param int $timeout
         * @return bool
         */
        public static function timeTokenValidate(string $token, string $secret, int $timeout = 5): bool
        {
            $token = trim($token);
            if (strpos($token, 'v2:') === 0) {
                $parts = explode(':', $token, 4);
                if (count($parts) === 4) {
                    [, $tsRaw, $nonce, $sig] = $parts;
                    if (ctype_digit((string) $tsRaw) && preg_match('/^[a-f0-9]{12}$/', (string) $nonce)) {
                        $ts = (int) $tsRaw;
                        if (abs(time() - $ts) <= max(1, $timeout)) {
                            $expected = hash_hmac('sha256', $ts . '|' . $nonce, $secret);
                            if (hash_equals($expected, (string) $sig)) {
                                return true;
                            }
                        }
                    }
                }
            }

            $now = time();
            $from = $now - $timeout;
            for ($i = $now; $i >= $from; $i--) {
                if (sha1($secret . '&' . $i) == $token) {
                    return true;
                }
            }

            return false;
        }

        /**
         * 获取gravatar头像地址
         *
         * @param string|null $mail
         * @param int $size
         * @param string|null $rating
         * @param string|null $default
         * @param bool $isSecure
         *
         * @return string
         */
        public static function gravatarUrl(
            ?string $mail,
            int $size,
            ?string $rating = null,
            ?string $default = null,
            bool $isSecure = true
        ): string {
            if (defined('__TYPECHO_GRAVATAR_PREFIX__')) {
                $url = __TYPECHO_GRAVATAR_PREFIX__;
            } else {
                $url = $isSecure ? 'https://secure.gravatar.com' : 'http://www.gravatar.com';
                $url .= '/avatar/';
            }

            if (!empty($mail)) {
                $url .= md5(strtolower(trim($mail)));
            }

            $url .= '?s=' . $size;

            if (isset($rating)) {
                $url .= '&amp;r=' . $rating;
            }

            if (isset($default)) {
                $url .= '&amp;d=' . $default;
            }

            return $url;
        }

        /**
         * 给javascript赋值加入扰码设计
         *
         * @param string $value
         *
         * @return string
         */
        public static function shuffleScriptVar(string $value): string
        {
            $length = strlen($value);
            $max = 3;
            $offset = 0;
            $result = [];
            $cut = [];

            while ($length > 0) {
                $len = rand(0, min($max, $length));
                $rand = "'" . self::randString(rand(1, $max)) . "'";

                if ($len > 0) {
                    $val = "'" . substr($value, $offset, $len) . "'";
                    $result[] = rand(0, 1) ? "//{$rand}\n{$val}" : "{$val}//{$rand}\n";
                } else {
                    if (rand(0, 1)) {
                        $result[] = rand(0, 1) ? "''///*{$rand}*/{$rand}\n" : "/* {$rand}//{$rand} */''";
                    } else {
                        $result[] = rand(0, 1) ? "//{$rand}\n{$rand}" : "{$rand}//{$rand}\n";
                        $cut[] = [$offset, strlen($rand) - 2 + $offset];
                    }
                }

                $offset += $len;
                $length -= $len;
            }

            $name = '_' . self::randString(rand(3, 7));
            $cutName = '_' . self::randString(rand(3, 7));
            $var = implode('+', $result);
            $cutVar = json_encode($cut);
            return "(function () {
    var {$name} = {$var}, {$cutName} = {$cutVar};
    
    for (var i = 0; i < {$cutName}.length; i ++) {
        {$name} = {$name}.substring(0, {$cutName}[i][0]) + {$name}.substring({$cutName}[i][1]);
    }

    return {$name};
})();";
        }

        /**
         * 创建备份文件缓冲
         *
         * @param string $type
         * @param string $header
         * @param string $body
         *
         * @return string
         */
        public static function buildBackupBuffer(string $type, string $header, string $body): string
        {
            $buffer = '';

            $buffer .= pack('vvV', $type, strlen($header), strlen($body));
            $buffer .= $header . $body;
            $buffer .= md5($buffer);

            return $buffer;
        }

        /**
         * 从备份文件中解压
         *
         * @param resource $fp
         * @param int|null $offset
         * @param string $version
         * @return array|bool
         */
        public static function extractBackupBuffer($fp, ?int &$offset, string $version)
        {
            $realMetaLen = $version == 'FILE' ? 6 : 8;

            $meta = fread($fp, $realMetaLen);
            $offset += $realMetaLen;
            $metaLen = strlen($meta);

            if (false === $meta || $metaLen != $realMetaLen) {
                return false;
            }

            [$type, $headerLen, $bodyLen]
                = array_values(unpack($version == 'FILE' ? 'v3' : 'v1type/v1headerLen/V1bodyLen', $meta));

            $header = @fread($fp, $headerLen);
            $offset += $headerLen;

            if (false === $header || strlen($header) != $headerLen) {
                return false;
            }

            if ('FILE' == $version) {
                $bodyLen = array_reduce(json_decode($header, true), function ($carry, $len) {
                    return null === $len ? $carry : $carry + $len;
                }, 0);
            }

            $body = @fread($fp, $bodyLen);
            $offset += $bodyLen;

            if (false === $body || strlen($body) != $bodyLen) {
                return false;
            }

            $md5 = @fread($fp, 32);
            $offset += 32;

            if (false === $md5 || $md5 != md5($meta . $header . $body)) {
                return false;
            }

            return [$type, $header, $body];
        }

        /**
         * 检查是否是一个安全的主机名
         *
         * @param string $host
         * @return bool
         */
        public static function checkSafeHost(string $host): bool
        {
            if ('localhost' == $host) {
                return false;
            }

            $address = gethostbyname($host);
            $inet = inet_pton($address);

            if (false === $inet) {
                // 有可能是ipv6的地址
                $records = dns_get_record($host, DNS_AAAA);

                if (empty($records)) {
                    return false;
                }

                $address = $records[0]['ipv6'];
                $inet = inet_pton($address);
            }

            return filter_var(
                $address,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            ) !== false;
        }

        /**
         * 获取图片
         *
         * @param string $fileName 文件名
         * @return string
         */
        public static function mimeContentType(string $fileName): string
        {
            //改为并列判断
            if (function_exists('mime_content_type')) {
                return mime_content_type($fileName);
            }

            if (function_exists('finfo_open')) {
                $fInfo = @finfo_open(FILEINFO_MIME_TYPE);

                if (false !== $fInfo) {
                    $mimeType = finfo_file($fInfo, $fileName);
                    finfo_close($fInfo);
                    return $mimeType;
                }
            }

            $mimeTypes = MimeTypes::get();

            $part = explode('.', $fileName);
            $size = count($part);

            if ($size > 1) {
                $ext = $part[$size - 1];
                if (isset($mimeTypes[$ext])) {
                    return $mimeTypes[$ext];
                }
            }

            return 'application/octet-stream';
        }

        /**
         * 寻找匹配的mime图标
         *
         * @param string $mime mime类型
         * @return string
         */
        public static function mimeIconType(string $mime): string
        {
            $parts = explode('/', $mime);

            if (count($parts) < 2) {
                return 'unknown';
            }

            [$type, $stream] = $parts;

            if (in_array($type, ['image', 'video', 'audio', 'text', 'application'])) {
                switch (true) {
                    case in_array($stream, ['msword', 'msaccess', 'ms-powerpoint', 'ms-powerpoint']):
                    case 0 === strpos($stream, 'vnd.'):
                        return 'office';
                    case false !== strpos($stream, 'html')
                        || false !== strpos($stream, 'xml')
                        || false !== strpos($stream, 'wml'):
                        return 'html';
                    case false !== strpos($stream, 'compressed')
                        || false !== strpos($stream, 'zip')
                        || in_array($stream, ['application/x-gtar', 'application/x-tar']):
                        return 'archive';
                    case 'text' == $type && 0 === strpos($stream, 'x-'):
                        return 'script';
                    default:
                        return $type;
                }
            } else {
                return 'unknown';
            }
        }

        /**
         * 解析属性
         *
         * @param string $attrs 属性字符串
         * @return array
         */
        private static function parseAttrs(string $attrs): array
        {
            $attrs = trim($attrs);
            $len = strlen($attrs);
            $pos = -1;
            $result = [];
            $quote = '';
            $key = '';
            $value = '';

            for ($i = 0; $i < $len; $i++) {
                if ('=' != $attrs[$i] && !ctype_space($attrs[$i]) && -1 == $pos) {
                    $key .= $attrs[$i];

                    /** 最后一个 */
                    if ($i == $len - 1) {
                        if ('' != ($key = trim($key))) {
                            $result[$key] = '';
                            $key = '';
                            $value = '';
                        }
                    }

                } elseif (ctype_space($attrs[$i]) && -1 == $pos) {
                    $pos = -2;
                } elseif ('=' == $attrs[$i] && 0 > $pos) {
                    $pos = 0;
                } elseif (('"' == $attrs[$i] || "'" == $attrs[$i]) && 0 == $pos) {
                    $quote = $attrs[$i];
                    $value .= $attrs[$i];
                    $pos = 1;
                } elseif ($quote != $attrs[$i] && 1 == $pos) {
                    $value .= $attrs[$i];
                } elseif ($quote == $attrs[$i] && 1 == $pos) {
                    $pos = -1;
                    $value .= $attrs[$i];
                    $result[trim($key)] = $value;
                    $key = '';
                    $value = '';
                } elseif ('=' != $attrs[$i] && !ctype_space($attrs[$i]) && -2 == $pos) {
                    if ('' != ($key = trim($key))) {
                        $result[$key] = '';
                    }

                    $key = '';
                    $value = '';
                    $pos = -1;
                    $key .= $attrs[$i];
                }
            }

            return $result;
        }

        /**
         * IDN转UTF8
         *
         * @param string $url
         * @return string
         */
        public static function idnToUtf8(string $url): string
        {
            if (function_exists('idn_to_utf8') && !empty($url)) {
                $host = parse_url($url, PHP_URL_HOST);
                $url = str_replace($host, idn_to_utf8($host), $url);
            }

            return $url;
        }
    }
}
