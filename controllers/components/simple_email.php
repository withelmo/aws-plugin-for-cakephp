<?php

/**
 * Description of ses_component
 *
 * @author SHINTARO
 */
App::import('Vendor', 'Amazon.sdk', array('file' => 'sdk/sdk.class.php'));
App::import('Vendor', 'Amazon.sdk', array('file' => 'sdk/services/ses.class.php'));

class SimpleEmailComponent extends Object {

    // SES Object
    var $ses;
    // Verify check flg
    var $verify_check_flg = false;
    // Srttings
    /* Encoding */
    var $charset_subject = 'ISO-2022-JP';
    var $charset_body = 'ISO-2022-JP';
    var $charset_name = 'ISO-2022-JP';
    var $charset_origin = 'UTF-8';
    private $iso_2022_jp = "ISO-2022-JP";
    private $iso_2022_jp_ms = "ISO-2022-JP-MS";
    var $accessKey = '';
    var $secretKey = '';
    var $view_dir = 'email';
    var $verified_from = array();
    // Mail data
    var $subject = '';
    var $body = '';
    var $from = '';
    var $to = '';
    var $cc = '';
    var $bcc = '';
    var $replyTo = '';
    var $returnPath = '';

    public function __construct() {
        parent::__construct();
    }

    function initialize(&$controller, $setting = array()) {
        // セッティングにデータがあればセットする
        if (!empty($setting['access_key']) && !empty($setting['secret_key'])) {
            $this->accessKey = $setting['access_key'];
            $this->secretKey = $setting['secret_key'];
        }

        if (isset($setting['verify_check_flg'])) {
            $this->verify_check_flg = $setting['verify_check_flg'];
        }

        // キーペアがあればインスタンス生成
        if (!empty($this->accessKey) && !empty($this->secretKey)) {
            $this->ses = new AmazonSES(array('key' => $this->accessKey, 'secret' => $this->secretKey));
            if ($this->verify_check_flg) {
                $this->verified_from = $this->verifiedList();
            }
        }
        $this->Controller = & $controller;
    }

    function startup(&$controller) {
        
    }

    /**
     * setInstance
     * キーペアをセットしてSESインスタンスを生成
     * 
     * @param string $access_key アクセスキー
     * @param string $secret_key シークレットキー
     * @return 
     */
    function setInstance($access_key = null, $secret_key = null, $verifyCheck = false) {
        if (empty($access_key) || empty($secret_key)) {
            return false;
        }
        if ($this->ses) {
            unset($this->ses);
        }

        // プロパティの初期化
        $this->clearParams();
        
        $this->accessKey = $access_key;
        $this->secretKey = $secret_key;
        $this->ses = new AmazonSES(array('key' => $this->accessKey, 'secret' => $this->secretKey));

        if (isset($verifyCheck)) {
            $this->verify_check_flg = $verifyCheck;
        }

        if ($this->verify_check_flg) {
            $this->verified_from = $this->verifiedList();

            if ($this->verified_from === false) {
                return false;
            }
        }
        return true;
    }

    /**
     * clearParams
     * 
     * @access private
     * @param 
     * @return 
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
     * @param string 認証するメールアドレス
     * @return boolean
     */
    function verifyEmail($mail) {
        if (empty($mail)) {
            false;
        }

        $res = $this->ses->verify_email_address($mail);
        return $res->isOK();
    }

    /**
     * unverifyEmail
     * メールアドレスの認証解除メソッド
     *
     * @param string 認証解除するメールアドレス
     * @return boolean
     */
    function unverifyEmail($mail) {
        if (empty($mail)) {
            false;
        }

        $res = $this->ses->delete_verified_email_address($mail);
        return $res->isOK();
    }

    /**
     * verifiedList
     * 認証済のメールアドレス一覧の取得メソッド
     *
     * @return Array
     */
    function verifiedList() {
        $res = $this->ses->list_verified_email_addresses();
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
     * subject
     * メールサブジェクト設定メソッド
     * 
     * @param 
     * @return 
     */
    function subject($subject) {
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
    function cakeText($content = array(), $element = 'default', $layout = 'default') {
        if (empty($content)) {
            return false;
        }
        $this->view = new view($this->Controller, false);
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
    function _addNameToAddress($address = '', $name = '') {
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
    function from($address, $name = '') {
        if (empty($address)) {
            $this->log("addressが存在しないエラー");
            return false;
        }

        // 送信元が認証済アドレスにない場合はエラー
        if ($this->verify_check_flg && !in_array($address, $this->verified_from['member'])) {
            $errorData = Set::merge(array($address, $name, $this->verified_from));
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
    function to($address, $name = '') {
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
    function cc($address, $name = '') {
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
    function bcc($address, $name = '') {
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
    function replyTo($address) {
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
    function returnPath($address) {
        if (empty($address)) {
            return false;
        }

        // 認証済アドレスにない場合はエラー
        if ($this->verify_check_flg && !in_array($address, $this->verified_from['member'])) {
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
    function tos($tos = array()) {
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
    function ccs($ccs = array()) {
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
    function bccs($bccs = array()) {
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
    function sendMail($isArray = false) {
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
    function batchSendMail() {
        if (!$mailData = $this->_setMailData()) {
            return false;
        }

        $res = $this->ses->batch()->send_email($this->from, $mailData['destination'], $mailData['message'], $mailData['opt']);

        return true;
    }

    function _setMailData() {
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
    function batchSend($clear = true, $isArray = true) {
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
    function getQuotaAll($isArray = true) {
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
    function getQuotaPerSecond() {
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
    function getQuotaPerDay() {
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
    function getQuotaSentLastDay() {
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
    function getSendStatistics($isArray = true) {
        $res = $this->ses->get_send_statistics();

        if ($isArray) {
            $res = Set::reverse($res->body);
        }
        return $res;
    }

}

?>
