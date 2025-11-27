<?php
/**
 * UtilityMethodsTrait - ユーティリティメソッド
 *
 * Class_cfFormMailer から分離したユーティリティメソッド群
 * - 文字コード変換
 * - HTMLエンコード
 * - 機種依存文字変換
 * - NULL値変換
 * - CAPTCHA URI取得
 *
 * @package cfFormMailer
 * @since 1.7.1
 */
trait UtilityMethodsTrait
{
    /**
     * サイトで使用する文字コードに変換
     *
     * @access private
     * @param  mixed $text 変換するテキスト
     * @return mixed 変換後のテキスト
     */
    private function convertText($text)
    {
        if (strtolower($this->config('charset')) === 'utf-8') {
            return $text;
        }
        if (is_array($text)) {
            foreach ($text as $k => $v) {
                $text[$k] = $this->convertText($v);
            }
            return $text;
        }
        return mb_convert_encoding($text, $this->config('charset'), 'utf-8');
    }

    /**
     * 文字コードをシステムに合わせる
     *  - （UTF-8 以外の文字コードを UTF-8 に変換）
     *
     * @param  string 変換するテキスト
     * @return array|string
     */
    private function adaptEncoding($text, $charset = '')
    {
        if (!$charset) {
            $charset = $this->charset();
        }
        if (strtolower($charset) === 'utf-8') {
            return $text;
        }
        if (is_array($text)) {
            foreach ($text as $k => $v) {
                $text[$k] = $this->adaptEncoding($v);
            }
            return $text;
        }
        return mb_convert_encoding($text, 'utf-8', $charset);
    }

    /**
     * HTMLエンコード
     *
     * @access private
     * @param  mixed   $text  変換するテキスト（または配列）
     * @param  boolean $nl2br TRUE の場合、改行コードを<br />に変換　（Default: FALSE）
     * @return mixed 変換後のテキストまたは配列
     */
    private function encodeHTML($text, $nl2br = false)
    {
        if (is_array($text)) {
            foreach ($text as $k => $v) {
                $text[$k] = $this->encodeHTML($v, $nl2br);
            }
            return $text;
        }
        $text = $this->convertjp(
            evo()->htmlspecialchars($text, ENT_QUOTES)
        );
        if ($nl2br) {
            return nl2br($text);
        }
        return $text;
    }

    /**
     * 機種依存文字を変換
     *
     * @param string $text 変換するテキスト
     * @return string 変換後のテキスト
     */
    function convertjp($text)
    {
        $char = array(
            '①' => '(1)',
            '②' => '(2)',
            '③' => '(3)',
            '④' => '(4)',
            '⑤' => '(5)',
            '⑥' => '(6)',
            '⑦' => '(7)',
            '⑧' => '(8)',
            '⑨' => '(9)',
            '⑩' => '(10)',
            '⑪' => '(11)',
            '⑫' => '(12)',
            '⑬' => '(13)',
            '⑭' => '(14)',
            '⑮' => '(15)',
            '⑯' => '(16)',
            '⑰' => '(17)',
            '⑱' => '(18)',
            '⑲' => '(19)',
            '⑳' => '(20)',
            'Ⅰ' => 'I',
            'Ⅱ' => 'II',
            'Ⅲ' => 'III',
            'Ⅳ' => 'IV',
            'Ⅴ' => 'V',
            'Ⅵ' => 'VI',
            'Ⅶ' => 'VII',
            'Ⅷ' => 'VIII',
            'Ⅸ' => 'IX',
            'Ⅹ' => 'X',
            '㍉' => 'ミリ',
            '㌔' => 'キロ',
            '㌢' => 'センチ',
            '㍍' => 'メートル',
            '㌘' => 'グラム',
            '㌧' => 'トン',
            '㌃' => 'アール',
            '㌶' => 'ヘクタール',
            '㍑' => 'リットル',
            '㍗' => 'ワット',
            '㌍' => 'カロリー',
            '㌦' => 'ドル',
            '㌣' => 'セント',
            '㌫' => 'パーセント',
            '㍊' => 'ミリバール',
            '㌻' => 'ページ',
            '㎜' => 'mm',
            '㎝' => 'cm',
            '㎞' => 'km',
            '㎎' => 'mg',
            '㎏' => 'kg',
            '㏄' => 'cc',
            '㎡' => '平方メートル',
            '㍻' => '平成',
            '〝' => '「',
            '〟' => '」',
            '№' => 'No.',
            '㏍' => 'k.k.',
            '℡' => 'Tel',
            '㊤' => '(上)',
            '㊥' => '(中)',
            '㊦' => '(下)',
            '㊧' => '(左)',
            '㊨' => '(右)',
            '㈱' => '(株)',
            '㈲' => '(有)',
            '㈹' => '(代)',
            '㍾' => '明治',
            '㍽' => '大正',
            '㍼' => '昭和'
        );

        return str_replace(
            array_keys($char),
            array_values($char),
            mb_convert_kana($text, 'KV', 'UTF-8')
        );
    }

    /**
     * 改行コードを<br />タグに変換
     *
     * @param mixed $text 変換するテキスト（または配列）
     * @return mixed 変換後のテキストまたは配列
     */
    public function nl2br_array($text)
    {
        if (is_array($text)) {
            return array_map(array($this, 'nl2br_array'), $text);
        }
        return nl2br($text);
    }

    /**
     * NULL 値を文字列に変換
     *
     * @param mixed $data 変換するデータ
     * @param string $string 変換される文字列(Default: &nbsp;)
     * @return mixed 変換後のデータ
     */
    private function convertNullToStr($data, $string = '&nbsp;')
    {
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $data[$k] = $this->convertNullToStr($v, $string);
            }
            return $data;
        }
        if (!$data) {
            return $string;
        }
        return $data;
    }

    /**
     * 認証コード用画像の URI を取得
     *
     * @access private
     * @param void
     * @return string 認証コード画像の URI
     */
    private function getCaptchaUri()
    {
        if (is_file(MODX_BASE_PATH . 'captcha.php')) {
            return MODX_BASE_URL . 'captcha.php';
        }

        if (is_file(MODX_MANAGER_PATH . 'media/captcha/veriword.php')) {
            return MODX_BASE_URL . 'index.php?get=captcha';
        }

        return MODX_BASE_URL . 'manager/includes/veriword.php?tmp=' . mt_rand();
    }
}
