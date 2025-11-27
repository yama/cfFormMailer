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
require_once __DIR__ . '/traits/ConfigTrait.php';
require_once __DIR__ . '/traits/FormParserTrait.php';

class Class_cfFormMailer
{
    use ValidationMethodsTrait;
    use FilterMethodsTrait;
    use TemplateMethodsTrait;
    use MailMethodsTrait;
    use FormRenderingTrait;
    use UtilityMethodsTrait;
    use FileUploadTrait;
    use ConfigTrait;
    use FormParserTrait;

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
