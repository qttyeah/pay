<?php
/**
 * Created by PhpStorm.
 * User: 15213
 * Date: 2020/8/6
 * Time: 10:45
 */

namespace Qttyeah\Pay;

/**
 * 暂时没有业务有待升级
 * Class Alipay
 * @package Qttyeah\Pay
 */
class Alipay
{
    /**
     * @var array
     */
    protected $options = [
        'appid'=>'',
        'format'=>'json',
        'charset'=>'utf-8',
        'sign_type'=>'RSA2',
        'version'=>'1.0',
        'pri_pem'=>'',
        'pub_pem'=>'',
    ];

}