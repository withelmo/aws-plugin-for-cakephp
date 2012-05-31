<?php
/**
 * Plugin component for CakePHP: Send email with SES on AWS.
 * 
 * PHP versions => 5.2 , CakePHP => 1.3
 * 
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright   Copyright 2012, Shintaro Sugimoto
 * @package     aws-plugin-for-cakephp
 * @subpackage  aws-plugin-for-cakephp.controllers.components
 * @version     0.2.0
 * @since       AWS SDK for PHP 1.5.6(http://docs.amazonwebservices.com/AWSSDKforPHP/latest)
 * @license	 MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

/*
 * Load AWS SDK for PHP
 */
App::import('Vendor', 'Aws.Sdk', array('file' => 'sdk/sdk.class.php'));
App::import('Vendor', 'Aws.Ses', array('file' => 'sdk/services/ses.class.php'));

class SimpleEmailComponent extends Object {

    // SES Object
    private $ses = null;
    
    /**
     * $accessKey
     * アクセスキー
     * 
     * @var string AWS's access key
     * @access public
     */
    public $accessKey = '';

    /**
     * $secretKey
     * シークレットキー
     * 
     * @var string AWS's secret key
     * @access public
     */
    public $secretKey = '';
    
    /**
     * from/returnPathアドレスが認証済みかチェックする
     * 
     * @var boolean check verified addresses
     * @access private
     */
    private $verifiedCheck = false;
    
    // Srttings
    /* Encoding */
    public $charset_subject = 'ISO-2022-JP';
    public $charset_body = 'ISO-2022-JP';
    public $charset_name = 'ISO-2022-JP';
    public $charset_origin = 'UTF-8';
    private $iso_2022_jp = "ISO-2022-JP";
    private $iso_2022_jp_ms = "ISO-2022-JP-MS";
    
    public $view_dir = 'email';
    public $verifiedAddresses = array();
    // Mail data
    public $subject = '';
    public $body = '';
    public $from = '';
    public $to = '';
    public $cc = '';
    public $bcc = '';
    public $replyTo = '';
    public $returnPath = '';
    
    private $controller;

    /**
     * initialize
     * 
     * @access public
     * @param object $controller Controller instance for the request
     * @return void
     */
    public function initialize(&$controller, $settings = array()) {
        // SESインスタンスの生成
        $this->setInstance($settings);

        // 実行中のcontrollerを保持
        $this->controller = & $controller;
    }

    /**
     * startup
     * 
     * @access public
     * @param object $controller Controller instance for the request
     * @return void
     */
    public function startup(&$controller) {
        // 処理なし
    }

    /**
     * setInstance
     * キーペアをセットしてSESインスタンスを生成
     * 
     * @access public
     * @param array $settings アクセスキー/シークレットキーを含むパラメータ
     * @return boolean
     */
    public function setInstance($settings) {
        // プロパティの初期化
        $this->clearParams();
        
        // 設定
        $this->_set($settings);
        
        // キーぺがない場合は生成できないためfalse
        if (empty($this->accessKey) || empty($this->secretKey)) {
            return false;
        }
        
        // SES Objectの初期化
        unset($this->ses);
        
        // SESインスタンスの生成
        $this->ses = new AmazonSES(array('key' => $this->accessKey, 'secret' => $this->secretKey));

        // 認証アドレスチェックをする場合は認証済みアドレスを取得セットする
        if ($this->verifiedCheck) {
            $this->verifiedAddresses = $this->verifiedList();
        }
        return true;
    }

    /**
     * existInstance
     * 
     * @access private
     * @param 
     * @return boolean
     */
    private function existInstance() {
        return is_object($this->ses);
    }
    
    /**
     * clearParams
     * 
     * @access private
     * @param 
     * @return void
     */
    private function clearParams() {
        $this->accessKey = '';
        $this->secretKey = '';
        // Mail data
        $this->subject = '';
        $this->body = '';
        $this->from = '';
        $this->to = '';
        $this->cc = '';
        $this->bcc = '';
        $this->replyTo = '';
        $this->returnPath = '';
    }

    /**
     * verifyEmail
     * メールアドレスの認証メソッド
     *
     * @param array|string $mails 認証するメールアドレス
     * @return boolean
     */
    public function verifyEmail($mails) {
        if (!$this->existInstance() || empty($mails)) {
            return false;
        }

        if (is_array($mails)) {
            $results = array();
            foreach ($mails as $key => $mail) {
                $response = $this->ses->verify_email_identity($mail);
                $results[] = $response->isOK();
            }
        } else {
            $response = $this->ses->verify_email_identity($mails);
            $results = $response->isOK();
        }
        return $results;
    }

    /**
     * verifyDomain
     * メールアドレスドメインの認証メソッド
     *
     * @param array|string $domains 認証するメールアドレスのドメイン
     * @return boolean
     */
    public function verifyDomain($domains) {
        if (!$this->existInstance() || empty($domains)) {
            return false;
        }

        if (is_array($domains)) {
            $results = array();
            foreach ($domains as $key => $mail) {
                $response = $this->ses->verify_domain_identity($mail);
                $results[] = $response->isOK();
            }
        } else {
            $response = $this->ses->verify_domain_identity($domains);
            $results = $response->isOK();
        }
        return $results;
    }

    /**
     * deleteIdentity
     * メールアドレスの認証解除メソッド
     *
     * @param array|string $identities 解除するメールアドレスのドメイン
     * @return boolean
     */
    public function deleteIdentity($identities) {
        if (!$this->existInstance() || empty($identities)) {
            return false;
        }

        if (is_array($identities)) {
            $results = array();
            foreach ($identities as $key => $mail) {
                $response = $this->ses->delete_identity($mail);
                $results[] = $response->isOK();
            }
        } else {
            $response = $this->ses->delete_identity($identities);
            $results = $response->isOK();
        }
        return $results;
    }

    /**
     * unverifyEmail
     * メールアドレスの認証解除メソッド
     * 後方互換用
     *
     * @param array|string 認証解除するメールアドレス
     * @return boolean
     */
    public function unverifyEmail($mails) {
        $this->deleteIdentity($mails);
    }

    /**
     * verifiedList
     * 認証済のメールアドレス一覧の取得メソッド
     *
     * @return Array
     */
    public function listIdentities() {
        if (!$this->existInstance()) {
            return false;
        }
        
        $res = $this->ses->list_identities();
        $results = Set::reverse($res->body->ListVerifiedEmailAddressesResult->VerifiedEmailAddresses);

        /* キーペア不正などで取得できなかった場合の処理 */
        if (is_null($results)) {
            return false;
        }

        if (empty($results)) {
            // 承認メールがない場合は空は入れのため、そのまま返す
        } else if (!is_array($results['member'])) {
            // 1件の場合は配列にする
            $results['member'] = array($results['member']);
        }
        return $results;
    }

    /**
     * verifiedList
     * 認証済のメールアドレス一覧の取得メソッド
     *
     * @return Array
     */
    public function verifiedList() {
        $this->listIdentities();
    }

    /**
     * getidentityVerificationAttributes
     * 
     * @access public
     * @param 
     * @return 
     */
    public function getidentityVerificationAttributes($identities) {
        // @todo 未実装
    }
    
    /**
     * subject
     * メールサブジェクト設定メソッド
     * 
     * @param 
     * @return 
     */
    public function subject($subject) {
        if (empty($subject)) {
            return false;
        }

        $this->subject = $subject;

        if (empty($this->subject)) {
            return false;
        }
        return true;
    }

    /**
     * cakeText
     * メール本文とテンプレート設定メソッド
     * 
     * @param 
     * @return 
     */
    public function cakeText($content = array(), $element = 'default', $layout = 'default') {
        if (empty($content)) {
            return false;
        }
        $this->view = new view($this->controller, false);
        $this->view->layout = $this->view_dir . DS . 'text' . DS . $layout;
        $this->body = $this->view->renderLayout($this->view->element($this->view_dir . DS . 'text' . DS . $element, array('content' => $content)));

        if (empty($this->body)) {
            return false;
        }
        return true;
    }

    /**
     * addNameToAddress
     * メールアドレスに名前を追加するメソッド
     * 
     * @param 
     * @return 
     */
    public function _addNameToAddress($address = '', $name = '') {
        if (empty($address)) {
            return false;
        }
        if (empty($name)) {
            return $address;
        }

        mb_language('ja');
        $encode_origin = mb_internal_encoding();
        mb_internal_encoding($this->charset_origin);
        if ($this->charset_name == $this->iso_2022_jp) {
            $namedAddress = mb_encode_mimeheader($name, $this->iso_2022_jp_ms);
        } else {
            $namedAddress = mb_encode_mimeheader($name, $this->charset_name);
        }
        mb_internal_encoding($encode_origin);

        // "(ダブルコーテーション)と\(エスケープ)のエスケープ処理
        $namedAddress = str_replace(array('\\', '"'), array('\\\\', '\"'), $namedAddress);
        $namedAddress = "\"{$namedAddress}\"<{$address}>";

        return $namedAddress;
    }

    /**
     * from
     * 送信元アドレスの設定メソッド
     * 
     * @param 
     * @return 
     */
    public function from($address, $name = '') {
        if (empty($address)) {
            $this->log("addressが存在しないエラー");
            return false;
        }

        // 送信元が認証済アドレスにない場合はエラー
        if ($this->verifiedCheck && !in_array($address, $this->verifiedAddresses['member'])) {
            $errorData = Set::merge(array($address, $name, $this->verifiedAddresses));
            $this->log("送信元が認証済アドレスチェックでエラー" . print_r($errorData, true), LOG_ERROR);
            return false;
        }

        $this->from = $this->_addNameToAddress($address, $name);

        if (empty($this->from)) {
            $this->log("this->formが空でエラー");
            return false;
        }
        return true;
    }

    /**
     * to
     * 送信先アドレスの設定メソッド
     * 
     * @param array array($address => $name)
     * @return 
     */
    public function to($address, $name = '') {
        if (empty($address)) {
            return false;
        }
        $this->to = $this->_addNameToAddress($address, $name);

        if (empty($this->to)) {
            return false;
        }
        return true;
    }

    /**
     * cc
     * cc送信先アドレスの設定メソッド
     * 
     * @param array array($address => $name)
     * @return 
     */
    public function cc($address, $name = '') {
        if (empty($address)) {
            return false;
        }

        // 複数設定がある場合
        if (is_array($address)) {
            return $this->ccs($address);
        }

        $this->cc = $this->_addNameToAddress($address, $name);

        if (empty($this->cc)) {
            return false;
        }
        return true;
    }

    /**
     * bcc
     * bcc送信先アドレスの設定メソッド
     * 
     * @param array array($address => $name)
     * @return 
     */
    public function bcc($address, $name = '') {
        if (empty($address)) {
            return false;
        }

        // 複数設定がある場合
        if (is_array($address)) {
            return $this->bccs($address);
        }

        $this->bcc = $this->_addNameToAddress($address, $name);

        if (empty($this->bcc)) {
            return false;
        }
        return true;
    }

    /**
     * replyTo
     * 返信先アドレスの設定メソッド
     * 
     * @param 
     * @return 
     */
    public function replyTo($address) {
        if (empty($address)) {
            return false;
        }
        $this->replyTo = $address;

        if (empty($this->replyTo)) {
            return false;
        }
        return true;
    }

    /**
     * returnPath
     * バウンスメールアドレスの設定メソッド
     * 
     * @param 
     * @return 
     */
    public function returnPath($address) {
        if (empty($address)) {
            return false;
        }

        // 認証済アドレスにない場合はエラー
        if ($this->verifiedCheck && !in_array($address, $this->verifiedAddresses['member'])) {
            return false;
        }

        $this->returnPath = $address;

        if (empty($this->returnPath)) {
            return false;
        }
        return true;
    }

    /**
     * tos
     * 複数送信先アドレスの設定メソッド
     * 
     * @param 
     * @return 
     */
    public function tos($tos = array()) {
        if (empty($tos) || !is_array($tos)) {
            return false;
        }

        foreach ($tos as $address => $name) {
            $this->to[] = $this->_addNameToAddress($address, $name);
        }
        return $this->to;
    }

    /**
     * cc
     * 複数cc送信先アドレスの設定メソッド
     * 
     * @param 
     * @return 
     */
    public function ccs($ccs = array()) {
        if (empty($ccs) || !is_array($ccs)) {
            return false;
        }

        if (empty($ccs[0])) {
            foreach ($ccs as $name => $address) {
                $this->cc[] = $this->_addNameToAddress($address, $name);
            }
        } else {
            $this->cc = $ccs;
        }
        return $this->cc;
    }

    /**
     * bcc
     * 複数bcc送信先アドレスの設定メソッド
     * 
     * @param 
     * @return 
     */
    public function bccs($bccs = array()) {
        if (empty($bccs) || !is_array($bccs)) {
            return false;
        }

        if (empty($bccs[0])) {
            // 送信先名との配列の場合
            foreach ($bccs as $name => $address) {
                $this->bcc[] = $this->_addNameToAddress($address, $name);
            }
        } else {
            $this->bcc = $bccs;
        }

        return $this->bcc;
    }

    /**
     * sendMail
     * メール送信メソッド
     *
     * @param
     * @return
     */
    public function sendMail($isArray = false) {
        if (!$mailData = $this->_setMailData()) {
            return false;
        }

        $res = $this->ses->send_email($this->from, $mailData['destination'], $mailData['message'], $mailData['opt']);

        // 返り値を配列に変換
        if ($isArray) {
            $res = Set::reverse($res);
        }

        return $res;
    }

    /**
     * batchSendMail
     * メール送信メソッド
     *
     * @param
     * @return
     */
    public function batchSendMail() {
        if (!$mailData = $this->_setMailData()) {
            return false;
        }

        $res = $this->ses->batch()->send_email($this->from, $mailData['destination'], $mailData['message'], $mailData['opt']);

        return true;
    }

    public function _setMailData() {
        if (empty($this->subject) || empty($this->body) || empty($this->charset_subject) || empty($this->charset_body)) {
            return false;
        }

        // 送信先の設定
        $destination = array();
        if (!empty($this->to)) {
            $destination['ToAddresses'] = $this->to;
        }
        if (!empty($this->cc)) {
            $destination['CcAddresses'] = $this->cc;
        }
        if (!empty($this->bcc)) {
            $destination['BccAddresses'] = $this->bcc;
        }

        // 送信先が一つも設定されていない場合はエラー
        if (empty($destination)) {
            return false;
        }

        // オプション
        $opt = array();
        if ($this->replyTo) {
            $opt['ReplyToAddresses'] = $this->replyTo;
        }
        if (!empty($this->returnPath)) {
            $opt['ReturnPath'] = $this->returnPath;
        }

        // 送信メッセージ
        $message = array();

        mb_language('ja');
        $encode_origin = mb_internal_encoding();
        mb_internal_encoding($this->charset_origin);

        // Subject
        if ($this->charset_subject == $this->iso_2022_jp) {
            // ISO-2022-JP-MSでコンバート
            $subject = mb_convert_encoding($this->subject, $this->iso_2022_jp_ms, $this->charset_origin);
            // ISO-2022-JP-MSで変換すると、ゴミが入る場合があるため
            $subject = "=?" . $this->iso_2022_jp . "?B?" . base64_encode($subject) . "?=";
        } else {
            $subject = mb_convert_encoding($this->subject, $this->charset_subject, $this->charset_origin);
        }
        $message['Subject']['Data'] = $subject;
        $message['Subject']['Charset'] = $this->charset_subject;

        // Body
        if ($this->charset_body == $this->iso_2022_jp) {
            $body = mb_convert_encoding($this->body, $this->iso_2022_jp_ms, $this->charset_origin);
        } else {
            $body = mb_convert_encoding($this->body, $this->charset_body, $this->charset_origin);
        }
        $message['Body']['Text']['Data'] = $body;
        $message['Body']['Text']['Charset'] = $this->charset_body;

        mb_internal_encoding($encode_origin);

        return array('destination' => $destination, 'opt' => $opt, 'message' => $message);
    }

    /**
     * batchSend
     * バッチの実行メソッド
     * 
     * @param 
     * @return 
     */
    public function batchSend($clear = true, $isArray = true) {
        $results = $this->ses->batch()->send($clear);

        // 返り値を配列に変換
        if ($isArray) {
            foreach ($results as $key => $result) {
                $results[$key] = Set::reverse($result);
            }
        }

        return $results;
    }

    /**
     * getQuotaAll
     * 全ての送信制限数ステータスの取得メソッド
     *
     * @param
     * @return
     */
    public function getQuotaAll($isArray = true) {
        $res = $this->ses->get_send_quota();

        if ($isArray) {
            $res = Set::reverse($res->body);
        }
        return $res;
    }

    /**
     * getQuotaPerSecond
     * 秒間送信能力を取得するメソッド
     * 
     * @param 
     * @return 
     */
    public function getQuotaPerSecond() {
        if (!$this->ses) {
            return false;
        }

        $quota = $this->getQuotaAll();

        if (empty($quota['GetSendQuotaResult']['MaxSendRate'])) {
            return false;
        }
        return $quota['GetSendQuotaResult']['MaxSendRate'];
    }

    /**
     * getQuotaPerDay
     * 秒間送信能力を取得するメソッド
     * 
     * @param 
     * @return 
     */
    public function getQuotaPerDay() {
        if (!$this->ses) {
            return false;
        }
        $quota = $this->getQuotaAll();

        if (empty($quota['GetSendQuotaResult']['Max24HourSend'])) {
            return false;
        }
        return $quota['GetSendQuotaResult']['Max24HourSend'];
    }

    /**
     * getQuotaSentLastDay
     * 秒間送信能力を取得するメソッド
     * 
     * @param 
     * @return 
     */
    public function getQuotaSentLastDay() {
        if (!$this->ses) {
            return false;
        }
        $quota = $this->getQuotaAll();

        if (empty($quota['GetSendQuotaResult']['SentLast24Hours'])) {
            return false;
        }
        return $quota['GetSendQuotaResult']['SentLast24Hours'];
    }

    /**
     * getSendStatistics
     * 送信履歴統計情報の取得
     * 
     * @param 
     * @return 
     */
    public function getSendStatistics($isArray = true) {
        $res = $this->ses->get_send_statistics();

        if ($isArray) {
            $res = Set::reverse($res->body);
        }
        return $res;
    }

}
