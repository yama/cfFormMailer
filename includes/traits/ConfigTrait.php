<?php
/**
 * ConfigTrait - 設定管理メソッド
 *
 * Class_cfFormMailer から分離した設定管理処理
 * - 設定の読み込み・解析
 * - デフォルト設定
 * - 文字コード判定
 * - エラーログ
 *
 * @package cfFormMailer
 * @since 1.7.1
 */
trait ConfigTrait
{
    /**
     * システムの文字コードを取得
     *
     * @return string 文字コード
     */
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

        if ($this->getConfigErrors()) {
            $this->setSystemError(
                implode(
                    '<br />',
                    $this->convertText($this->getConfigErrors())
                )
            );
            return false;
        }
        return $this->cfg;
    }

    /**
     * 設定値を取得
     *
     * @param string $key 設定キー
     * @param mixed $default デフォルト値
     * @return mixed 設定値
     */
    public function config($key, $default = null)
    {
        if (!isset($this->cfg[$key])) {
            return $default;
        }
        return $this->cfg[$key];
    }

    /**
     * 設定エラーを取得
     *
     * @return array エラーメッセージ配列
     */
    private function getConfigErrors()
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

    /**
     * デフォルト設定を取得
     *
     * @return array デフォルト設定配列
     */
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

    /**
     * システムエラーを設定
     *
     * @param string $error_string エラーメッセージ
     */
    private function setSystemError($error_string)
    {
        $this->sysError = $error_string;
    }

    /**
     * システムエラーがあるかチェック
     *
     * @return boolean エラーがあればtrue
     */
    public function hasSystemError()
    {
        if ($this->sysError) {
            return true;
        }

        return false;
    }

    /**
     * システムエラーを取得
     *
     * @return string エラーメッセージ
     */
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
}
