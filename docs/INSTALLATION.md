# インストールガイド

cfFormMailerのインストール手順を説明します。

---

## 📋 目次

1. [システム要件](#システム要件)
2. [基本インストール](#基本インストール)
3. [オプション機能のセットアップ](#オプション機能のセットアップ)
4. [バージョンアップ](#バージョンアップ)
5. [トラブルシューティング](#トラブルシューティング)

---

## システム要件

### 必須

- **MODX Evolution**: 1.0.x以降
- **PHP**: 7.4～8.4（推奨: 8.1以降）
- **文字コード**: UTF-8
- **PHP拡張**:
  - `mb_string` - マルチバイト文字列処理
  - `session` - セッション管理
  - `gd` または `imagick` - CAPTCHA使用時

### オプション

- **ファイルアップロード機能**:
  - [class.upload.php](http://www.verot.net/php_class_upload.htm) (Colin Verot氏作)
  - `fileinfo` 拡張（推奨）

- **データベース保存機能**:
  - cfFormDBモジュール

---

## 基本インストール

### ステップ1: ファイルの配置

```bash
# 1. cfFormMailerディレクトリを作成
/assets/snippets/cfFormMailer/

# 2. 以下のファイルをアップロード
/assets/snippets/cfFormMailer/
├── includes/
│   ├── class.cfFormMailer.inc.php  # メインクラス
│   └── bootstrap.php                # エントリーポイント
├── extras/
│   ├── additionalMethods.inc.php   # カスタム検証・フィルター（オプション）
│   └── plugin.cfFileView.php       # ファイル表示プラグイン（オプション）
└── forms/
    └── sample/                      # サンプルテンプレート
        ├── config.with_comment.ini
        ├── web_form.tpl.html
        ├── web_confirm.tpl.html
        └── ...
```

**ディレクトリパーミッション**:
- `assets/snippets/cfFormMailer/` → 755
- ファイル → 644
- `tmp/`ディレクトリ（作成する場合） → 777

---

### ステップ2: スニペット登録

MODX管理画面にログインし、以下の手順でスニペットを作成します。

#### 方法1: 管理画面から

1. **エレメント** → **スニペット** → **新規スニペット**
2. 以下の情報を入力:

   | 項目 | 値 |
   |------|-----|
   | **スニペット名** | `cfFormMailer` |
   | **説明** | 高機能メールフォームスニペット |
   | **カテゴリ** | Mail（任意） |
   | **スニペットコード** | `install-code.php`の内容 |

3. **保存**をクリック

---

### ステップ3: 設定ファイルの作成

環境設定として利用する設定ファイルを作成します。

#### 方法A: ファイルとして作成（推奨）

```bash
# 1. サンプルをコピー
cp forms/sample/config.with_comment.ini forms/myform/config.ini

# 2. 設定を編集
nano forms/myform/config.ini
```

**最小限の設定例** (`forms/myform/config.ini`):

```ini
# 管理者宛メール設定
admin_mail = info@example.com
admin_subject = お問い合わせフォームからの送信

# 自動返信メール設定
auto_reply = 1
reply_to = email
reply_subject = お問い合わせありがとうございます

# テンプレート設定
tmpl_input = @FILE:forms/myform/web_form.tpl.html
tmpl_conf = @FILE:forms/myform/web_confirm.tpl.html
tmpl_comp = @FILE:forms/myform/web_thanks.tpl.html
tmpl_mail_admin = @FILE:forms/myform/mail_receive.tpl.txt
tmpl_mail_reply = @FILE:forms/myform/mail_autoreply.tpl.txt
```

#### 方法B: チャンクとして作成

1. **エレメント** → **チャンク** → **新規チャンク**
2. チャンク名: `myform_config`
3. 上記の設定内容を貼り付け
4. **保存**

詳細な設定項目については [⚙️ 環境設定リファレンス](CONFIGURATION.md) を参照してください。

---

### ステップ4: テンプレートの作成

最低限、以下の5つのテンプレートが必要です。

```
forms/myform/
├── config.ini              # 環境設定
├── web_form.tpl.html       # 入力画面
├── web_confirm.tpl.html    # 確認画面
├── web_thanks.tpl.html     # 完了画面
├── mail_receive.tpl.txt    # 管理者宛メール
└── mail_autoreply.tpl.txt  # 自動返信メール
```

サンプルは `forms/sample/` にあります。コピーしてカスタマイズしてください。

詳細は [🎨 テンプレート作成ガイド](TEMPLATE_GUIDE.md) を参照してください。

---

### ステップ5: ドキュメントでの使用

フォームを表示したいMODXドキュメントに以下のスニペットコールを記述します。

#### ファイル形式の設定を使用する場合:

```php
[!cfFormMailer?&config=`@FILE:forms/myform/config.ini`!]
```

#### チャンク形式の設定を使用する場合:

```php
[!cfFormMailer?&config=`myform_config`!]
```

**重要**:
- `!`マークを付けてキャッシュ無効化すること（`[!...!]`）
- `config`パラメータは必須

---

## オプション機能のセットアップ

### ファイルアップロード機能

ユーザーがファイルをアップロードして送信できるようにします。

#### 1. class.upload.phpのインストール

```bash
# 1. ダウンロード
wget http://www.verot.net/php_class_upload_download.htm

# 2. 解凍して配置
cp class.upload.php /assets/snippets/cfFormMailer/includes/

# 3. パーミッション設定
chmod 644 /assets/snippets/cfFormMailer/includes/class.upload.php
```

#### 2. cfFileViewプラグインのインストール

確認画面でアップロードされたファイルを表示するために必要です。

1. **エレメント** → **プラグイン** → **新規プラグイン**
2. 以下の情報を入力:

   | 項目 | 値 |
   |------|-----|
   | **プラグイン名** | `cfFileView` |
   | **プラグインコード** | `extras/plugin.cfFileView.php`の内容 |

3. **システムイベント** タブをクリック
4. **OnPageNotFound** にチェック
5. **保存**

#### 3. テンプレートでの使用

**入力画面**:
```html
<input type="file" name="photo" valid=":allowtype(gif|jpg|png),allowsize(5000)" />
```

**確認画面**:
```html
<img src="cfFileView?field=photo" alt="アップロードされた画像" />
```

詳細は [📤 ファイルアップロード](FILE_UPLOAD.md) を参照してください。

---

### データベース保存機能

フォーム送信内容をデータベースに記録します。

#### 1. cfFormDBモジュールのインストール

別途、cfFormDBモジュールをインストールしてください。

#### 2. 設定ファイルで有効化

```ini
# config.ini
use_store_db = 1
```

---

### CAPTCHA画像認証

スパム対策として画像認証を追加します。

#### 設定

```ini
# config.ini
vericode = 1
```

#### テンプレート

```html
<!-- 入力画面 -->
<p>
  <label for="veri">認証コード</label>
  <img src="[+verimageurl+]" alt="認証コード" />
  <input type="text" name="veri" id="veri" valid="1:vericode" />
</p>
```

---

## バージョンアップ

### v1.6からv1.7へ

```bash
# 1. ファイルを置き換え
cp -r new_version/* /assets/snippets/cfFormMailer/

# 2. スニペットコードを更新
# MODX管理画面で cfFormMailer スニペットを開き、
# 新しい snippet.cfFormMailer.php の内容に置き換え
```

**互換性**: 設定ファイルとテンプレートはそのまま使用可能です。

---

### v1.3以前からv1.7へ

#### 重要な変更点

1. **設定ファイル形式が変更** (チャンク→.ini形式)
2. **サンプルファイルの配置が変更** (`forms/sample/`へ移動)
3. **独自検証メソッド・フィルターの定義方法が変更**

#### 移行手順

```bash
# 1. ファイルを置き換え
cp -r new_version/* /assets/snippets/cfFormMailer/

# 2. 設定ファイルを.ini形式に移行
# 旧: チャンクまたはテキストファイル
# 新: .ini形式（forms/sample/config.with_comment.iniを参照）

# 3. カスタム検証・フィルターを additionalMethods.inc.php に移動
```

---

### v2.0への移行（準備中）

v2.0では100%の後方互換性を維持しつつ、新しいアーキテクチャに移行します。

詳細は [🔄 移行ガイド](MIGRATION_GUIDE.md) を参照してください。

---

## トラブルシューティング

### 問題1: フォームが表示されない

**症状**: ページにスニペットコールを記述しても何も表示されない

**原因と解決策**:

1. **スニペット名が間違っている**
   ```php
   # NG
   [!cfformmailer?&config=`myform_config`!]

   # OK (大文字小文字を確認)
   [!cfFormMailer?&config=`myform_config`!]
   ```

2. **キャッシュが効いている**
   ```php
   # NG (キャッシュあり)
   [[cfFormMailer?&config=`myform_config`]]

   # OK (キャッシュなし)
   [!cfFormMailer?&config=`myform_config`!]
   ```

3. **ファイルパスが間違っている**
   ```bash
   # ファイルの存在確認
   ls -la /assets/snippets/cfFormMailer/includes/class.cfFormMailer.inc.php
   ```

---

### 問題2: "config パラメータは必須です" エラー

**症状**: エラーメッセージが表示される

**解決策**:

```php
# configパラメータを追加
[!cfFormMailer?&config=`@FILE:forms/myform/config.ini`!]
```

---

### 問題3: テンプレート読み込みエラー

**症状**: "tpl read error" と表示される

**原因と解決策**:

1. **ファイルパスが間違っている**
   ```ini
   # config.ini を確認
   tmpl_input = @FILE:forms/myform/web_form.tpl.html

   # 実際のファイル存在確認
   ls -la /assets/snippets/cfFormMailer/forms/myform/web_form.tpl.html
   ```

2. **パーミッションの問題**
   ```bash
   chmod 644 /assets/snippets/cfFormMailer/forms/myform/*.html
   ```

---

### 問題4: メールが送信されない

**症状**: フォーム送信は成功するがメールが届かない

**原因と解決策**:

1. **デバッグモードを有効化して調査**
   ```ini
   # config.ini
   debug_mode = 1  # デバッグモード有効化
   ```
   
   詳細は [🐛 デバッグモード](DEBUG.md) を参照してください。

2. **MODXのメール設定を確認**
   - MODX管理画面 → **システム設定** → **メール設定**
   - 送信者メールアドレス、SMTP設定等を確認

3. **ログを確認**
   ```php
   # MODX管理画面 → システム → イベントログ
   ```

4. **テスト用にsend_mailを一時的に無効化**
   ```ini
   # config.ini
   send_mail = 0  # メール送信を無効化（テスト用）
   use_store_db = 1  # DBに保存して確認
   ```

---

### 問題5: PHP 8.1+ で strftime() エラー

**症状**: `Deprecated: Function strftime() is deprecated`

**解決策**:

v1.7.0では部分対応済みです。v2.0で完全対応します。

一時的な対処:
```php
# php.ini
error_reporting = E_ALL & ~E_DEPRECATED
```

または v2.0への移行をご検討ください。

---

### 問題6: ファイルアップロードができない

**症状**: ファイルアップロードすると "SAFE Mode Restriction in effect" エラー

**解決策**:

```ini
# config.ini
upload_tmp_path = 1  # スニペットディレクトリ内のtmpを使用

# tmpディレクトリのパーミッション設定
mkdir /assets/snippets/cfFormMailer/tmp
chmod 777 /assets/snippets/cfFormMailer/tmp
```

---

## サポート

### ドキュメント

- [⚙️ 環境設定リファレンス](CONFIGURATION.md)
- [🎨 テンプレート作成ガイド](TEMPLATE_GUIDE.md)
- [⚡ 機能解説](FEATURES.md)
- [🐛 デバッグモード](DEBUG.md)
- [❓ FAQ](FAQ.md)

### コミュニティ

- [GitHub Issues](https://github.com/yama/cfFormMailer/issues)
- [MODX公式フォーラム](https://modx.jp/)
- [ブログ：網的脚本実験室](http://www.clefarray-web.net/blog/)

---

**最終更新**: 2025-11-18
