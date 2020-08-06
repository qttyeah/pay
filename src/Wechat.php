<?php

namespace Qttyeah\Pay;

use Qttyeah\Sfunc;

/**
 * Created by PhpStorm.
 * User: 15213
 * Date: 2020/8/5
 * Time: 16:34
 */
class Wechat
{
    /**
     * 配置
     * @var array
     */
    protected $config = [
        'app_id' => '',
        'mch_id' => '',
        'key' => '',
        'cert_path' => '',
        'key_path' => '',
        'notify_url' => ''
    ];

    /**
     * 微信支付urls
     * @var array
     */
    protected $urls = [
        'unifiedorder' => 'https://api.mch.weixin.qq.com/pay/unifiedorder',
        'orderquery' => 'https://api.mch.weixin.qq.com/pay/orderquery',
        'refund' => 'https://api.mch.weixin.qq.com/pay/refund',
    ];

    /**
     * 构造函数
     * Wechat constructor.
     * @param array $options
     */
    public function __construct($options = [])
    {
        if (!empty($options)) {
            $this->config = array_merge($this->config, $options);
        }
    }

    /**
     * 支付下单
     * @param array $data
     * @return array
     */
    public function createOrder(array $data = [])
    {
        $timestamp = time();
        $data['appid'] = $this->config['app_id'];
        $data['mch_id'] = $this->config['mch_id'];
        $data['body'] = isset($data['body']) ? $data['body'] : '微信支付';
        $data['nonce_str'] = Sfunc::nonceStr();
        $data['spbill_create_ip'] = Sfunc::getIP();
        $data['total_fee'] = intval($data['total_fee'] * 100);
        $data['notify_url'] = isset($data['notify_url']) ? $data['notify_url'] : $this->config['notify_url'];
        $data['out_trade_no'] = $data['out_trade_no'] . rand(1000, 9999);
        //第一次签名
        $data['sign'] = Sfunc::WeChatSign($data, $this->config['key']);

        $responseXML = $this->curlPost($this->urls['unifiedorder'], Sfunc::arrayToXml($data));

        $unifiedOrder = simplexml_load_string($responseXML, 'SimpleXMLElement', LIBXML_NOCDATA);

        if (false === $unifiedOrder) {
            return ['result_code' => 0, 'return_msg' => "parse xml error"];
        }
        if ($unifiedOrder->return_code != 'SUCCESS') {
            return ['result_code' => 0, 'return_msg' => $unifiedOrder->return_msg];
        }
        if ($unifiedOrder->result_code != 'SUCCESS') {
            return ['result_code' => 0, 'return_msg' => $unifiedOrder->err_code];
        }
        $resultArr = [
            "appId" => $this->config['app_id'],
            "timeStamp" => "$timestamp",        //这里是字符串的时间戳，不是int，所以需加引号
            "nonceStr" => Sfunc::nonceStr(),
            "package" => "prepay_id=" . $unifiedOrder->prepay_id,
            "signType" => 'MD5'
        ];
        //二次签名
        $resultArr['paySign'] = Sfunc::WeChatSign($resultArr, $this->config['key']);
        return $resultArr;
    }

    /**
     * 查找订单
     * @param $transaction_id
     * @return array|bool|mixed
     */
    public function findOrder($transaction_id)
    {
        $data = [
            'appid' => $this->config['app_id'],
            'mch_id' => $this->config['mch_id'],
            'nonce_str' => Sfunc::nonceStr(),
            'transaction_id' => $transaction_id
        ];
        $data['sign'] = Sfunc::WeChatSign($data, $this->config['key']);
        $responseXML = $this->curlPost($this->urls['orderquery'], Sfunc::arrayToXml($data));
        if (false === $responseXML) {
            return ['result_code' => 0, 'return_msg' => "parse xml error"];
        }
        $response = Sfunc::xmlToarray($responseXML);
        if (isset($response['result_code']) && 'SUCCESS' !== $response['result_code']) {
            return ['result_code' => 0, 'return_msg' => $response];
        }
        return $response;
    }

    /**
     * 订单退款
     * @param array $data transaction_id out_refund_no total_fee refund_fee
     * @return array|bool|mixed
     */
    public function refundOrder(array $data)
    {
        $data['appid'] = $this->config['app_id'];
        $data['mch_id'] = $this->config['mch_id'];
        $data['nonce_str'] = Sfunc::nonceStr();
        $data['total_fee'] = $data['total_fee']*100;
        $data['refund_fee'] = $data['refund_fee']*100;
        $data['sign'] = Sfunc::WeChatSign($data,$this->config['key']);
        $options = [
            'CURLOPT_SSLCERTTYPE' => 'PEM',
            'CURLOPT_SSLCERT' => $this->config['cert_path'],
            'CURLOPT_SSLKEYTYPE' => 'PEM',
            'CURLOPT_SSLKEY' => $this->config['key_path'],
        ];
        $responseXML = $this->curlPost($this->urls['refund'],Sfunc::arrayToXml($data),$options);
        if (false === $responseXML) {
            return ['result_code' => 0, 'return_msg' => "parse xml error"];
        }
        $response = Sfunc::xmlToarray($responseXML);
        if (isset($response['result_code']) && 'SUCCESS' !== $response['result_code']) {
            return ['result_code' => 0, 'return_msg' => $response];
        }
        return $response;
    }

    function __call($name, $arguments)
    {
        return false;
    }


    /**
     * curl 发送
     * @param string $url
     * @param string $postData
     * @param array $options
     * @return mixed
     */
    protected function curlPost($url = '', $postData = '', $options = array())
    {
        if (is_array($postData)) {
            $postData = http_build_query($postData);
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); //设置cURL允许执行的最长秒数

        //https请求 不验证证书和host
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        if (!empty($options)) {
            curl_setopt_array($ch, $options);
        }

        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }

}