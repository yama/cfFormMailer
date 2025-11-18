# cfFormMailer 移行ガイド

**バージョン**: v1.7.x → v2.0.0
**対象**: 既存のcfFormMailerユーザー
**最終更新**: 2025-11-18

---

## 📋 目次

1. [移行の概要](#移行の概要)
2. [後方互換性について](#後方互換性について)
3. [段階的移行戦略](#段階的移行戦略)
4. [Phase別移行手順](#phase別移行手順)
5. [コード変更例](#コード変更例)
6. [トラブルシューティング](#トラブルシューティング)
7. [移行チェックリスト](#移行チェックリスト)

---

## 移行の概要

### なぜ移行が必要か

cfFormMailer v2.0では、以下の重要な改善が行われます：

- ✅ **PHP 7.4～8.4完全対応**（現在のPHP 8.1+ `strftime()`問題の完全解決）
- ✅ **保守性の向上**（2,481行の巨大クラスを責務別に分離）
- ✅ **テスト可能性**（単体テスト90%以上のカバレッジ）
- ✅ **型安全性**（厳格な型宣言による堅牢性向上）
- ✅ **拡張性**（プラグインアーキテクチャによるカスタマイズ容易化）

### 移行の選択肢

| オプション | 推奨対象 | 移行期間 | リスク |
|-----------|---------|---------|--------|
| **A. 即時移行** | 新規プロジェクト | 1日 | 低 |
| **B. 段階的移行（推奨）** | 既存プロジェクト | 1-2週間 | 最低 |
| **C. 後方互換モード** | レガシーシステム | 無期限 | 低 |

---

## 後方互換性について

### ✅ 完全な後方互換性を提供

**v2.0でも既存のコードはそのまま動作します**

```php
<?php
// v1.7.x のコード（変更不要）
include_once(CFM_PATH . 'class.cfFormMailer.inc.php');

$mf = new Class_cfFormMailer($modx);
$mf->parseConfig($config);

if (!postv()) {
    return $mf->renderForm();
}

// ... 既存のロジック
```

v2.0では、`Class_cfFormMailer`は内部的に新しいアーキテクチャを使用しますが、**外部APIは100%互換性を維持**します。

### 後方互換レイヤーの仕組み

```
既存コード
    ↓
Class_cfFormMailer (レガシーラッパー)
    ↓
新しいアーキテクチャ
├─ FormProcessor
├─ FormValidator
├─ MailSender
└─ TemplateEngine
```

---

## 段階的移行戦略

### 戦略1: 並行運用（Zero Downtime）

```
Week 1-2: 新コード実装（既存コードはそのまま）
Week 3-4: 段階的に新APIに置き換え
Week 5-6: 完全移行、レガシーコード削除
```

### 戦略2: フィーチャーフラグ

```php
<?php
// config.ini に追加
use_new_validator = 1  # 新しいValidatorを使用
use_new_mailer = 0     # まだ旧Mailerを使用

# 段階的に切り替え
```

---

## Phase別移行手順

### Phase 1: 環境準備（所要時間: 1-2時間）

#### 1.1 Composerのインストール

```bash
cd /path/to/cfFormMailer

# Composerが未インストールの場合
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer

# プロジェクト初期化
composer init --name="yourname/cfformmailer" --type="library"
```

#### 1.2 依存関係のインストール

```bash
# 開発用ツールをインストール
composer require --dev phpunit/phpunit ^9.5
composer require --dev phpstan/phpstan ^1.10

# Autoloadの生成
composer dump-autoload
```

#### 1.3 ディレクトリ構造の準備

```bash
# 新しいディレクトリを作成
mkdir -p src/{Core,Validation/Rules,Mail,Template/Filters,Upload,Database,Support,Legacy}
mkdir -p tests/{Unit,Integration}
mkdir -p config

# 既存ファイルのバックアップ
cp -r includes includes.backup
cp -r forms forms.backup
```

---

### Phase 2: Validation移行（所要時間: 1-2日）

#### 2.1 新しいValidatorの導入

**Before (v1.7.x)**:
```php
<?php
// includes/bootstrap.php
$mf = new Class_cfFormMailer($modx);
$mf->parseConfig($config);

if (postv('_mode') === 'conf') {
    if(!$mf->validate()) {
        return $mf->renderFormWithError();
    }
    return $mf->renderConfirm();
}
```

**After (v2.0 - オプション)**:
```php
<?php
// includes/bootstrap.php
require_once __DIR__ . '/../vendor/autoload.php';

use CfFormMailer\Core\Container;
use CfFormMailer\Validation\FormValidator;

$container = new Container($modx);
$validator = $container->get(FormValidator::class);

if (postv('_mode') === 'conf') {
    $result = $validator->validate($_POST);
    if (!$result->isValid()) {
        return $renderer->renderWithErrors($result->getErrors());
    }
    return $renderer->renderConfirmation($_POST);
}
```

#### 2.2 カスタム検証ルールの移行

**Before (v1.7.x)**:
```php
<?php
// extras/additionalMethods.inc.php

function _validate_custom_rule($value, $param)
{
    // カスタム検証ロジック
    if (!preg_match('/^custom-pattern$/', $value)) {
        return 'カスタムエラーメッセージ';
    }
    return true;
}
```

**After (v2.0)**:
```php
<?php
// src/Validation/Rules/CustomRule.php
namespace CfFormMailer\Validation\Rules;

class CustomRule implements ValidationRuleInterface
{
    public function validate(mixed $value, array $params): bool
    {
        return (bool) preg_match('/^custom-pattern$/', $value);
    }

    public function getErrorMessage(): string
    {
        return 'カスタムエラーメッセージ';
    }

    public function transform(mixed $value): mixed
    {
        return $value;
    }
}

// 使用例
$validator->addRule('custom_field', new CustomRule(), required: true);
```

---

### Phase 3: Template移行（所要時間: 1-2日）

#### 3.1 テンプレートファイルの移行

```bash
# templatesディレクトリへの移動
mv forms/sample templates/sample

# config.iniの更新
# Before: tmpl_input = @FILE:tpl/web_form.tpl
# After:  tmpl_input = @FILE:../templates/sample/web_form.tpl.html
```

#### 3.2 カスタムフィルターの移行

**Before (v1.7.x)**:
```php
<?php
// extras/additionalMethods.inc.php

function _filter_custom_format($value, $param)
{
    // カスタムフォーマット処理
    return strtoupper($value);
}
```

**After (v2.0)**:
```php
<?php
// src/Template/Filters/CustomFormatFilter.php
namespace CfFormMailer\Template\Filters;

class CustomFormatFilter implements FilterInterface
{
    public function apply(mixed $value, string $param): string
    {
        return strtoupper($value);
    }
}

// テンプレートでの使用
// [+field_name|custom_format+]
```

---

### Phase 4: Mail移行（所要時間: 半日〜1日）

#### 4.1 メールテンプレートの互換性

**既存のメールテンプレートはそのまま使用可能**

```
# 変更不要
templates/sample/mail_receive.tpl.txt
templates/sample/mail_autoreply.tpl.txt
```

#### 4.2 動的送信先の移行

**Before (v1.7.x)**:
```ini
# config.ini
dynamic_send_to_field = department

# テンプレート内
<select name="department">
  <option value="sales" sendto="sales@example.com">営業部</option>
  <option value="support" sendto="support@example.com">サポート部</option>
</select>
```

**After (v2.0 - 同じ仕様で動作)**:
```ini
# config.ini（変更不要）
dynamic_send_to_field = department
```

---

### Phase 5: Database移行（所要時間: 30分〜1時間）

#### 5.1 cfFormDB互換性

**既存のcfFormDBモジュールはそのまま使用可能**

```ini
# config.ini（変更不要）
use_store_db = 1
```

新しいアーキテクチャでは、`FormRepository`が内部的にデータベース保存を処理しますが、既存のテーブル構造を維持します。

---

## コード変更例

### 例1: 基本的なフォーム処理

#### Before (v1.7.x)

```php
<?php
// includes/bootstrap.php
if (!isset($config)) {
    return '<strong>ERROR!:</strong> 「config」パラメータは必須です';
}

define('CFM_PATH', __DIR__ . '/');
include_once(CFM_PATH . 'class.cfFormMailer.inc.php');

$mf = new Class_cfFormMailer($modx);
$mf->parseConfig($config);

if ($mf->hasSystemError()) {
    return '<strong>ERROR!</strong> ' . $mf->getSystemError();
}

if (is_file(CFM_PATH . 'additionalMethods.inc.php')) {
    include_once CFM_PATH . 'additionalMethods.inc.php';
}

if (!postv()) {
    return $mf->renderForm();
}

if (postv('return')) {
    return $mf->renderFormOnBack();
}

if ($mf->alreadySent()) {
    return $mf->raiseError('すでに送信しています');
}

if (postv('_mode') === 'conf') {
    if(!$mf->validate()) {
        return $mf->renderFormWithError();
    }
    return $mf->renderConfirm();
}

if (postv('_mode') === 'send') {
    if (!$mf->isValidToken(postv('_cffm_token'))) {
        return $mf->raiseError('画面遷移が正常に行われませんでした');
    }
    $sent = $mf->sendMail();
    if (!$sent) {
        return $mf->raiseError($mf->getError());
    }

    $mf->cleanUploadedFiles();
    $mf->storeDataInSession();
    $mf->storeDB();

    return $mf->renderComplete();
}

return $mf->renderForm();
```

#### After (v2.0 - 新API)

```php
<?php
// includes/bootstrap.php (新しいアーキテクチャを使用)
if (!isset($config)) {
    return '<strong>ERROR!:</strong> 「config」パラメータは必須です';
}

require_once __DIR__ . '/../vendor/autoload.php';

use CfFormMailer\Core\Container;
use CfFormMailer\Core\FormProcessor;

try {
    $container = new Container($modx);
    $processor = $container->make(FormProcessor::class, ['configName' => $config]);

    return $processor->process();

} catch (\Exception $e) {
    return '<strong>ERROR!</strong> ' . $e->getMessage();
}
```

**シンプルになったポイント**:
- フロー制御を`FormProcessor`に集約
- 例外ベースのエラーハンドリング
- DIコンテナによる依存解決

#### After (v2.0 - レガシー互換モード)

```php
<?php
// includes/bootstrap.php (既存コードと100%互換)
if (!isset($config)) {
    return '<strong>ERROR!:</strong> 「config」パラメータは必須です';
}

define('CFM_PATH', __DIR__ . '/');

// v2.0のレガシーラッパーを使用（内部的に新アーキテクチャを使用）
require_once __DIR__ . '/../vendor/autoload.php';
require_once CFM_PATH . 'class.cfFormMailer.inc.php';

$mf = new Class_cfFormMailer($modx);
$mf->parseConfig($config);

// ... 以下、既存コードと全く同じ
```

---

### 例2: カスタム検証ルールの追加

#### Before (v1.7.x)

```php
<?php
// extras/additionalMethods.inc.php

/**
 * カスタム検証: 電話番号（市外局番必須）
 */
function _validate_tel_with_area($value, $param)
{
    // 市外局番が0から始まる10桁以上
    if (!preg_match('/^0\d{9,}$/', str_replace('-', '', $value))) {
        return '市外局番から入力してください（例: 03-1234-5678）';
    }
    return true;
}

/**
 * カスタムフィルター: 電話番号フォーマット
 */
function _filter_tel_format($value, $param)
{
    $numbers = str_replace('-', '', $value);

    // 03-XXXX-XXXX 形式
    if (substr($numbers, 0, 2) === '03') {
        return substr($numbers, 0, 2) . '-' . substr($numbers, 2, 4) . '-' . substr($numbers, 6);
    }

    // 0XX-XXX-XXXX 形式
    return substr($numbers, 0, 3) . '-' . substr($numbers, 3, 3) . '-' . substr($numbers, 6);
}
```

#### After (v2.0)

```php
<?php
// src/Validation/Rules/TelWithAreaRule.php
namespace CfFormMailer\Validation\Rules;

class TelWithAreaRule implements ValidationRuleInterface
{
    public function validate(mixed $value, array $params): bool
    {
        $numbers = str_replace('-', '', $value);
        return (bool) preg_match('/^0\d{9,}$/', $numbers);
    }

    public function getErrorMessage(): string
    {
        return '市外局番から入力してください（例: 03-1234-5678）';
    }

    public function transform(mixed $value): string
    {
        // 半角変換
        return mb_convert_kana($value, 'a', 'UTF-8');
    }
}

// src/Template/Filters/TelFormatFilter.php
namespace CfFormMailer\Template\Filters;

class TelFormatFilter implements FilterInterface
{
    public function apply(mixed $value, string $param): string
    {
        $numbers = str_replace('-', '', $value);

        if (strlen($numbers) < 10) {
            return $value;
        }

        // 03-XXXX-XXXX 形式
        if (substr($numbers, 0, 2) === '03') {
            return sprintf(
                '%s-%s-%s',
                substr($numbers, 0, 2),
                substr($numbers, 2, 4),
                substr($numbers, 6)
            );
        }

        // 0XX-XXX-XXXX 形式
        return sprintf(
            '%s-%s-%s',
            substr($numbers, 0, 3),
            substr($numbers, 3, 3),
            substr($numbers, 6)
        );
    }
}

// 登録（config/custom_rules.php）
use CfFormMailer\Validation\RuleFactory;
use CfFormMailer\Template\FilterRegistry;

RuleFactory::register('tel_with_area', TelWithAreaRule::class);
FilterRegistry::register('tel_format', TelFormatFilter::class);
```

**使用例（テンプレート）**:
```html
<!-- 入力フォーム -->
<input type="tel" name="phone" valid="1:tel_with_area" />

<!-- 確認画面 -->
<p>電話番号: [+phone|tel_format+]</p>
```

---

### 例3: メール送信のカスタマイズ

#### Before (v1.7.x)

```php
<?php
// Class_cfFormMailerを継承してカスタマイズ
class MyFormMailer extends Class_cfFormMailer
{
    protected function sendAdminMail()
    {
        // 親クラスのメール送信
        $result = parent::sendAdminMail();

        // 追加処理: Slackに通知
        $this->notifySlack($this->form);

        return $result;
    }

    private function notifySlack($formData)
    {
        // Slack通知ロジック
        $webhook = 'https://hooks.slack.com/services/YOUR/WEBHOOK/URL';
        $message = json_encode([
            'text' => sprintf('新しいお問い合わせ: %s', $formData['email'])
        ]);

        file_get_contents($webhook, false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'content' => $message
            ]
        ]));
    }
}
```

#### After (v2.0)

```php
<?php
// src/Mail/Listeners/SlackNotificationListener.php
namespace CfFormMailer\Mail\Listeners;

use CfFormMailer\Events\MailSentEvent;

class SlackNotificationListener
{
    public function __construct(
        private string $webhookUrl
    ) {}

    public function handle(MailSentEvent $event): void
    {
        $formData = $event->getFormData();

        $message = json_encode([
            'text' => sprintf('新しいお問い合わせ: %s', $formData['email'] ?? 'Unknown'),
            'blocks' => [
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => "*新しいお問い合わせ*\n" .
                                  "名前: {$formData['name']}\n" .
                                  "メール: {$formData['email']}\n" .
                                  "内容: {$formData['message']}"
                    ]
                ]
            ]
        ]);

        file_get_contents($this->webhookUrl, false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'content' => $message
            ]
        ]));
    }
}

// config/events.php でリスナーを登録
use CfFormMailer\Events\EventDispatcher;
use CfFormMailer\Mail\Listeners\SlackNotificationListener;

$dispatcher = app(EventDispatcher::class);
$dispatcher->listen(
    MailSentEvent::class,
    new SlackNotificationListener('https://hooks.slack.com/services/YOUR/WEBHOOK/URL')
);
```

---

## トラブルシューティング

### 問題1: Composerがインストールできない

**症状**:
```
bash: composer: command not found
```

**解決策**:
```bash
# Composerをローカルにインストール
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
php -r "unlink('composer-setup.php');"

# 使用
php composer.phar install
```

---

### 問題2: Autoloadが読み込まれない

**症状**:
```
Fatal error: Class 'CfFormMailer\Core\FormProcessor' not found
```

**解決策**:
```bash
# Autoloadを再生成
composer dump-autoload

# bootstrap.phpで正しくrequireしているか確認
require_once __DIR__ . '/../vendor/autoload.php';
```

---

### 問題3: 既存のカスタム検証が動かない

**症状**:
```
extras/additionalMethods.inc.php で定義した _validate_* 関数が呼ばれない
```

**解決策（v2.0互換モード）**:
```php
<?php
// includes/bootstrap.php

// レガシーラッパーを使用
$mf = new Class_cfFormMailer($modx);  // v2.0でも動作

// additionalMethods.inc.phpは自動で読み込まれる
if (is_file(CFM_PATH . 'additionalMethods.inc.php')) {
    include_once CFM_PATH . 'additionalMethods.inc.php';
}
```

**解決策（新API）**:
カスタムルールクラスに移行（上記「例2」参照）

---

### 問題4: テンプレートが見つからない

**症状**:
```
ERROR! tpl read error (@FILE:tpl/web_form.tpl)
```

**解決策**:
```ini
# config.ini のパス修正

# Before
tmpl_input = @FILE:tpl/web_form.tpl

# After（templatesディレクトリに移動した場合）
tmpl_input = @FILE:../templates/sample/web_form.tpl.html

# または、絶対パスを使用
tmpl_input = @FILE:/var/www/html/assets/cfFormMailer/templates/sample/web_form.tpl.html
```

---

### 問題5: PHP 8.1+ で strftime() エラー

**症状**:
```
Deprecated: Function strftime() is deprecated in ...
```

**解決策**:

v2.0では`DateFormatFilter`が自動的に対応します。

```php
<?php
// v1.7.xでの一時的な対処
if (!function_exists('strftime')) {
    function strftime($format, $timestamp = null) {
        // 互換実装
        // ... (REFACTORING_PROPOSAL.md参照)
    }
}
```

v2.0に移行すれば自動解決します。

---

## 移行チェックリスト

### ✅ 事前準備

- [ ] 既存コードのバックアップ作成
- [ ] Gitでバージョン管理開始（未実施の場合）
- [ ] テスト環境の準備
- [ ] PHP 7.4以上の環境確認

### ✅ Phase 1: 環境構築

- [ ] Composerのインストール
- [ ] `composer.json`の作成
- [ ] Autoload設定
- [ ] ディレクトリ構造の作成
- [ ] 既存ファイルのバックアップ

### ✅ Phase 2: Validation移行

- [ ] ValidationRuleInterfaceの実装
- [ ] 基本ルール（Email, Numeric等）の作成
- [ ] カスタムルールの移行
- [ ] 単体テストの作成
- [ ] 動作確認

### ✅ Phase 3: Template移行

- [ ] TemplateLoaderの実装
- [ ] PlaceholderResolverの実装
- [ ] カスタムフィルターの移行
- [ ] テンプレートファイルの移動
- [ ] 動作確認

### ✅ Phase 4: Mail移行

- [ ] MailSenderの実装
- [ ] AdminMailBuilderの実装
- [ ] AutoReplyMailBuilderの実装
- [ ] メール送信テスト
- [ ] 動作確認

### ✅ Phase 5: 統合・テスト

- [ ] FormProcessorの実装
- [ ] 全機能の統合テスト
- [ ] 本番環境での動作確認
- [ ] パフォーマンステスト
- [ ] セキュリティチェック

### ✅ Phase 6: 本番デプロイ

- [ ] 本番環境へのデプロイ計画
- [ ] ロールバック計画の準備
- [ ] デプロイ実施
- [ ] 動作確認
- [ ] モニタリング開始

### ✅ Phase 7: 後処理

- [ ] レガシーコードの削除（オプション）
- [ ] ドキュメントの更新
- [ ] チーム内共有
- [ ] 運用マニュアル更新

---

## サポート・質問

### ドキュメント

- [リファクタリング提案書](./REFACTORING_PROPOSAL.md)
- [アーキテクチャ設計](./ARCHITECTURE.md)
- [コーディング規約](./CODING_STANDARDS.md)
- [テストガイド](./TESTING_GUIDE.md)
- [APIリファレンス](./API_REFERENCE.md)

### よくある質問

**Q: v1.7.xのコードはいつまで使えますか？**
A: v2.0でも後方互換レイヤーにより無期限でサポートされます。ただし、新機能はv2.0の新APIでのみ提供されます。

**Q: 移行は必須ですか？**
A: 必須ではありませんが、PHP 8.1+での完全動作保証や新機能を利用するには推奨されます。

**Q: 段階的に移行できますか？**
A: はい、Phase別に段階的に移行可能です。Validation→Template→Mail→Coreの順で進めることを推奨します。

**Q: カスタマイズした部分はどうなりますか？**
A: `additionalMethods.inc.php`のカスタム関数は、レガシー互換モードでそのまま使用可能です。新APIに移行する場合は、クラスベースに書き換えが必要です。

---

**文書バージョン**: 1.0
**最終更新**: 2025-11-18
**作成者**: Claude (Sonnet 4.5)
