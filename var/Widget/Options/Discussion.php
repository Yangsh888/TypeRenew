<?php

namespace Widget\Options;

use Typecho\Db\Exception;
use Typecho\Widget\Helper\Form;
use Widget\ActionInterface;
use Widget\Base\Options;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 评论设置组件
 *
 * @author qining
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Discussion extends Options implements ActionInterface
{
    use EditTrait;

    /**
     * 执行更新动作
     *
     * @throws Exception
     */
    public function updateDiscussionSettings()
    {
        $this->validateFormOrGoBack($this->form());

        $settings = $this->request->from(
            'commentDateFormat',
            'commentsListSize',
            'commentsPageSize',
            'commentsPageDisplay',
            'commentsAvatar',
            'commentsOrder',
            'commentsMaxNestingLevels',
            'commentsUrlNofollow',
            'commentsPostTimeout',
            'commentsWhitelist',
            'commentsRequireMail',
            'commentsAvatarRating',
            'commentsPostInterval',
            'commentsRequireModeration',
            'commentsRequireUrl',
            'commentsHTMLTagAllowed'
        );
        $settings['commentsShow'] = $this->request->getArray('commentsShow');
        $settings['commentsPost'] = $this->request->getArray('commentsPost');

        $settings['commentsShowCommentOnly'] = $this->isEnableByCheckbox(
            $settings['commentsShow'],
            'commentsShowCommentOnly'
        );
        $settings['commentsMarkdown'] = $this->isEnableByCheckbox($settings['commentsShow'], 'commentsMarkdown');
        $settings['commentsShowUrl'] = $this->isEnableByCheckbox($settings['commentsShow'], 'commentsShowUrl');
        $settings['commentsUrlNofollow'] = $this->isEnableByCheckbox($settings['commentsShow'], 'commentsUrlNofollow');
        $settings['commentsAvatar'] = $this->isEnableByCheckbox($settings['commentsShow'], 'commentsAvatar');
        $settings['commentsPageBreak'] = $this->isEnableByCheckbox($settings['commentsShow'], 'commentsPageBreak');
        $settings['commentsThreaded'] = $this->isEnableByCheckbox($settings['commentsShow'], 'commentsThreaded');

        $settings['commentsListSize'] = max(1, (int) $settings['commentsListSize']);
        $settings['commentsPageSize'] = max(1, (int) $settings['commentsPageSize']);
        $settings['commentsMaxNestingLevels'] = min(7, max(2, intval($settings['commentsMaxNestingLevels'])));
        $settings['commentsPageDisplay'] = ('first' == $settings['commentsPageDisplay']) ? 'first' : 'last';
        $settings['commentsOrder'] = ('DESC' == $settings['commentsOrder']) ? 'DESC' : 'ASC';
        $settings['commentsAvatarRating'] = in_array($settings['commentsAvatarRating'], ['G', 'PG', 'R', 'X'])
            ? $settings['commentsAvatarRating'] : 'G';

        $settings['commentsRequireModeration'] = $this->isEnableByCheckbox(
            $settings['commentsPost'],
            'commentsRequireModeration'
        );
        $settings['commentsWhitelist'] = $this->isEnableByCheckbox($settings['commentsPost'], 'commentsWhitelist');
        $settings['commentsRequireMail'] = $this->isEnableByCheckbox($settings['commentsPost'], 'commentsRequireMail');
        $settings['commentsRequireUrl'] = $this->isEnableByCheckbox($settings['commentsPost'], 'commentsRequireUrl');
        $settings['commentsCheckReferer'] = $this->isEnableByCheckbox(
            $settings['commentsPost'],
            'commentsCheckReferer'
        );
        $settings['commentsAntiSpam'] = $this->isEnableByCheckbox($settings['commentsPost'], 'commentsAntiSpam');
        $settings['commentsAutoClose'] = $this->isEnableByCheckbox($settings['commentsPost'], 'commentsAutoClose');
        $settings['commentsPostIntervalEnable'] = $this->isEnableByCheckbox(
            $settings['commentsPost'],
            'commentsPostIntervalEnable'
        );

        $settings['commentsPostTimeout'] = intval($settings['commentsPostTimeout']) * 24 * 3600;
        $settings['commentsPostInterval'] = round($settings['commentsPostInterval'], 1) * 60;

        unset($settings['commentsShow']);
        unset($settings['commentsPost']);

        $this->persistOptions($settings);

        $this->saveSuccessAndGoBack();
    }

    /**
     * @return Form
     */
    public function form(): Form
    {
        $form = new Form($this->security->getIndex('/action/options-discussion'), Form::POST_METHOD);

        $commentDateFormat = new Form\Element\Text(
            'commentDateFormat',
            null,
            $this->options->commentDateFormat,
            _t('评论日期格式'),
            _t('此为评论日期的默认格式：在模板中调用评论日期显示方法时，若未指定日期格式，将按此格式输出') . '<br />'
            . _t('具体写法请参考 <a href="https://www.php.net/manual/zh/function.date.php">PHP 日期格式写法</a>')
        );
        $commentDateFormat->input->setAttribute('class', 'w-40 mono');
        $form->addInput($commentDateFormat);

        /** 评论列表数目 */
        $commentsListSize = new Form\Element\Number(
            'commentsListSize',
            null,
            $this->options->commentsListSize,
            _t('评论列表数目'),
            _t('此数目用于指定显示在侧边栏中的评论列表数目')
        );
        $commentsListSize->input->setAttribute('class', 'w-20');
        $form->addInput($commentsListSize->addRule('isInteger', _t('请填入一个数字')));

        $commentsShowOptions = [
            'commentsShowCommentOnly' => _t('仅显示评论, 不显示 Pingback 和 Trackback'),
            'commentsMarkdown'        => _t('在评论中使用 Markdown 语法'),
            'commentsShowUrl'         => _t('评论者名称显示时自动加上其个人主页链接'),
            'commentsUrlNofollow'     => _t('对评论者个人主页链接使用 <a href="https://en.wikipedia.org/wiki/Nofollow">nofollow 属性</a>'),
            'commentsAvatar'          => _t('启用 <a href="https://gravatar.com">Gravatar</a> 头像服务, 最高显示评级为 %s 的头像',
                '</label><select id="commentsShow-commentsAvatarRating" name="commentsAvatarRating">
            <option value="G"' . ('G' == $this->options->commentsAvatarRating ? ' selected="true"' : '') . '>' . _t('G - 普通') . '</option>
            <option value="PG"' . ('PG' == $this->options->commentsAvatarRating ? ' selected="true"' : '') . '>' . _t('PG - 13岁以上') . '</option>
            <option value="R"' . ('R' == $this->options->commentsAvatarRating ? ' selected="true"' : '') . '>' . _t('R - 17岁以上成人') . '</option>
            <option value="X"' . ('X' == $this->options->commentsAvatarRating ? ' selected="true"' : '') . '>' . _t('X - 限制级') . '</option></select>
            <label for="commentsShow-commentsAvatarRating">'),
            'commentsPageBreak'       => _t('启用分页, 并且每页显示 %s 篇评论, 在列出时将 %s 作为默认显示',
                '</label><input type="number" value="' . $this->options->commentsPageSize
                . '" class="text num text-s" id="commentsShow-commentsPageSize" name="commentsPageSize" /><label for="commentsShow-commentsPageSize">',
                '</label><select id="commentsShow-commentsPageDisplay" name="commentsPageDisplay">
            <option value="first"' . ('first' == $this->options->commentsPageDisplay ? ' selected="true"' : '') . '>' . _t('第一页') . '</option>
            <option value="last"' . ('last' == $this->options->commentsPageDisplay ? ' selected="true"' : '') . '>' . _t('最后一页') . '</option></select>'
                . '<label for="commentsShow-commentsPageDisplay">'),
            'commentsThreaded'        => _t('启用评论回复, 以 %s 层作为每个评论最多的回复层数',
                    '</label><input name="commentsMaxNestingLevels" type="number" class="text num text-s" value="' . $this->options->commentsMaxNestingLevels . '" id="commentsShow-commentsMaxNestingLevels" />
            <label for="commentsShow-commentsMaxNestingLevels">') . '</label></span><span class="multiline">'
                . _t('将 %s 的评论显示在前面', '<select id="commentsShow-commentsOrder" name="commentsOrder">
            <option value="DESC"' . ('DESC' == $this->options->commentsOrder ? ' selected="true"' : '') . '>' . _t('较新的') . '</option>
            <option value="ASC"' . ('ASC' == $this->options->commentsOrder ? ' selected="true"' : '') . '>' . _t('较旧的') . '</option></select><label for="commentsShow-commentsOrder">')
        ];

        $commentsShowOptionsValue = $this->collectEnabledKeys($this->options, [
            'commentsShowCommentOnly',
            'commentsMarkdown',
            'commentsShowUrl',
            'commentsUrlNofollow',
            'commentsAvatar',
            'commentsPageBreak',
            'commentsThreaded'
        ]);

        $commentsShow = new Form\Element\Checkbox(
            'commentsShow',
            $commentsShowOptions,
            $commentsShowOptionsValue,
            _t('评论显示')
        );
        $form->addInput($commentsShow->multiMode());

        $commentsPostOptions = [
            'commentsRequireModeration'  => _t('所有评论必须经过审核'),
            'commentsWhitelist'          => _t('评论者之前须有评论通过了审核'),
            'commentsRequireMail'        => _t('必须填写邮箱'),
            'commentsRequireUrl'         => _t('必须填写网址'),
            'commentsCheckReferer'       => _t('检查评论来源页 URL 是否与文章链接一致'),
            'commentsAntiSpam'           => _t('开启反垃圾保护'),
            'commentsAutoClose'          => _t('在文章发布 %s 天以后自动关闭评论',
                '</label><input name="commentsPostTimeout" type="number" class="text num text-s" value="' . intval($this->options->commentsPostTimeout / (24 * 3600)) . '" id="commentsPost-commentsPostTimeout" />
            <label for="commentsPost-commentsPostTimeout">'),
            'commentsPostIntervalEnable' => _t('同一 IP 发布评论的时间间隔限制为 %s 分钟',
                '</label><input name="commentsPostInterval" type="number" class="text num text-s" value="' . round($this->options->commentsPostInterval / (60), 1) . '" id="commentsPost-commentsPostInterval" />
            <label for="commentsPost-commentsPostInterval">')
        ];

        $commentsPostOptionsValue = $this->collectEnabledKeys($this->options, [
            'commentsRequireModeration',
            'commentsWhitelist',
            'commentsRequireMail',
            'commentsRequireUrl',
            'commentsCheckReferer',
            'commentsAntiSpam',
            'commentsAutoClose',
            'commentsPostIntervalEnable'
        ]);

        $commentsPost = new Form\Element\Checkbox(
            'commentsPost',
            $commentsPostOptions,
            $commentsPostOptionsValue,
            _t('评论提交')
        );
        $form->addInput($commentsPost->multiMode());

        $commentsHTMLTagAllowed = new Form\Element\Textarea(
            'commentsHTMLTagAllowed',
            null,
            $this->options->commentsHTMLTagAllowed,
            _t('允许使用的HTML标签和属性'),
            _t('默认的用户评论不允许填写任何的HTML标签, 你可以在这里填写允许使用的HTML标签') . '<br />'
            . _t('比如: %s', '<code>&lt;a href=&quot;&quot;&gt; &lt;img src=&quot;&quot;&gt; &lt;blockquote&gt;</code>')
        );
        $commentsHTMLTagAllowed->input->setAttribute('class', 'mono');
        $form->addInput($commentsHTMLTagAllowed);

        $submit = new Form\Element\Submit('submit', null, _t('保存设置'));
        $submit->input->setAttribute('class', 'btn primary');
        $form->addItem($submit);

        return $form;
    }

    public function action()
    {
        $this->user->pass('administrator');
        $this->security->protect();
        $this->on($this->request->isPost())->updateDiscussionSettings();
        $this->response->redirect($this->options->adminUrl);
    }
}
