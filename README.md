# cfFormMailer

**高機能なPHPメールフォームスニペット for MODX Evolution**

[![Version](https://img.shields.io/badge/version-1.7.0-blue.svg)](https://github.com/yama/cfFormMailer)
[![PHP](https://img.shields.io/badge/PHP-7.4%20~%208.4-777BB4.svg)](https://www.php.net/)
[![MODX](https://img.shields.io/badge/MODX-Evolution-green.svg)](https://modx.com/)
[![License](https://img.shields.io/badge/license-GPL-blue.svg)](LICENSE)

cfFormMailerは、MODX Evolutionで動作する高機能なメールフォームスニペットです。入力検証、CAPTCHA認証、ファイルアップロード、自動返信など、豊富な機能を提供します。

---

## 主な機能

- **豊富な入力検証**（メール、数値、文字数、電話番号、郵便番号など19種類）
- **CAPTCHA画像認証**によるスパム対策
- **ファイルアップロード**対応（画像・PDF・Wordなど）
- **自動返信メール**機能
- **HTMLメール送信**対応
- **動的送信先変更**（選択肢によって管理者宛先を切り替え）
- **データベース保存**（cfFormDBモジュール連携）
- **デバッグモード**（メール送信の詳細ログ記録）
- **カスタム検証ルール**・フィルター追加可能
- **PHP 7.4～8.4完全対応**

---

## クイックスタート

### インストール

```bash
# 1. ファイルをアップロード
/assets/snippets/cfFormMailer/ に以下をアップロード
  includes/
    - class.cfFormMailer.inc.php
    - bootstrap.php
  extras/
    - additionalMethods.inc.php (オプション)
  forms/
    - sample/ (サンプルテンプレート)

# 2. スニペット登録
MODXの管理画面で新規スニペット作成:
  スニペット名: cfFormMailer
  コード: install-code.php の内容をコピー&ペースト

# 3. 設定ファイル作成
forms/sample/config.with_comment.ini を参考に設定ファイルを作成
```

### 基本的な使い方

```php
<!-- MODXドキュメント内 -->
[!cfFormMailer?&config=`@FILE:forms/myform/config.ini`!]
```

詳細は [インストールガイド](docs/INSTALLATION.md) をご覧ください。

---

## ドキュメント

### はじめに

- [インストールガイド](docs/INSTALLATION.md) - インストール手順の詳細
- [環境設定リファレンス](docs/CONFIGURATION.md) - 全設定項目の説明
- [テンプレート作成ガイド](docs/TEMPLATE_GUIDE.md) - 入力・確認・完了画面の作り方

### 機能・仕様

- [機能解説](docs/FEATURES.md) - 入力検証、フィルター、CAPTCHA等
- [カスタマイズガイド](docs/CUSTOMIZATION.md) - 独自検証・フィルターの追加方法
- [ファイルアップロード](docs/FILE_UPLOAD.md) - アップロード機能の詳細
- [デバッグモード](docs/DEBUG.md) - メール送信のトラブルシューティング

### その他

- [更新履歴](docs/CHANGELOG.md) - バージョンごとの変更内容
- [FAQ](docs/FAQ.md) - よくある質問と回答

---

## 使用例

### シンプルなお問い合わせフォーム

```html
<!-- 入力画面テンプレート -->
<form action="[[~[[*id]]]]" method="post">
  <p>
    <label for="name">お名前 <span class="required">*</span></label>
    <input type="text" name="name" id="name" valid="1" />
  </p>

  <p>
    <label for="email">メールアドレス <span class="required">*</span></label>
    <input type="email" name="email" id="email" valid="1:email" />
  </p>

  <p>
    <label for="message">お問い合わせ内容 <span class="required">*</span></label>
    <textarea name="message" id="message" rows="10" valid="1"></textarea>
  </p>

  <iferror>
    <div class="error-messages">
      [+errors|implodetag(li)+]
    </div>
  </iferror>

  <button type="submit">送信確認</button>
</form>
```

詳細は [サンプルテンプレート](forms/sample/) を参照してください。

---

## システム要件

- **MODX Evolution**: 1.0.x以降
- **PHP**: 7.4～8.4（推奨: 8.1以降）
- **文字コード**: UTF-8
- **その他**: mb_string拡張、mail関数またはMODxMailer

---

## バージョン情報

### 現在のバージョン: v1.7.0 (2025-11-18)

**主な変更点**:

- PHP 8.1+ `strftime()` 非推奨問題の部分対応
- セッションからのファイルアップロード処理の改善
- 各種バグ修正とコードクリーンアップ

詳細は [CHANGELOG.md](docs/CHANGELOG.md) をご覧ください。

---

## 貢献・開発

### 開発に参加する

```bash
# リポジトリをクローン
git clone https://github.com/yama/cfFormMailer.git
cd cfFormMailer

# 開発に必要なツールをインストール
composer install

# テストを実行
vendor/bin/phpunit
```

プルリクエストを歓迎します！詳細は [CONTRIBUTING.md](CONTRIBUTING.md) をご覧ください。

### バグ報告・機能リクエスト

- [GitHub Issues](https://github.com/yama/cfFormMailer/issues)
- [公式フォーラム](https://modx.jp/)
- [ブログ：網的脚本実験室](http://www.clefarray-web.net/blog/)

---

## ライセンス

cfFormMailerは[GPLライセンス](LICENSE)の下で配布されています。

---

## クレジット

**作者**: Clefarray Factory
**サイト**: [網的脚本実験室](http://www.clefarray-web.net/blog/)
**MODX公式フォーラム**: https://modx.jp/

### 使用ライブラリ

- [MODX Evolution](https://modx.com/) - CMSフレームワーク
- [MODxMailer / PHPMailer](https://github.com/PHPMailer/PHPMailer) - メール送信
- [class.upload.php](http://www.verot.net/php_class_upload.htm) - ファイルアップロード（オプション）

---

## 免責事項

本スクリプトの使用によって生じた損害等について、作者は一切の責任を負わないものとします。ご了承ください。

---

## スターをお願いします

cfFormMailerが役に立ったら、ぜひ GitHub でスターをお願いします！

