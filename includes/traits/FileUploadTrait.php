<?php
/**
 * FileUploadTrait - ファイルアップロード関連メソッド
 *
 * Class_cfFormMailer から分離したファイルアップロード処理
 * - 一時ファイル管理
 * - MIMEタイプ検出
 * - アップロードファイルのクリーンアップ
 *
 * @package cfFormMailer
 * @since 1.7.1
 */
trait FileUploadTrait
{
    /**
     * 画像ファイルの拡張子を取得
     *
     * @param string $tmp_name 一時ファイルパス
     * @return string 拡張子（ドット付き）
     */
    private function extension($tmp_name)
    {
        $info = @getimagesize($tmp_name);
        if ($info === false || !isset($info['mime'])) {
            return '';
        }
        return '.' . $this->_getType($info['mime']);
    }

    /**
     * cfFormMailer用の一時ファイルパスを生成
     *
     * @param string $tmp_name 元の一時ファイルパス
     * @return string cfFormMailer用の一時ファイルパス
     */
    private function cfm_upload_tmp_name($tmp_name)
    {
        $tmp_path = CFM_PATH . 'tmp';
        if (!is_dir($tmp_path) && !mkdir($tmp_path)) {
            exit('ディレクトリを生成できません');
        }
        if (!is_writable($tmp_path)) {
            exit('ディレクトリに書き込めません');
        }
        return $tmp_path . substr($tmp_name, strlen(dirname($tmp_name)));
    }

    /**
     * アップロードされた一時ファイルを削除
     *
     * @return void
     */
    public function cleanUploadedFiles()
    {
        $uploaded = sessionv('_cf_uploaded');
        if (empty($uploaded)) {
            return;
        }
        if (!is_array($uploaded)) {
            return;
        }
        foreach ($uploaded as $attach_file) {
            unlink($attach_file['path']);
        }
        unset($_SESSION['_cf_uploaded']);
    }

    /**
     * ファイルのMIMEタイプを取得
     * class.upload.php 使用推奨
     *
     * @param string  $filename MIMEタイプを調べるファイル
     * @param string  $field  アップロードされたフィールド名
     * @return string MIMEタイプ。失敗の場合は false
     */
    private function _getMimeType($filename, $field = '')
    {
        if (class_exists('upload')) {
            // class.upload.php使用
            $up = new upload($filename);
            if ($up->uploaded) {
                return $up->file_src_mime;
            }
            return false;
        }

        // class.upload.php未使用。image以外の結果はあまり信用できない
        $size = @getimagesize($filename);
        if ($size === false) {
            if (isset($_FILES[$field]['type']) && $_FILES[$field]['type']) {
                return $_FILES[$field]['type'];
            }
            return false;
        }

        return $size['mime'];
    }

    /**
     * MIMEタイプからファイルの識別子を取得
     *
     * @param string  MIMEタイプ
     * @return  string  タイプ
     */
    private function _getType($mime)
    {
        $mime_list = array(
            'image/gif'          => 'gif',
            'image/jpeg'         => 'jpg',
            'image/pjpeg'        => 'jpg',
            'image/png'          => 'png',
            'application/pdf'    => 'pdf',
            'text/plain'         => 'txt',
            'text/html'          => 'html',
            'application/msword' => 'word'
        );

        if (isset($mime_list[$mime])) {
            return $mime_list[$mime];
        }

        return '';
    }
}
