<?php
/**
 * cfFormMailer - FormRenderingTrait
 *
 * フォームレンダリング補助メソッド群
 *
 * @author  Clefarray Factory
 * @link    https://www.clefarray-web.net/
 *
 * このトレイトは Class_cfFormMailer で使用されることを前提としています。
 * 以下のプロパティ・メソッドへの依存があります：
 *
 * @method mixed config(string $key, mixed $default = null)
 * @method string|array encodeHTML(mixed $text, bool $nl2br = false)
 */

trait FormRenderingTrait
{
    /* ------------------------------------------------------------------ */
    /* フォームレンダリング補助メソッド
    /* ------------------------------------------------------------------ */

    /**
     * 入力値を復元
     *
     * @param  string $html   HTML text
     * @param  array  $params 再現する値の配列
     * @return string
     */
    private function restoreForm($html, $params)
    {
        $match = array();
        preg_match_all("@<(input|textarea|select)(.+?)([\s/]*?)>(.*?</\\1>)?@uism", $html, $match, PREG_SET_ORDER);

        foreach ($match as $tag) {
            $html = $this->restoreFormTag($html, $tag, $params);
        }
        return $html;
    }

    /**
     * 個別のフォームタグを復元
     *
     * @param string $html HTML
     * @param array $tag マッチしたタグ情報
     * @param array $params フォームデータ
     * @return string 処理後のHTML
     */
    private function restoreFormTag($html, $tag, $params)
    {
        $m_type = array();
        $m_name = array();
        $m_value = array();
        preg_match("/type=([\"'])(.+?)\\1/i", $tag[0], $m_type);
        preg_match("/name=([\"'])(.+?)\\1/i", $tag[0], $m_name);
        preg_match("/value=([\"'])(.*?)\\1/i", $tag[0], $m_value);

        if (!isset($m_name[2]) || !isset($m_type[2])) {
            return $html;
        }

        $fieldName = str_replace('[]', '', $m_name[2]);
        if ($fieldName === '_mode') {
            return $html;
        }

        $fieldType = $this->determineFieldType($m_type[2]);
        if ($fieldType === null) {
            return $html;
        }

        $fieldValue = $params[$fieldName] ?? '';

        switch ($tag[1]) {
            case 'input':
                return $this->restoreInputTag($html, $tag, $fieldType, $fieldValue, $m_value);
            case 'select':
                return $this->restoreSelectTag($html, $tag, $fieldValue);
            case 'textarea':
                return $this->restoreTextareaTag($html, $tag, $fieldValue);
            default:
                return $html;
        }
    }

    /**
     * フィールドタイプを判定
     *
     * @param string $type input type属性値
     * @return string|null フィールドタイプ (text/checkbox/radio) または null (復元しないタイプ)
     */
    private function determineFieldType($type)
    {
        $skipTypes = ['submit', 'image', 'file', 'button', 'reset', 'hidden'];
        if (in_array($type, $skipTypes, true)) {
            return null;
        }

        if ($type === 'checkbox' || $type === 'radio') {
            return $type;
        }

        return 'text';
    }

    /**
     * input要素の値を復元
     *
     * @param string $html HTML
     * @param array $tag タグ情報
     * @param string $fieldType フィールドタイプ
     * @param mixed $fieldValue フィールド値
     * @param array $m_value value属性のマッチ結果
     * @return string 処理後のHTML
     */
    private function restoreInputTag($html, $tag, $fieldType, $fieldValue, $m_value)
    {
        if ($fieldType === 'text') {
            return $this->restoreTextInput($html, $tag, $fieldValue, $m_value);
        }

        if ($fieldType === 'checkbox') {
            return $this->restoreCheckbox($html, $tag, $fieldValue, $m_value);
        }

        if ($fieldType === 'radio') {
            return $this->restoreRadio($html, $tag, $fieldValue, $m_value);
        }

        return $html;
    }

    /**
     * テキストinputの値を復元
     *
     * @param string $html HTML
     * @param array $tag タグ情報
     * @param mixed $fieldValue フィールド値
     * @param array $m_value value属性のマッチ結果
     * @return string 処理後のHTML
     */
    private function restoreTextInput($html, $tag, $fieldValue, $m_value)
    {
        if (count($m_value) > 1) {
            $pat = $m_value[0];
            $rep = 'value="' . $this->encodeHTML($fieldValue) . '"';
        } else {
            $pat = $tag[2];
            $rep = $tag[2] . ' value="' . $this->encodeHTML($fieldValue) . '"';
        }

        $tag_new = str_replace($pat, $rep, $tag[0]);
        return str_replace($tag[0], $tag_new, $html);
    }

    /**
     * チェックボックスの状態を復元
     *
     * @param string $html HTML
     * @param array $tag タグ情報
     * @param mixed $fieldValue フィールド値
     * @param array $m_value value属性のマッチ結果
     * @return string 処理後のHTML
     */
    private function restoreCheckbox($html, $tag, $fieldValue, $m_value)
    {
        if (!isset($m_value[2])) {
            return $html;
        }

        $isChecked = ($m_value[2] == $fieldValue) ||
                     (is_array($fieldValue) && in_array($m_value[2], $fieldValue));

        if (!$isChecked) {
            return $html;
        }

        $tag_new = str_replace($tag[2], $tag[2] . ' checked="checked"', $tag[0]);
        return str_replace($tag[0], $tag_new, $html);
    }

    /**
     * ラジオボタンの状態を復元
     *
     * @param string $html HTML
     * @param array $tag タグ情報
     * @param mixed $fieldValue フィールド値
     * @param array $m_value value属性のマッチ結果
     * @return string 処理後のHTML
     */
    private function restoreRadio($html, $tag, $fieldValue, $m_value)
    {
        if (!isset($m_value[2]) || $m_value[2] != $fieldValue) {
            return $html;
        }

        $tag_new = str_replace($tag[2], $tag[2] . ' checked="checked"', $tag[0]);
        return str_replace($tag[0], $tag_new, $html);
    }

    /**
     * select要素の値を復元
     *
     * @param string $html HTML
     * @param array $tag タグ情報
     * @param mixed $fieldValue フィールド値
     * @return string 処理後のHTML
     */
    private function restoreSelectTag($html, $tag, $fieldValue)
    {
        $tag_opt = array();
        preg_match_all(
            "/<option(.*?)value=(['\"])(.*?)\\2(.*?>)/uism",
            $tag[4],
            $tag_opt,
            PREG_SET_ORDER
        );

        if (count($tag_opt) <= 1) {
            return $html;
        }

        $old = $tag[0];
        foreach ($tag_opt as $v) {
            // 既存のselected属性を削除
            $tag[0] = str_replace(
                $v[0],
                preg_replace("/selected(=(['\"])selected\\2)?/uism", '', $v[0]),
                $tag[0]
            );
            // 一致する値にselected属性を追加
            if ($v[3] == $fieldValue) {
                $tag[0] = str_replace(
                    $v[0],
                    str_replace($v[4], ' selected="selected"' . $v[4], $v[0]),
                    $tag[0]
                );
            }
        }
        return str_replace($old, $tag[0], $html);
    }

    /**
     * textarea要素の値を復元
     *
     * @param string $html HTML
     * @param array $tag タグ情報
     * @param mixed $fieldValue フィールド値
     * @return string 処理後のHTML
     */
    private function restoreTextareaTag($html, $tag, $fieldValue)
    {
        if (!$fieldValue) {
            return $html;
        }

        $rep = sprintf(
            '<%s%s%s>%s</textarea>',
            $tag[1],
            $tag[2],
            $tag[3],
            $this->encodeHTML($fieldValue)
        );
        return str_replace($tag[0], $rep, $html);
    }

    /**
     * iferrorタグを処理
     *
     * @access private
     * @param string $html   処理対象のHTML文書
     * @param array  $errors フォームエラーメッセージ
     * @return string 処理後のHTML文書
     */
    private function assignErrorTag($html, $errors)
    {
        if (!is_array($errors)) {
            return $html;
        }

        preg_match_all("@<iferror\.?([^>]+?)?>(.+?)</iferror>@uism", $html, $match, PREG_SET_ORDER);
        if (!count($match)) {
            return $html;
        }

        foreach ($match as $tag) {
            $html = $this->processErrorTag($html, $tag, $errors);
        }
        return $html;
    }

    /**
     * 個別のiferrorタグを処理
     *
     * @param string $html HTML
     * @param array $tag マッチしたタグ情報
     * @param array $errors エラー情報
     * @return string 処理後のHTML
     */
    private function processErrorTag($html, $tag, $errors)
    {
        // フィールド指定なし: エラー全体の処理
        if (empty($tag[1])) {
            if (count($errors)) {
                return str_replace($tag[0], $tag[2], $html);
            }
            return $html;
        }

        // グルーピングされたタグの処理 (例: iferror.(field1,field2))
        if (preg_match("/^\((.+?)\)$/", $tag[1], $g_match)) {
            return $this->processGroupedErrorTag($html, $tag, $errors, $g_match[1]);
        }

        // 単一フィールドのエラー処理
        if (isset($errors['error.' . $tag[1]])) {
            return str_replace($tag[0], $tag[2], $html);
        }

        return $html;
    }

    /**
     * グループ化されたエラータグを処理
     *
     * @param string $html HTML
     * @param array $tag マッチしたタグ情報
     * @param array $errors エラー情報
     * @param string $groupStr グループ文字列 (カンマ区切り)
     * @return string 処理後のHTML
     */
    private function processGroupedErrorTag($html, $tag, $errors, $groupStr)
    {
        $groups = explode(',', $groupStr);
        $hasError = false;

        foreach ($groups as $group) {
            $fieldName = 'error.' . strtr($group, array(' ' => ''));
            if (!empty($errors[$fieldName])) {
                $hasError = true;
                break;
            }
        }

        if ($hasError) {
            return str_replace($tag[0], $tag[2], $html);
        }

        return $html;
    }

    /**
     * エラーのあるフォーム項目にクラスセレクタを付加
     *
     * @access private
     * @param string $html   付加対象のHTML文書
     * @param array  $errors フォームエラーメッセージ
     * @return string 処理後のHTML文書
     */
    private function assignErrorClass($html, $errors)
    {
        if (!$this->config('invalid_class')) {
            return $html;
        }

        if (count($errors) < 2) return $html;

        // エラーのあるフィールド名リストを作成
        if (isset($errors['errors'])) unset($errors['errors']);
        $keys = array_unique(array_keys($errors));
        foreach ($keys as $field) {
            $pattern = sprintf(
                "#<(input|textarea|select)[^>]*?name=([\"'])%s\\2[^/>]*/?>#uim",
                str_replace('error.', '', $field)
            );
            if (!preg_match_all($pattern, $html, $match, PREG_SET_ORDER)) {
                continue;
            }
            foreach ($match as $m) {
                if (preg_match("/class=([\"'])(.+?)\\1/", $m[0], $match_classes)) {
                    $html = str_replace(
                        $m[0],
                        str_replace(
                            $match_classes[0],
                            sprintf(
                                'class=%s%s %s%s',
                                $match_classes[1],
                                $match_classes[2],
                                $this->config('invalid_class', ''),
                                $match_classes[1]
                            ),
                            $m[0]
                        ),
                        $html
                    );
                    continue;
                }
                $html = str_replace(
                    $m[0],
                    preg_replace(
                        "#\s*/?>$#",
                        '',
                        $m[0]
                    ) . sprintf(
                        ' class="%s"',
                        $this->config('invalid_class', '')
                    ) . ($m[1] === 'input' ? ' /' : '') . '>',
                    $html
                );
            }
        }
        return $html;
    }

    /**
     * フォームにhiddenタグを追加
     *
     * @access private
     * @param string $html フォームを含むHTML文書
     * @param array  $form フォームデータ
     * @return string 処理後のHTML文書
     */
    private function addHiddenTags($html, $form)
    {
        if (!is_array($form)) {
            return $html;
        }
        if (isset($form['_mode'])) {
            unset($form['_mode']);
        }
        $tag = array();
        foreach ($form as $k => $v) {
            if (!is_array($v)) {
                $tag[] = sprintf(
                    '<input type="hidden" name="%s" value="%s" />',
                    $this->encodeHTML($k),
                    $this->encodeHTML($v)
                );
                continue;
            }
            foreach ($v as $subv) {
                $tag[] = sprintf(
                    '<input type="hidden" name="%s[]" value="%s" />',
                    $this->encodeHTML($k),
                    $this->encodeHTML($subv)
                );
            }
        }
        return str_replace('</form>', implode("\n", $tag) . '</form>', $html);
    }
}
