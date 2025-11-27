<?php
/**
 * FormParserTrait - フォーム解析メソッド
 *
 * Class_cfFormMailer から分離したフォーム解析処理
 * - フォームデータの前処理
 * - フォームHTML解析
 * - 動的送信先フィールド処理
 * - エラー管理
 *
 * @package cfFormMailer
 * @since 1.7.1
 */
trait FormParserTrait
{
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

    /**
     * 動的送信先フィールドの解析
     *
     * @param string $html 対象のHTML文書
     * @return array|null 動的送信先データ
     */
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

    /**
     * input要素のタイプを取得
     *
     * @param array $v マッチ結果
     * @return string タイプ名
     */
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
}
