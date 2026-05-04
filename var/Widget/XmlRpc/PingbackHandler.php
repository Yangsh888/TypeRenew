<?php

namespace Widget\XmlRpc;

use IXR\Exception;
use IXR\Pingback;
use Typecho\Common;
use Typecho\Router;
use Widget\Archive;
use Widget\Base\Comments;
use Widget\XmlRpc as XmlRpcWidget;
use Typecho\Widget\Exception as WidgetException;

class PingbackHandler extends AbstractHandler
{
    /**
     * @throws \Exception
     */
    public function pingbackPing(string $source, string $target): int
    {
        $options = $this->xmlRpc->optionsWidget();
        $request = $this->xmlRpc->requestWidget();
        $db = $this->xmlRpc->db();

        if ((int) $options->allowXmlRpc !== 2) {
            throw new Exception(_t('Pingback 接口已关闭'), 49);
        }

        $pathInfo = Common::url(substr($target, strlen((string) $options->index)), '/');
        $post = Router::match($pathInfo);

        $params = Common::parseUrl($source);
        if (!isset($params['host']) || !isset($params['scheme']) || !in_array($params['scheme'], ['http', 'https'])) {
            throw new Exception(_t('源地址服务器错误'), 16);
        }

        if (!$this->isSafePingbackHost((string) $params['host'])) {
            throw new Exception(_t('源地址服务器错误'), 16);
        }

        if (!($post instanceof Archive) || !$post->have() || !$post->is('single')) {
            throw new Exception(_t('这个目标地址不存在'), 33);
        }

        if (!$post->allowPing) {
            throw new Exception(_t('目标地址禁止Ping'), 49);
        }

        $pingNum = $db->fetchObject($db->select(['COUNT(coid)' => 'num'])
            ->from('table.comments')
            ->where(
                'table.comments.cid = ? AND table.comments.url = ? AND table.comments.type <> ?',
                $post->cid,
                $source,
                'comment'
            ))->num;

        if ($pingNum > 0) {
            throw new Exception(_t('PingBack已经存在'), 48);
        }

        try {
            $pingbackRequest = new Pingback($source, $target);

            $pingback = [
                'cid' => $post->cid,
                'created' => $options->time,
                'agent' => $request->getAgent(),
                'ip' => $request->getIp(),
                'author' => $pingbackRequest->getTitle(),
                'url' => Common::safeUrl($source),
                'text' => $pingbackRequest->getContent(),
                'ownerId' => $post->author->uid,
                'type' => 'pingback',
                'status' => $options->commentsRequireModeration ? 'waiting' : 'approved'
            ];

            $pingback = XmlRpcWidget::pluginHandle()->filter('pingback', $pingback, $post);
            $insertId = Comments::alloc()->insert($pingback);
            XmlRpcWidget::pluginHandle()->call('finishPingback', $this->xmlRpc);

            return $insertId;
        } catch (WidgetException $e) {
            throw new Exception(_t('源地址服务器错误'), 16);
        }
    }

    private function isSafePingbackHost(string $host): bool
    {
        if (!Common::checkSafeHost($host)) {
            return false;
        }

        $ipv4s = gethostbynamel($host);
        if (is_array($ipv4s) && !empty($ipv4s)) {
            foreach ($ipv4s as $ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                    return false;
                }
            }

            return true;
        }

        $records = @dns_get_record($host, DNS_AAAA);
        if (!is_array($records) || empty($records)) {
            return false;
        }

        foreach ($records as $record) {
            $ipv6 = (string) ($record['ipv6'] ?? '');
            if (
                $ipv6 === ''
                || filter_var($ipv6, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
            ) {
                return false;
            }
        }

        return true;
    }
}
