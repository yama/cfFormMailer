<?php
/**
 * cfFormMailer - TemplateMethodsTrait
 *
 * テンプレート処理メソッド群
 *
 * @author  Clefarray Factory
 * @link    https://www.clefarray-web.net/
 *
 * このトレイトは Class_cfFormMailer で使用されることを前提としています。
 * 以下のメソッドへの依存があります：
 *
 * @method void setError(string $mes)
 * @method mixed config(string $key, mixed $default = null)
 */

trait TemplateMethodsTrait
{
    /* ------------------------------------------------------------------ */
    /* テンプレート処理メソッド
    /* ------------------------------------------------------------------ */

    /**
     * テンプレートチャンクの読み込み
     *
     * @access private
     * @param  string $tpl_name チャンク名・またはリソースID
     * @return string 読み込んだデータ
     */
    private function loadTemplate($tpl_name)
    {
        $tpl_name = trim($tpl_name);
        if (preg_match('/^@FILE:.+/', $tpl_name)) {
            $list = array(
                CFM_PATH . trim(substr($tpl_name, 6)),
                MODX_BASE_PATH . trim(substr($tpl_name, 6))
            );
            foreach ($list as $path) {
                if (!is_file($path)) {
                    continue;
                }
                return $this->parseDocumentSource(
                    file_get_contents($path)
                );
            }
        } elseif (preg_match('/^[1-9][0-9]*$/', $tpl_name)) {
            $doc = $this->getDocument($tpl_name);
            if (isset($doc['content']) && $doc['content']) {
                return $this->parseDocumentSource($doc['content']);
            }
        } elseif ($content = evo()->getChunk($tpl_name)) {
            return $this->parseDocumentSource($content);
        }

        $error = 'tpl read error';
        if ($tpl_name) {
            $error .= sprintf(' (%s)', $tpl_name);
        }
        $this->setError($error);
        return false;
    }

    /**
     * ドキュメントソースをパース
     *
     * @param string $content
     * @return string
     */
    private function parseDocumentSource($content)
    {
        if (strpos($content, '[!') !== false) {
            $content = str_replace(array('[!', '!]'), array('[[', ']]'), $content);
        }
        return evo()->parseDocumentSource($content);
    }

    /**
     * ドキュメント取得
     *
     * @param int $docid
     * @return array|false
     */
    private function getDocument($docid)
    {
        $rs = db()->select(
            '*',
            '[+prefix+]site_content',
            'id=' . $docid,
            '',
            1
        );
        $doc = db()->getRow($rs);
        if (!$doc) {
            return false;
        }
        return $doc;
    }

    /**
     * プレースホルダを置換
     *
     * @access private
     * @param  string $text HTMLテキスト
     * @param  array  $params 置換するデータ
     *                         (プレースホルダ名) => (値)
     * @param  string $join 値が配列の場合に連結に使用する文字列
     * @return string プレースホルダが置換された文字列
     */
    private function replacePlaceHolder($text, $params, $join = '<br />')
    {
        if (!is_array($params) || !$text) {
            return false;
        }

        preg_match_all("/\[\+([^+|]+)(\|(.*?)(\((.+?)\))?)?\+]/is", $text, $match, PREG_SET_ORDER);
        if (!count($match)) {
            return $text;
        }

        $toFilter = true;
        //旧バージョン用
        if (isset(evo()->config['output_filter']) && evo()->config['output_filter'] == 0) {
            $toFilter = false;
        }

        if ($toFilter) evo()->loadExtension('PHx') or die('Could not load PHx class.');

        // 基本プレースホルダ
        $replaceKeys = array_keys($params);
        foreach ($match as $m) {
            if ($toFilter && strpos($m[1], ':') !== false) {
                $parts = explode(':', $m[1], 2);
                $m[1] = $parts[0];
                $modifiers = $parts[1] ?? false;
            } else {
                $modifiers = false;
            }

            if (!in_array($m[1], $replaceKeys)) {
                continue;
            }

            $val = $params[$m[1]] ?? '';
            if ($toFilter && $modifiers !== false) {
                if ($val === '&nbsp;') {
                    $val = '';
                }
                $val = evo()->filter->phxFilter($m[1], $val, $modifiers);
                if ($val === '') {
                    $val = '&nbsp;';
                }
            }
            // テキストフィルターの処理
            $fType = $m[3] ?? '';
            if (empty($fType)) {
                $text = str_replace(
                    $m[0],
                    is_array($val) ? implode($join, $val) : $val,
                    $text
                );
                continue;
            }
            if (is_callable(array($this, '_f_' . $fType))) {
                $funcName = '_f_' . $fType;
                $fParam = $m[5] ?? '';
                $text = str_replace(
                    $m[0],
                    $this->$funcName($val, $fParam),
                    $text
                );
                continue;
            }
            if (is_callable('_filter_' . $fType)) {
                $funcName = '_filter_' . $fType;
                $fParam = $m[5] ?? '';
                $text = str_replace(
                    $m[0],
                    $funcName($val, $fParam),
                    $text
                );
                continue;
            }
            $text = str_replace($m[0], '', $text); // フィルター無し
        }
        return $text;
    }

    /**
     * 全てのプレースホルダを削除
     *
     * @param string $text 対象となるHTML
     * @return string [+variable_name+]削除後のHTML
     */
    private function clearPlaceHolder($text)
    {
        return preg_replace("@\[\+.+?\+]@uism", '', $text);
    }
}
