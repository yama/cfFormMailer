<?php
/**
 * cfFormMailer
 *
 * @author  Clefarray Factory
 * @link  http://www.clefarray-web.net/
 * @version 1.7.0
 *
 * Documentation: http://www.clefarray-web.net/blog/manual/cfFormMailer_manual.html
 * LICENSE: GNU General Public License (GPL) (http://www.gnu.org/copyleft/gpl.html)
 */

/** @var documentParser $modx */

// ========================================
// 1. 初期チェック
// ========================================
if ($modx->isBackend()) {
    return '';
}

if (!isset($config)) {
    return '<strong>ERROR!:</strong> 「config」パラメータは必須です';
}

// ========================================
// 2. 初期化
// ========================================
define('CFM_PATH', __DIR__ . '/');

include_once CFM_PATH . 'class.cfFormMailer.inc.php';

$mf = new Class_cfFormMailer($modx);
$mf->parseConfig($config);

if ($mf->hasSystemError()) {
    return '<strong>ERROR!</strong> ' . $mf->getSystemError();
}

// カスタムメソッドの読み込み
if (is_file(CFM_PATH . 'additionalMethods.inc.php')) {
    include_once CFM_PATH . 'additionalMethods.inc.php';
}

// ========================================
// 3. フォーム処理フロー
//    入力 → 確認 → 送信 → 完了
// ========================================
return handleFormFlow($mf);

/**
 * フォーム処理フローのハンドラ
 */
function handleFormFlow(Class_cfFormMailer $mf): string
{
    // 初回表示（POSTなし）
    if (!postv()) {
        return $mf->renderForm();
    }

    // 確認画面から戻る
    if (postv('return')) {
        return $mf->renderFormOnBack();
    }

    // 二重送信防止
    if ($mf->alreadySent()) {
        return $mf->raiseError('すでに送信しています');
    }

    // モード別処理
    $mode = postv('_mode');

    switch ($mode) {
        case 'conf':
            return handleConfirmMode($mf);

        case 'send':
            return handleSendMode($mf);

        default:
            return $mf->renderForm();
    }
}

/**
 * 確認モードの処理
 */
function handleConfirmMode(Class_cfFormMailer $mf): string
{
    if (!$mf->validate()) {
        return $mf->renderFormWithError();
    }
    return $mf->renderConfirm();
}

/**
 * 送信モードの処理
 */
function handleSendMode(Class_cfFormMailer $mf): string
{
    // CSRFトークン検証
    if (!$mf->isValidToken(postv('_cffm_token'))) {
        return $mf->raiseError('画面遷移が正常に行われませんでした');
    }

    // メール送信
    if (!$mf->sendMail()) {
        return $mf->raiseError($mf->getError());
    }

    // 後処理
    $mf->cleanUploadedFiles();
    $mf->storeDataInSession();
    $mf->storeDB();

    return $mf->renderComplete();
}
