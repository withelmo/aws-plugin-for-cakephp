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

    /**
     * SES Object
     *
     * @var object SES Object
     */
    private $__Ses = null;
    
    /**
     * accessKey:アクセスキー
     * 
     * @var string AWS's access key
     * @access public
     */
    public $accessKey = '';

    /**
     * secretKey:シークレットキー
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
    private $__verifiedCheck = false;
    
    /**
     * verifiedAddresses
     * 
     * @var array verified addresses
     */
    public $verifiedAddresses = array();
    
    /**
     * Encodings
     */
    const UTF_8 = 'UTF-8';
    const ISO_2022_JP = "ISO-2022-JP";
    const ISO_2022_JP_MS = "ISO-2022-JP-MS";
    
    public $charsetSubject = self::ISO_2022_JP;
    public $charsetBody = self::ISO_2022_JP;
    public $charsetName = self::ISO_2022_JP;
    public $charsetOrigin = self::UTF_8;
    
    /**
     * Mail data
     */
    public $subject = '';
    public $body = '';
    public $from = '';
    public $to = '';
    public $cc = '';
    public $bcc = '';
    public $replyTo = '';
    public $returnPath = '';
    
    /**
     * layout/elementファイルのディレクトリ名
     * 
     * @var type 
     */
    public $viewDir = 'email';
    
    /**
     * 実行中のController
     * 
     * @var object
     */
    private $__Controller;

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
        $this->__Controller = & $controller;
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
        $this->__clearParams();
        
        // 設定
        $this->_set($settings);
        
        // キーぺがない場合は生成できないためfalse
        if (empty($this->accessKey) || empty($this->secretKey)) {
            return false;
        }
        
        // SES Objectの初期化
        unset($this->__Ses);
        
        // SESインスタンスの生成
        $this->__Ses = new AmazonSES(array('key' => $this->accessKey, 'secret' => $this->secretKey));

        // 認証アドレスチェックをする場合は認証済みアドレスを取得セットする
        if ($this->__verifiedCheck) {
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
    private function __existInstance() {
        return is_object($this->__Ses);
    }
    
    /**
     * clearParams
     * 
     * @access private
     * @param 
     * @return void
     */
    private function __clearParams() {
        // @todo リファクタリング
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
     * @access public
     * @param array|string $mails 認証するメールアドレス
     * @return boolean
     */
    public function verifyEmail($mails) {
        if (!$this->__existInstance() || empty($mails)) {
            return false;
        }

        if (is_array($mails)) {
            $results = array();
            foreach ($mails as $key => $mail) {
                $response = $this->__Ses->verify_email_identity($mail);
                $results[] = $response->isOK();
            }
        } else {
            $response = $this->__Ses->verify_email_identity($mails);
            $results = $response->isOK();
        }
        return $results;
    }

    /**
     * verifyDomain
     * メールアドレスドメインの認証メソッド
     *
     * @access public
     * @param array|string $domains 認証するメールアドレスのドメイン
     * @return boolean
     */
    public function verifyDomain($domains) {
        if (!$this->__existInstance() || empty($domains)) {
            return false;
        }

        if (is_array($domains)) {
            $results = array();
            foreach ($domains as $key => $mail) {
                $response = $this->__Ses->verify_domain_identity($mail);
                $results[] = $response->isOK();
            }
        } else {
            $response = $this->__Ses->verify_domain_identity($domains);
            $results = $response->isOK();
        }
        return $results;
    }

    /**
     * deleteIdentity
     * メールアドレスの認証解除メソッド
     *
     * @access public
     * @param array|string $identities 解除するメールアドレスのドメイン
     * @return boolean
     */
    public function deleteIdentity($identities) {
        if (!$this->__existInstance() || empty($identities)) {
            return false;
        }

        if (is_array($identities)) {
            $results = array();
            foreach ($identities as $key => $mail) {
                $response = $this->__Ses->delete_identity($mail);
                $results[] = $response->isOK();
            }
        } else {
            $response = $this->__Ses->delete_identity($identities);
            $results = $response->isOK();
        }
        return $results;
    }

    /**
     * unverifyEmail
     * メールアドレスの認証解除メソッド
     * 後方互換用
     *
     * @access public
     * @param array|string 認証解除するメールアドレス
     * @return boolean
     */
    public function unverifyEmail($mails) {
        $this->deleteIdentity($mails);
    }

    /**
     * verifiedList
     * 認証済のメールアドレス/ドメイン一覧の取得メソッド
     *
     * @access public
     * @param
     * @return array
     */
    public function listIdentities() {
        if (!$this->__existInstance()) {
            return false;
        }
        
        $res = $this->__Ses->list_identities();
        $results = Set::reverse($res->body->ListIdentitiesResult->Identities);
        
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
    * listEmailIdentities
    * 認証済みのメールアドレスのみを取得（ドメインは除く）
    * 
    * @access public
    * @param 
    * @return array
    */
    public function listEmailIdentities() {
        $results = $this->listIdentities();

        $mails = array();
        if (!empty($results['member'])) {
            foreach ($results['member'] as $identity) {
                if (strstr($identity, '@')) {
                    $mails['member'][] =  $identity;
                }
            }
        } else {
            $mails = $results;
        }

        return $mails;
    }

    /**
    * listDomainIdentities
    * 認証済みのドメインのみを取得（メールアドレスは除く）
    * 
    * @access public
    * @param 
    * @return 
    */
    public function listDomainIdentities() {
        $results = $this->listIdentities();

        $domains = array();
        if (!empty($results['member'])) {
            foreach ($results['member'] as $identity) {
                if (!strstr($identity, '@')) {
                    $domains['member'][] =  $identity;
                }
            }
        } else {
            $domains = $results;
        }

        return $domains;
    }

    /**
     * verifiedList
     * 認証済のメールアドレス一覧の取得メソッド
     *
     * @return array
     */
    public function verifiedList() {
        $this->listEmailIdentities();
    }
    

    /**
     * getidentityVerificationAttributes
     * 
     * @access public
     * @param 
     * @return 
     */
    public function getIdentityVerificationAttributes($identities) {
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
        $this->view = new view($this->__Controller, false);
        $this->view->layout = $this->viewDir . DS . 'text' . DS . $layout;
        $this->body = $this->view->renderLayout($this->view->element($this->viewDir . DS . 'text' . DS . $element, array('content' => $content)));

        if (empty($this->body)) {
            return false;
        }
        return true;
    }

    /**
     * addNameToAddress
     * メールアドレスに名前を追加するメソッド
     * 
     * @access private
     * @param string $address
     * @param string $name
     * @return string
     */
    private function __addNameToAddress($address = '', $name = '') {
        if (empty($address)) {
            return false;
        }
        if (empty($name)) {
            return $address;
        }

        mb_language('ja');
        $encode_origin = mb_internal_encoding();
        mb_internal_encoding($this->charsetOrigin);
        if ($this->charsetName == self::ISO_2022_JP) {
            $namedAddress = mb_encode_mimeheader($name, self::ISO_2022_JP_MS);
        } else {
            $namedAddress = mb_encode_mimeheader($name, $this->charsetName);
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
     * キーペアに対してアドレスを認証させておく必要がある
     * 
     * @access public
     * @param string $address
     * @param string $name
     * @return boolean
     */
    public function from($address, $name = '') {
        if (empty($address)) {
            $this->log("addressが存在しないエラー");
            return false;
        }

        // 送信元が認証済アドレスにない場合はエラー
        if ($this->__verifiedCheck && !in_array($address, $this->verifiedAddresses['member'])) {
            $errorData = Set::merge(array($address, $name, $this->verifiedAddresses));
            $this->log("送信元が認証済アドレスチェックでエラー" . print_r($errorData, true), LOG_ERROR);
            return false;
        }

        $this->from = $this->__addNameToAddress($address, $name);

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
     * @access public
     * @param string|array $address $address or array($address => $name)
     * @param string $name
     * @return boolean
     */
    public function to($address, $name = '') {
        if (empty($address)) {
            return false;
        }
        $this->to = $this->__addNameToAddress($address, $name);

        if (empty($this->to)) {
            return false;
        }
        return true;
    }

    /**
     * cc
     * cc送信先アドレスの設定メソッド
     * 
     * @access public
     * @param string|array $address $address or array($address => $name)
     * @param string $name
     * @return boolean
     */
    public function cc($address, $name = '') {
        if (empty($address)) {
            return false;
        }

        // 複数設定がある場合
        if (is_array($address)) {
            return $this->ccs($address);
        }

        $this->cc = $this->__addNameToAddress($address, $name);

        if (empty($this->cc)) {
            return false;
        }
        return true;
    }

    /**
     * bcc
     * bcc送信先アドレスの設定メソッド
     * 
     * @access public
     * @param string|array $address $address or array($address => $name)
     * @param string $name
     * @return boolean
     */
    public function bcc($address, $name = '') {
        if (empty($address)) {
            return false;
        }

        // 複数設定がある場合
        if (is_array($address)) {
            return $this->bccs($address);
        }

        $this->bcc = $this->__addNameToAddress($address, $name);

        if (empty($this->bcc)) {
            return false;
        }
        return true;
    }

    /**
     * replyTo
     * 返信先アドレスの設定メソッド
     * 
     * @access public
     * @param string $address
     * @return boolean
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
     * キーペアに対してアドレスを認証させておく必要がある
     * 
     * @access public
     * @param string $address
     * @return boolean
     */
    public function returnPath($address) {
        if (empty($address)) {
            return false;
        }

        // 認証済アドレスにない場合はエラー
        if ($this->__verifiedCheck && !in_array($address, $this->verifiedAddresses['member'])) {
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
     * @access public
     * @param array $tos array('address1' => 'name1', 'address3' => 'name2', ...)
     * @return array
     */
    public function tos($tos = array()) {
        if (empty($tos) || !is_array($tos)) {
            return false;
        }

        foreach ($tos as $address => $name) {
            $this->to[] = $this->__addNameToAddress($address, $name);
        }
        return $this->to;
    }

    /**
     * cc
     * 複数cc送信先アドレスの設定メソッド
     * 
     * @access public
     * @param array $tos array('address1' => 'name1', 'address3' => 'name2', ...)
     * @return array
     */
    public function ccs($ccs = array()) {
        if (empty($ccs) || !is_array($ccs)) {
            return false;
        }

        if (empty($ccs[0])) {
            foreach ($ccs as $name => $address) {
                $this->cc[] = $this->__addNameToAddress($address, $name);
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
     * @access public
     * @param array $tos array('address1' => 'name1', 'address3' => 'name2', ...)
     * @return array
     */
    public function bccs($bccs = array()) {
        if (empty($bccs) || !is_array($bccs)) {
            return false;
        }

        if (empty($bccs[0])) {
            // 送信先名との配列の場合
            foreach ($bccs as $name => $address) {
                $this->bcc[] = $this->__addNameToAddress($address, $name);
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
        if (!$mailData = $this->__setMailData()) {
            return false;
        }

        $res = $this->__Ses->send_email($this->from, $mailData['destination'], $mailData['message'], $mailData['opt']);

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
        if (!$mailData = $this->__setMailData()) {
            return false;
        }

        $res = $this->__Ses->batch()->send_email($this->from, $mailData['destination'], $mailData['message'], $mailData['opt']);

        return true;
    }

    /**
     * _setMailData
     * 
     * @access private
     * @param
     * @return boolean 
     */
    private function __setMailData() {
        if (empty($this->subject) || empty($this->body) || empty($this->charsetSubject) || empty($this->charsetBody)) {
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
        mb_internal_encoding($this->charsetOrigin);

        // Subject
        if ($this->charsetSubject == self::ISO_2022_JP) {
            // ISO-2022-JP-MSでコンバート
            $subject = mb_convert_encoding($this->subject, self::ISO_2022_JP_MS, $this->charsetOrigin);
            // ISO-2022-JP-MSで変換すると、ゴミが入る場合があるため
            $subject = "=?" . self::ISO_2022_JP . "?B?" . base64_encode($subject) . "?=";
        } else {
            $subject = mb_convert_encoding($this->subject, $this->charsetSubject, $this->charsetOrigin);
        }
        $message['Subject']['Data'] = $subject;
        $message['Subject']['Charset'] = $this->charsetSubject;

        // Body
        if ($this->charsetBody == self::ISO_2022_JP) {
            $body = mb_convert_encoding($this->body, self::ISO_2022_JP_MS, $this->charsetOrigin);
        } else {
            $body = mb_convert_encoding($this->body, $this->charsetBody, $this->charsetOrigin);
        }
        $message['Body']['Text']['Data'] = $body;
        $message['Body']['Text']['Charset'] = $this->charsetBody;

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
        $results = $this->__Ses->batch()->send($clear);

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
     * @access public
     * @param boolean $isArray
     * @return array|object
     */
    public function getQuotaAll($isArray = true) {
        if (!$this->__existInstance()) {
            return false;
        }
        
        $res = $this->__Ses->get_send_quota();

        if ($isArray) {
            $res = Set::reverse($res->body->GetSendQuotaResult);
        }
        return $res;
    }

    /**
     * getQuotaPerSecond
     * 秒間送信能力を取得するメソッド
     * 
     * @access public
     * @param 
     * @return 
     */
    public function getQuotaPerSecond() {
        if (!$this->__existInstance()) {
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
     * @access public
     * @param 
     * @return 
     */
    public function getQuotaPerDay() {
        if (!$this->__existInstance()) {
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
     * @access public
     * @param 
     * @return 
     */
    public function getQuotaSentLastDay() {
        if (!$this->__existInstance()) {
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
        $res = $this->__Ses->get_send_statistics();

        if ($isArray) {
            $res = Set::reverse($res->body);
        }
        return $res;
    }

}
