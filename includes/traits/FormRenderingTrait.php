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

        // タグごとに処理
        foreach ($match as $tag) {
            $m_type = array();
            $m_name = array();
            $m_value = array();
            preg_match("/type=([\"'])(.+?)\\1/i", $tag[0], $m_type);
            preg_match("/name=([\"'])(.+?)\\1/i", $tag[0], $m_name);
            preg_match("/value=([\"'])(.*?)\\1/i", $tag[0], $m_value);

            if (!isset($m_name[2]) || !isset($m_type[2])) {
                continue;
            }

            $fieldName = str_replace('[]', '', $m_name[2]);
            // 復元処理しないタグ
            if ($fieldName === '_mode') {
                continue;
            }

            switch ($m_type[2]) {
                // 復元処理しないタグ
                case 'submit';
                case 'image';
                case 'file';
                case 'button';
                case 'reset';
                case 'hidden';
                    continue 2;
                case 'checkbox';
                case 'radio';
                    $fieldType = $m_type[2];
                    break;
                default:
                    $fieldType = 'text';
            }

            // テキストボックス
            if ($tag[1] === 'input' && $fieldType === 'text') {
                $fieldValue = $params[$fieldName] ?? '';
                if (count($m_value) > 1) {
                    $pat = $m_value[0];
                    $rep = 'value="' . $this->encodeHTML($fieldValue) . '"';
                } else {
                    $pat = $tag[2];
                    $rep = $tag[2] . ' value="' . $this->encodeHTML($fieldValue) . '"';
                }
                // チェックボックス
            } elseif ($tag[1] === 'input' && $fieldType === 'checkbox') {
                $fieldValue = $params[$fieldName] ?? null;
                if (isset($m_value[2]) && ($m_value[2] == $fieldValue || (is_array($fieldValue) && in_array($m_value[2], $fieldValue)))) {
                    $pat = $tag[2];
                    $rep = $tag[2] . ' checked="checked"';
                }
                // ラジオボタン
            } elseif ($tag[1] === 'input' && $fieldType === 'radio') {
                $fieldValue = $params[$fieldName] ?? null;
                if (isset($m_value[2]) && $m_value[2] == $fieldValue) {
                    $pat = $tag[2];
                    $rep = $tag[2] . ' checked="checked"';
                }
                // プルダウンリスト
            } elseif ($tag[1] === 'select') {
                $pat = '';
                $rep = '';
                $tag_opt = array();
                preg_match_all(
                    "/<option(.*?)value=(['\"])(.*?)\\2(.*?>)/uism",
                    $tag[4],
                    $tag_opt,
                    PREG_SET_ORDER
                );
                if (count($tag_opt) > 1) {
                    $old = $tag[0];
                    $fieldValue = $params[$fieldName] ?? null;
                    foreach ($tag_opt as $v) {
                        $tag[0] = str_replace(
                            $v[0],
                            preg_replace(
                                "/selected(=(['\"])selected\\2)?/uism",
                                '',
                                $v[0]
                            ),
                            $tag[0]
                        );
                        if ($v[3] == $fieldValue) {
                            $tag[0] = str_replace(
                                $v[0],
                                str_replace(
                                    $v[4],
                                    ' selected="selected"' . $v[4],
                                    $v[0]
                                ),
                                $tag[0]
                            );
                        }
                    }
                    $html = str_replace($old, $tag[0], $html);
                }
                // 複数行テキスト
            } elseif ($tag[1] === 'textarea') {
                $fieldValue = $params[$fieldName] ?? '';
                if ($fieldValue) {
                    $pat = $tag[0];
                    $rep = sprintf(
                        '<%s%s%s>%s</textarea>',
                        $tag[1],
                        $tag[2],
                        $tag[3],
                        $this->encodeHTML($fieldValue)
                    );
                }
            }

            // HTMLタグのみを置換
            if (!$rep || !$pat) {
                continue;
            }
            $tag_new = str_replace($pat, $rep, $tag[0]);
            // HTML全文を置換
            if (trim($tag_new) === '') {
                continue;
            }
            $html = str_replace($tag[0], $tag_new, $html);
        }
        return $html;
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
            if (empty($tag[1])) {
                // エラー全体の処理
                if (count($errors)) {
                    $html = str_replace($tag[0], $tag[2], $html);
                    // pr($html);exit;
                }
                continue;
            }
            // グルーピングされたタグの処理
            if (preg_match("/^\((.+?)\)$/", $tag[1], $g_match)) {
                $groups = explode(',', $g_match[1]);
                $isErr = 0;
                foreach ($groups as $group) {
                    $isErr = $errors['error.' . strtr($group, array(' ' => ''))] ? 1 : $isErr;
                }
                if ($isErr) {
                    $html = str_replace($tag[0], $tag[2], $html);
                }
                continue;
            }
            if (isset($errors['error.' . $tag[1]])) {
                $html = str_replace($tag[0], $tag[2], $html);
            }
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
