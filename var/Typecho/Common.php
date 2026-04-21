<?php

namespace {

    use Typecho\I18n;

    function _t(string $string, ...$args): string
    {
        if (empty($args)) {
            return I18n::translate($string);
        } else {
            return vsprintf(I18n::translate($string), $args);
        }
    }

    function _e(string $string, ...$args)
    {
        array_unshift($args, $string);
        echo call_user_func_array('_t', $args);
    }

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

    class Common
    {
        public const SOFTWARE = 'TypeRenew';
        public const VERSION = '1.4.1';

        public static function generator(?string $version = null): string
        {
            return self::SOFTWARE . ' ' . ($version ?? self::VERSION);
        }

        public static function url(?string $path, ?string $prefix): string
        {
            $path = $path ?? '';
            $path = (0 === strpos($path, './')) ? substr($path, 2) : $path;
            return rtrim($prefix ?? '', '/') . '/'
                . str_replace('//', '/', ltrim($path, '/'));
        }

        public static function uploadErrorMessage(int $error, string $prefix, ?string $noFile = null): string
        {
            switch ($error) {
                case UPLOAD_ERR_NO_FILE:
                    return $noFile === null ? _t('%s失败', $prefix) : _t($noFile);
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    return _t('%s失败：文件体积超过服务器限制', $prefix);
                case UPLOAD_ERR_PARTIAL:
                    return _t('%s失败：文件仅部分上传', $prefix);
                case UPLOAD_ERR_NO_TMP_DIR:
                    return _t('%s失败：服务器缺少临时目录', $prefix);
                case UPLOAD_ERR_CANT_WRITE:
                    return _t('%s失败：无法写入服务器磁盘', $prefix);
                case UPLOAD_ERR_EXTENSION:
                    return _t('%s失败：上传被扩展中止', $prefix);
                default:
                    return _t('%s失败', $prefix);
            }
        }

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

            $message = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));

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

        public static function nativeClassName(string $className): string
        {
            return trim(str_replace('\\', '_', $className), '_');
        }

        public static function splitByCount(int $count, int ...$sizes): int
        {
            foreach ($sizes as $size) {
                if ($count < $size) {
                    return $size;
                }
            }

            return 0;
        }

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

        public static function filterSearchQuery(?string $query): string
        {
            return isset($query) ? str_replace('-', ' ', self::slugName($query) ?? '') : '';
        }

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

        public static function parseUrl(?string $url): array
        {
            if ($url === null || $url === '') {
                return [];
            }

            $parts = parse_url($url);
            return is_array($parts) ? $parts : [];
        }

        public static function safeUrl(?string $url): string
        {
            if (empty($url)) {
                return '/';
            }
            $params = self::parseUrl(str_replace(["\r", "\n", "\t", ' '], '', $url));

            if ($params === []) {
                return '/';
            }

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

        public static function cleanHex(string $val): string
        {
            $search = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890!@#$%^&*()~`";:?+/={}[]-_|\'\\';
            for ($i = 0; $i < strlen($search); $i++) {
                $val = preg_replace('/(&#[xX]0{0,8}' . dechex(ord($search[$i])) . ';?)/i', $search[$i], $val);
                $val = preg_replace('/(&#0{0,8}' . ord($search[$i]) . ';?)/', $search[$i], $val);
            }
            return $val;
        }

        public static function removeXSS(?string $val): string
        {
            $val = (string) $val;
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

        public static function strBy(?string $a, ?string $b = null): ?string
        {
            return isset($a) && $a !== '' ? $a : $b;
        }

        public static function strLen(string $str): int
        {
            return mb_strlen($str, 'UTF-8');
        }

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

        public static function buildBackupBuffer(string $type, string $header, string $body): string
        {
            $buffer = '';

            $buffer .= pack('vvV', $type, strlen($header), strlen($body));
            $buffer .= $header . $body;
            $buffer .= md5($buffer);

            return $buffer;
        }

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

            $header = fread($fp, $headerLen);
            $offset += $headerLen;

            if (false === $header || strlen($header) != $headerLen) {
                return false;
            }

            if ('FILE' == $version) {
                $bodyLen = array_reduce(json_decode($header, true), function ($carry, $len) {
                    return null === $len ? $carry : $carry + $len;
                }, 0);
            }

            $body = fread($fp, $bodyLen);
            $offset += $bodyLen;

            if (false === $body || strlen($body) != $bodyLen) {
                return false;
            }

            $md5 = fread($fp, 32);
            $offset += 32;

            if (false === $md5 || $md5 != md5($meta . $header . $body)) {
                return false;
            }

            return [$type, $header, $body];
        }

        public static function checkSafeHost(string $host): bool
        {
            if ('localhost' == $host) {
                return false;
            }

            $address = gethostbyname($host);
            $inet = inet_pton($address);

            if (false === $inet) {
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

        public static function mimeContentType(string $fileName): string
        {
            if (is_file($fileName) && is_readable($fileName) && function_exists('mime_content_type')) {
                return mime_content_type($fileName);
            }

            if (is_file($fileName) && is_readable($fileName) && function_exists('finfo_open')) {
                $fInfo = finfo_open(FILEINFO_MIME_TYPE);

                if (false !== $fInfo) {
                    $mimeType = (string) finfo_file($fInfo, $fileName);
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

        public static function idnToUtf8(string $url): string
        {
            if (function_exists('idn_to_utf8') && !empty($url)) {
                $host = parse_url($url, PHP_URL_HOST);
                if (is_string($host) && $host !== '') {
                    $utf8Host = idn_to_utf8($host);
                    if (is_string($utf8Host) && $utf8Host !== '') {
                        $url = str_replace($host, $utf8Host, $url);
                    }
                }
            }

            return $url;
        }
    }
}
