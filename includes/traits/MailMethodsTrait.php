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

        $reply_to = $this->getAutoReplyAddress();

        // 管理者宛メールの本文生成
        $tmpl = $this->loadTemplate($this->config('tmpl_mail_admin'));
        if (!$tmpl) {
            $this->setError('メールテンプレートの読み込みに失敗しました');
            return false;
        }

        $admin_addresses = $this->makeAdminAddress();
        $admin_address = $admin_addresses[0];

        // 管理者宛送信
        evo()->loadExtension('MODxMailer');
        $pm = evo()->mail;
        foreach ($admin_addresses as $v) {
            $pm->AddAddress($v);
        }
        if ($this->config('admin_mail_cc')) {
            foreach (explode(',', $this->config('admin_mail_cc')) as $v) {
                $v = trim($v);
                if ($this->_isValidEmail($v)) {
                    $pm->AddCC($v);
                }
            }
        }
        if ($this->config('admin_mail_bcc')) {
            foreach (explode(',', $this->config('admin_mail_bcc')) as $v) {
                $v = trim($v);
                if ($this->_isValidEmail($v)) {
                    $pm->AddBCC($v);
                }
            }
        }

        $pm->Subject = $this->config('admin_subject')
            ? evo()->parseText($this->config('admin_subject'), $this->form)
            : 'サイトから送信されたメール';

        $pm->setFrom(
            $admin_address,
            $this->config('admin_name')
                ? evo()->parseText($this->config('admin_name'), $this->form)
                : ''
        );
        if ($reply_to) {
            $pm->addReplyTo($reply_to);
        }
        $pm->Sender = $pm->From;
        $pm->Body = $this->makeBody(
            $tmpl,
            ($this->makePh($this->form, $admin_address))
        );
        $pm->Encoding = '7bit';

        // ユーザーからのファイル送信
        $upload_flag = false;
        $uploaded = sessionv('_cf_uploaded');
        if (is_array($uploaded) && count($uploaded)) {
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
                if (!$upload_flag) {
                    $upload_flag = true;
                }
            }
        }

        // デバッグログ: メール送信情報
        if ($this->config('debug_mode')) {
            $debug_info = "管理者宛メール送信を試行\n";
            $debug_info .= "宛先: " . implode(', ', $admin_addresses) . "\n";
            if ($this->config('admin_mail_cc')) {
                $debug_info .= "CC: " . $this->config('admin_mail_cc') . "\n";
            }
            if ($this->config('admin_mail_bcc')) {
                $debug_info .= "BCC: " . $this->config('admin_mail_bcc') . "\n";
            }
            $debug_info .= "件名: " . $pm->Subject . "\n";
            $debug_info .= "送信元: " . $pm->From . "\n";
            if ($reply_to) {
                $debug_info .= "Reply-To: " . $reply_to . "\n";
            }
            $debug_info .= "文字コード: " . ($this->config('mail_charset') ?: 'iso-2022-jp') . "\n";
            $debug_info .= "HTMLメール: " . ($this->config('admin_ishtml') ? '有効' : '無効') . "\n";
            if ($upload_flag) {
                $debug_info .= "添付ファイル: あり\n";
            }
            $body_preview = mb_substr(strip_tags($pm->Body), 0, 100);
            $debug_info .= "本文プレビュー: " . $body_preview . "...";
            $this->debugLog($debug_info);
        }

        $sent = $pm->Send();
        if (!$sent) {
            $errormsg = 'メール送信に失敗しました::' . $pm->ErrorInfo;
            $this->setError($errormsg);
            $vars = evo()->htmlspecialchars(
                var_export($pm, true)
            );
            $this->logEvent(1, 3, $errormsg . "\n" . $vars, 'Admin Mail Error');
            return false;
        }

        $this->debugLog('管理者宛メール送信に成功しました');
        return true;
    }

    /**
     * 自動返信メール送信
     *
     * @return bool
     */
    private function sendAutoReply()
    {
        // 自動返信
        $reply_to = $this->getAutoReplyAddress();
        if (!$this->config('auto_reply') || !$reply_to) {
            $this->debugLog('自動返信メールは無効、またはReply-Toアドレスが取得できませんでした');
            return true;
        }

        $this->debugLog('自動返信メール送信処理を開始');

        evo()->loadExtension('MODxMailer');
        $pm = evo()->mail;
        $pm->AddAddress($reply_to);
        $pm->Subject = $this->config('reply_subject')
            ? evo()->parseText($this->config('reply_subject'), $this->form)
            : '自動返信メール';
        $admin_addresses = $this->makeAdminAddress();
        $admin_address = $admin_addresses[0];
        $pm->setFrom(
            $admin_address,
            $this->config('reply_fromname')
        );
        // モバイル用のテンプレート切り替え
        $pattern = '/(docomo\.ne\.jp|ezweb\.ne\.jp|softbank\.ne\.jp)$/';
        if ($this->config('tmpl_mail_reply_mobile') && preg_match($pattern, $reply_to)) {
            $template_filename = $this->config('tmpl_mail_reply_mobile');
        } else {
            $template_filename = $this->config('tmpl_mail_reply');
        }
        $tmpl_u = $this->loadTemplate($template_filename);
        if (!$tmpl_u) {
            $this->setError('メールテンプレートの読み込みに失敗しました');
            return false;
        }

        $pm->Sender = $pm->From;
        $pm->Body = $this->makeBody(
            $tmpl_u,
            ($this->makePh($this->form, $admin_address))
        );
        $pm->Encoding = '7bit';
        // 添付ファイル処理
        if ($this->config('attach_file') && is_file($this->config('attach_file'))) {
            if (!$this->config('attach_file_name')) {
                $pm->AddAttachment($this->config('attach_file'));
            } else {
                $pm->AddAttachment(
                    $this->config('attach_file'),
                    mb_convert_encoding(
                        $this->config('attach_file_name'),
                        $this->config('mail_charset'),
                        $this->config('charset')
                    )
                );
            }
        }

        $uploaded = sessionv('_cf_uploaded');
        $has_uploaded_attachment = false;
        if (is_array($uploaded)) {
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
                $has_uploaded_attachment = true;
            }
        }

        // デバッグログ: メール送信情報
        if ($this->config('debug_mode')) {
            $debug_info = "自動返信メール送信を試行\n";
            $debug_info .= "宛先: " . $reply_to . "\n";
            $debug_info .= "件名: " . $pm->Subject . "\n";
            $debug_info .= "送信元: " . $pm->From . "\n";
            if ($this->config('reply_fromname')) {
                $debug_info .= "送信者名: " . $this->config('reply_fromname') . "\n";
            }
            $debug_info .= "文字コード: " . ($this->config('mail_charset') ?: 'iso-2022-jp') . "\n";
            $debug_info .= "HTMLメール: " . ($this->config('reply_ishtml') ? '有効' : '無効') . "\n";
            $debug_info .= "テンプレート: " . $template_filename . "\n";
            if ($this->config('attach_file') || $has_uploaded_attachment) {
                $debug_info .= "添付ファイル: あり\n";
            }
            $body_preview = mb_substr(strip_tags($pm->Body), 0, 100);
            $debug_info .= "本文プレビュー: " . $body_preview . "...";
            $this->debugLog($debug_info);
        }

        $sent = $pm->Send();

        if (!$sent) {
            $errormsg = 'メール送信に失敗しました::' . $pm->ErrorInfo;
            $this->setError($errormsg);
            $vars = evo()->htmlspecialchars(
                var_export($pm, true)
            );
            $this->logEvent(1, 3, $errormsg . "\n" . $vars, 'AutoReply Error');
            return false;
        }

        $this->debugLog('自動返信メール送信に成功しました');
        return true;
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
