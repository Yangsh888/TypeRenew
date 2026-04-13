<?php

namespace Widget\Options;

use Typecho\Db\Exception;
use Typecho\Plugin;
use Typecho\Widget\Helper\Form;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 文章阅读设置组件
 *
 * @author qining
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Reading extends Permalink
{
    /**
     * 执行更新动作
     *
     * @throws Exception
     */
    public function updateReadingSettings()
    {
        $this->validateFormOrGoBack($this->form());

        $settings = $this->request->from(
            'postDateFormat',
            'frontPage',
            'frontArchive',
            'pageSize',
            'postsListSize',
            'feedFullText'
        );

        $settings['pageSize'] = max(1, (int) $settings['pageSize']);
        $settings['postsListSize'] = max(1, (int) $settings['postsListSize']);

        if (
            'page' == $settings['frontPage'] && $this->request->is('frontPagePage') &&
            $this->db->fetchRow($this->db->select('cid')
                ->from('table.contents')->where('type = ?', 'page')
                ->where('status = ?', 'publish')->where('created < ?', $this->options->time)
                ->where('cid = ?', $pageId = intval($this->request->get('frontPagePage'))))
        ) {
            $settings['frontPage'] = 'page:' . $pageId;
        } elseif (
            'file' == $settings['frontPage'] && $this->request->is('frontPageFile') &&
            file_exists(__TYPECHO_ROOT_DIR__ . '/' . __TYPECHO_THEME_DIR__ . '/' . $this->options->theme . '/' .
                ($file = trim((string) $this->request->get('frontPageFile', ''), " ./\\")))
        ) {
            $settings['frontPage'] = 'file:' . $file;
        } else {
            $settings['frontPage'] = 'recent';
        }

        if ('recent' != $settings['frontPage']) {
            $settings['frontArchive'] = empty($settings['frontArchive']) ? 0 : 1;
            if ($settings['frontArchive']) {
                $routingTable = $this->options->routingTable;
                $routingTable['archive']['url'] = '/' . ltrim($this->encodeRule($this->request->get('archivePattern')), '/');
                $routingTable['archive_page']['url'] = rtrim($routingTable['archive']['url'], '/')
                    . '/page/[page:digital]/';

                if (isset($routingTable[0])) {
                    unset($routingTable[0]);
                }

                $settings['routingTable'] = json_encode($routingTable);
            }
        } else {
            $settings['frontArchive'] = 0;
        }

        $this->persistOptions($settings);

        $this->saveSuccessAndGoBack();
    }

    /**
     * @return Form
     */
    public function form(): Form
    {
        $form = new Form($this->security->getIndex('/action/options-reading'), Form::POST_METHOD);

        $postDateFormat = new Form\Element\Text(
            'postDateFormat',
            null,
            $this->options->postDateFormat,
            _t('文章日期格式'),
            _t('此格式用于定义文章归档中日期的默认显示样式') . '<br />'
            . _t('需注意，部分主题中该格式可能不生效，因主题作者可自定义日期格式') . '<br />'
            . _t('具体写法，请参考 <a href="https://www.php.net/manual/zh/function.date.php">PHP 日期格式写法</a>')
        );
        $postDateFormat->input->setAttribute('class', 'w-40 mono');
        $form->addInput($postDateFormat->addRule('xssCheck', _t('请不要在日期格式中使用特殊字符')));

        $frontPageParts = explode(':', $this->options->frontPage);
        $frontPageType = $frontPageParts[0];
        $frontPageValue = count($frontPageParts) > 1 ? $frontPageParts[1] : '';

        $frontPageOptions = [
            'recent' => _t('显示最新发布的文章')
        ];

        $frontPattern = '</label></span><span class="multiline front-archive%class%">'
            . '<input type="checkbox" id="frontArchive" name="frontArchive" value="1"'
            . ($this->options->frontArchive && 'recent' != $frontPageType ? ' checked' : '') . ' />
<label for="frontArchive">' . _t(
                '同时将文章列表页路径更改为 %s',
                '<input type="text" name="archivePattern" class="w-20 mono" value="'
                . htmlspecialchars($this->decodeRule($this->options->routingTable['archive']['url'])) . '" />'
            )
            . '</label>';

        $pages = $this->db->fetchAll($this->db->select('cid', 'title')
            ->from('table.contents')->where('type = ?', 'page')
            ->where('status = ?', 'publish')->where('created < ?', $this->options->time));

        if (!empty($pages)) {
            $pagesSelect = '<select name="frontPagePage" id="frontPage-frontPagePage">';
            foreach ($pages as $page) {
                $selected = '';
                if ('page' == $frontPageType && $page['cid'] == $frontPageValue) {
                    $selected = ' selected="true"';
                }

                $pagesSelect .= '<option value="' . $page['cid'] . '"' . $selected
                    . '>' . $page['title'] . '</option>';
            }
            $pagesSelect .= '</select>';
            $frontPageOptions['page'] = _t(
                '使用 %s 页面作为首页',
                '</label>' . $pagesSelect . '<label for="frontPage-frontPagePage">'
            );
            $selectedFrontPageType = 'page';
        }

        $files = glob($this->options->themeFile($this->options->theme, '*.php'));
        $filesSelect = '';

        foreach ($files as $file) {
            $info = Plugin::parseInfo($file);
            $file = basename($file);

            if ('index.php' != $file && 'index' == $info['title']) {
                $selected = '';
                if ('file' == $frontPageType && $file == $frontPageValue) {
                    $selected = ' selected="true"';
                }

                $filesSelect .= '<option value="' . $file . '"' . $selected
                    . '>' . $file . '</option>';
            }
        }

        if (!empty($filesSelect)) {
            $frontPageOptions['file'] = _t(
                '直接调用 %s 模板文件',
                '</label><select name="frontPageFile" id="frontPage-frontPageFile">'
                . $filesSelect . '</select><label for="frontPage-frontPageFile">'
            );
            $selectedFrontPageType = 'file';
        }

        if (isset($frontPageOptions[$frontPageType]) && 'recent' != $frontPageType && isset($selectedFrontPageType)) {
            $selectedFrontPageType = $frontPageType;
            $frontPattern = str_replace('%class%', '', $frontPattern);
        }

        if (isset($selectedFrontPageType)) {
            $frontPattern = str_replace('%class%', ' hidden', $frontPattern);
            $frontPageOptions[$selectedFrontPageType] .= $frontPattern;
        }

        $frontPage = new Form\Element\Radio('frontPage', $frontPageOptions, $frontPageType, _t('站点首页'));
        $form->addInput($frontPage->multiMode());

        $postsListSize = new Form\Element\Number(
            'postsListSize',
            null,
            $this->options->postsListSize,
            _t('文章列表数目'),
            _t('此数目用于指定显示在侧边栏中的文章列表数目')
        );
        $postsListSize->input->setAttribute('class', 'w-20');
        $form->addInput($postsListSize->addRule('isInteger', _t('请填入一个数字')));

        $pageSize = new Form\Element\Number(
            'pageSize',
            null,
            $this->options->pageSize,
            _t('每页文章数目'),
            _t('此数目用于指定文章归档输出时每页显示的文章数目')
        );
        $pageSize->input->setAttribute('class', 'w-20');
        $form->addInput($pageSize->addRule('isInteger', _t('请填入一个数字')));

        $feedFullText = new Form\Element\Radio(
            'feedFullText',
            ['0' => _t('仅输出摘要'), '1' => _t('全文输出')],
            $this->options->feedFullText,
            _t('聚合全文输出'),
            _t('若不希望在内容聚合页展示文章全文，可选择「仅输出摘要」选项') . '<br />'
            . _t('摘要的文字范围由您在文章内添加的分隔符位置决定')
        );
        $form->addInput($feedFullText);

        $submit = new Form\Element\Submit('submit', null, _t('保存设置'));
        $submit->input->setAttribute('class', 'btn primary');
        $form->addItem($submit);

        return $form;
    }

    public function action()
    {
        $this->user->pass('administrator');
        $this->security->protect();
        $this->on($this->request->isPost())->updateReadingSettings();
        $this->response->redirect($this->options->adminUrl);
    }
}
