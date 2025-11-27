<?php
/**
 * IDE Helper for cfFormMailer
 *
 * このファイルはIDEの静的解析を補助するためのスタブファイルです。
 * 実際の実行時には使用されません。
 *
 * MODX Evolution CMS のヘルパー関数を定義しています。
 * @see https://github.com/modxcms-jp/evolution-jp/blob/php8/manager/includes/helpers.php
 *
 * @author  Clefarray Factory / yama
 * @link    http://www.clefarray-web.net/
 * @version 1.7
 * @license GPL (http://www.gnu.org/copyleft/gpl.html)
 */

// このファイルは実行されないようにする
if (defined('MODX_BASE_PATH')) {
    return;
}

// IDE用の定数定義
define('MODX_BASE_PATH', dirname(__FILE__, 3) . '/');
define('MODX_CORE_PATH', MODX_BASE_PATH . 'manager/includes/');
define('MODX_MANAGER_PATH', MODX_BASE_PATH . 'manager/');
define('MODX_MANAGER_URL', '/manager/');
define('MODX_BASE_URL', '/');

// ============================================================================
// MODX Evolution Core Classes (スタブ定義)
// ============================================================================

/**
 * MODX Evolution Document Parser (メインクラス)
 *
 * @property DBAPI $db データベースAPI
 * @property ManagerAPI $manager マネージャーAPI
 * @property MODxMailer $mail メール送信クラス
 * @property SystemEvent $event イベントオブジェクト
 * @property PHx $filter フィルタークラス
 * @property array $config 設定配列
 * @property int $documentIdentifier 現在のドキュメントID
 * @property array $documentObject 現在のドキュメントオブジェクト
 */
class DocumentParser
{
    /** @var DBAPI */
    public $db;

    /** @var ManagerAPI */
    public $manager;

    /** @var MODxMailer */
    public $mail;

    /** @var SystemEvent */
    public $event;

    /** @var PHx */
    public $filter;

    /** @var array */
    public $config = [];

    /** @var int */
    public $documentIdentifier;

    /** @var array */
    public $documentObject;

    /**
     * 設定値を取得
     *
     * @param string $key 設定キー
     * @param mixed $default デフォルト値
     * @return mixed
     */
    public function config($key, $default = null) {}

    /**
     * 権限チェック
     *
     * @param string|null $key 権限キー
     * @return bool
     */
    public function hasPermission($key = null) {}

    /**
     * テンプレート変数を解析
     *
     * @param string $tpl テンプレート
     * @param array $ph プレースホルダー配列
     * @param string $left 左デリミタ
     * @param string $right 右デリミタ
     * @param bool $execModifier モディファイア実行フラグ
     * @return string
     */
    public function parseText($tpl, $ph, $left = '[+', $right = '+]', $execModifier = false) {}

    /**
     * ドキュメントソースを解析
     *
     * @param string $content コンテンツ
     * @return string
     */
    public function parseDocumentSource($content) {}

    /**
     * チャンクを取得
     *
     * @param string $name チャンク名
     * @return string|false
     */
    public function getChunk($name) {}

    /**
     * スニペットを実行
     *
     * @param string $name スニペット名
     * @param array $params パラメータ
     * @return mixed
     */
    public function runSnippet($name, $params = []) {}

    /**
     * リダイレクト
     *
     * @param string $url リダイレクト先URL
     * @param int $count_attempts リトライ回数
     * @param string $type リダイレクトタイプ
     * @param string $responseCode HTTPレスポンスコード
     * @return void
     */
    public function sendRedirect($url, $count_attempts = 0, $type = '', $responseCode = '') {}

    /**
     * URLを生成
     *
     * @param int $id ドキュメントID
     * @param string $alias エイリアス
     * @param string $args クエリ文字列
     * @param string $scheme スキーム
     * @return string
     */
    public function makeUrl($id, $alias = '', $args = '', $scheme = '') {}

    /**
     * ドキュメントオブジェクトを取得
     *
     * @param string $method 取得方法 ('id' or 'alias')
     * @param mixed $identifier 識別子
     * @param string $active アクティブ状態
     * @return array|false
     */
    public function getDocumentObject($method, $identifier, $active = 'all') {}

    /**
     * 親ドキュメントIDを取得
     *
     * @param int $docid ドキュメントID
     * @return int
     */
    public function getParentID($docid) {}

    /**
     * 最上位親ドキュメントIDを取得
     *
     * @param int $docid ドキュメントID
     * @param int $top 起点レベル
     * @return int
     */
    public function getUltimateParentId($docid, $top = 0) {}

    /**
     * HTMLタグを生成
     *
     * @param string $tag_name タグ名
     * @param array $attrib 属性配列
     * @param string|null $content コンテンツ
     * @return string
     */
    public function html_tag($tag_name, $attrib = [], $content = null) {}

    /**
     * HTML特殊文字をエスケープ
     *
     * @param string $string 文字列
     * @param int $flags フラグ
     * @return string
     */
    public function htmlspecialchars($string, $flags = ENT_QUOTES) {}

    /**
     * 拡張機能をロード
     *
     * @param string $name 拡張名
     * @return bool
     */
    public function loadExtension($name) {}

    /**
     * ファイルをアップロード
     *
     * @param string $tmp_name 一時ファイル名
     * @param string $destination 保存先パス
     * @return bool
     */
    public function move_uploaded_file($tmp_name, $destination) {}

    /**
     * プレースホルダーを設定
     *
     * @param string $key キー
     * @param mixed $value 値
     * @return void
     */
    public function setPlaceholder($key, $value) {}

    /**
     * プレースホルダーを取得
     *
     * @param string $key キー
     * @return mixed
     */
    public function getPlaceholder($key) {}

    /**
     * フィルターを適用
     *
     * @param mixed $value 値
     * @param string $key キー
     * @param string $modifiers モディファイア文字列
     * @return mixed
     */
    public function applyFilter($value, $key, $modifiers) {}

    /**
     * mb_strftime互換関数
     *
     * @param string $format フォーマット
     * @param int $timestamp タイムスタンプ
     * @return string
     */
    public function mb_strftime($format, $timestamp) {}

    /**
     * 継承ドキュメントIDを取得
     *
     * @param string $key キー
     * @param int $docid ドキュメントID
     * @return int
     */
    public function inheritDocId($key, $docid) {}

    /**
     * フロントエンドかどうか判定
     *
     * @return bool
     */
    public function isFrontEnd() {}

    /**
     * イベントをログに記録
     *
     * @param int $evtid イベントID
     * @param int $type タイプ (1=情報, 2=警告, 3=エラー)
     * @param string $msg メッセージ
     * @param string $source ソース
     * @return void
     */
    public function logEvent($evtid, $type, $msg, $source = '') {}
}

/**
 * MODX Database API
 *
 * @property array $config データベース設定配列
 */
class DBAPI
{
    /** @var array */
    public $config = [];

    /**
     * クエリを実行
     *
     * @param string $sql SQL文
     * @return mixed
     */
    public function query($sql) {}

    /**
     * レコードを取得
     *
     * @param mixed $result クエリ結果
     * @param string $mode 取得モード
     * @return array|false
     */
    public function getRow($result, $mode = 'assoc') {}

    /**
     * 全レコードを配列で取得
     *
     * @param mixed $result クエリ結果
     * @return array
     */
    public function makeArray($result) {}

    /**
     * 文字列をエスケープ
     *
     * @param string $string 文字列
     * @return string
     */
    public function escape($string) {}

    /**
     * SELECTクエリを実行
     *
     * @param string|array $fields フィールド
     * @param string $from テーブル名
     * @param string $where WHERE句
     * @param string $orderby ORDER BY句
     * @param string $limit LIMIT句
     * @return mixed
     */
    public function select($fields, $from, $where = '', $orderby = '', $limit = '') {}

    /**
     * INSERTを実行
     *
     * @param array $fields フィールドと値の配列
     * @param string $table テーブル名
     * @return mixed
     */
    public function insert($fields, $table) {}

    /**
     * UPDATEを実行
     *
     * @param array $fields フィールドと値の配列
     * @param string $table テーブル名
     * @param string $where WHERE句
     * @return mixed
     */
    public function update($fields, $table, $where = '') {}

    /**
     * DELETEを実行
     *
     * @param string $table テーブル名
     * @param string $where WHERE句
     * @return mixed
     */
    public function delete($table, $where) {}

    /**
     * 最後に挿入したIDを取得
     *
     * @return int
     */
    public function getInsertId() {}

    /**
     * 影響を受けた行数を取得
     *
     * @return int
     */
    public function getAffectedRows() {}

    /**
     * レコード数を取得
     *
     * @param mixed $result クエリ結果
     * @return int
     */
    public function getRecordCount($result) {}

    /**
     * 単一値を取得
     *
     * @param string $sql SQL文
     * @return mixed
     */
    public function getValue($sql) {}
}

/**
 * MODX Manager API
 */
class ManagerAPI
{
    /**
     * アラートを表示
     *
     * @param string $message メッセージ
     * @return void
     */
    public function alert($message) {}
}

/**
 * MODX System Event
 *
 * @property string $name イベント名
 * @property array $params イベントパラメータ
 */
class SystemEvent
{
    /** @var string */
    public $name;

    /** @var array */
    public $params = [];

    /**
     * 出力を設定
     *
     * @param string $output 出力内容
     * @return void
     */
    public function output($output) {}

    /**
     * 出力を取得
     *
     * @return string
     */
    public function getOutput() {}
}

/**
 * MODx Mailer (PHPMailerベース)
 */
class MODxMailer
{
    /** @var string */
    public $From;

    /** @var string */
    public $FromName;

    /** @var string */
    public $Sender;

    /** @var string */
    public $ErrorInfo;

    /** @var string */
    public $Subject;

    /** @var string */
    public $Body;

    /** @var string */
    public $CharSet = 'UTF-8';

    /** @var string */
    public $Encoding = 'base64';

    /** @var bool */
    public $isHTML;

    /**
     * 宛先を追加
     *
     * @param string $address メールアドレス
     * @param string $name 名前
     * @return bool
     */
    public function AddAddress($address, $name = '') {}

    /**
     * CCを追加
     *
     * @param string $address メールアドレス
     * @param string $name 名前
     * @return bool
     */
    public function AddCC($address, $name = '') {}

    /**
     * BCCを追加
     *
     * @param string $address メールアドレス
     * @param string $name 名前
     * @return bool
     */
    public function AddBCC($address, $name = '') {}

    /**
     * 返信先を追加
     *
     * @param string $address メールアドレス
     * @param string $name 名前
     * @return bool
     */
    public function AddReplyTo($address, $name = '') {}

    /**
     * 添付ファイルを追加
     *
     * @param string $path ファイルパス
     * @param string $name ファイル名
     * @param string $encoding エンコーディング
     * @param string $type MIMEタイプ
     * @return bool
     */
    public function AddAttachment($path, $name = '', $encoding = 'base64', $type = '') {}

    /**
     * メールを送信
     *
     * @return bool
     */
    public function Send() {}

    /**
     * 全設定をクリア
     *
     * @return void
     */
    public function ClearAllRecipients() {}

    /**
     * 添付ファイルをクリア
     *
     * @return void
     */
    public function ClearAttachments() {}

    /**
     * HTMLモードを設定
     *
     * @param bool $isHtml HTMLモードかどうか
     * @return void
     */
    public function IsHTML($isHtml = true) {}

    /**
     * エラーメッセージを取得
     *
     * @return string
     */
    public function getError() {}

    /**
     * 送信元を設定
     *
     * @param string $address メールアドレス
     * @param string $name 名前
     * @param bool $auto 自動設定フラグ
     * @return bool
     */
    public function setFrom($address, $name = '', $auto = true) {}
}

/**
 * PHx Filter Class
 */
class PHx
{
    /**
     * PHxフィルターを適用
     *
     * @param string $key キー
     * @param mixed $value 値
     * @param string $modifiers モディファイア
     * @return mixed
     */
    public function phxFilter($key, $value, $modifiers) {}
}

/**
 * Error Handler Class
 */
class errorHandler
{
    /**
     * エラーを追加
     *
     * @param string $message エラーメッセージ
     * @param string $type エラータイプ
     * @return void
     */
    public function add($message, $type = '') {}

    /**
     * エラーがあるか確認
     *
     * @return bool
     */
    public function hasError() {}

    /**
     * エラーを取得
     *
     * @return array
     */
    public function getErrors() {}
}

// ============================================================================
// MODX Evolution Helper Functions
// @see https://github.com/modxcms-jp/evolution-jp/blob/php8/manager/includes/helpers.php
// ============================================================================

/**
 * MODX Evolutionインスタンスを取得
 *
 * @return DocumentParser|null
 */
function evo() {}

/**
 * データベースAPIを取得
 *
 * @return DBAPI
 */
function db() {}

/**
 * マネージャーAPIを取得
 *
 * @return ManagerAPI
 */
function manager() {}

/**
 * 権限をチェック
 *
 * @param string|null $key 権限キー
 * @return bool
 */
function hasPermission($key = null) {}

/**
 * 設定値を取得
 *
 * @param string $key 設定キー
 * @param mixed $default デフォルト値
 * @return mixed
 */
function config($key, $default = null) {}

/**
 * 現在のドキュメントIDを取得
 *
 * @return int
 */
function docid() {}

/**
 * ベースパスを取得
 *
 * @return string
 */
function base_path() {}

/**
 * 言語文字列を取得
 *
 * @param string $key 言語キー
 * @param string|null $default デフォルト値
 * @return string
 */
function lang($key, $default = null) {}

/**
 * スタイル値を取得
 *
 * @param string $key スタイルキー
 * @return mixed
 */
function style($key) {}

/**
 * HTML特殊文字をエスケープ
 *
 * @param string|array|object $string 文字列
 * @param int $flags フラグ
 * @param string|null $encode エンコーディング
 * @param bool $double_encode 二重エンコードフラグ
 * @return string|array|object
 */
function hsc($string = '', $flags = ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401, $encode = null, $double_encode = true) {}

/**
 * テンプレートテキストを解析
 *
 * @param string $tpl テンプレート
 * @param array $ph プレースホルダー配列
 * @param string $left 左デリミタ
 * @param string $right 右デリミタ
 * @param bool $execModifier モディファイア実行フラグ
 * @return string
 */
function parseText($tpl, $ph, $left = '[+', $right = '+]', $execModifier = false) {}

/**
 * HTMLタグを生成
 *
 * @param string $tag_name タグ名
 * @param array $attrib 属性配列
 * @param string|null $content コンテンツ
 * @return string
 */
function html_tag($tag_name, $attrib = [], $content = null) {}

/**
 * inputテキストタグを生成
 *
 * @param array $props プロパティ配列
 * @return string
 */
function input_text_tag($props = []) {}

/**
 * textareaタグを生成
 *
 * @param array $props プロパティ配列
 * @param string $content コンテンツ
 * @return string
 */
function textarea_tag($props = [], $content = '') {}

/**
 * selectタグを生成
 *
 * @param array $props プロパティ配列
 * @param string|array $options オプション
 * @return string
 */
function select_tag($props = [], $options = '') {}

/**
 * imgタグを生成
 *
 * @param string $src ソースURL
 * @param array $props プロパティ配列
 * @return string
 */
function img_tag($src, $props = []) {}

/**
 * アラートハンドラを取得
 *
 * @return errorHandler
 */
function alert() {}

/**
 * 配列から値を取得（ドット記法対応）
 *
 * @param array $array 配列
 * @param string|null $key キー（ドット記法可）
 * @param mixed $default デフォルト値
 * @param callable|null $validate 検証関数
 * @return mixed
 */
function array_get($array, $key = null, $default = null, $validate = null) {}

/**
 * 配列に値を設定（ドット記法対応）
 *
 * @param array &$array 配列
 * @param string|null $key キー（ドット記法可）
 * @param mixed $value 値
 * @return mixed
 */
function array_set(&$array, $key, $value) {}

/**
 * リクエストから整数値を取得
 *
 * @param string $key キー
 * @return int
 */
function request_intvar($key) {}

/**
 * イベントオブジェクトを取得
 *
 * @return SystemEvent
 */
function event() {}

/**
 * 親ドキュメントIDを取得
 *
 * @param int|null $docid ドキュメントID
 * @return int
 */
function parent($docid) {}

/**
 * 最上位親ドキュメントIDを取得
 *
 * @param int|null $docid ドキュメントID
 * @param int $top 起点レベル
 * @return int
 */
function uparent($docid = null, $top = 0) {}

/**
 * 拡張sprintfフォーマット
 *
 * @param string $format フォーマット文字列
 * @param mixed ...$args 引数
 * @return string
 */
function exprintf() {}

/**
 * GETパラメータを取得
 *
 * @param string|null $key キー
 * @param mixed $default デフォルト値
 * @return mixed
 */
function getv($key = null, $default = null) {}

/**
 * POSTパラメータを取得
 *
 * @param string|null $key キー
 * @param mixed $default デフォルト値
 * @return mixed
 */
function postv($key = null, $default = null) {}

/**
 * REQUEST（GET/POST）パラメータを取得
 *
 * @param string|null $key キー
 * @param mixed $default デフォルト値
 * @return mixed
 */
function anyv($key = null, $default = null) {}

/**
 * SERVERパラメータを取得
 *
 * @param string|null $key キー
 * @param mixed $default デフォルト値
 * @return mixed
 */
function serverv($key = null, $default = null) {}

/**
 * POSTリクエストかどうか判定
 *
 * @return bool
 */
function is_post() {}

/**
 * GETリクエストかどうか判定
 *
 * @return bool
 */
function is_get() {}

/**
 * SESSIONパラメータを取得・設定
 *
 * キーが '*' で始まる場合は値を設定
 * 例: sessionv('*key', $value) で設定
 *     sessionv('key') で取得
 *
 * @param string|null $key キー（'*'プレフィックスで設定モード）
 * @param mixed $default デフォルト値（設定モードでは設定する値）
 * @return mixed
 */
function sessionv($key = null, $default = null) {}

/**
 * グローバル変数を取得・設定
 *
 * キーが '*' で始まる場合は値を設定
 * 例: globalv('*key', $value) で設定
 *     globalv('key') で取得
 *
 * @param string|null $key キー（'*'プレフィックスで設定モード）
 * @param mixed $default デフォルト値（設定モードでは設定する値）
 * @return mixed
 */
function globalv($key = null, $default = null) {}

/**
 * COOKIEパラメータを取得
 *
 * @param string|null $key キー
 * @param mixed $default デフォルト値
 * @return mixed
 */
function cookiev($key = null, $default = null) {}

/**
 * FILESパラメータを取得
 *
 * @param string|null $key キー
 * @param mixed $default デフォルト値
 * @return mixed
 */
function filev($key = null, $default = null) {}

/**
 * デバッグ出力（print_r）
 *
 * @param mixed $content 出力内容
 * @return void
 */
function pr($content) {}

/**
 * ドキュメント情報を取得
 *
 * @param string $key キー
 * @param mixed $default デフォルト値
 * @return mixed
 */
function doc($key, $default = '') {}

/**
 * ファイルをincludeして出力をキャプチャ
 *
 * @param string $path ファイルパス
 * @return string|false
 */
function ob_get_include($path) {}

/**
 * 簡易ハッシュを生成
 *
 * @param string $seed シード値
 * @return string
 */
function easy_hash($seed) {}

/**
 * デバイスタイプを判定
 *
 * @return string 'pc', 'tablet', 'smartphone', 'mobile', 'bot'
 */
function device() {}

/**
 * 日時をフォーマット
 *
 * @param string $format フォーマット
 * @param string|int $timestamp タイムスタンプ
 * @param string $default デフォルト値
 * @return string
 */
function datetime_format($format, $timestamp = '', $default = '') {}

/**
 * リクエストURIを取得
 *
 * @return string
 */
function request_uri() {}

/**
 * リクエスト開始時刻を取得
 *
 * @return int
 */
function request_time() {}

/**
 * 実IPアドレスを取得
 *
 * @return string
 */
function real_ip() {}

/**
 * ユーザーエージェントを取得
 *
 * @return string
 */
function user_agent() {}

/**
 * HTMLタグを除去
 *
 * @param string $value 対象文字列
 * @param string $params 許可するタグ
 * @return string
 */
function remove_tags($value, $params = '') {}

/**
 * マネージャースタイル画像パスを取得
 *
 * @param string $subdir サブディレクトリ
 * @return string
 */
function manager_style_image_path($subdir = '') {}

/**
 * マネージャースタイル画像URLを取得
 *
 * @param string $subdir サブディレクトリ
 * @return string
 */
function manager_style_image_url($subdir = '') {}

/**
 * マネージャースタイルプレースホルダーを取得
 *
 * @return array
 */
function manager_style_placeholders() {}

/**
 * マネージャースタイルプレースホルダーを設定
 *
 * @return void
 */
function set_manager_style_placeholders() {}

/**
 * 環境変数を取得
 *
 * @param string $key 環境変数キー
 * @param mixed $default デフォルト値
 * @return mixed
 */
function env(string $key, $default = null) {}

// ============================================================================
// External Libraries (外部ライブラリのスタブ定義)
// ============================================================================

/**
 * class.upload.php - ファイルアップロード処理クラス
 *
 * @see https://www.verot.net/php_class_upload.htm
 *
 * @property bool $uploaded アップロード成功フラグ
 * @property string $file_src_mime ソースファイルのMIMEタイプ
 * @property string $file_src_name ソースファイル名
 * @property string $file_src_name_ext ソースファイル拡張子
 * @property int $file_src_size ソースファイルサイズ
 * @property string $file_dst_path 保存先パス
 * @property string $file_dst_name 保存先ファイル名
 * @property bool $processed 処理成功フラグ
 * @property string $error エラーメッセージ
 * @property string $log ログメッセージ
 */
class upload
{
    /** @var bool */
    public $uploaded;

    /** @var string */
    public $file_src_mime;

    /** @var string */
    public $file_src_name;

    /** @var string */
    public $file_src_name_ext;

    /** @var int */
    public $file_src_size;

    /** @var string */
    public $file_dst_path;

    /** @var string */
    public $file_dst_name;

    /** @var bool */
    public $processed;

    /** @var string */
    public $error;

    /** @var string */
    public $log;

    /**
     * コンストラクタ
     *
     * @param string|array $file ファイルパスまたは$_FILES配列
     */
    public function __construct($file) {}

    /**
     * ファイルを処理・保存
     *
     * @param string $destination 保存先ディレクトリ
     * @return bool
     */
    public function process($destination) {}

    /**
     * 一時ファイルを削除
     *
     * @return void
     */
    public function clean() {}
}
