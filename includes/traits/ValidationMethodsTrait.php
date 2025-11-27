<?php
/**
 * cfFormMailer - ValidationMethodsTrait
 *
 * 標準装備の検証メソッド群
 *
 * @author  Clefarray Factory
 * @link    https://www.clefarray-web.net/
 *
 * このトレイトは Class_cfFormMailer で使用されることを前提としています。
 * 以下のプロパティ・メソッドへの依存があります：
 *
 * @property array $form        POSTデータ
 * @property array $parsedForm  フォームのvalid要素
 *
 * @method mixed config(string $key, mixed $default = null)
 * @method bool _isValidEmail(string $addr)
 * @method string|false _getMimeType(string $filename, string $field = '')
 * @method string|false _getType(string $mime)
 * @method string adaptEncoding(string $text, string $charset = '')
 */

trait ValidationMethodsTrait
{
    /* ------------------------------------------------------------------ */
    /* 標準装備の検証メソッド
    /* ------------------------------------------------------------------ */

    /**
     * num : 数値？
     */
    private function _def_num($value, $param, $field)
    {
        // 強制的に半角に変換します。
        $this->form[$field] = mb_convert_kana(
            $this->form[$field] ?? '',
            'n',
            $this->config('charset')
        );

        if (is_numeric($this->form[$field])) {
            return true;
        }

        return '半角数字で入力してください';
    }

    /**
     * email : 正しいメールアドレス形式か？
     */
    private function _def_email($value, $param, $field)
    {
        // 強制的に半角に変換します。
        $this->form[$field] = mb_convert_kana(
            $this->form[$field] ?? '',
            'a',
            $this->config('charset')
        );

        if ($this->_isValidEmail($this->form[$field])) {
            return true;
        }

        return 'メールアドレスの形式が正しくありません';
    }

    /**
     * len(min, max) : 文字数チェック
     */
    private function _def_len($value, $param, $field)
    {

        if (!preg_match("/([0-9]+)?(-)?([0-9]+)?/", $param, $match)) {
            return true;
        }

        if ($match[1] && empty($match[2]) && empty($match[3])) {
            if (mb_strlen($value) != $match[1]) {
                return $match[1] . '文字で入力してください';
            }
            return true;
        }

        if (!$match[1] && $match[2] && $match[3]) {
            if (mb_strlen($value) > $match[3]) {
                return $match[3] . '文字以内で入力してください';
            }
            return true;
        }

        if ($match[1] && $match[2] && !$match[3]) {
            if (mb_strlen($value) < $match[1]) {
                return $match[1] . '文字以上で入力してください';
            }
            return true;
        }

        if ($match[1] && $match[2] && $match[3]) {
            if (mb_strlen($value) < $match[1] || mb_strlen($value) > $match[3]) {
                return sprintf('%s～%s文字で入力してください', $match[1], $match[3]);
            }
            return true;
        }
        return true;
    }

    /**
     * vericode : CAPTCHA による認証チェック
     *   Added in v0.0.5
     */
    private function _def_vericode($value, $param, $field)
    {
        if (!$this->config('vericode')) {
            return true;
        }
        if (sessionv('veriword') == $value) {
            return true;
        }

        $this->form[$field] = '';
        return '入力値が正しくありません';
    }

    /**
     * range(min, max) : 値範囲チェック
     *   Added in v0.0.5
     */
    private function _def_range($value, $param, $field)
    {

        if (!preg_match('/([0-9-]+)?(~)?([0-9-]+)?/', $param, $match)) {
            return true;
        }

        if ($match[1] && !$match[2] && !$match[3]) {
            if ($match[1] < $value) {
                return '入力値が範囲外です';
            }

            return true;
        }

        if (!$match[1] && $match[2] && $match[3]) {
            if ($match[3] < $value) {
                return '入力値が範囲外です';
            }
            return true;
        }

        if (isset($match[1], $match[2])) {
            if ($value < $match[1]) {
                return '入力値が範囲外です';
            }

            if ($match[3] < $value) {
                return '入力値が範囲外です';
            }
            return true;
        }

        return true;
    }

    /**
     * sameas(field) : 同一確認
     *   Added in v0.0.6
     */
    private function _def_sameas($value, $param, $field)
    {
        if ($value == ($this->form[$param] ?? null)) {
            return true;
        }

        unset($this->form[$field]);
        return sprintf(
            '&laquo; %s &raquo; と一致しません',
            $this->adaptEncoding(
                $this->parsedForm[$param]['label'] ?? $param
            )
        );
    }

    /**
     * tel : 電話番号？
     *   Added in v0.0.7
     */
    private function _def_tel($value, $param, $field)
    {
        // 強制的に半角に変換します。
        $this->form[$field] = mb_convert_kana($this->form[$field] ?? '', 'a', $this->config('charset'));
        $this->form[$field] = preg_replace('@([0-9])ー@u', '$1-', $this->form[$field]);
        if ((strpos($this->form[$field], '0') === 0)) {
            $checkLen = 10;
        } else {
            $checkLen = 5;
        }
        $checkStr = preg_replace('/[^0-9]/', '', $this->form[$field]);
        if (!preg_match('/[0-9]{4}$/', $this->form[$field])) {
            return '正しい電話番号を入力してください。';
        }

        if (preg_match("/[^0-9\-+]/", $checkStr) || strlen($checkStr) < $checkLen) {
            return '半角数字とハイフンで正しく入力してください';
        }

        return true;
    }

    /**
     * zip : 郵便番号
     *   Added in v1.3.x
     */
    private function _def_zip($value, $param, $field)
    {
        // 強制的に半角に変換します。
        $this->form[$field] = mb_convert_kana($this->form[$field] ?? '', 'as', $this->config('charset'));
        $this->form[$field] = preg_replace('/[^0-9]/', '', $this->form[$field]);
        $str = $this->form[$field];

        if (strlen($str) !== 7) {
            return '半角数字とハイフンで正しく入力してください';
        }

        $this->form[$field] = substr($str, 0, 3) . '-' . substr($str, -4);

        return true;
    }

    /**
     * allowtype(type) : アップロードを許可するファイル形式
     *   Added in v1.0
     */
    private function _def_allowtype($value, $param, $field)
    {
        $file_tmp_name = $_FILES[$field]['tmp_name'] ?? '';
        if (empty($file_tmp_name) || !is_uploaded_file($file_tmp_name)) {
            return true;
        }
        $allow_list = explode('|', $param);
        if (!count($allow_list)) {
            return true;
        }

        $mime = $this->_getMimeType($file_tmp_name, $field);
        if ($mime === false) {
            return '許可されたファイル形式ではありません';
        }

        $type = $this->_getType($mime);
        if (!$type || !in_array($type, $allow_list, true)) {
            return '許可されたファイル形式ではありません';
        }

        return true;
    }

    /**
     * allowsize(size) : アップロードを許可する最大ファイルサイズ
     *   Added in v1.0
     */
    private function _def_allowsize($value, $param, $field)
    {
        $file_tmp_name = $_FILES[$field]['tmp_name'] ?? '';
        if (empty($file_tmp_name) || !is_uploaded_file($file_tmp_name)) {
            return true;
        }

        if (!$param || !is_numeric($param)) {
            return false;
        }

        $size = @stat($file_tmp_name);
        if ($size === false) {
            return 'ファイルのアップロードに失敗しました';
        }

        if (($size['size'] <= $param * 1024)) {
            return true;
        }

        return $param . 'キロバイト以内のファイルを指定してください';
    }

    /**
     * convert(param)：半角英数字に変換
     *   See: http://jp2.php.net/manual/ja/function.mb-convert-kana.php
     *   Added in v1.2
     * @param $value
     * @param string $param
     * @param $field
     * @return bool
     */
    private function _def_convert($value, $param = 'K', $field)
    {
        if (!$param) {
            $param = 'K';
        }
        $this->form[$field] = mb_convert_kana(
            $this->form[$field] ?? '',
            $param,
            $this->config('charset')
        );
        return true;  // 常にtrueを返す
    }

    /**
     * zenhan：半角英数字記号に変換
     *   See: http://jp2.php.net/manual/ja/function.mb-convert-kana.php
     *   Added in v1.2
     * @param $value
     * @param string $param
     * @param $field
     * @return bool
     */
    private function _def_zenhan($value, $param = 'VKas', $field)
    {
        $this->form[$field] = mb_convert_kana(
            $this->form[$field] ?? '',
            $param,
            $this->config('charset')
        );
        $this->form[$field] = preg_replace('@([0-9])ー@u', '$1-', $this->form[$field]);
        return true;  // 常にtrueを返す
    }

    /**
     * hanzen：全角英数字記号に変換
     *   See: http://jp2.php.net/manual/ja/function.mb-convert-kana.php
     *   Added in v1.2
     * @param $value
     * @param string $param
     * @param $field
     * @return bool
     */
    private function _def_hanzen($value, $param = 'VKAS', $field)
    {
        $this->form[$field] = mb_convert_kana(
            $this->form[$field] ?? '',
            $param,
            $this->config('charset')
        );
        return true;  // 常にtrueを返す
    }

    /**
     * url(string)：URL値検証
     *   Added in v1.2
     */
    private function _def_url($value, $param, $field)
    {
        return preg_match("@^https?://.+\..+@", $value);
    }

    /* ------------------------------------------------------------------ */
    /* ユーティリティメソッド（検証用）
    /* ------------------------------------------------------------------ */

    /**
     * 配列から値を取得
     *
     * @param array $array
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    private function array_get($array, $key, $default = null)
    {
        if (!isset($array[$key])) {
            return $default;
        }
        return $array[$key];
    }

    /**
     * スパム判定
     *
     * @return bool
     */
    private function isSpam()
    {
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            return false;
        }
        return true;
    }
}
