<?php

/**
 * cfFormMailer
 *
 * @author  Clefarray Factory
 * @link  https://www.clefarray-web.net/
 *
 * Documentation: http://www.clefarray-web.net/blog/manual/cfFormMailer_manual.html
 * LICENSE: GNU General Public License (GPL) (http://www.gnu.org/copyleft/gpl.html)
 */

// トレイトの読み込み
require_once __DIR__ . '/traits/ValidationMethodsTrait.php';
require_once __DIR__ . '/traits/FilterMethodsTrait.php';
require_once __DIR__ . '/traits/TemplateMethodsTrait.php';
require_once __DIR__ . '/traits/MailMethodsTrait.php';
require_once __DIR__ . '/traits/FormRenderingTrait.php';
require_once __DIR__ . '/traits/UtilityMethodsTrait.php';
require_once __DIR__ . '/traits/FileUploadTrait.php';

class Class_cfFormMailer
{
    use ValidationMethodsTrait;
    use FilterMethodsTrait;
    use TemplateMethodsTrait;
    use MailMethodsTrait;
    use FormRenderingTrait;
    use UtilityMethodsTrait;
    use FileUploadTrait;

    public $cfg = array();

    /**
     * postされたデータ
     * @private array
     */
    private $form;

    /**
     * 検証エラーのメッセージ
     * @private array
     */
    private $formError;

    /**
     * システムエラーメッセージ
     * @private string
     */
    private $error_message;

    /**
     * フォームの valid 要素
     * @private array
     */
    private $parsedForm;

    /**
     * バージョン番号
     * @public string
     */
    public $version = '1.7.0';

    private $sysError;

    /**
     * コンストラクタ
     *
     */
    public function __construct(&$modx)
    {

        // 変数初期設定
        $this->form = $this->getFormVariables($_POST);
        $this->error_message = '';
        $this->formError = array();

        // uploadクラス読み込み
        if (is_file(__DIR__ . '/class.upload.php')) {
            include_once __DIR__ . '/class.upload.php';
        }
    }

    public function renderForm()
    {
        $text = $this->loadTemplate($this->config('tmpl_input'));

        if ($text === false) {
            return false;
        }

        if ($this->config('vericode')) {
            $text = $this->replacePlaceHolder($text, array('verimageurl' => $this->getCaptchaUri()));
        }
        $text = preg_replace(
            "@\ssendto=([\"']).+?\\1@i",
            '',
            preg_replace(
                "/\svalid=([\"']).+?\\1/i",
                '',
                $text
            )
        );

        $autosave = sessionv('_cf_autosave');
        if ($autosave) {
            $text = $this->restoreForm($text, $autosave);
        }

        // 余った<iferror>タグ、プレースホルダを削除
        $text = $this->clearPlaceHolder(
            preg_replace("@<iferror.*?>.+?</iferror>@uism", '', $text)
        );

        // 次の処理名をフォームに付記
        return preg_replace(
            '/(<form.*?>)/i',
            '\\1<input type="hidden" name="_mode" value="conf" />',
            $text
        );
    }

    public function renderFormWithError()
    {
        $text = $this->loadTemplate($this->config('tmpl_input'));

        if ($text === false) {
            return false;
        }

        // ポストされた内容を一時的に退避（事故対策）
        if ($this->config('autosave')) {
            $_SESSION['_cf_autosave'] = $this->form;
        }

        // CAPTCHA  # Added in v0.0.5
        if ($this->config('vericode')) {
            $text = $this->replacePlaceHolder($text, array('verimageurl' => $this->getCaptchaUri()));
        }
        $text = preg_replace(
            "/\ssendto=([\"']).+?\\1/i",
            '',
            preg_replace(
                "/\svalid=([\"']).+?\\1/i",
                '',
                $text
            )
        );

        // エラーメッセージを付記
        $text = $this->restoreForm(
            $this->replacePlaceHolder(
                $this->assignErrorClass(
                    $this->assignErrorTag(
                        $text,
                        $this->getFormError()
                    ),
                    $this->getFormError()
                ),
                $this->getFormError()
            ),
            $this->form
        );
        // 「戻り」の場合は入力値のみ復元

        // 余った<iferror>タグ、プレースホルダを削除
        $text = $this->clearPlaceHolder(
            preg_replace("@<iferror.*?>.+?</iferror>@uism", '', $text)
        );

        return preg_replace(
            '/(<form.*?>)/i',
            '\\1<input type="hidden" name="_mode" value="conf" />',
            $text
        );
    }

    public function renderFormOnBack()
    {
        // ページテンプレート読み込み
        $text = $this->loadTemplate($this->config('tmpl_input'));

        if ($text === false) {
            return false;
        }

        if ($this->config('vericode')) {
            $text = $this->replacePlaceHolder($text, array('verimageurl' => $this->getCaptchaUri()));
        }
        $text = preg_replace(
            "/\ssendto=([\"']).+?\\1/i",
            '',
            preg_replace(
                "/\svalid=([\"']).+?\\1/i",
                '',
                $text
            )
        );

        $text = $this->restoreForm($text, $this->form);
        // アップロード済みのファイルを削除
        $uploaded = sessionv('_cf_uploaded');
        if (is_array($uploaded) && count($uploaded)) {
            foreach ($uploaded as $filedata) {
                @unlink($filedata['path']);
            }
            unset($_SESSION['_cf_uploaded']);
        }

        // 余った<iferror>タグ、プレースホルダを削除
        $text = $this->clearPlaceHolder(
            preg_replace("@<iferror.*?>.+?</iferror>@uism", '', $text)
        );

        return preg_replace(
            '/(<form.*?>)/i',
            '\\1<input type="hidden" name="_mode" value="conf" />',
            $text
        );
    }

    public function renderConfirm()
    {
        $text = $this->loadTemplate($this->config('tmpl_conf'));
        if ($text === false) {
            return false;
        }

        // ポストされた内容を一時的に退避（事故対策）
        if ($this->config('autosave')) {
            $_SESSION['_cf_autosave'] = $this->form;
        }

        $values = $this->encodeHTML($this->form, true);
        $values = $this->convertNullToStr($values, '&nbsp;');
        if ($this->config('auto_reply')) {
            $values['reply_to'] = $this->getAutoReplyAddress();
        }
        // アップロードファイル関連
        if (is_array($_FILES) && count($_FILES)) {
            unset($_SESSION['_cf_uploaded']);
            foreach ($_FILES as $field => $var) {
                if (!empty($var['error']) && $var['error'] !== $this->config('upload_err_ok')) {
                    continue;
                }
                $confirm_tmp_name = (
                    $this->config('upload_tmp_path')
                    ? $this->cfm_upload_tmp_name($var['tmp_name'])
                    : $var['tmp_name']
                ) . $this->extension($var['tmp_name']);
                evo()->move_uploaded_file($var['tmp_name'], $confirm_tmp_name);
                $mime = $this->_getMimeType($confirm_tmp_name, $field);
                $_SESSION['_cf_uploaded'][$field] = array(
                    'path' => $confirm_tmp_name,
                    'mime' => $mime
                );
                // プレースホルダ定義
                $name =  evo()->htmlspecialchars($var['name'], ENT_QUOTES);
                $type = strtoupper($this->_getType($mime));
                if (strpos($mime, 'image/') === 0) {
                    $values[sprintf('%s.imagename', $field)]   = $name;
                    $values[sprintf('%s.imagetype', $field)]   = $type;
                } else {
                    $values[sprintf('%s.filename', $field)] = $name;
                    $values[sprintf('%s.filetype', $field)] = $type;
                }
            }
        }
        $text = $this->addHiddenTags(
            $this->replacePlaceHolder(
                $text,
                $values
            ),
            $this->form
        );

        // ワンタイムトークンを生成
        $token = $this->getToken();
        $_SESSION['_cffm_token'] = $token;
        $text = str_ireplace(
            '</form>',
            sprintf(
                '<input type="hidden" name="_cffm_token" value="%s" /></form>',
                $token
            ),
            $text
        );

        // 余った<iferror>タグ、プレースホルダを削除
        $text = $this->clearPlaceHolder(
            preg_replace("@<iferror.*?>.+?</iferror>@uism", '', $text)
        );

        return preg_replace(
            '/(<form.*?>)/i',
            '\\1<input type="hidden" name="_mode" value="send" />',
            $text
        );
    }

    public function renderComplete()
    {

        if ($this->config('complete_redirect')) {
            if (sessionv('_cf_autosave')) {
                unset($_SESSION['_cf_autosave']);
            }
            if (!preg_match('/^[1-9][0-9]*$/', $this->config('complete_redirect'))) {
                evo()->sendRedirect($this->config('complete_redirect'));
                exit;
            }
            evo()->sendRedirect(evo()->makeUrl($this->config('complete_redirect')));
            exit;
        }

        $text = $this->loadTemplate($this->config('tmpl_comp'));

        if ($text === false) {
            return false;
        }

        $text = $this->replacePlaceHolder($text, $this->encodeHTML($this->form));

        // 余った<iferror>タグ、プレースホルダを削除
        $text = $this->clearPlaceHolder(
            preg_replace("@<iferror.*?>.+?</iferror>@uism", '', $text)
        );

        return $text;
    }

    private function getToken()
    {
        return base_convert(str_shuffle(mt_rand()), 10, 36);
    }

    /**
     * 入力値を検証
     *
     * @access public
     * @param  void
     * @return boolean result
     */
    public function validate()
    {
        $this->formError = array();

        // 検証メソッドを取得
        $tmp_html = $this->loadTemplate($this->config('tmpl_input'));
        if (!$tmp_html) {
            $this->raiseError($this->convertText('入力画面テンプレートの読み込みに失敗しました'));
        }
        $this->parsedForm = $this->parseForm($tmp_html);
        if (!$this->parsedForm) {
            $this->raiseError($this->getError());
            return false;
        }

        $_SESSION['dynamic_send_to'] = $this->dynamic_send_to_field($tmp_html);

        foreach ($this->parsedForm as $field => $method) {
            // 初期変換
            if ($method['type'] !== 'textarea') {
                // <textarea>タグ以外は改行を削除
                if (is_array($this->form[$field] ?? null)) {
                    foreach ($this->form[$field] as $k => $v) {
                        $this->form[$field][$k] = strtr($v, array("\r" => '', "\n" => ''));
                    }
                } elseif (isset($this->form[$field])) {
                    $this->form[$field] = strtr($this->form[$field], array("\r" => '', "\n" => ''));
                }
            }

            // 入力必須項目のチェック
            if ($method['required']) {
                if ($method['type'] === 'file') {
                    $uploaded_file = sessionv("_cf_uploaded.{$field}");
                    $file_tmp_name = $_FILES[$field]['tmp_name'] ?? '';
                    if (
                        (!is_array($uploaded_file) || !is_file($uploaded_file['path']))
                        && (!postv('return') && empty($file_tmp_name))
                    ) {
                        $this->setFormError($field, $this->adaptEncoding($method['label']), '選択必須項目です');
                    }
                } elseif ((is_array($this->form[$field] ?? null) && !count($this->form[$field])) || ($this->form[$field] ?? '') == '') {
                    $this->setFormError(
                        $field,
                        $this->adaptEncoding($method['label']),
                        (in_array($method['type'], array('radio', 'select')) ? '選択' : '入力') . '必須項目です'
                    );
                }
            }

            // 入力値の検証
            $field_value = $this->form[$field] ?? '';
            $file_tmp_name = $_FILES[$field]['tmp_name'] ?? '';
            if ($field_value || $file_tmp_name || $field_value === '0') {
                $methods = explode(',', $method['method']);
                foreach ($methods as $indiv_m) {
                    $method_name = array();
                    if (!preg_match("/^([^(]+)(\(([^)]*)\))?$/", $indiv_m, $method_name)) {
                        continue;
                    }
                    $method_func = $method_name[1] ?? '';
                    $method_param = $method_name[3] ?? '';

                    if (!$method_func) {
                        continue;
                    }

                    // 標準メソッドを処理
                    $funcName = '_def_' . $method_func;
                    if (is_callable(array($this, $funcName))) {
                        $result = $this->$funcName($field_value, $method_param, $field);
                        if ($result !== true) {
                            $this->setFormError($field, $this->adaptEncoding($method['label']), $result);
                        }
                    }
                    // ユーザー追加メソッドを処理
                    $funcName = '_validate_' . $method_func;
                    if (is_callable($funcName)) {
                        $result = $funcName($field_value, $method_param);
                        if ($result !== true) {
                            $this->setFormError($field, $this->adaptEncoding($method['label']), $this->adaptEncoding($result));
                        }
                    }
                }
            }
        }

        // 自動返信先メールアドレスをチェック
        if ($this->config('auto_reply')) {
            if (!$this->_isValidEmail($this->getAutoReplyAddress())) {
                $this->setFormError('reply_to', 'メールアドレス', '形式が正しくありません');
            }
        }

        return $this->formError ? false : true;
    }

    /**
     * メール送信
     *
     * @access public
     * @param  void
     * @return boolean 結果
     */
    public function sendMail()
    {
        if (!$this->config('send_mail')) {
            return false;
        }

        if (!$this->form) {
            $this->setError('フォームが取得できません');
            return false;
        }

        $rs = $this->sendAdminMail();
        if (!$rs) {
            return false;
        }

        if (sessionv('_cf_autosave')) {
            unset($_SESSION['_cf_autosave']);
        }

        $rs = $this->sendAutoReply();
        return $rs;
    }

    /**
     * トークンをチェック
     *
     * @access public
     * @param  void
     * @return boolean 結果
     */
    public function isValidToken($token)
    {
        $session_token = sessionv('_cffm_token');
        if (empty($session_token)) {
            return false;
        }
        $isValid = $session_token === $token;
        unset($_SESSION['_cffm_token']);
        return $isValid;
    }

    /**
     * すでに送信済みかどうかをチェック
     *
     * @access public
     * @param  void
     * @return boolean 結果
     */
    public function alreadySent()
    {
        return ($this->form === sessionv('_cffm_recently_send'));
    }

    /**
     * 投稿されたデータの初期処理
     *
     * @access private
     * @param  array $array データ
     * @return array 初期処理の終わったデータ
     */
    private function getFormVariables($array)
    {

        if (!is_array($array)) {
            return array();
        }

        $ret = array();
        foreach ($array as $k => $v) {
            if (!is_int($k) && in_array($k, array('_mode', '_cffm_token'))) {
                continue;
            }
            if (is_array($v)) {
                $ret[$k] = $this->getFormVariables($v);
                continue;
            }
            $ret[$k] = preg_replace(
                "/\n+$/m",
                "\n",
                strtr(
                    str_replace(
                        "\0",
                        '',
                        $v
                    ),
                    array("\r\n" => "\n", "\r" => "\n")
                )
            );
        }
        return $ret;
    }

    /**
     * <form></form>タグ内を解析し、検証メソッド等を取得する
     *
     * @access private
     * @param  string $html 解析対象のHTML文書
     * @return array|boolean 解析失敗の場合は false
     */
    private function parseForm($html)
    {
        // print_r($html);
        $html = $this->extractForm($html, '');
        if ($html === false) {
            return false;
        }

        preg_match_all(
            "/<(input|textarea|select).*?name=([\"'])(.+?)\\2.*?>/is",
            $html,
            $match,
            PREG_SET_ORDER
        );

        $methods = array();
        foreach ($match as $v) {
            // 検証メソッドを取得
            if (preg_match("/valid=([\"'])(.+?)\\1/", $v[0], $v_match)) {
                $parts = explode(':', $v_match[2]);
                $required = $parts[0] ?? '';
                $method = $parts[1] ?? '';
                $param = $parts[2] ?? '';
            } else {
                $required = $method = $param = '';
            }

            // 項目名の取得
            if ($param) {
                $label = $param;
            } elseif (preg_match("/id=([\"'])(.+?)\\1/", $v[0], $l_match)) {
                if (preg_match(
                    "@<label for=([\"']){$l_match[2]}\\1.*>(.+?)</label>@",
                    $html,
                    $match_label
                )) {
                    $label = $match_label[2];
                } else {
                    $label = '';
                }
            } else {
                $label = '';
            }

            $fieldName = str_replace('[]', '', $v[3]); // 項目名を取得
            if (!isset($methods[$fieldName])) {
                $methods[$fieldName] = array(
                    'type'     => $this->_get_input_type($v),
                    'required' => $required,
                    'method'   => $method,
                    'param'    => $param,
                    'label'    => $label
                );
            }
        }
        return $methods;
    }

    private function dynamic_send_to_field($html)
    {
        $methods = $this->parsedForm;
        // 送信先動的変更のためのデータ取得(from v1.3)
        if (!$this->config('dynamic_send_to_field')) {
            return null;
        }
        $field_name = $this->config('dynamic_send_to_field');
        if (!isset($methods[$field_name])) {
            return null;
        }

        $m_options = array();
        if ($methods[$field_name]['type'] === 'select') {
            preg_match(
                sprintf(
                    "@<select.*?name=(\"|\')%s\\1.*?>(.+?)</select>@uims",
                    $field_name
                ),
                $html,
                $matches
            );
            if ($matches[2]) {
                preg_match_all(
                    "@<option.+?</option>@uims",
                    $matches[2],
                    $m_options
                );
            }
        } elseif ($methods[$field_name]['type'] === 'radio') {
            preg_match_all(
                sprintf(
                    "/<input.*?name=(\"|\')%s\\1.*?>/im",
                    $field_name
                ),
                $html,
                $m_options
            );
        }

        if (!$m_options[0]) {
            return null;
        }

        foreach ($m_options[0] as $m_option) {
            preg_match_all(
                "/(value|sendto)=([\"'])(.+?)\\2/i",
                $m_option,
                $buf,
                PREG_SET_ORDER
            );
            if ($buf && count($buf) != 2) {
                continue;
            }
            if ($buf[0][1] === 'value') {
                $dynamic_send_to[$buf[0][3]] = $buf[1][3];
            } else {
                $dynamic_send_to[$buf[1][3]] = $buf[0][3];
            }
        }
        return $dynamic_send_to;
    }

    private function _get_input_type($v)
    {
        // 項目タイプを取得
        if ($v[1] === 'input') {
            if (preg_match("/type=([\"'])(.+?)\\1/", $v[0], $t_match) && isset($t_match[2])) {
                return $t_match[2];
            }
            return 'text'; // デフォルトのinputタイプ
        }
        return $v[1];
    }
    /**
     * 指定したIDのフォームタグ内のみ抽出
     *
     * @access private
     * @param string $html 対象のHTML文書
     * @param string $id   抽出対象のフォームID
     * @return mixed 抽出されたHTML文書（失敗の場合は FALSE）
     */
    private function extractForm($html, $id = '')
    {
        if (preg_match("@<form.+?>([\S\s]+)</form>@m", $html, $match_form)) {
            return $match_form[1];
        }

        $this->setError('&lt;form&gt;タグが見つかりません');
        return false;
    }

    /**
     * 入力値をセッションに待避
     *
     * @access public
     * @param  void
     * @return void
     */
    public function storeDataInSession()
    {
        $_SESSION['_cffm_recently_send'] = array();
        foreach ($this->form as $k => $v) {
            if ($k === '_mode' || $k === '_cffm_token') {
                continue;
            }
            $_SESSION['_cffm_recently_send'][$k] = $v;
        }
        return;
    }

    /**
     * エラーメッセージを設定
     *
     * @access private
     * @param  string $mes メッセージ
     * @return void
     */
    private function setError($mes)
    {
        $this->error_message = $mes;
    }

    /**
     * エラーメッセージを取得
     *
     * @access public
     * @param  void
     * @return string 取得したメッセージ
     */
    public function getError()
    {
        return $this->convertText($this->error_message);
    }

    /**
     * デバッグログを出力
     *
     * @access private
     * @param  string $message ログメッセージ
     * @param  int $type イベントタイプ (1=Information, 2=Warning, 3=Error)
     * @return void
     */
    private function debugLog($message, $type = 1)
    {
        if ($this->config('debug_mode')) {
            $this->logEvent(1, $type, $message, 'Debug');
        }
    }

    /**
     * 検証エラーを設定
     *
     * @access private
     * @param  string $field   エラーのあるフィールド名
     * @param  string $label  エラーのある項目名
     * @param  string $message 割り当てるメッセージ
     * @return bool
     */
    private function setFormError($field, $label, $message)
    {
        $this->formError[$field][] = array('label' => $label, 'text' => $message);
        return true;
    }

    /**
     * 検証エラーを取得
     *
     * @access public
     * @return array 取得したメッセージ（プレースホルダ用に整形。個別用の error.field_name と 一括用の errors の両方が返される）
     */
    public function getFormError()
    {
        static $ret = null;
        if ($ret !== null) {
            return $ret;
        }
        if (count($this->formError) < 1) {
            return array();
        }
        $ret = array();
        foreach ($this->formError as $field => $val) {
            foreach ($val as $mes) {
                $ret['error.' . $field][] = $this->convertText($mes['text']);
                $ret['errors'][] = sprintf(
                    '[%s] %s',
                    $mes['label'] ? $this->convertText($mes['label']) : $field,
                    $this->convertText($mes['text'])
                );
            }
        }
        return $ret;
    }

    /**
     * エラー制御
     *
     * @access public
     * @param  string  $mes   エラーメッセージ
     * @param  boolean $ifDie TRUE の場合、プロセス終了
     * @return string
     */
    public function raiseError($mes, $ifDie = false)
    {
        return sprintf(
            '<p style="color:#cc0000;background:#fff;font-weight:bold;">SYSTEM ERROR::%s</p>',
            $mes
        );
    }

    /**
     * バージョン番号を取得
     *
     * @return string バージョン番号
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * 自動返信先メールアドレスを取得
     *
     * @return string メールアドレス
     */
    private function getAutoReplyAddress()
    {
        if (!$this->config('auto_reply')) {
            return '';
        }
        if (strpos($this->config('reply_to'), '+') === false) {
            return $this->form[$this->config('reply_to')] ?? '';
        }

        $reply_to = array();
        $tmp = explode('+', $this->config('reply_to'));
        foreach ($tmp as $t) {
            $t = trim($t);
            if ($t === '@') {
                $reply_to[] = '@';
                continue;
            }
            if (empty($this->form[$t])) {
                exit('reply_toの設定が正しくありません');
            }
            $reply_to[] = $this->form[$t];
        }
        return implode('', $reply_to);
    }

    /**
     * メールアドレス妥当性チェック
     *
     * @access private
     * @param string $addr チェックするメールアドレス
     * @return boolean 結果
     */
    private function _isValidEmail($addr)
    {
        return preg_match(
            "/^(?:[a-z0-9+_-]+?\.)*?[a-z0-9_+-]+?@(?:[a-z0-9_-]+?\.)*?[a-z0-9_-]+?\.[a-z0-9]{2,5}$/i",
            $addr
        );
    }

    private function charset()
    {
        if (isset(evo()->event->params['language'])) {
            $lang = evo()->event->params['language'];
        } else {
            $lang = strtolower(evo()->config['manager_language']);
        }
        if (strpos($lang, 'utf') !== false) {
            return 'utf-8';
        }
        if (strpos($lang, 'euc') !== false) {
            return 'euc-jp';
        }
        return $lang;
    }
    /**
     * 環境設定の読み込み
     *
     * @param string $config_name 環境設定チャンク名
     * @return mixed true || エラーメッセージ
     */
    public function parseConfig($config_name)
    {
        $conf = $this->loadTemplate($config_name);
        if (!$conf) {
            return '環境設定の読み込みに失敗しました。';
        }

        $cfg = $this->setDefaultConfig();

        $cfg['charset'] = $this->charset();
        if (!defined('CHARSET')) {
            define('CHARSET', $cfg['charset']);
        }
        $conf = $this->adaptEncoding($conf, $cfg['charset']);

        $conf_arr = explode("\n", $conf);
        foreach ($conf_arr as $line) {
            if (strpos($line, '#') === 0 || !preg_match('/[a-zA-Z0-9=]/', $line)) {
                continue;
            }
            $parts = explode('=', $line, 2);
            $key = trim($parts[0] ?? '');
            if (!$key) {
                continue;
            }
            $val = trim($parts[1] ?? '');
            $cfg[$key] = $val;
        }

        $this->cfg = $cfg;

        if ($this->getErrors()) {
            $this->setSystemError(
                implode(
                    '<br />',
                    $this->convertText($this->getErrors())
                )
            );
            return false;
        }
        return $this->cfg;
    }

    public function config($key, $default = null)
    {
        if (!isset($this->cfg[$key])) {
            return $default;
        }
        return $this->cfg[$key];
    }

    private function getErrors()
    {
        static $errors = null;

        if ($errors !== null) {
            return $errors;
        }

        // 必須項目チェック
        $errors = array();
        if (!$this->config('tmpl_input')) {
            $errors[] = '`入力画面テンプレート`を指定してください';
        }
        if (!$this->config('tmpl_conf')) {
            $errors[] = '`確認画面テンプレート`を指定してください';
        }
        if (!$this->config('tmpl_comp') && !$this->config('complete_redirect')) {
            $errors[] = '`完了画面テンプレート`または`送信後遷移する完了画面リソースID`を指定してください';
        }

        if (!$this->config('tmpl_mail_admin')) {
            $errors[] = '`管理者宛メールテンプレート`を指定してください';
        }
        if ($this->config('auto_reply') && !$this->config('tmpl_mail_reply')) {
            $errors[] = '`自動返信メールテンプレート`を指定してください';
        }

        return $errors;
    }

    private function setDefaultConfig()
    {
        // 値の指定が無い場合はデフォルト値を設定
        return array(
            'charset'        => 'utf-8',
            'admin_mail'     => evo()->config['emailsender'],
            'auto_reply'     => 0,
            'reply_to'       => 'email',
            'reply_fromname' => evo()->config['site_name'],
            'vericode'       => 0,
            'admin_ishtml'   => 0,
            'reply_ishtml'   => 0,
            'allow_html'     => 0,
            'autosave'       => 0,
            'send_mail'      => 1,
            'mail_charset'   => 'iso-2022-jp-ms',
        );
    }

    private function setSystemError($error_string)
    {
        $this->sysError = $error_string;
    }

    public function hasSystemError()
    {
        if ($this->sysError) {
            return true;
        }

        return false;
    }

    public function getSystemError()
    {
        return $this->sysError;
    }

    /**
     * MODXイベントログに記録（改行をHTMLタグに変換）
     *
     * @param int $evtid イベントID（1: 情報）
     * @param int $type イベントタイプ（1: 情報、2: 警告、3: エラー）
     * @param string $msg メッセージ（改行コードは自動的に<br>タグに変換される）
     * @param string $title ログのタイトル（例: 'Debug', 'Mail Error'）
     * @return void
     */
    private function logEvent($evtid, $type, $msg, $title = 'Info')
    {
        // 呼び出し元の情報を取得
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = $trace[1] ?? [];
        $file = isset($caller['file']) ? basename($caller['file']) : 'unknown';
        $line = $caller['line'] ?? '?';

        // メッセージに呼び出し元情報を付加
        $msg = "[{$file}:{$line}] {$msg}";

        // 改行コード（\n, \r\n, \r）を<br>タグに変換
        $msg = str_replace(["\r\n", "\r", "\n"], '<br>', $msg);

        // タイトルを指定してログ出力
        evo()->logEvent($evtid, $type, $msg, "cfFormMailer - {$title}");
    }

    /**
     * 送信内容をDBに保存
     * 動作にはcfFormDBモジュールが必要
     *
     * @access public
     */
    public function storeDB()
    {
        if (!$this->config('use_store_db')) {
            return;
        }

        if (!$this->ifTableExists()) {
            return;
        }

        $newID = db()->insert(
            array(
                'created' => date('Y-m-d H:i:s', request_time())
            ),
            '[+prefix+]cfformdb'
        );
        $rank = 0;
        foreach ($this->form as $key => $val) {
            if ($key === 'veri') {
                continue;
            }
            if (is_array($val)) {
                $val = implode(',', $val);
            }
            db()->insert(
                array(
                    'postid' => $newID,
                    'field'  => db()->escape($key),
                    'value'  => db()->escape($val),
                    'rank'   => $rank
                ),
                '[+prefix+]cfformdb_detail'
            );
            $rank++;
        }
    }

    /**
     * テーブルの存在確認
     * @access private
     */
    private function ifTableExists()
    {
        $sql = sprintf(
            "SHOW TABLES FROM `%s` LIKE '%%cfformdb%%'",
            db()->config['dbase']
        );
        return db()->getRecordCount(db()->query($sql)) == 2;
    }
}
