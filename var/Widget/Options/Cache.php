<?php

namespace Widget\Options;

use Typecho\Cache as CacheFacade;
use Typecho\Db\Exception;
use Typecho\Widget\Helper\Form;
use Widget\ActionInterface;
use Widget\Base\Options;
use Widget\Notice;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Cache extends Options implements ActionInterface
{
    public function updateCacheSettings()
    {
        if ($this->form()->validate()) {
            $this->response->goBack();
        }

        $settings = $this->request->from(
            'cacheStatus',
            'cacheDriver',
            'cacheTtl',
            'cachePrefix',
            'cacheCommentFlush',
            'cacheRedisHost',
            'cacheRedisPort',
            'cacheRedisPassword',
            'cacheRedisDatabase'
        );

        $settings['cacheStatus'] = (int) (!empty($settings['cacheStatus']) ? 1 : 0);
        $settings['cacheDriver'] = strtolower((string) ($settings['cacheDriver'] ?? 'redis'));
        if (!in_array($settings['cacheDriver'], ['apcu', 'redis'], true)) {
            $settings['cacheDriver'] = 'redis';
        }

        $settings['cacheTtl'] = max(1, min(86400, (int) $settings['cacheTtl']));
        $settings['cachePrefix'] = preg_replace('/[^a-zA-Z0-9:_-]/', '', trim((string) $settings['cachePrefix'])) ?: 'typerenew:cache:';
        $settings['cacheCommentFlush'] = (int) (!empty($settings['cacheCommentFlush']) ? 1 : 0);
        $settings['cacheRedisHost'] = trim((string) $settings['cacheRedisHost']);
        if ($settings['cacheRedisHost'] === '') {
            $settings['cacheRedisHost'] = '127.0.0.1';
        }

        $settings['cacheRedisPort'] = max(1, min(65535, (int) $settings['cacheRedisPort']));
        $settings['cacheRedisPassword'] = (string) ($settings['cacheRedisPassword'] ?? '');
        if ($settings['cacheRedisPassword'] === '') {
            $settings['cacheRedisPassword'] = (string) ($this->options->cacheRedisPassword ?? '');
        }
        $settings['cacheRedisDatabase'] = max(0, min(15, (int) $settings['cacheRedisDatabase']));

        foreach ($settings as $name => $value) {
            $this->saveOption($name, $value);
        }

        CacheFacade::init([
            'status' => $settings['cacheStatus'],
            'driver' => $settings['cacheDriver'],
            'ttl' => $settings['cacheTtl'],
            'prefix' => $settings['cachePrefix'],
            'redisHost' => $settings['cacheRedisHost'],
            'redisPort' => $settings['cacheRedisPort'],
            'redisPassword' => $settings['cacheRedisPassword'],
            'redisDatabase' => $settings['cacheRedisDatabase']
        ]);

        Notice::alloc()->set(_t('设置已经保存'), 'success');
        $this->response->goBack();
    }

    public function clearCache()
    {
        $count = CacheFacade::getInstance()->flush();
        Notice::alloc()->set(_t('缓存已清空，共清理 %d 项', $count), 'success');
        $this->response->goBack();
    }

    public function panel(): array
    {
        return CacheFacade::getInstance()->panel();
    }

    public function form(): Form
    {
        $form = new Form($this->security->getIndex('/action/options-cache'), Form::POST_METHOD);

        $cacheStatus = new Form\Element\Radio(
            'cacheStatus',
            ['0' => _t('关闭'), '1' => _t('开启')],
            (string) ($this->options->cacheStatus ?? '0'),
            _t('缓存状态')
        );
        $form->addInput($cacheStatus);

        $cacheCommentFlush = new Form\Element\Radio(
            'cacheCommentFlush',
            ['1' => _t('开启'), '0' => _t('关闭')],
            (string) ($this->options->cacheCommentFlush ?? '1'),
            _t('评论后自动清理缓存'),
            _t('评论发布、后台审核通过或后台回复评论后自动清理缓存，减少前台评论延迟显示')
        );
        $form->addInput($cacheCommentFlush);

        $cacheDriver = new Form\Element\Select(
            'cacheDriver',
            ['redis' => 'Redis', 'apcu' => 'APCu'],
            (string) ($this->options->cacheDriver ?? 'redis'),
            _t('缓存驱动')
        );
        $form->addInput($cacheDriver);

        $cacheTtl = new Form\Element\Number(
            'cacheTtl',
            null,
            (int) ($this->options->cacheTtl ?? 300),
            _t('缓存时间'),
            _t('单位为秒，最小 1 秒')
        );
        $cacheTtl->input->setAttribute('class', 'w-20');
        $form->addInput($cacheTtl->addRule('isInteger', _t('请填入一个数字')));

        $cachePrefix = new Form\Element\Text(
            'cachePrefix',
            null,
            (string) ($this->options->cachePrefix ?? 'typerenew:cache:'),
            _t('索引前缀'),
            _t('仅允许字母、数字、冒号、下划线与短横线')
        );
        $cachePrefix->input->setAttribute('class', 'w-100 mono');
        $form->addInput($cachePrefix->addRule('required', _t('请填写索引前缀')));

        $cacheRedisHost = new Form\Element\Text(
            'cacheRedisHost',
            null,
            (string) ($this->options->cacheRedisHost ?? '127.0.0.1'),
            _t('Redis 主机')
        );
        $cacheRedisHost->input->setAttribute('class', 'w-100 mono');
        $form->addInput($cacheRedisHost->addRule('required', _t('请填写 Redis 主机')));

        $cacheRedisPort = new Form\Element\Number(
            'cacheRedisPort',
            null,
            (int) ($this->options->cacheRedisPort ?? 6379),
            _t('Redis 端口')
        );
        $cacheRedisPort->input->setAttribute('class', 'w-20');
        $form->addInput($cacheRedisPort->addRule('isInteger', _t('请填入一个数字')));

        $cacheRedisPassword = new Form\Element\Password(
            'cacheRedisPassword',
            null,
            '',
            _t('Redis 密码')
        );
        $cacheRedisPassword->input->setAttribute('class', 'w-100 mono');
        $form->addInput($cacheRedisPassword);

        $cacheRedisDatabase = new Form\Element\Number(
            'cacheRedisDatabase',
            null,
            (int) ($this->options->cacheRedisDatabase ?? 0),
            _t('Redis 数据库')
        );
        $cacheRedisDatabase->input->setAttribute('class', 'w-20');
        $form->addInput($cacheRedisDatabase->addRule('isInteger', _t('请填入一个数字')));

        $submit = new Form\Element\Submit('submit', null, _t('保存设置'));
        $submit->input->setAttribute('class', 'btn primary');
        $form->addItem($submit);

        return $form;
    }

    public function action()
    {
        $this->user->pass('administrator');
        $this->security->protect();

        if ($this->request->isPost() && $this->request->get('do') === 'flush') {
            $this->clearCache();
            return;
        }

        $this->on($this->request->isPost())->updateCacheSettings();
        $this->response->redirect($this->options->adminUrl);
    }

    private function saveOption(string $name, $value): void
    {
        $exists = $this->db->fetchRow($this->db->select('name')->from('table.options')->where('name = ?', $name));
        $value = is_array($value) ? json_encode($value) : (string) $value;

        if ($exists) {
            $this->update(['value' => $value], $this->db->sql()->where('name = ?', $name));
            return;
        }

        $this->insert([
            'name' => $name,
            'user' => 0,
            'value' => $value
        ]);
    }
}
