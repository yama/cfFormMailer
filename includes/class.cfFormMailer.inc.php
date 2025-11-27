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

    /**
     * 入力フォームをレンダリング
     *
     * @return string|false レンダリング結果、失敗時 false
     */
    public function renderForm()
    {
        $text = $this->loadTemplate($this->config('tmpl_input'));
        if ($text === false) {
            return false;
        }

        // CAPTCHA画像URL設定
        $text = $this->applyCaptchaPlaceholder($text);

        // valid/sendto属性を削除
        $text = $this->removeValidationAttributes($text);

        // 自動保存データがあればフォームを復元
        $autosave = sessionv('_cf_autosave');
        if ($autosave) {
            $text = $this->restoreForm($text, $autosave);
        }

        // 不要なタグを削除
        $text = $this->cleanupTemplate($text);

        // 次の処理名をフォームに付記
        return preg_replace(
            '/(<form.*?>)/i',
            '\\1<input type="hidden" name="_mode" value="conf" />',
            $text
        );
    }

    /**
     * エラー表示付きフォームをレンダリング
     *
     * @return string|false レンダリング結果、失敗時 false
     */
    public function renderFormWithError()
    {
        $text = $this->loadTemplate($this->config('tmpl_input'));
        if ($text === false) {
            return false;
        }

        // 事故対策: フォーム内容を一時退避
        $this->autoSaveFormData();

        // CAPTCHA画像URL設定
        $text = $this->applyCaptchaPlaceholder($text);

        // valid/sendto属性を削除
        $text = $this->removeValidationAttributes($text);

        // エラー情報を適用してフォーム値を復元
        $errors = $this->getFormError();
        $text = $this->restoreForm(
            $this->replacePlaceHolder(
                $this->assignErrorClass(
                    $this->assignErrorTag($text, $errors),
                    $errors
                ),
                $errors
            ),
            $this->form
        );

        // 不要なタグを削除
        $text = $this->cleanupTemplate($text);

        return preg_replace(
            '/(<form.*?>)/i',
            '\\1<input type="hidden" name="_mode" value="conf" />',
            $text
        );
    }

    /**
     * 戻り時のフォームをレンダリング
     *
     * @return string|false レンダリング結果、失敗時 false
     */
    public function renderFormOnBack()
    {
        $text = $this->loadTemplate($this->config('tmpl_input'));
        if ($text === false) {
            return false;
        }

        // CAPTCHA画像URL設定
        $text = $this->applyCaptchaPlaceholder($text);

        // valid/sendto属性を削除
        $text = $this->removeValidationAttributes($text);

        // フォーム値を復元
        $text = $this->restoreForm($text, $this->form);

        // アップロード済みファイルを削除
        $this->cleanupUploadedFilesOnBack();

        // 不要なタグを削除
        $text = $this->cleanupTemplate($text);

        return preg_replace(
            '/(<form.*?>)/i',
            '\\1<input type="hidden" name="_mode" value="conf" />',
            $text
        );
    }

    /**
     * CAPTCHA画像URLをプレースホルダに設定
     *
     * @param string $text HTML文書
     * @return string 処理後のHTML
     */
    private function applyCaptchaPlaceholder($text)
    {
        if (!$this->config('vericode')) {
            return $text;
        }
        return $this->replacePlaceHolder($text, array('verimageurl' => $this->getCaptchaUri()));
    }

    /**
     * valid/sendto属性を削除
     *
     * @param string $text HTML文書
     * @return string 処理後のHTML
     */
    private function removeValidationAttributes($text)
    {
        return preg_replace(
            "/\ssendto=([\"']).+?\\1/i",
            '',
            preg_replace("/\svalid=([\"']).+?\\1/i", '', $text)
        );
    }

    /**
     * 戻り時にアップロード済みファイルを削除
     */
    private function cleanupUploadedFilesOnBack()
    {
        $uploaded = sessionv('_cf_uploaded');
        if (!is_array($uploaded) || count($uploaded) === 0) {
            return;
        }

        foreach ($uploaded as $filedata) {
            @unlink($filedata['path']);
        }
        unset($_SESSION['_cf_uploaded']);
    }

    public function renderConfirm()
    {
        $text = $this->loadTemplate($this->config('tmpl_conf'));
        if ($text === false) {
            return false;
        }

        // 事故対策: フォーム内容を一時退避
        $this->autoSaveFormData();

        // 表示用の値を準備
        $values = $this->prepareConfirmValues();

        // アップロードファイルの処理
        $values = $this->processUploadedFilesForConfirm($values);

        // プレースホルダ置換とhidden要素追加
        $text = $this->addHiddenTags(
            $this->replacePlaceHolder($text, $values),
            $this->form
        );

        // セキュリティトークンを埋め込み
        $text = $this->embedSecurityToken($text);

        // 不要なタグを削除
        $text = $this->cleanupTemplate($text);

        return preg_replace(
            '/(<form.*?>)/i',
            '\\1<input type="hidden" name="_mode" value="send" />',
            $text
        );
    }

    /**
     * フォームデータを自動保存（事故対策）
     */
    private function autoSaveFormData()
    {
        if ($this->config('autosave')) {
            $_SESSION['_cf_autosave'] = $this->form;
        }
    }

    /**
     * 確認画面用の表示値を準備
     *
     * @return array 表示用の値
     */
    private function prepareConfirmValues()
    {
        $values = $this->encodeHTML($this->form, true);
        $values = $this->convertNullToStr($values, '&nbsp;');

        if ($this->config('auto_reply')) {
            $values['reply_to'] = $this->getAutoReplyAddress();
        }

        return $values;
    }

    /**
     * 確認画面用にアップロードファイルを処理
     *
     * @param array $values 表示値配列
     * @return array 更新された表示値配列
     */
    private function processUploadedFilesForConfirm($values)
    {
        if (!is_array($_FILES) || count($_FILES) === 0) {
            return $values;
        }

        unset($_SESSION['_cf_uploaded']);

        foreach ($_FILES as $field => $var) {
            if (!$this->isValidUploadedFile($var)) {
                continue;
            }

            $fileInfo = $this->moveAndStoreUploadedFile($field, $var);
            $values = $this->addFileValuesToPlaceholders($values, $field, $var['name'], $fileInfo['mime']);
        }

        return $values;
    }

    /**
     * アップロードファイルが有効かチェック
     *
     * @param array $fileVar $_FILESの要素
     * @return bool 有効な場合 true
     */
    private function isValidUploadedFile($fileVar)
    {
        if (empty($fileVar['tmp_name'])) {
            return false;
        }
        if (!empty($fileVar['error']) && $fileVar['error'] !== $this->config('upload_err_ok')) {
            return false;
        }
        return true;
    }

    /**
     * アップロードファイルを移動してセッションに保存
     *
     * @param string $field フィールド名
     * @param array $fileVar $_FILESの要素
     * @return array ファイル情報
     */
    private function moveAndStoreUploadedFile($field, $fileVar)
    {
        $tmpName = $fileVar['tmp_name'];
        $confirmTmpName = $this->config('upload_tmp_path')
            ? $this->cfm_upload_tmp_name($tmpName)
            : $tmpName;
        $confirmTmpName .= $this->extension($tmpName);

        evo()->move_uploaded_file($tmpName, $confirmTmpName);
        $mime = $this->_getMimeType($confirmTmpName, $field);

        $_SESSION['_cf_uploaded'][$field] = array(
            'path' => $confirmTmpName,
            'mime' => $mime
        );

        return array('path' => $confirmTmpName, 'mime' => $mime);
    }

    /**
     * ファイル情報をプレースホルダ値に追加
     *
     * @param array $values 表示値配列
     * @param string $field フィールド名
     * @param string $originalName 元のファイル名
     * @param string $mime MIMEタイプ
     * @return array 更新された表示値配列
     */
    private function addFileValuesToPlaceholders($values, $field, $originalName, $mime)
    {
        $name = evo()->htmlspecialchars($originalName, ENT_QUOTES);
        $type = strtoupper($this->_getType($mime));

        if (strpos($mime, 'image/') === 0) {
            $values["{$field}.imagename"] = $name;
            $values["{$field}.imagetype"] = $type;
        } else {
            $values["{$field}.filename"] = $name;
            $values["{$field}.filetype"] = $type;
        }

        return $values;
    }

    /**
     * セキュリティトークンをフォームに埋め込む
     *
     * @param string $text HTML文書
     * @return string トークン埋め込み後のHTML
     */
    private function embedSecurityToken($text)
    {
        $token = $this->getToken();
        $_SESSION['_cffm_token'] = $token;

        return str_ireplace(
            '</form>',
            sprintf('<input type="hidden" name="_cffm_token" value="%s" /></form>', $token),
            $text
        );
    }

    /**
     * テンプレートから不要なタグを削除
     *
     * @param string $text HTML文書
     * @return string クリーンアップ後のHTML
     */
    private function cleanupTemplate($text)
    {
        return $this->clearPlaceHolder(
            preg_replace("@<iferror.*?>.+?</iferror>@uism", '', $text)
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
     * @return bool 検証成功時 true、失敗時 false
     */
    public function validate()
    {
        $this->formError = array();

        // テンプレート読み込みとパース
        if (!$this->loadAndParseInputTemplate()) {
            return false;
        }

        // 各フィールドの検証
        foreach ($this->parsedForm as $field => $method) {
            $this->normalizeFieldValue($field, $method);
            $this->validateRequiredField($field, $method);
            $this->validateFieldRules($field, $method);
        }

        // 自動返信先メールアドレスの検証
        $this->validateAutoReplyAddress();

        return empty($this->formError);
    }

    /**
     * 入力テンプレートを読み込みパースする
     *
     * @return bool 成功時 true
     */
    private function loadAndParseInputTemplate()
    {
        $tmp_html = $this->loadTemplate($this->config('tmpl_input'));
        if (!$tmp_html) {
            $this->raiseError($this->convertText('入力画面テンプレートの読み込みに失敗しました'));
            return false;
        }

        $this->parsedForm = $this->parseForm($tmp_html);
        if (!$this->parsedForm) {
            $this->raiseError($this->getError());
            return false;
        }

        $_SESSION['dynamic_send_to'] = $this->dynamic_send_to_field($tmp_html);
        return true;
    }

    /**
     * フィールド値を正規化（改行コードの処理など）
     *
     * @param string $field フィールド名
     * @param array $method フィールド定義
     */
    private function normalizeFieldValue($field, $method)
    {
        // textarea以外は改行を削除
        if ($method['type'] === 'textarea') {
            return;
        }

        if (!isset($this->form[$field])) {
            return;
        }

        if (is_array($this->form[$field])) {
            foreach ($this->form[$field] as $k => $v) {
                $this->form[$field][$k] = strtr($v, array("\r" => '', "\n" => ''));
            }
        } else {
            $this->form[$field] = strtr($this->form[$field], array("\r" => '', "\n" => ''));
        }
    }

    /**
     * 必須フィールドの検証
     *
     * @param string $field フィールド名
     * @param array $method フィールド定義
     */
    private function validateRequiredField($field, $method)
    {
        if (!$method['required']) {
            return;
        }

        $label = $this->adaptEncoding($method['label']);

        // ファイルアップロードの必須チェック
        if ($method['type'] === 'file') {
            $uploaded_file = sessionv("_cf_uploaded.{$field}");
            $file_tmp_name = $_FILES[$field]['tmp_name'] ?? '';
            $hasUploadedFile = is_array($uploaded_file) && is_file($uploaded_file['path']);
            $hasNewFile = !postv('return') && !empty($file_tmp_name);

            if (!$hasUploadedFile && !$hasNewFile) {
                $this->setFormError($field, $label, '選択必須項目です');
            }
            return;
        }

        // 通常フィールドの必須チェック
        $fieldValue = $this->form[$field] ?? '';
        $isEmpty = (is_array($fieldValue) && count($fieldValue) === 0) || $fieldValue === '';

        if ($isEmpty) {
            $errorType = in_array($method['type'], array('radio', 'select')) ? '選択' : '入力';
            $this->setFormError($field, $label, $errorType . '必須項目です');
        }
    }

    /**
     * フィールドの検証ルールを適用
     *
     * @param string $field フィールド名
     * @param array $method フィールド定義
     */
    private function validateFieldRules($field, $method)
    {
        $fieldValue = $this->form[$field] ?? '';
        $fileTmpName = $_FILES[$field]['tmp_name'] ?? '';

        // 値がない場合は検証スキップ
        if (!$fieldValue && !$fileTmpName && $fieldValue !== '0') {
            return;
        }

        $methods = explode(',', $method['method']);
        foreach ($methods as $indiv_m) {
            $this->executeValidationMethod($field, $method, $fieldValue, $indiv_m);
        }
    }

    /**
     * 個別の検証メソッドを実行
     *
     * @param string $field フィールド名
     * @param array $method フィールド定義
     * @param mixed $fieldValue フィールド値
     * @param string $validationRule 検証ルール文字列
     */
    private function executeValidationMethod($field, $method, $fieldValue, $validationRule)
    {
        // ルール文字列をパース: methodName(param)
        if (!preg_match("/^([^(]+)(\(([^)]*)\))?$/", $validationRule, $matches)) {
            return;
        }

        $methodFunc = $matches[1] ?? '';
        $methodParam = $matches[3] ?? '';

        if (!$methodFunc) {
            return;
        }

        $label = $this->adaptEncoding($method['label']);

        // 標準検証メソッド（_def_xxx）を実行
        $defFuncName = '_def_' . $methodFunc;
        if (is_callable(array($this, $defFuncName))) {
            $result = $this->$defFuncName($fieldValue, $methodParam, $field);
            if ($result !== true) {
                $this->setFormError($field, $label, $result);
            }
        }

        // ユーザー定義検証メソッド（_validate_xxx）を実行
        $userFuncName = '_validate_' . $methodFunc;
        if (is_callable($userFuncName)) {
            $result = $userFuncName($fieldValue, $methodParam);
            if ($result !== true) {
                $this->setFormError($field, $label, $this->adaptEncoding($result));
            }
        }
    }

    /**
     * 自動返信先メールアドレスを検証
     */
    private function validateAutoReplyAddress()
    {
        if (!$this->config('auto_reply')) {
            return;
        }

        $replyAddress = $this->getAutoReplyAddress();
        if (!$this->_isValidEmail($replyAddress)) {
            $this->setFormError('reply_to', 'メールアドレス', '形式が正しくありません');
        }
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
