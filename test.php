<?php
/**
 * Created by PhpStorm.
 * User: lyx
 * Date: 19-1-4
 * Time: 下午2:26
 */

require 'SimpleMailer.php';
use SimpleMailer\SimpleMailer;

go(function() {
    $mailFrom = 'xxx@163.com';
    $username = 'xxx@163.com';
    $password = 'xxx';
    $mailTo = 'xxx@qq.com';

    $mail = new SimpleMailer(['server' => 'smtp.163.com', 'mailFrom' => $mailFrom, 'username' => $username, 'password' => $password]);
    var_dump($mail->mail(['html' => true, 'body' => '<h1>test</h1>', 'subject' => 'test mail', 'mailTo' => $mailTo]));
});