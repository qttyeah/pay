<?php
/**
 * Created by PhpStorm.
 * User: 15213
 * Date: 2020/8/6
 * Time: 9:32
 */

namespace Qttyeah\Pay;


use Qttyeah\Sfunc;

class Fuiou
{

    /**
     * @var array
     */
    protected $options = [
        'ins_cd' => '',
        'mchnt_cd' => '',
        'term_id' => '88888888',
        'noPrefix' => '',
        'notify_url' => '',
        'appid' => '',
        'cert_key' => '',
    ];

    /**
     * @var array
     */
    protected $urls = [
        'wxPreCreate' => 'https://spay-mc.fuioupay.com/wxPreCreate',
        'commonQuery' => 'https://spay-mc.fuioupay.com/commonQuery',
        'commonRefund' => 'https://spay-mc.fuioupay.com/commonRefund',
    ];

    protected $xml;

    /**
     * Fuiou constructor.
     * @param array $options
     */
    public function __construct(array $options)
    {
        if (!empty($options)) {
            $this->options = array_merge($this->options, $options);
        }

        $this->xml = new \XMLWriter();
    }

    /**
     * 下单
     * @param array $data version=1;mchnt_order_no订单号;goods_des商品信息；order_amt价格（元）;notify_url(回调url)
     *                     trade_type类型（JSAPI--公众号支付、FWC--支付宝服 务窗、LETPAY-小程序、BESTPAY--翼支付js）
     *                      goods_detail商品；addn_inf：备注；goods_tag(商品标记)；limit_pay（no_credit:不能使用信用卡）
     *                      openid：product_id（商品标识）
     * @return array
     */
    public function createOrder(array $data)
    {
        $data['version'] = isset($data['version']) ? $data['version'] : '1';;
        $data['ins_cd'] = $this->options['ins_cd'];
        $data['mchnt_cd'] = $this->options['mchnt_cd'];
        $data['term_id'] = $this->options['term_id'];
        $data['random_str'] = Sfunc::nonceStr();
        $data['mchnt_order_no'] = $this->options['noPrefix'] . $data['mchnt_order_no'] . rand(1000, 9999);
        $data['order_amt'] = $data['order_amt'] * 100;
        $data['goods_des'] = isset($data['goods_des']) ? $data['goods_des'] : '富友支付';
        $data['term_ip'] = Sfunc::getIP();
        $data['txn_begin_ts'] = date('YmdHis', time());
        $data['addn_inf'] = isset($data['addn_inf']) ? $data['addn_inf'] : '';
        $data['notify_url'] = isset($data['notify_url']) ? $data['notify_url'] : $this->options['notify_url'];
        $data['trade_type'] = isset($data['trade_type']) ? $data['trade_type'] : 'LETPAY';
        $data['goods_tag'] = isset($data['goods_tag']) ? $data['goods_tag'] : '';
        $data['limit_pay'] = isset($data['limit_pay']) ? $data['limit_pay'] : '';
        $data['curr_type'] = isset($data['curr_type']) ? $data['curr_type'] : 'CNY';
        $data['goods_detail'] = isset($data['goods_detail']) ? $data['goods_detail'] : '';
        $data['product_id'] = isset($data['product_id']) ? $data['product_id'] : '';
        $data['openid'] = '';
        $data['sub_appid'] = $this->options['appid'];
        $data['sub_openid'] = isset($data['openid']) ? $data['openid'] : false;
        return $this->execute($data);
    }

    /**
     * 查找订单
     * @param $mchntOrderNo
     * @param string $orderType
     *              订单类型:ALIPAY (统一下单、条码支付、服务窗 支付), WECHAT(统一下单、条码支付，公众号支 付,小程序),UNIONPAY,BESTPAY(翼支付)
     * @return array
     */
    public function findOrder($mchntOrderNo, $orderType = 'WECHAT')
    {
        $data = [
            "version" => "1.0",
            "ins_cd" => $this->options['ins_cd'],
            "mchnt_cd" => $this->options['mchnt_cd'],
            "term_id" => $this->options['term_id'],
            "random_str" => Sfunc::nonceStr(),
            "order_type" => $mchntOrderNo,
            "mchnt_order_no" => $orderType
        ];
        return $this->execute($data);
    }

    /**
     * 订单退款
     * @param array $data
     * @param string $orderType
     *              订单类型:ALIPAY (统一下单、条码支付、服务 窗支付), WECHAT(统一下单、条码支付，公众号支付),UNIONPAY,BESTPAY(翼支付)
     * @return array
     */
    public function refundOrder(array $data, $orderType = 'WECHAT')
    {
        $data = [
            "version" => "1.0",
            "ins_cd" => $this->options['ins_cd'],
            "mchnt_cd" => $this->options['mchnt_cd'],
            "term_id" => $this->options['term_id'],
            "random_str" => Sfunc::nonceStr(),
            "order_type" => $orderType,
            "mchnt_order_no" => $data["mchnt_order_no"],
            "refund_order_no" => $data["refund_order_no"],
            "total_amt" => $data["total_amt"] * 100,
            "refund_amt" => $data["refund_amt"] * 100,
            "operator_id" => '',
//            "reserved_fy_term_id" => ''
        ];
        return $this->execute($data);
    }

    /**
     * 请求回调
     * @param $data
     * @return array
     */
    protected function execute($data)
    {
        ksort($data, SORT_NATURAL | SORT_FLAG_CASE);
        $sign = http_build_query($data);
        $data['sign'] = $this->sign($sign, $this->options['cert_key']);
        $xmlStr = "<?xml version=\"1.0\" encoding=\"GBK\" standalone=\"yes\"?><xml>" . $this->toXml($data) . "</xml>";
        //需要两次urlencode()
        $xmlStr = "req=" . urlencode(urlencode($xmlStr));
        $responsXML = urldecode($this->curl_post_ssl($this->urls['wxPreCreate'], $xmlStr));

        $respons = simplexml_load_string($responsXML);
        return Sfunc::objectToArray($respons);
    }

    /**
     * 富友openssl 加密
     * @param $str
     * @param $key
     * @return string
     */
    protected function sign($str, $key)
    {
        $pem = file_get_contents($key);
        //密钥
        $pkeyId = openssl_pkey_get_private($pem);
        //私密md5
        openssl_sign($str, $sign, $pkeyId, OPENSSL_ALGO_MD5);
        //加密
        return base64_encode($sign);

        //反解 -1:error验证错误 1:correct验证成功 0:incorrect验证失败
//        $pubkey = openssl_pkey_get_public($pem);
//        $ok = openssl_verify($data,base64_decode($t),$pubkey,OPENSSL_ALGO_MD5);
    }

    /**
     * 转xml数据
     * @param array $data
     * @param bool $elsArr
     * @return bool|string
     */
    protected function toXml(array $data, $elsArr = false)
    {
        if (!$elsArr) $this->xml->openMemory();
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $this->xml->startElement($key);
                $this->toXml($value, TRUE);
                $this->xml->endElement();
                continue;
            }
            $this->xml->writeElement($key, $value);
        }
        if (!$elsArr) {
            $this->xml->endElement();
            return $this->xml->outputMemory(true);
        }
        return false;
    }

    /**
     * curl方式发送
     * @param $url
     * @param $data
     * @param int $second
     * @return mixed|string
     */
    private function curl_post_ssl($url, $data, $second = 30)
    {
        $ch = curl_init();
        //超时时间
        curl_setopt($ch, CURLOPT_TIMEOUT, $second);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, "$url");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSLVERSION, 1);
        $aHeader = [0];
        $aHeader[] = "Content-Type: application/x-www-form-urlencoded; charset=gb2312";
        curl_setopt($ch, CURLOPT_HTTPHEADER, $aHeader);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $data = curl_exec($ch);
        curl_close($ch);
        if ($data) {
            return $data;
        } else {
            $error = curl_errno($ch);
            return json_encode(["code" => $error, "msg" => "curl请求发送失败"]);
        }
    }
}