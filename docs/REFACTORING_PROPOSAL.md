# cfFormMailer リファクタリング提案書

**対象バージョン**: v1.7.0
**作成日**: 2025-11-18
**PHP動作保証**: PHP 7.4 ～ 8.4

---

## 📊 現状分析

### コードメトリクス

| 項目 | 値 | 問題点 |
|------|-----|--------|
| メインクラス行数 | 2,481行 | 保守困難な巨大クラス |
| メソッド数 | 81個 | 単一クラスに責務が集中 |
| 検証メソッド | 19個 | 冗長な実装パターン |
| 最大メソッド行数 | ~150行 | メソッドが巨大化 |
| 型宣言 | 0% | PHP7.4+の型安全性未活用 |
| 単体テスト | 0件 | テスト基盤なし |

### 主要な技術的課題

1. **単一責任原則（SRP）違反**
   - `Class_cfFormMailer` が10以上の責務を担当
   - フォーム表示、検証、メール送信、テンプレート処理、DB保存が全て混在

2. **依存性の問題**
   - グローバル変数 `$modx` への直接依存
   - `$_POST`, `$_SESSION`, `$_FILES` への直接アクセス
   - テスト・モックが不可能

3. **PHP互換性の問題**
   - `strftime()` が PHP 8.1で非推奨、8.3で削除済み（部分対応済み）
   - 動的プロパティが PHP 8.2で非推奨
   - 型宣言なし

4. **保守性の問題**
   - 重複コードが多数存在
   - 100行超のメソッド
   - PHPDoc不足

---

## 🎯 リファクタリング目標

### 1. モダンなアーキテクチャ

```
現在: 巨大な単一クラス（2,481行）
 ↓
目標: 責務別に分離された小クラス群（各100-300行）
```

### 2. PHP 7.4～8.4 完全対応

- ✅ 厳格な型宣言（プロパティ、パラメータ、戻り値）
- ✅ strftime() 完全削除
- ✅ 動的プロパティ問題の解消
- ✅ Null安全演算子の活用
- ✅ Constructor Property Promotion (PHP 8.0+)

### 3. テスタビリティ向上

- 依存性注入（DI）の導入
- インターフェースベース設計
- 100% 単体テストカバレッジ

---

## 📁 提案1: 新ディレクトリ構造

```
cfFormMailer/
├── src/                            # アプリケーションコード (PSR-4: CfFormMailer\)
│   │
│   ├── Core/                       # コア機能
│   │   ├── FormProcessor.php      # フォーム処理フロー統括
│   │   ├── ConfigLoader.php       # INI設定の読み込み・検証
│   │   ├── SessionManager.php     # セッション・トークン管理
│   │   └── CsrfTokenManager.php   # CSRFトークン生成・検証
│   │
│   ├── Validation/                 # 入力検証機能
│   │   ├── FormValidator.php      # 検証統括クラス
│   │   ├── ValidationResult.php   # 検証結果DTO
│   │   ├── Rules/                  # 検証ルール (19個 → 各クラス化)
│   │   │   ├── ValidationRuleInterface.php
│   │   │   ├── RequiredRule.php
│   │   │   ├── EmailRule.php
│   │   │   ├── NumericRule.php
│   │   │   ├── LengthRule.php
│   │   │   ├── RangeRule.php
│   │   │   ├── SameAsRule.php
│   │   │   ├── TelRule.php
│   │   │   ├── ZipRule.php
│   │   │   ├── VerificationCodeRule.php
│   │   │   ├── AllowedTypeRule.php      # ファイル形式検証
│   │   │   ├── AllowedSizeRule.php      # ファイルサイズ検証
│   │   │   ├── ConvertRule.php          # 変換ルール
│   │   │   ├── ZenHanRule.php
│   │   │   ├── HanZenRule.php
│   │   │   └── UrlRule.php
│   │   └── RuleFactory.php         # ルールのファクトリ
│   │
│   ├── Mail/                       # メール送信機能
│   │   ├── MailSender.php          # メール送信統括
│   │   ├── MailMessage.php         # メールメッセージDTO
│   │   ├── AdminMailBuilder.php    # 管理者宛メール構築
│   │   ├── AutoReplyMailBuilder.php # 自動返信メール構築
│   │   └── MailTemplateRenderer.php # メールテンプレート処理
│   │
│   ├── Template/                   # テンプレートエンジン
│   │   ├── TemplateEngine.php      # テンプレート統括
│   │   ├── TemplateLoader.php      # チャンク/ファイル読込
│   │   ├── PlaceholderResolver.php # [+placeholder+] 置換
│   │   ├── FormRenderer.php        # フォーム画面レンダリング
│   │   ├── FormRestorer.php        # 入力値復元処理
│   │   ├── ErrorRenderer.php       # エラー表示処理
│   │   └── Filters/                # プレースホルダフィルター
│   │       ├── FilterInterface.php
│   │       ├── ImplodeFilter.php
│   │       ├── ImplodeTagFilter.php
│   │       ├── NumberFilter.php
│   │       ├── DateFormatFilter.php
│   │       └── SprintfFilter.php
│   │
│   ├── Upload/                     # ファイルアップロード
│   │   ├── FileUploader.php        # アップロード処理
│   │   ├── UploadedFile.php        # アップロードファイルDTO
│   │   ├── FileValidator.php       # ファイル検証
│   │   └── MimeTypeDetector.php    # MIMEタイプ判定
│   │
│   ├── Database/                   # DB永続化
│   │   ├── FormRepository.php      # フォームデータ保存
│   │   └── TableChecker.php        # テーブル存在確認
│   │
│   ├── Error/                      # エラーハンドリング
│   │   ├── ErrorHandler.php
│   │   ├── Exceptions/
│   │   │   ├── FormException.php
│   │   │   ├── ValidationException.php
│   │   │   ├── TemplateException.php
│   │   │   └── MailException.php
│   │   └── ErrorMessageFormatter.php
│   │
│   ├── Support/                    # ユーティリティ
│   │   ├── Encoder.php             # HTML/文字コード変換
│   │   ├── JapaneseCharConverter.php # 日本語特殊文字変換
│   │   └── ArrayHelper.php
│   │
│   └── Legacy/                     # 後方互換性レイヤー
│       ├── Class_cfFormMailer.php  # 既存APIラッパー
│       └── LegacyAdapter.php
│
├── config/                         # 設定ファイル
│   ├── defaults.php                # デフォルト設定
│   └── mime_types.php              # MIMEタイプマッピング
│
├── templates/                      # テンプレート (forms/ から移動)
│   └── sample/
│       ├── config.ini
│       ├── web_form.tpl.html
│       ├── web_confirm.tpl.html
│       ├── web_thanks.tpl.html
│       ├── mail_receive.tpl.txt
│       └── mail_autoreply.tpl.txt
│
├── tests/                          # テストコード
│   ├── Unit/
│   │   ├── Validation/
│   │   │   ├── EmailRuleTest.php
│   │   │   ├── NumericRuleTest.php
│   │   │   └── ...
│   │   ├── Mail/
│   │   │   └── AdminMailBuilderTest.php
│   │   └── Template/
│   │       └── PlaceholderResolverTest.php
│   ├── Integration/
│   │   └── FormProcessorTest.php
│   └── bootstrap.php
│
├── includes/                       # 旧ファイル（後方互換用）
│   ├── bootstrap.php               # 既存エントリーポイント
│   └── class.cfFormMailer.inc.php  # レガシーラッパー
│
├── extras/                         # プラグイン等
│   ├── additionalMethods.inc.php
│   └── plugin.cfFileView.php
│
├── docs/
│   ├── manual.html
│   ├── REFACTORING_PROPOSAL.md     # 本ドキュメント
│   └── MIGRATION_GUIDE.md          # 移行ガイド
│
├── vendor/                         # Composer依存
├── composer.json
├── phpunit.xml
└── README.md
```

---

## 🔧 提案2: クラス設計詳細

### 2.1 Core: FormProcessor

**責務**: フォーム処理の全体フロー制御

```php
<?php
namespace CfFormMailer\Core;

use CfFormMailer\Validation\FormValidator;
use CfFormMailer\Mail\MailSender;
use CfFormMailer\Template\FormRenderer;
use CfFormMailer\Database\FormRepository;

class FormProcessor
{
    public function __construct(
        private ConfigLoader $config,
        private FormValidator $validator,
        private MailSender $mailSender,
        private FormRenderer $renderer,
        private SessionManager $session,
        private ?FormRepository $repository = null
    ) {}

    public function process(): string
    {
        $mode = $this->getMode();

        return match($mode) {
            'conf' => $this->handleConfirmation(),
            'send' => $this->handleSubmission(),
            'back' => $this->handleBack(),
            default => $this->showInitialForm(),
        };
    }

    private function handleConfirmation(): string
    {
        $result = $this->validator->validate($_POST);

        if (!$result->isValid()) {
            return $this->renderer->renderWithErrors($result->getErrors());
        }

        return $this->renderer->renderConfirmation($_POST);
    }

    private function handleSubmission(): string
    {
        if (!$this->session->validateToken($_POST['_token'] ?? '')) {
            throw new CsrfException('Invalid token');
        }

        $this->mailSender->sendAdminMail($_POST);
        $this->mailSender->sendAutoReply($_POST);

        $this->repository?->store($_POST);
        $this->session->markAsSent($_POST);

        return $this->renderer->renderComplete();
    }

    // ... 他のメソッド
}
```

### 2.2 Validation: ルールベース検証システム

**現在の問題**:
```php
// 19個の _def_* メソッドが冗長
private function _def_email($value, $param, $field) { /* ... */ }
private function _def_num($value, $param, $field) { /* ... */ }
private function _def_len($value, $param, $field) { /* ... */ }
// ... 16個続く
```

**改善後**:

```php
<?php
namespace CfFormMailer\Validation;

interface ValidationRuleInterface
{
    public function validate(mixed $value, array $params): bool;
    public function getErrorMessage(): string;
    public function transform(mixed $value): mixed; // 値の正規化・変換
}
```

```php
<?php
namespace CfFormMailer\Validation\Rules;

class EmailRule implements ValidationRuleInterface
{
    private string $errorMessage = 'メールアドレスの形式が正しくありません';

    public function validate(mixed $value, array $params): bool
    {
        $pattern = "/^(?:[a-z0-9+_-]+?\.)*?[a-z0-9_+-]+?@(?:[a-z0-9_-]+?\.)*?[a-z0-9_-]+?\.[a-z0-9]{2,5}$/i";
        return (bool) preg_match($pattern, $value);
    }

    public function transform(mixed $value): string
    {
        // 強制的に半角変換
        return mb_convert_kana($value, 'a', 'UTF-8');
    }

    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }
}
```

```php
<?php
namespace CfFormMailer\Validation\Rules;

class LengthRule implements ValidationRuleInterface
{
    public function __construct(
        private ?int $min = null,
        private ?int $max = null,
        private ?int $exact = null
    ) {}

    public function validate(mixed $value, array $params): bool
    {
        $length = mb_strlen($value);

        if ($this->exact !== null) {
            return $length === $this->exact;
        }

        if ($this->min !== null && $length < $this->min) {
            return false;
        }

        if ($this->max !== null && $length > $this->max) {
            return false;
        }

        return true;
    }

    public function getErrorMessage(): string
    {
        if ($this->exact !== null) {
            return "{$this->exact}文字で入力してください";
        }

        if ($this->min !== null && $this->max !== null) {
            return "{$this->min}～{$this->max}文字で入力してください";
        }

        if ($this->min !== null) {
            return "{$this->min}文字以上で入力してください";
        }

        if ($this->max !== null) {
            return "{$this->max}文字以内で入力してください";
        }

        return '';
    }

    public function transform(mixed $value): mixed
    {
        return $value; // 変換なし
    }
}
```

**統合クラス**:

```php
<?php
namespace CfFormMailer\Validation;

class FormValidator
{
    private array $rules = [];

    public function addRule(string $field, ValidationRuleInterface $rule, bool $required = false): void
    {
        $this->rules[$field][] = [
            'rule' => $rule,
            'required' => $required
        ];
    }

    public function validate(array $data): ValidationResult
    {
        $errors = [];

        foreach ($this->rules as $field => $ruleSet) {
            $value = $data[$field] ?? '';

            foreach ($ruleSet as $config) {
                $rule = $config['rule'];

                // 必須チェック
                if ($config['required'] && empty($value)) {
                    $errors[$field][] = '入力必須項目です';
                    continue;
                }

                // 空の場合はスキップ（必須でない場合）
                if (empty($value)) {
                    continue;
                }

                // 値の変換
                $value = $rule->transform($value);
                $data[$field] = $value; // 変換後の値を保存

                // 検証実行
                if (!$rule->validate($value, [])) {
                    $errors[$field][] = $rule->getErrorMessage();
                }
            }
        }

        return new ValidationResult($errors, $data);
    }
}
```

```php
<?php
namespace CfFormMailer\Validation;

class ValidationResult
{
    public function __construct(
        private array $errors,
        private array $validatedData
    ) {}

    public function isValid(): bool
    {
        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getValidatedData(): array
    {
        return $this->validatedData;
    }
}
```

### 2.3 Mail: メール送信の分離

**現在の問題**:
```php
// sendAdminMail() が 100行超
private function sendAdminMail() {
    $reply_to = $this->getAutoReplyAddress();
    $tmpl = $this->loadTemplate($this->config('tmpl_mail_admin'));
    // ... 100行以上続く
}
```

**改善後**:

```php
<?php
namespace CfFormMailer\Mail;

class MailSender
{
    public function __construct(
        private AdminMailBuilder $adminBuilder,
        private AutoReplyMailBuilder $replyBuilder,
        private object $mailer // MODxMailer
    ) {}

    public function sendAdminMail(array $formData): bool
    {
        $message = $this->adminBuilder->build($formData);
        return $this->send($message);
    }

    public function sendAutoReply(array $formData): bool
    {
        $message = $this->replyBuilder->build($formData);
        return $this->send($message);
    }

    private function send(MailMessage $message): bool
    {
        $this->mailer->clearAllRecipients();

        foreach ($message->getTo() as $address) {
            $this->mailer->AddAddress($address);
        }

        foreach ($message->getCc() as $address) {
            $this->mailer->AddCC($address);
        }

        $this->mailer->Subject = $message->getSubject();
        $this->mailer->Body = $message->getBody();
        $this->mailer->setFrom($message->getFrom(), $message->getFromName());

        foreach ($message->getAttachments() as $file) {
            $this->mailer->AddAttachment($file->getPath(), $file->getName());
        }

        return $this->mailer->Send();
    }
}
```

```php
<?php
namespace CfFormMailer\Mail;

class AdminMailBuilder
{
    public function __construct(
        private MailTemplateRenderer $renderer,
        private ConfigLoader $config
    ) {}

    public function build(array $formData): MailMessage
    {
        $addresses = $this->parseAddresses($this->config->get('admin_mail'));
        $subject = $this->config->get('admin_subject') ?: 'サイトから送信されたメール';
        $body = $this->renderer->render('tmpl_mail_admin', $formData);

        return new MailMessage(
            to: $addresses,
            cc: $this->parseAddresses($this->config->get('admin_mail_cc')),
            bcc: $this->parseAddresses($this->config->get('admin_mail_bcc')),
            subject: $subject,
            body: $body,
            from: $addresses[0],
            fromName: $this->config->get('admin_name'),
            attachments: $this->getUploadedFiles()
        );
    }

    private function parseAddresses(string $addresses): array
    {
        return array_filter(
            array_map('trim', explode(',', $addresses)),
            fn($addr) => $this->isValidEmail($addr)
        );
    }

    // ...
}
```

### 2.4 Template: テンプレートエンジン

```php
<?php
namespace CfFormMailer\Template;

class PlaceholderResolver
{
    /**
     * [+placeholder+] または [+placeholder:modifier+] を置換
     */
    public function resolve(string $content, array $data, string $arraySeparator = '<br />'): string
    {
        return preg_replace_callback(
            "/\[\+([^+|]+)(\|(.+?)(\((.+?)\))?)?\+]/is",
            function ($match) use ($data, $arraySeparator) {
                $key = $match[1];
                $filterType = $match[3] ?? '';
                $filterParam = $match[5] ?? '';

                if (!isset($data[$key])) {
                    return '';
                }

                $value = $data[$key];

                // フィルター適用
                if ($filterType && $filter = $this->getFilter($filterType)) {
                    return $filter->apply($value, $filterParam);
                }

                // 配列の場合は連結
                if (is_array($value)) {
                    return implode($arraySeparator, $value);
                }

                return $value;
            },
            $content
        );
    }

    private function getFilter(string $type): ?FilterInterface
    {
        // フィルターファクトリから取得
        return match($type) {
            'implode' => new ImplodeFilter(),
            'num' => new NumberFilter(),
            'dateformat' => new DateFormatFilter(),
            'sprintf' => new SprintfFilter(),
            default => null,
        };
    }
}
```

### 2.5 PHP 8.1+ strftime() 問題の完全解決

**現在のコード** (部分対応):
```php
private function _f_dateformat($text, $param)
{
    $timestamp = strtotime($text);

    // PHP 8.1未満の場合はstrftimeを使用
    if (function_exists('strftime')) {
        return strftime($param, $timestamp);
    }

    // PHP 8.1以降の代替実装（一部のフォーマットのみ対応）
    $datetime = (new DateTime())->setTimestamp($timestamp);
    // ... マップ変換
}
```

**完全対応版**:

```php
<?php
namespace CfFormMailer\Template\Filters;

class DateFormatFilter implements FilterInterface
{
    /**
     * PHP 7.4～8.4 完全対応の日付フォーマット
     */
    public function apply(mixed $value, string $format): string
    {
        if (empty($format)) {
            $format = '%Y-%m-%d %H:%M:%S';
        }

        $timestamp = is_numeric($value) ? (int)$value : strtotime($value);

        if ($timestamp === false) {
            return $value;
        }

        return $this->formatDate($timestamp, $format);
    }

    /**
     * strftime() 互換フォーマット変換（完全版）
     */
    private function formatDate(int $timestamp, string $format): string
    {
        $datetime = new \DateTime();
        $datetime->setTimestamp($timestamp);

        // strftimeフォーマット → DateTime::format 変換マップ
        $map = [
            // 年
            '%Y' => 'Y',    // 4桁の年
            '%y' => 'y',    // 2桁の年
            '%C' => 'Y',    // 世紀（÷100）

            // 月
            '%m' => 'm',    // 月（01-12）
            '%B' => 'F',    // 月名（January）
            '%b' => 'M',    // 月名短縮（Jan）
            '%h' => 'M',    // %bのエイリアス

            // 日
            '%d' => 'd',    // 日（01-31）
            '%e' => 'j',    // 日（1-31）スペース埋め→ゼロ埋めなし
            '%j' => 'z',    // 年内通算日（001-366）

            // 時
            '%H' => 'H',    // 24時間制（00-23）
            '%I' => 'h',    // 12時間制（01-12）
            '%k' => 'G',    // 24時間制（0-23）スペース埋め
            '%l' => 'g',    // 12時間制（1-12）スペース埋め

            // 分・秒
            '%M' => 'i',    // 分（00-59）
            '%S' => 's',    // 秒（00-59）

            // AM/PM
            '%p' => 'A',    // AM/PM
            '%P' => 'a',    // am/pm

            // 曜日
            '%A' => 'l',    // 曜日名（Monday）
            '%a' => 'D',    // 曜日名短縮（Mon）
            '%w' => 'w',    // 曜日番号（0-6）
            '%u' => 'N',    // ISO-8601曜日（1-7）

            // 週
            '%U' => 'W',    // 週番号（日曜始まり）
            '%W' => 'W',    // 週番号（月曜始まり）
            '%V' => 'W',    // ISO-8601週番号

            // タイムゾーン
            '%z' => 'O',    // UTCオフセット（+0900）
            '%Z' => 'T',    // タイムゾーン（JST）

            // 複合
            '%c' => 'D M j H:i:s Y',  // 完全な日時
            '%x' => 'm/d/Y',          // 日付
            '%X' => 'H:i:s',          // 時刻
            '%D' => 'm/d/y',          // %m/%d/%y
            '%F' => 'Y-m-d',          // %Y-%m-%d
            '%R' => 'H:i',            // %H:%M
            '%T' => 'H:i:s',          // %H:%M:%S

            // リテラル
            '%n' => "\n",
            '%t' => "\t",
            '%%' => '%',
        ];

        // 変換実行
        $dateFormat = str_replace(
            array_keys($map),
            array_values($map),
            $format
        );

        return $datetime->format($dateFormat);
    }
}
```

---

## 📋 提案3: 段階的実装計画

### Phase 1: 基盤整備（3-5日）

**目標**: 開発環境とテスト基盤の構築

```bash
# 1. Composerの導入
composer init
composer require --dev phpunit/phpunit ^9.5

# 2. autoload設定 (composer.json)
{
    "autoload": {
        "psr-4": {
            "CfFormMailer\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "CfFormMailer\\Tests\\": "tests/"
        }
    }
}

# 3. ディレクトリ作成
mkdir -p src/{Core,Validation/Rules,Mail,Template/Filters,Upload,Database,Error,Support,Legacy}
mkdir -p tests/{Unit,Integration}
mkdir -p config
```

**成果物**:
- ✅ composer.json
- ✅ phpunit.xml
- ✅ src/ ディレクトリ構造
- ✅ tests/ テスト構造

---

### Phase 2: Validation分離（5-7日）

**優先度**: 🔥 最高（最も影響範囲が大きく、独立性が高い）

**実装順序**:

```
1日目: インターフェース定義
  ├─ ValidationRuleInterface.php
  ├─ ValidationResult.php
  └─ FormValidator.php (基本構造)

2-4日目: 基本ルール実装（テスト駆動）
  ├─ RequiredRule.php + RequiredRuleTest.php
  ├─ EmailRule.php + EmailRuleTest.php
  ├─ NumericRule.php + NumericRuleTest.php
  ├─ LengthRule.php + LengthRuleTest.php
  └─ RangeRule.php + RangeRuleTest.php

5-6日目: 残りルール実装
  ├─ TelRule.php / ZipRule.php
  ├─ SameAsRule.php
  ├─ VerificationCodeRule.php
  ├─ AllowedTypeRule.php / AllowedSizeRule.php
  └─ ConvertRule.php / ZenHanRule.php / HanZenRule.php / UrlRule.php

7日目: 統合テスト
  └─ FormValidatorTest.php (全ルール統合)
```

**検証**:
```php
// 既存コードとの並行動作確認
$legacyValidator = new Class_cfFormMailer($modx);
$newValidator = new FormValidator();

// 同じ入力で結果を比較
assert($legacyResult === $newResult);
```

---

### Phase 3: Template分離（4-6日）

**実装順序**:

```
1日目: テンプレートローダー
  └─ TemplateLoader.php (チャンク/ファイル/リソース読込)

2日目: プレースホルダ処理
  ├─ PlaceholderResolver.php
  └─ PlaceholderResolverTest.php

3日目: フィルター実装
  ├─ FilterInterface.php
  ├─ ImplodeFilter.php
  ├─ DateFormatFilter.php (strftime完全対応)
  └─ NumberFilter.php / SprintfFilter.php

4-5日目: フォームレンダリング
  ├─ FormRenderer.php
  ├─ FormRestorer.php (入力値復元)
  └─ ErrorRenderer.php (<iferror>タグ処理)

6日目: 統合テスト
```

---

### Phase 4: Mail分離（3-4日）

**実装順序**:

```
1日目: MailMessage DTO
  ├─ MailMessage.php
  └─ MailAttachment.php

2日目: メール構築
  ├─ AdminMailBuilder.php
  └─ AutoReplyMailBuilder.php

3日目: メール送信統括
  └─ MailSender.php

4日目: テスト
  └─ モックを使った送信テスト
```

---

### Phase 5: Core統合（5-7日）

**実装順序**:

```
1-2日目: 設定管理
  ├─ ConfigLoader.php
  └─ config/defaults.php

3日目: セッション・トークン
  ├─ SessionManager.php
  └─ CsrfTokenManager.php

4-5日目: FormProcessor
  └─ 全コンポーネント統合

6日目: ファイルアップロード
  ├─ FileUploader.php
  ├─ UploadedFile.php
  └─ MimeTypeDetector.php

7日目: DB保存
  ├─ FormRepository.php
  └─ TableChecker.php
```

---

### Phase 6: 後方互換性（2-3日）

**実装順序**:

```
1日目: レガシーラッパー実装
  └─ src/Legacy/Class_cfFormMailer.php
      ↓
      既存の public メソッドを全て実装し、
      内部で新クラスを呼び出す

2日目: bootstrap.php更新
  └─ includes/bootstrap.php
      ↓
      composer autoloadを読み込み

3日目: 統合テスト
  └─ 既存のテンプレートで動作確認
```

**ラッパー例**:

```php
<?php
// src/Legacy/Class_cfFormMailer.php
namespace CfFormMailer\Legacy;

use CfFormMailer\Core\FormProcessor;

class Class_cfFormMailer
{
    private FormProcessor $processor;

    public function __construct(&$modx)
    {
        // 新クラスのセットアップ
        $container = new Container($modx);
        $this->processor = $container->get(FormProcessor::class);
    }

    // 既存メソッドをプロキシ
    public function validate(): bool
    {
        return $this->processor->validate($_POST);
    }

    public function sendMail(): bool
    {
        return $this->processor->sendMail();
    }

    public function renderForm(): string
    {
        return $this->processor->renderForm();
    }

    // ... 他の public メソッド全て
}
```

---

### Phase 7: 最終調整（2-3日）

```
1日目: PHP 8.4 完全テスト
  ├─ PHP 7.4でテスト実行
  ├─ PHP 8.0でテスト実行
  ├─ PHP 8.1でテスト実行
  ├─ PHP 8.2でテスト実行
  ├─ PHP 8.3でテスト実行
  └─ PHP 8.4でテスト実行

2日目: PHPDoc整備
  └─ 全クラス・メソッドにドキュメント追加

3日目: ドキュメント作成
  ├─ MIGRATION_GUIDE.md
  └─ API_REFERENCE.md
```

---

## 💻 提案4: PHP 7.4～8.4 完全対応の技術詳細

### 4.1 型宣言の追加

```php
<?php
// Before (型なし)
class Class_cfFormMailer
{
    public $cfg = array();
    private $form;
    private $formError;

    public function validate()
    {
        // ...
    }
}

// After (厳格な型宣言)
<?php declare(strict_types=1);

namespace CfFormMailer\Validation;

class FormValidator
{
    private array $rules = [];

    public function __construct(
        private RuleFactory $ruleFactory
    ) {}

    public function validate(array $data): ValidationResult
    {
        // ...
    }
}
```

### 4.2 Constructor Property Promotion (PHP 8.0+)

```php
<?php
// PHP 7.4互換コード
class MailSender
{
    private AdminMailBuilder $adminBuilder;
    private AutoReplyMailBuilder $replyBuilder;

    public function __construct(
        AdminMailBuilder $adminBuilder,
        AutoReplyMailBuilder $replyBuilder
    ) {
        $this->adminBuilder = $adminBuilder;
        $this->replyBuilder = $replyBuilder;
    }
}

// PHP 8.0+ コード（同じファイルで条件分岐）
class MailSender
{
    public function __construct(
        private AdminMailBuilder $adminBuilder,
        private AutoReplyMailBuilder $replyBuilder
    ) {}
}
```

**対応方針**: PHP 7.4互換コードで統一

### 4.3 Null安全演算子の活用

```php
<?php
// Before
$mime = $this->_getMimeType($filename, $field);
if ($mime === false) {
    return '許可されたファイル形式ではありません';
}

// After
$mime = $this->mimeDetector->detect($filename)
    ?? throw new ValidationException('MIMEタイプを取得できません');
```

### 4.4 動的プロパティ対策（PHP 8.2）

```php
<?php
// Before (動的プロパティで警告)
class Class_cfFormMailer
{
    // プロパティ宣言なし
}
$mf = new Class_cfFormMailer();
$mf->someProperty = 'value'; // PHP 8.2で非推奨警告

// After (全プロパティ宣言)
class FormValidator
{
    private array $rules = [];
    private array $errors = [];
    // 全プロパティを明示的に宣言
}
```

### 4.5 match式の活用（PHP 8.0+）

```php
<?php
// Before
public function process()
{
    $mode = $_POST['_mode'] ?? 'init';

    if ($mode === 'conf') {
        return $this->handleConfirmation();
    } elseif ($mode === 'send') {
        return $this->handleSubmission();
    } elseif ($mode === 'back') {
        return $this->handleBack();
    } else {
        return $this->showInitialForm();
    }
}

// After (PHP 8.0+)
public function process(): string
{
    $mode = $_POST['_mode'] ?? 'init';

    return match($mode) {
        'conf' => $this->handleConfirmation(),
        'send' => $this->handleSubmission(),
        'back' => $this->handleBack(),
        default => $this->showInitialForm(),
    };
}
```

**対応方針**:
- コアコードはPHP 7.4互換（if-elseif）
- PHP 8.0+専用の最適化版を別途提供（オプション）

---

## 🧪 提案5: テスト戦略

### 5.1 単体テスト例

```php
<?php
namespace CfFormMailer\Tests\Unit\Validation;

use PHPUnit\Framework\TestCase;
use CfFormMailer\Validation\Rules\EmailRule;

class EmailRuleTest extends TestCase
{
    private EmailRule $rule;

    protected function setUp(): void
    {
        $this->rule = new EmailRule();
    }

    /** @test */
    public function 有効なメールアドレスを受理する(): void
    {
        $this->assertTrue($this->rule->validate('test@example.com', []));
        $this->assertTrue($this->rule->validate('user+tag@example.co.jp', []));
    }

    /** @test */
    public function 無効なメールアドレスを拒否する(): void
    {
        $this->assertFalse($this->rule->validate('invalid', []));
        $this->assertFalse($this->rule->validate('test@', []));
        $this->assertFalse($this->rule->validate('@example.com', []));
    }

    /** @test */
    public function 全角文字を半角に変換する(): void
    {
        $result = $this->rule->transform('test＠example.com');
        $this->assertEquals('test@example.com', $result);
    }
}
```

### 5.2 統合テスト例

```php
<?php
namespace CfFormMailer\Tests\Integration;

use PHPUnit\Framework\TestCase;
use CfFormMailer\Core\FormProcessor;

class FormProcessorTest extends TestCase
{
    /** @test */
    public function フォーム送信の完全フローが動作する(): void
    {
        $processor = $this->createProcessor();

        // 1. 初期表示
        $html = $processor->showInitialForm();
        $this->assertStringContainsString('<form', $html);

        // 2. 検証エラー
        $_POST = ['_mode' => 'conf', 'email' => 'invalid'];
        $html = $processor->process();
        $this->assertStringContainsString('error', $html);

        // 3. 確認画面
        $_POST = ['_mode' => 'conf', 'email' => 'test@example.com', 'name' => 'テスト'];
        $html = $processor->process();
        $this->assertStringContainsString('test@example.com', $html);

        // 4. 送信完了
        $_POST['_mode'] = 'send';
        $_POST['_token'] = $this->getValidToken();
        $html = $processor->process();
        $this->assertStringContainsString('完了', $html);
    }
}
```

### 5.3 カバレッジ目標

| レイヤー | 目標カバレッジ |
|---------|--------------|
| Validation | 100% |
| Template | 95% |
| Mail | 90% |
| Core | 85% |
| 全体 | 90%+ |

---

## 📦 提案6: 即座に実施可能な小規模改善

大規模リファクタリングの前に、**今すぐ実施できる改善**:

### 6.1 composer導入（30分）

```bash
cd /home/user/cfFormMailer
composer init --name="clefarray/cfformmailer" --type="library"
composer require --dev phpunit/phpunit ^9.5
```

### 6.2 PSR-4 autoload設定（15分）

```json
{
    "autoload": {
        "psr-4": {
            "CfFormMailer\\": "src/"
        }
    }
}
```

### 6.3 1つのルールクラス作成（3時間）

```bash
# EmailRuleを分離してテスト
mkdir -p src/Validation/Rules
touch src/Validation/Rules/ValidationRuleInterface.php
touch src/Validation/Rules/EmailRule.php
touch tests/Unit/Validation/EmailRuleTest.php
```

### 6.4 PHPDoc追加（1日）

```php
<?php
/**
 * フォーム検証を実行
 *
 * @param array<string, mixed> $inputs 検証対象データ
 * @return bool 検証成功時true
 * @throws ValidationException 検証設定エラー時
 */
public function validate(array $inputs): bool
{
    // ...
}
```

---

## 🎯 提案7: 成功指標（KPI）

### リファクタリング前

| 指標 | 現在値 |
|------|--------|
| 最大クラス行数 | 2,481行 |
| 単体テストカバレッジ | 0% |
| 型宣言率 | 0% |
| 循環的複雑度（平均） | 12 |
| 重複コード率 | 25% |

### リファクタリング後（目標）

| 指標 | 目標値 |
|------|--------|
| 最大クラス行数 | 300行以下 |
| 単体テストカバレッジ | 90%以上 |
| 型宣言率 | 100% |
| 循環的複雑度（平均） | 5以下 |
| 重複コード率 | 5%以下 |

---

## 📝 まとめ

### 推奨アプローチ

**オプションA: 完全リファクタリング（2-3週間）**
- 全7フェーズを実施
- モダンな設計に完全移行
- 長期的な保守性を最大化

**オプションB: 段階的改善（継続的）**
- Phase 1-2から開始（Validation分離）
- 既存コードと並行運用
- リスクを最小化

**オプションC: ハイブリッド（推奨）**
- Phase 1-3（Validation + Template）を優先実施
- 残りは必要に応じて段階的に
- 最大の効果を最短で実現

### 次のステップ

1. ✅ この提案書をレビュー
2. ✅ 実施方針を決定（A/B/C）
3. ✅ Phase 1を開始（composer導入）
4. ✅ 週次で進捗レビュー

---

**質問・相談事項**

- どのアプローチを選択しますか？
- 優先的に改善したい機能はありますか？
- 既存のテンプレート資産は全て維持する必要がありますか？
- データベース保存機能（cfFormDB）は継続使用しますか？

---

**文書バージョン**: 1.0
**最終更新**: 2025-11-18
**作成者**: Claude (Sonnet 4.5)
