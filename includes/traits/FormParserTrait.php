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
     * @param string $html 解析対象のHTML文書
     * @return array|false 解析失敗の場合は false
     */
    private function parseForm($html)
    {
        $html = $this->extractForm($html, '');
        if ($html === false) {
            return false;
        }

        $formElements = $this->extractFormElements($html);
        return $this->buildFieldDefinitions($formElements, $html);
    }

    /**
     * フォーム要素（input/textarea/select）を抽出
     *
     * @param string $html HTML文書
     * @return array マッチした要素の配列
     */
    private function extractFormElements($html)
    {
        preg_match_all(
            "/<(input|textarea|select).*?name=([\"'])(.+?)\\2.*?>/is",
            $html,
            $match,
            PREG_SET_ORDER
        );
        return $match;
    }

    /**
     * 抽出した要素からフィールド定義を構築
     *
     * @param array $elements フォーム要素の配列
     * @param string $html 元のHTML（ラベル検索用）
     * @return array フィールド定義の配列
     */
    private function buildFieldDefinitions($elements, $html)
    {
        $methods = array();

        foreach ($elements as $element) {
            $fieldName = str_replace('[]', '', $element[3]);

            // 既に定義済みならスキップ
            if (isset($methods[$fieldName])) {
                continue;
            }

            $validationInfo = $this->parseValidationAttribute($element[0]);
            $label = $this->resolveFieldLabel($element, $validationInfo['param'], $html);

            $methods[$fieldName] = array(
                'type'     => $this->_get_input_type($element),
                'required' => $validationInfo['required'],
                'method'   => $validationInfo['method'],
                'param'    => $validationInfo['param'],
                'label'    => $label
            );
        }

        return $methods;
    }

    /**
     * valid属性をパースして検証情報を取得
     *
     * @param string $elementHtml 要素のHTML
     * @return array required, method, param を含む配列
     */
    private function parseValidationAttribute($elementHtml)
    {
        if (!preg_match("/valid=([\"'])(.+?)\\1/", $elementHtml, $match)) {
            return array('required' => '', 'method' => '', 'param' => '');
        }

        $parts = explode(':', $match[2]);
        return array(
            'required' => $parts[0] ?? '',
            'method'   => $parts[1] ?? '',
            'param'    => $parts[2] ?? ''
        );
    }

    /**
     * フィールドのラベルを解決
     *
     * @param array $element フォーム要素
     * @param string $paramLabel パラメータで指定されたラベル
     * @param string $html 元のHTML
     * @return string ラベル文字列
     */
    private function resolveFieldLabel($element, $paramLabel, $html)
    {
        // パラメータで指定されていればそれを使用
        if ($paramLabel) {
            return $paramLabel;
        }

        // id属性からlabel要素を検索
        if (!preg_match("/id=([\"'])(.+?)\\1/", $element[0], $idMatch)) {
            return '';
        }

        $pattern = sprintf("@<label for=([\"'])%s\\1.*>(.+?)</label>@", $idMatch[2]);
        if (preg_match($pattern, $html, $labelMatch)) {
            return $labelMatch[2];
        }

        return '';
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
