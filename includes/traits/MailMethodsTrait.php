<?php
/**
 * cfFormMailer - MailMethodsTrait
 *
 * メール送信処理メソッド群
 *
 * @author  Clefarray Factory
 * @link    https://www.clefarray-web.net/
 *
 * このトレイトは Class_cfFormMailer で使用されることを前提としています。
 * 以下のプロパティ・メソッドへの依存があります：
 *
 * @property array $form POSTデータ
 *
 * @method mixed config(string $key, mixed $default = null)
 * @method void setError(string $mes)
 * @method void debugLog(string $message, int $type = 1)
 * @method void logEvent(int $evtid, int $type, string $msg, string $title = 'Info')
 * @method bool _isValidEmail(string $addr)
 * @method string getAutoReplyAddress()
 * @method string|false loadTemplate(string $tpl_name)
 * @method string replacePlaceHolder(string $text, array $params, string $join = '<br />')
 * @method string clearPlaceHolder(string $text)
 * @method string|array encodeHTML(mixed $text, bool $nl2br = false)
 * @method array nl2br_array(array $text)
 */

trait MailMethodsTrait
{
    /* ------------------------------------------------------------------ */
    /* メール送信処理メソッド
    /* ------------------------------------------------------------------ */

    /**
     * 管理者宛メール送信
     *
     * @return bool
     */
    private function sendAdminMail()
    {
        $this->debugLog('管理者宛メール送信処理を開始');

        // テンプレート読み込み
        $tmpl = $this->loadTemplate($this->config('tmpl_mail_admin'));
        if (!$tmpl) {
            $this->setError('メールテンプレートの読み込みに失敗しました');
            return false;
        }

        // メーラー初期化
        evo()->loadExtension('MODxMailer');
        $pm = evo()->mail;

        // 宛先設定
        $admin_addresses = $this->makeAdminAddress();
        $admin_address = $admin_addresses[0];
        $this->setAdminMailRecipients($pm, $admin_addresses);

        // 送信者設定
        $reply_to = $this->getAutoReplyAddress();
        $this->setAdminMailSender($pm, $admin_address, $reply_to);

        // 本文設定
        $pm->Body = $this->makeBody($tmpl, $this->makePh($this->form, $admin_address));
        $pm->Encoding = '7bit';

        // 添付ファイル
        $hasAttachment = $this->attachUploadedFiles($pm);

        // デバッグログ
        $this->logAdminMailDebugInfo($pm, $admin_addresses, $reply_to, $hasAttachment);

        // 送信
        return $this->executeMailSend($pm, 'Admin Mail Error', '管理者宛メール');
    }

    /**
     * 管理者メールの宛先を設定
     *
     * @param object $pm PHPMailerインスタンス
     * @param array $adminAddresses 管理者アドレス配列
     */
    private function setAdminMailRecipients($pm, $adminAddresses)
    {
        foreach ($adminAddresses as $v) {
            $pm->AddAddress($v);
        }

        // CC
        if ($this->config('admin_mail_cc')) {
            foreach (explode(',', $this->config('admin_mail_cc')) as $v) {
                $v = trim($v);
                if ($this->_isValidEmail($v)) {
                    $pm->AddCC($v);
                }
            }
        }

        // BCC
        if ($this->config('admin_mail_bcc')) {
            foreach (explode(',', $this->config('admin_mail_bcc')) as $v) {
                $v = trim($v);
                if ($this->_isValidEmail($v)) {
                    $pm->AddBCC($v);
                }
            }
        }
    }

    /**
     * 管理者メールの送信者情報を設定
     *
     * @param object $pm PHPMailerインスタンス
     * @param string $adminAddress 管理者アドレス
     * @param string $replyTo 返信先アドレス
     */
    private function setAdminMailSender($pm, $adminAddress, $replyTo)
    {
        $pm->Subject = $this->config('admin_subject')
            ? evo()->parseText($this->config('admin_subject'), $this->form)
            : 'サイトから送信されたメール';

        $pm->setFrom(
            $adminAddress,
            $this->config('admin_name')
                ? evo()->parseText($this->config('admin_name'), $this->form)
                : ''
        );

        if ($replyTo) {
            $pm->addReplyTo($replyTo);
        }

        $pm->Sender = $pm->From;
    }

    /**
     * アップロードファイルを添付
     *
     * @param object $pm PHPMailerインスタンス
     * @return bool 添付ファイルがあればtrue
     */
    private function attachUploadedFiles($pm)
    {
        $uploaded = sessionv('_cf_uploaded');
        if (!is_array($uploaded) || count($uploaded) === 0) {
            return false;
        }

        $hasAttachment = false;
        foreach ($uploaded as $attach_file) {
            if (!is_file($attach_file['path'])) {
                continue;
            }
            $pm->AddAttachment(
                $attach_file['path'],
                mb_convert_encoding(
                    urldecode(basename($attach_file['path'])),
                    $this->config('mail_charset'),
                    $this->config('charset')
                )
            );
            $hasAttachment = true;
        }

        return $hasAttachment;
    }

    /**
     * 管理者メールのデバッグ情報をログ出力
     *
     * @param object $pm PHPMailerインスタンス
     * @param array $adminAddresses 管理者アドレス配列
     * @param string $replyTo 返信先アドレス
     * @param bool $hasAttachment 添付ファイルの有無
     */
    private function logAdminMailDebugInfo($pm, $adminAddresses, $replyTo, $hasAttachment)
    {
        if (!$this->config('debug_mode')) {
            return;
        }

        $debug_info = "管理者宛メール送信を試行\n";
        $debug_info .= "宛先: " . implode(', ', $adminAddresses) . "\n";
        if ($this->config('admin_mail_cc')) {
            $debug_info .= "CC: " . $this->config('admin_mail_cc') . "\n";
        }
        if ($this->config('admin_mail_bcc')) {
            $debug_info .= "BCC: " . $this->config('admin_mail_bcc') . "\n";
        }
        $debug_info .= "件名: " . $pm->Subject . "\n";
        $debug_info .= "送信元: " . $pm->From . "\n";
        if ($replyTo) {
            $debug_info .= "Reply-To: " . $replyTo . "\n";
        }
        $debug_info .= "文字コード: " . ($this->config('mail_charset') ?: 'iso-2022-jp') . "\n";
        $debug_info .= "HTMLメール: " . ($this->config('admin_ishtml') ? '有効' : '無効') . "\n";
        if ($hasAttachment) {
            $debug_info .= "添付ファイル: あり\n";
        }
        $body_preview = mb_substr(strip_tags($pm->Body), 0, 100);
        $debug_info .= "本文プレビュー: " . $body_preview . "...";
        $this->debugLog($debug_info);
    }

    /**
     * メール送信を実行
     *
     * @param object $pm PHPMailerインスタンス
     * @param string $errorTitle エラー時のログタイトル
     * @param string $mailType メールの種類（ログ用）
     * @return bool 成功時true
     */
    private function executeMailSend($pm, $errorTitle, $mailType)
    {
        $sent = $pm->Send();
        if (!$sent) {
            $errormsg = 'メール送信に失敗しました::' . $pm->ErrorInfo;
            $this->setError($errormsg);
            $vars = evo()->htmlspecialchars(var_export($pm, true));
            $this->logEvent(1, 3, $errormsg . "\n" . $vars, $errorTitle);
            return false;
        }

        $this->debugLog($mailType . '送信に成功しました');
        return true;
    }

    /**
     * 自動返信メール送信
     *
     * @return bool
     */
    private function sendAutoReply()
    {
        $reply_to = $this->getAutoReplyAddress();

        // 自動返信が無効または返信先がない場合はスキップ
        if (!$this->config('auto_reply') || !$reply_to) {
            $this->debugLog('自動返信メールは無効、またはReply-Toアドレスが取得できませんでした');
            return true;
        }

        $this->debugLog('自動返信メール送信処理を開始');

        // テンプレート読み込み
        $templateFile = $this->getAutoReplyTemplate($reply_to);
        $tmpl = $this->loadTemplate($templateFile);
        if (!$tmpl) {
            $this->setError('メールテンプレートの読み込みに失敗しました');
            return false;
        }

        // メーラー初期化
        evo()->loadExtension('MODxMailer');
        $pm = evo()->mail;

        // 宛先・送信者設定
        $admin_addresses = $this->makeAdminAddress();
        $admin_address = $admin_addresses[0];
        $this->setAutoReplySender($pm, $reply_to, $admin_address);

        // 本文設定
        $pm->Sender = $pm->From;
        $pm->Body = $this->makeBody($tmpl, $this->makePh($this->form, $admin_address));
        $pm->Encoding = '7bit';

        // 添付ファイル
        $hasConfiguredAttachment = $this->attachConfiguredFile($pm);
        $hasUploadedAttachment = $this->attachUploadedFiles($pm);

        // デバッグログ
        $this->logAutoReplyDebugInfo($pm, $reply_to, $templateFile, $hasConfiguredAttachment || $hasUploadedAttachment);

        // 送信
        return $this->executeMailSend($pm, 'AutoReply Error', '自動返信メール');
    }

    /**
     * 自動返信用テンプレートを取得（モバイル対応）
     *
     * @param string $replyTo 返信先アドレス
     * @return string テンプレート名
     */
    private function getAutoReplyTemplate($replyTo)
    {
        $mobilePattern = '/(docomo\.ne\.jp|ezweb\.ne\.jp|softbank\.ne\.jp)$/';

        if ($this->config('tmpl_mail_reply_mobile') && preg_match($mobilePattern, $replyTo)) {
            return $this->config('tmpl_mail_reply_mobile');
        }

        return $this->config('tmpl_mail_reply');
    }

    /**
     * 自動返信メールの送信者情報を設定
     *
     * @param object $pm PHPMailerインスタンス
     * @param string $replyTo 返信先アドレス
     * @param string $adminAddress 管理者アドレス
     */
    private function setAutoReplySender($pm, $replyTo, $adminAddress)
    {
        $pm->AddAddress($replyTo);

        $pm->Subject = $this->config('reply_subject')
            ? evo()->parseText($this->config('reply_subject'), $this->form)
            : '自動返信メール';

        $pm->setFrom($adminAddress, $this->config('reply_fromname'));
    }

    /**
     * 設定ファイルで指定された添付ファイルを追加
     *
     * @param object $pm PHPMailerインスタンス
     * @return bool 添付ファイルがあればtrue
     */
    private function attachConfiguredFile($pm)
    {
        $attachFile = $this->config('attach_file');
        if (!$attachFile || !is_file($attachFile)) {
            return false;
        }

        $attachName = $this->config('attach_file_name');
        if (!$attachName) {
            $pm->AddAttachment($attachFile);
        } else {
            $pm->AddAttachment(
                $attachFile,
                mb_convert_encoding($attachName, $this->config('mail_charset'), $this->config('charset'))
            );
        }

        return true;
    }

    /**
     * 自動返信メールのデバッグ情報をログ出力
     *
     * @param object $pm PHPMailerインスタンス
     * @param string $replyTo 返信先アドレス
     * @param string $templateFile テンプレートファイル名
     * @param bool $hasAttachment 添付ファイルの有無
     */
    private function logAutoReplyDebugInfo($pm, $replyTo, $templateFile, $hasAttachment)
    {
        if (!$this->config('debug_mode')) {
            return;
        }

        $debug_info = "自動返信メール送信を試行\n";
        $debug_info .= "宛先: " . $replyTo . "\n";
        $debug_info .= "件名: " . $pm->Subject . "\n";
        $debug_info .= "送信元: " . $pm->From . "\n";
        if ($this->config('reply_fromname')) {
            $debug_info .= "送信者名: " . $this->config('reply_fromname') . "\n";
        }
        $debug_info .= "文字コード: " . ($this->config('mail_charset') ?: 'iso-2022-jp') . "\n";
        $debug_info .= "HTMLメール: " . ($this->config('reply_ishtml') ? '有効' : '無効') . "\n";
        $debug_info .= "テンプレート: " . $templateFile . "\n";
        if ($hasAttachment) {
            $debug_info .= "添付ファイル: あり\n";
        }
        $body_preview = mb_substr(strip_tags($pm->Body), 0, 100);
        $debug_info .= "本文プレビュー: " . $body_preview . "...";
        $this->debugLog($debug_info);
    }

    /**
     * メール本文を生成
     *
     * @param string $tpl テンプレート
     * @param array $ph プレースホルダ
     * @return string
     */
    private function makeBody($tpl, $ph)
    {
        $body = $this->clearPlaceHolder(
            $this->replacePlaceHolder(
                str_replace(
                    array("\r\n", "\r"),
                    "\n",
                    $tpl
                ),
                $ph,
                $this->config('allow_html') ? '<br />' : "\n"
            )
        );

        $mail_charset = $this->config('mail_charset');
        $charset = $this->config('charset');

        // mail_charsetが空の場合はエンコード変換をスキップ
        if (empty($mail_charset) || empty($charset)) {
            return $body;
        }

        return mb_convert_encoding($body, $mail_charset, $charset);
    }

    /**
     * メール用プレースホルダを生成
     *
     * @param array $form フォームデータ
     * @param string $adminmail 管理者メールアドレス
     * @return array
     */
    private function makePh($form, $adminmail)
    {
        $remote_addr = serverv('REMOTE_ADDR', '');
        $user_agent = serverv('HTTP_USER_AGENT', '');

        $additional = array(
            'senddate'    => date('Y-m-d H:i:s'),
            'adminmail'   => $adminmail,
            'sender_ip'   => $remote_addr,
            'sender_host' => $remote_addr ? gethostbyaddr($remote_addr) : '',
            'sender_ua'   => $this->encodeHTML($user_agent),
            'reply_to'    => $this->getAutoReplyAddress(),
        );
        if (!$this->config('reply_ishtml')) {
            return $form + $additional;
        }
        if (!$this->config('allow_html')) {
            return $this->encodeHTML($form, 'true') + $additional;
        }
        return $this->nl2br_array($form) + $additional;
    }

    /**
     * 管理者メールアドレスを取得
     *
     * @return array
     */
    private function makeAdminAddress()
    {
        if (!$this->config('dynamic_send_to_field')) {
            $mails = explode(',', $this->config('admin_mail'));
        } else {
            $dynamic_send_to = sessionv('dynamic_send_to');
            $field_value = $this->form[$this->config('dynamic_send_to_field')] ?? '';
            if (empty($dynamic_send_to) || !$field_value || !isset($dynamic_send_to[$field_value])) {
                $mails = explode(',', $this->config('admin_mail'));
            } else {
                $mails = explode(',', $dynamic_send_to[$field_value]);
            }
        }
        $admin_addresses = array();
        foreach ($mails as $buf) {
            $buf = trim($buf);
            if ($this->_isValidEmail($buf)) {
                $admin_addresses[] = $buf;
            }
        }
        return $admin_addresses;
    }
}
