<?php

namespace Utils;

use Typecho\Common;
use Typecho\Db;
use Typecho\I18n;
use Typecho\Plugin;
use Typecho\Widget;
use Widget\Base\Options as BaseOptions;
use Widget\Options;
use Widget\Plugins\Edit;
use Widget\Security;
use Widget\Service;

class Helper
{
    public static function security(): Security
    {
        return Security::alloc();
    }

    public static function widgetById(string $table, int $pkId): ?Widget
    {
        $table = ucfirst($table);
        if (!in_array($table, ['Contents', 'Comments', 'Metas', 'Users'])) {
            return null;
        }

        $keys = [
            'Contents' => 'cid',
            'Comments' => 'coid',
            'Metas'    => 'mid',
            'Users'    => 'uid'
        ];

        $className = '\Widget\Base\\' . $table;
        $key = $keys[$table];
        $db = Db::get();
        $widget = Widget::widget($className . '@' . $pkId);

        $db->fetchRow(
            $widget->select()->where("{$key} = ?", $pkId)->limit(1),
            [$widget, 'push']
        );

        return $widget;
    }

    public static function requestService($method, ... $params)
    {
        Service::alloc()->requestService($method, ... $params);
    }

    public static function removePlugin(string $pluginName)
    {
        try {
            $pluginName = Plugin::normalizeName($pluginName);
            [$pluginFileName, $className] = Plugin::portal(
                $pluginName,
                __TYPECHO_ROOT_DIR__ . '/' . __TYPECHO_PLUGIN_DIR__
            );

            $plugins = Plugin::export();
            $activatedPlugins = $plugins['activated'];

            require_once $pluginFileName;

            if (
                !array_key_exists($pluginName, $activatedPlugins) || !class_exists($className)
                || !method_exists($className, 'deactivate')
            ) {
                throw new Widget\Exception(_t('无法禁用插件'), 500);
            }

            call_user_func([$className, 'deactivate']);
        } catch (\Exception $e) {
        }

        $db = Db::get();

        try {
            Plugin::deactivate($pluginName);
            self::setOption('plugins', Plugin::export());
        } catch (Plugin\Exception $e) {
        }

        $db->query($db->delete('table.options')->where('name = ?', 'plugin:' . $pluginName));
    }

    public static function lang(string $domain)
    {
        $currentLang = I18n::getLang();
        if ($currentLang) {
            $currentLang = basename($currentLang);
            $fileName = dirname(__FILE__) . '/' . $domain . '/lang/' . $currentLang;
            if (file_exists($fileName)) {
                I18n::addLang($fileName);
            }
        }
    }

    public static function options(): Options
    {
        return Options::alloc();
    }

    public static function setOption(string $name, $value): int
    {
        $options = self::options();
        $options->{$name} = $value;

        return BaseOptions::alloc()->update(
            ['value' => is_array($value) ? json_encode($value) : $value],
            Db::get()->sql()->where('name = ?', $name)
        );
    }

    public static function addRoute(
        string $name,
        string $url,
        string $widget,
        ?string $action = null,
        ?string $after = null
    ): int {
        $routingTable = self::options()->routingTable;
        if (isset($routingTable[0])) {
            unset($routingTable[0]);
        }

        $pos = 0;
        foreach ($routingTable as $key => $val) {
            $pos++;

            if ($key == $after) {
                break;
            }
        }

        $pre = array_slice($routingTable, 0, $pos);
        $next = array_slice($routingTable, $pos);

        $routingTable = array_merge($pre, [
            $name => [
                'url'    => $url,
                'widget' => $widget,
                'action' => $action
            ]
        ], $next);

        return self::setOption('routingTable', $routingTable);
    }

    public static function removeRoute(string $name): int
    {
        $routingTable = self::options()->routingTable;
        if (isset($routingTable[0])) {
            unset($routingTable[0]);
        }

        unset($routingTable[$name]);
        return self::setOption('routingTable', $routingTable);
    }

    public static function addAction(string $actionName, string $widgetName): int
    {
        $actionTable = self::options()->actionTable;
        $actionTable = empty($actionTable) ? [] : $actionTable;
        $actionTable[$actionName] = $widgetName;

        return self::setOption('actionTable', $actionTable);
    }

    public static function removeAction(string $actionName): int
    {
        $actionTable = self::options()->actionTable;
        $actionTable = empty($actionTable) ? [] : $actionTable;

        if (isset($actionTable[$actionName])) {
            unset($actionTable[$actionName]);
            reset($actionTable);
        }

        return self::setOption('actionTable', $actionTable);
    }

    public static function addMenu(string $menuName): int
    {
        $panelTable = self::options()->panelTable;
        $panelTable['parent'] = empty($panelTable['parent']) ? [] : $panelTable['parent'];
        $panelTable['parent'][] = $menuName;

        self::setOption('panelTable', $panelTable);

        end($panelTable['parent']);
        return key($panelTable['parent']) + 10;
    }

    public static function removeMenu(string $menuName): int
    {
        $panelTable = self::options()->panelTable;
        $panelTable['parent'] = empty($panelTable['parent']) ? [] : $panelTable['parent'];

        $index = array_search($menuName, $panelTable['parent']);
        if ($index !== false) {
            unset($panelTable['parent'][$index]);
        }

        self::setOption('panelTable', $panelTable);

        return $index !== false ? (int) $index + 10 : -1;
    }

    public static function addPanel(
        int $index,
        string $fileName,
        string $title,
        string $subTitle,
        string $level,
        bool $hidden = false,
        string $addLink = ''
    ): int {
        $panelTable = self::options()->panelTable;
        $panelTable['child'] = empty($panelTable['child']) ? [] : $panelTable['child'];
        $panelTable['child'][$index] = empty($panelTable['child'][$index]) ? [] : $panelTable['child'][$index];
        $fileName = urlencode(trim($fileName, '/'));
        $panelTable['child'][$index][]
            = [$title, $subTitle, 'extending.php?panel=' . $fileName, $level, $hidden, $addLink];

        $panelTable['file'] = empty($panelTable['file']) ? [] : $panelTable['file'];
        $panelTable['file'][] = $fileName;
        $panelTable['file'] = array_unique($panelTable['file']);

        self::setOption('panelTable', $panelTable);

        end($panelTable['child'][$index]);
        return key($panelTable['child'][$index]);
    }

    public static function removePanel(int $index, string $fileName): int
    {
        $panelTable = self::options()->panelTable;
        $panelTable['child'] = empty($panelTable['child']) ? [] : $panelTable['child'];
        $panelTable['child'][$index] = empty($panelTable['child'][$index]) ? [] : $panelTable['child'][$index];
        $panelTable['file'] = empty($panelTable['file']) ? [] : $panelTable['file'];
        $fileName = urlencode(trim($fileName, '/'));

        $key = array_search($fileName, $panelTable['file']);
        if ($key !== false) {
            unset($panelTable['file'][$key]);
        }

        $return = -1;
        if (!empty($panelTable['child'][$index])) {
            foreach ($panelTable['child'][$index] as $k => $val) {
                if ($val[2] == 'extending.php?panel=' . $fileName) {
                    unset($panelTable['child'][$index][$k]);
                    $return = (int) $k;
                    break;
                }
            }
        }

        self::setOption('panelTable', $panelTable);
        return $return;
    }

    public static function url(string $fileName): string
    {
        return Common::url('extending.php?panel=' . (trim($fileName, '/')), self::options()->adminUrl);
    }

    public static function configPlugin($pluginName, array $settings, bool $isPersonal = false)
    {
        if (empty($settings)) {
            return;
        }

        Edit::configPlugin($pluginName, $settings, $isPersonal);
    }

    public static function replyLink(
        string $theId,
        int $coid,
        string $word = 'Reply',
        string $formId = 'respond',
        int $style = 2
    ) {
        if (self::options()->commentsThreaded) {
            echo '<a href="#' . $formId . '" rel="nofollow" onclick="return typechoAddCommentReply(\'' .
                $theId . '\', ' . $coid . ', \'' . $formId . '\', ' . $style . ');">' . $word . '</a>';
        }
    }

    public static function cancelCommentReplyLink(string $word = 'Cancel', string $formId = 'respond')
    {
        if (self::options()->commentsThreaded) {
            echo '<a href="#' . $formId . '" rel="nofollow" onclick="return typechoCancelCommentReply(\'' .
                $formId . '\');">' . $word . '</a>';
        }
    }

    public static function threadedCommentsScript()
    {
        if (self::options()->commentsThreaded) {
            echo
            <<<EOF
<script type="text/javascript">
var typechoAddCommentReply = function (cid, coid, cfid, style) {
    var _ce = document.getElementById(cid), _cp = _ce.parentNode;
    var _cf = document.getElementById(cfid);

    var _pi = document.getElementById('comment-parent');
    if (null == _pi) {
        _pi = document.createElement('input');
        _pi.setAttribute('type', 'hidden');
        _pi.setAttribute('name', 'parent');
        _pi.setAttribute('id', 'comment-parent');

        var _form = 'form' == _cf.tagName ? _cf : _cf.getElementsByTagName('form')[0];

        _form.appendChild(_pi);
    }
    _pi.setAttribute('value', coid);

    if (null == document.getElementById('comment-form-place-holder')) {
        var _cfh = document.createElement('div');
        _cfh.setAttribute('id', 'comment-form-place-holder');
        _cf.parentNode.insertBefore(_cfh, _cf);
    }

    1 == style ? (null == _ce.nextSibling ? _cp.appendChild(_cf)
    : _cp.insertBefore(_cf, _ce.nextSibling)) : _ce.appendChild(_cf);

    return false;
};

var typechoCancelCommentReply = function (cfid) {
    var _cf = document.getElementById(cfid),
    _cfh = document.getElementById('comment-form-place-holder');

    var _pi = document.getElementById('comment-parent');
    if (null != _pi) {
        _pi.parentNode.removeChild(_pi);
    }

    if (null == _cfh) {
        return true;
    }

    _cfh.parentNode.insertBefore(_cf, _cfh);
    return false;
};
</script>
EOF;
        }
    }
}
