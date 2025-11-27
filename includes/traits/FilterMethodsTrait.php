<?php
/**
 * cfFormMailer - FilterMethodsTrait
 *
 * 標準装備のフィルターメソッド群
 *
 * @author  Clefarray Factory
 * @link    https://www.clefarray-web.net/
 *
 * このトレイトは Class_cfFormMailer で使用されることを前提としています。
 * プレースホルダのフィルター処理に使用されます。
 * 外部依存はありません（自己完結）。
 */

trait FilterMethodsTrait
{
    /* ------------------------------------------------------------------ */
    /* 標準装備のフィルターメソッド
    /* ------------------------------------------------------------------ */

    /**
     * implode : 文字列結合
     */
    private function _f_implode($text, $param)
    {
        if (is_array($text)) {
            return implode(str_replace("\\n", "\n", $param), $text);
        }

        return $text;
    }

    /**
     * implodetag(tag) : HTMLタグで文字列結合
     */
    private function _f_implodetag($text, $param)
    {

        if (!is_array($text)) {
            $text = array($text);
        }

        $ret = '';
        foreach ($text as $v) {
            $ret .= sprintf('<%s>%s</%s>', $param, $v, $param);
        }
        return $ret;
    }

    /**
     * num : 数値のフォーマット （※PHP関数 number_format() と同様）
     */
    private function _f_num($text, $param)
    {

        if (is_array($text)) {
            return array_map([$this, '_f_num'], $text, $param);
        }

        return number_format($text);
    }

    /**
     * dateformat(format) : 日付のフォーマット
     *
     * strftime形式（%Y-%m-%d等）とdate形式（Y-m-d等）の両方に対応
     */
    private function _f_dateformat($text, $param)
    {
        if (is_array($text)) {
            return array_map([$this, '_f_dateformat'], $text, $param);
        }

        $timestamp = strtotime($text);
        if ($timestamp === false) {
            return $text;
        }

        $datetime = (new DateTime())->setTimestamp($timestamp);

        // strftimeフォーマット（%で始まる）が含まれている場合は変換
        if (strpos($param, '%') !== false) {
            $format_map = [
                '%Y' => 'Y',  // 4桁の年
                '%y' => 'y',  // 2桁の年
                '%m' => 'm',  // 月（01-12）
                '%d' => 'd',  // 日（01-31）
                '%H' => 'H',  // 時（00-23）
                '%M' => 'i',  // 分（00-59）
                '%S' => 's',  // 秒（00-59）
                '%B' => 'F',  // 月の完全名
                '%b' => 'M',  // 月の短縮名
                '%A' => 'l',  // 曜日の完全名
                '%a' => 'D',  // 曜日の短縮名
                '%e' => 'j',  // 日（1-31）
                '%I' => 'h',  // 12時間制の時（01-12）
                '%p' => 'A',  // AM/PM
                '%w' => 'w',  // 曜日（0-6）
            ];
            $param = str_replace(array_keys($format_map), array_values($format_map), $param);
        }

        return $datetime->format($param);
    }

    /**
     * sprintf(format) : テキストのフォーマット （※PHP関数 sprintf() と同様）
     */
    private function _f_sprintf($text, $param)
    {

        if (is_array($text)) {
            return array_map([$this, '_f_sprintf'], $text, $param);
        }

        return sprintf($param, $text);
    }
}
