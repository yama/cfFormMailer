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

        $toFilter = $this->isFilterEnabled();
        if ($toFilter) {
            evo()->loadExtension('PHx') or die('Could not load PHx class.');
        }

        $replaceKeys = array_keys($params);
        foreach ($match as $m) {
            $text = $this->processPlaceholderMatch($text, $m, $params, $replaceKeys, $toFilter, $join);
        }
        return $text;
    }

    /**
     * PHxフィルターが有効かどうかを判定
     *
     * @return bool
     */
    private function isFilterEnabled()
    {
        if (isset(evo()->config['output_filter']) && evo()->config['output_filter'] == 0) {
            return false;
        }
        return true;
    }

    /**
     * 個別のプレースホルダマッチを処理
     *
     * @param string $text テキスト
     * @param array $m マッチ結果
     * @param array $params パラメータ
     * @param array $replaceKeys 置換対象キー一覧
     * @param bool $toFilter フィルター有効フラグ
     * @param string $join 配列連結文字列
     * @return string 処理後のテキスト
     */
    private function processPlaceholderMatch($text, $m, $params, $replaceKeys, $toFilter, $join)
    {
        // PHxモディファイア抽出
        $modifiers = false;
        $placeholderName = $m[1];
        if ($toFilter && strpos($m[1], ':') !== false) {
            $parts = explode(':', $m[1], 2);
            $placeholderName = $parts[0];
            $modifiers = $parts[1] ?? false;
        }

        if (!in_array($placeholderName, $replaceKeys)) {
            return $text;
        }

        $val = $params[$placeholderName] ?? '';

        // PHxモディファイア適用
        if ($toFilter && $modifiers !== false) {
            $val = $this->applyPhxModifiers($placeholderName, $val, $modifiers);
        }

        // テキストフィルター適用
        $filterType = $m[3] ?? '';
        $filterParam = $m[5] ?? '';
        $replacement = $this->applyTextFilter($val, $filterType, $filterParam, $join);

        return str_replace($m[0], $replacement, $text);
    }

    /**
     * PHxモディファイアを適用
     *
     * @param string $name プレースホルダ名
     * @param mixed $val 値
     * @param string $modifiers モディファイア文字列
     * @return mixed 処理後の値
     */
    private function applyPhxModifiers($name, $val, $modifiers)
    {
        if ($val === '&nbsp;') {
            $val = '';
        }
        $val = evo()->filter->phxFilter($name, $val, $modifiers);
        if ($val === '') {
            $val = '&nbsp;';
        }
        return $val;
    }

    /**
     * テキストフィルターを適用
     *
     * @param mixed $val 値
     * @param string $filterType フィルタータイプ
     * @param string $filterParam フィルターパラメータ
     * @param string $join 配列連結文字列
     * @return string 処理後の値
     */
    private function applyTextFilter($val, $filterType, $filterParam, $join)
    {
        // フィルタータイプが空の場合は値をそのまま返す
        if (empty($filterType)) {
            return is_array($val) ? implode($join, $val) : $val;
        }

        // クラス内フィルターメソッド (_f_*)
        $methodName = '_f_' . $filterType;
        if (is_callable(array($this, $methodName))) {
            return $this->$methodName($val, $filterParam);
        }

        // グローバルフィルター関数 (_filter_*)
        $funcName = '_filter_' . $filterType;
        if (is_callable($funcName)) {
            return $funcName($val, $filterParam);
        }

        // フィルターが見つからない場合は空文字
        return '';
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
