# cfFormMailer - Project Context

**最終更新**: 2025-11-18

プロジェクト全体のコンテキスト情報を一元管理します。AIエージェントや新規参加者が最初に読むべきドキュメントです。

---

## 🎯 プロジェクトミッション

MODX Evolutionで動作する、**現代的で保守性の高いメールフォームスニペット**を提供する。

15年前の設計を引きずる legacy codebase を、PHP 8.4対応・型安全・テスタブルな modern architecture にリファクタリングしつつ、**100%の後方互換性**を維持する。

---

## 📊 プロジェクト概要

| 項目 | 内容 |
|------|------|
| **プロジェクト名** | cfFormMailer |
| **種類** | MODX Evolution Snippet (メールフォーム) |
| **現在のバージョン** | v1.7.0 (stable) |
| **次期バージョン** | v2.0.0 (in development) |
| **作成年** | ~2007年 (約15年前) |
| **主要言語** | PHP |
| **対応PHP** | 7.4 ～ 8.4 |
| **文字コード** | UTF-8 |
| **ライセンス** | GPL |
| **作者** | Clefarray Factory |
| **公式サイト** | [網的脚本実験室](http://www.clefarray-web.net/blog/) |

---

## 🏗️ プロジェクトの歴史

### タイムライン

```
2007年頃  v0.0.3 初期バージョン
2007年    v0.0.7 HTMLメール対応、tel検証追加
2010年    v1.0   ファイルアップロード対応
2011年    v1.2   cfFormDB連携、url/convert検証追加
2013年    v1.3   動的送信先、CC/BCC対応
2015年    v1.4   MODxMailer直接使用
2020年    v1.6   PHP 7対応、.ini設定ファイル形式
2025年    v1.7.0 strftime部分対応、コードクリーンアップ
```

### 技術スタック変遷

| 時代 | PHP | 設計 | 特徴 |
|------|-----|------|------|
| **2007～2015** | PHP 5.x | 単一クラス | 機能追加でファイル肥大化 |
| **2015～2020** | PHP 5.6～7.2 | 同上 | PHP 7移行対応 |
| **2020～2025** | PHP 7.4～8.1 | 同上 | .ini形式導入、strftime問題 |
| **2025～** | PHP 7.4～8.4 | **モダンアーキテクチャ** | **リファクタリング** |

---

## 🎨 アーキテクチャ変遷

### v1.7.x (現在 - Legacy)

```
                includes/class.cfFormMailer.inc.php
                          (2,481行)
                              │
        ┌──────────────────────┼──────────────────────┐
        │                      │                      │
   フォーム処理            入力検証              メール送信
   (renderForm等)        (_def_email等)      (sendAdminMail等)
        │                      │                      │
   テンプレート処理      ファイルアップロード    データベース保存
   (replacePlaceHolder等) (_getMimeType等)      (storeDB)
```

**問題点**:
- 単一クラスに全責務が集中（2,481行）
- テスト不可能（グローバル変数依存）
- 型安全性なし
- 保守困難

---

### v2.0 (目標 - Modern)

```
                     FormProcessor
                      (フロー制御)
                          │
        ┌─────────────────┼─────────────────┐
        │                 │                 │
   FormValidator      MailSender      TemplateEngine
        │                 │                 │
   ┌────┴────┐      ┌────┴────┐      ┌────┴────┐
EmailRule  NumRule  AdminMail  Auto   Placeholder Filters
LenRule    TelRule  Builder   Reply   Resolver    ...
...                 Builder
```

**改善点**:
- ✅ 責務別クラス分離（各100-300行）
- ✅ 依存性注入（DI）
- ✅ 厳格な型宣言
- ✅ 単体テスト90%+
- ✅ PHP 7.4～8.4完全対応

---

## 📁 ディレクトリ構造

### 現在 (v1.7.x)

```
cfFormMailer/
├── includes/
│   ├── class.cfFormMailer.inc.php  ← 全ロジック（2,481行）
│   └── bootstrap.php
├── extras/
│   ├── additionalMethods.inc.php   ← カスタム検証・フィルター
│   └── plugin.cfFileView.php
├── forms/sample/                    ← サンプルテンプレート
├── docs/                            ← ドキュメント（新規追加）
└── README.md
```

### 目標 (v2.0)

```
cfFormMailer/
├── src/                            ← 新コード (PSR-4)
│   ├── Core/
│   ├── Validation/Rules/           ← 19+ 検証ルール
│   ├── Mail/
│   ├── Template/Filters/
│   ├── Upload/                     ← 自作FileUploader
│   ├── Database/
│   └── Legacy/                     ← 後方互換ラッパー
├── tests/                          ← PHPUnit
│   ├── Unit/
│   └── Integration/
├── config/                         ← PHP設定ファイル
├── templates/                      ← Twigテンプレート
├── vendor/                         ← Composer
├── includes/                       ← v1.7.x互換用
├── docs/                           ← 充実したドキュメント
├── composer.json
└── phpunit.xml
```

---

## 🔑 主要コンポーネント

### v2.0で実装する主要クラス

| レイヤー | クラス | 責務 | 行数目安 |
|---------|--------|------|---------|
| **Core** | FormProcessor | フォーム処理フロー統括 | 150-200 |
| | ConfigLoader | .ini設定読込・検証 | 100-150 |
| | SessionManager | セッション・CSRF管理 | 100 |
| **Validation** | FormValidator | 検証統括 | 150-200 |
| | EmailRule | メールアドレス検証 | 50-80 |
| | NumericRule | 数値検証 | 50 |
| | ... | (計19個のルール) | 各50-100 |
| **Mail** | MailSender | メール送信統括 | 100-150 |
| | AdminMailBuilder | 管理者宛メール構築 | 100-150 |
| | AutoReplyMailBuilder | 自動返信メール構築 | 100-150 |
| **Template** | TemplateEngine | テンプレート処理統括 | 150-200 |
| | PlaceholderResolver | [+placeholder+] 置換 | 100-150 |
| | ImplodeFilter | 配列結合フィルター | 30-50 |
| | ... | (計5-10個のフィルター) | 各30-50 |
| **Upload** | FileUploader | ファイルアップロード | 100-150 |
| | MimeTypeDetector | MIME判定 | 80-100 |
| | FileValidator | ファイル検証 | 100 |

**合計**: 約3,000-4,000行（旧2,481行 → クラス分割で多少増えるが保守性向上）

---

## 🎯 リファクタリング目標

### 技術的目標

- [x] **PHP 7.4～8.4完全対応**
  - [x] `strftime()` 完全削除
  - [x] 動的プロパティ問題解消
  - [x] 厳格な型宣言
- [ ] **責務別クラス分離**（Phase 2-5）
  - [ ] Validation分離（Phase 2）
  - [ ] Template分離（Phase 3）
  - [ ] Mail分離（Phase 4）
  - [ ] Core統合（Phase 5）
- [ ] **テストカバレッジ90%以上**
- [ ] **100%後方互換性維持**
- [ ] **外部依存削減**（class.upload.php → 自作FileUploader）

### UX/DX目標

- [ ] **HTML5標準準拠**（`required`, `type="email"` 等）
- [ ] **data-*属性による検証**（HTML汚染回避）
- [ ] **PHPでの検証ルール定義**（関心の分離）
- [ ] **Twigテンプレート対応**
- [ ] **JSON APIモード**（SPA連携）
- [ ] **プリセットテーマ**（Bootstrap, Tailwind）

---

## 📚 ドキュメント体系

### コア設計ドキュメント

| ドキュメント | 内容 | 対象読者 |
|------------|------|---------|
| **REFACTORING_PROPOSAL.md** | リファクタリング全体設計 | 開発者 |
| **USER_EXPERIENCE_PROPOSAL.md** | UX/DX改善提案 | 開発者・ユーザー |
| **FILE_UPLOAD_REDESIGN.md** | ファイルアップロード再設計 | 開発者 |
| **AGENTS.md** | AI開発者向けガイド | AI |
| **PROJECT_CONTEXT.md** | 本ドキュメント | 全員 |

### ユーザー向けドキュメント

| ドキュメント | 内容 |
|------------|------|
| **README.md** | プロジェクト概要 |
| **INSTALLATION.md** | インストール手順 |
| **MIGRATION_GUIDE.md** | v1.7→v2.0移行 |
| **CHANGELOG.md** | 更新履歴 |

### 開発者向けドキュメント（予定）

- **ARCHITECTURE.md** - クラス図・シーケンス図
- **CODING_STANDARDS.md** - コーディング規約詳細
- **TESTING_GUIDE.md** - テスト方針・実装
- **API_REFERENCE.md** - 新API仕様
- **CONFIGURATION.md** - 全設定項目リファレンス
- **TEMPLATE_GUIDE.md** - テンプレート作成ガイド
- **FEATURES.md** - 機能詳細解説

---

## 🔧 開発環境

### 必須

- **PHP**: 7.4 ～ 8.4
- **Composer**: 2.x
- **MODX Evolution**: 1.0.x以降

### 推奨

- **PHPUnit**: ^9.5 (テスト)
- **PHPStan**: ^1.10 (静的解析)
- **IDE**: VSCode / PhpStorm / Cursor
- **AI Assistant**: Claude / GitHub Copilot / Cursor AI

### セットアップ

```bash
# Composerインストール
composer install

# Autoload生成
composer dump-autoload

# テスト実行
vendor/bin/phpunit

# 静的解析
vendor/bin/phpstan analyse src
```

---

## 🌊 開発フロー

### Phase別実装計画

```
Phase 1: 環境準備（完了）
  ├─ Composer導入
  ├─ PSR-4 autoload設定
  └─ ディレクトリ構造作成

Phase 2: Validation分離（🔥最優先）
  ├─ ValidationRuleInterface
  ├─ 19個のルールクラス化
  ├─ FormValidator
  └─ 単体テスト作成

Phase 3: Template分離
  ├─ TemplateEngine
  ├─ PlaceholderResolver
  └─ Filters実装

Phase 4: Mail分離
  ├─ MailSender
  ├─ AdminMailBuilder
  └─ AutoReplyMailBuilder

Phase 5: Core統合
  ├─ FormProcessor
  ├─ ConfigLoader
  └─ SessionManager

Phase 6: Upload実装
  ├─ FileUploader（class.upload.php置換）
  ├─ MimeTypeDetector
  └─ FileValidator

Phase 7: 後方互換性
  └─ Legacy/Class_cfFormMailer.php
```

---

## 🎨 主要な設計パターン

### 使用パターン

1. **Strategy Pattern** - 検証ルール、フィルター
2. **Factory Pattern** - RuleFactory, FilterRegistry
3. **DTO Pattern** - ValidationResult, UploadedFile
4. **Dependency Injection** - コンストラクタインジェクション
5. **Interface Segregation** - 小さなインターフェース

### 避けるパターン

- ❌ Singleton（テスト困難）
- ❌ Global State（グローバル変数）
- ❌ God Object（巨大クラス）

---

## 🔐 セキュリティ方針

### 重要な対策

1. **入力検証**: すべての外部入力を検証
2. **出力エスケープ**: HTML出力は必ずエスケープ
3. **CSRFトークン**: フォーム送信時に検証
4. **ファイルアップロード**:
   - MIMEタイプ検証（拡張子偽装対策）
   - ランダムファイル名生成
   - サイズ制限
5. **SQLインジェクション**: プリペアドステートメント

---

## 📊 メトリクス・KPI

### 目標値

| 指標 | 現在(v1.7) | 目標(v2.0) |
|------|-----------|-----------|
| 最大クラス行数 | 2,481行 | 300行以下 |
| 単体テストカバレッジ | 0% | 90%以上 |
| 型宣言率 | 0% | 100% |
| 循環的複雑度（平均） | 12 | 5以下 |
| 重複コード率 | 25% | 5%以下 |
| PHP対応バージョン | ～8.1（部分） | 7.4～8.4（完全） |

---

## 🔗 関連リソース

### 外部リンク

- **公式サイト**: [網的脚本実験室](http://www.clefarray-web.net/blog/)
- **MODX公式**: [MODX Evolution](https://modx.com/)
- **MODX日本語フォーラム**: [modx.jp](https://modx.jp/)

### 依存ライブラリ

- **MODxMailer** (MODX内蔵) - メール送信
- **PHPMailer** (MODxMailer経由) - SMTP対応

### 置き換え予定

- **class.upload.php** → 自作FileUploader（軽量化）

---

## 👥 ステークホルダー

### 対象ユーザー

1. **エンドユーザー**: ウェブサイト訪問者（フォーム利用者）
2. **サイト管理者**: MODX運用者（メールフォーム設置）
3. **開発者**: カスタマイズ実装者
4. **AI開発者**: コード生成・リファクタリング支援

### コミュニティ

- GitHub Issues
- MODX公式フォーラム
- ブログコメント

---

## 🚀 次のマイルストーン

### 短期（1-2週間）

- [ ] Phase 2: Validation分離完了
- [ ] 単体テスト基盤構築
- [ ] EmailRule, NumericRule等の実装

### 中期（1-2ヶ月）

- [ ] Phase 3-4: Template, Mail分離
- [ ] FileUploader実装
- [ ] Twigテンプレート対応

### 長期（3-6ヶ月）

- [ ] v2.0 正式リリース
- [ ] 完全なドキュメント整備
- [ ] コミュニティフィードバック対応

---

## 📝 まとめ

cfFormMailerは**15年の歴史を持つ成熟プロジェクト**です。

v2.0リファクタリングの目標は：
- ✅ **技術的負債の解消**（2,481行クラス → モダンアーキテクチャ）
- ✅ **PHP 8.4完全対応**（型安全、最新機能活用）
- ✅ **100%後方互換性**（既存ユーザーに影響なし）
- ✅ **開発者体験向上**（HTML5標準、Twig、JSON API）

段階的リファクタリングにより、リスクを最小化しながら modernization を進めます。

---

**文書バージョン**: 1.0
**最終更新**: 2025-11-18
**作成者**: Claude (Sonnet 4.5)
