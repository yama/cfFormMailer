# v2.0 UX改善提案書

**作成日**: 2025-11-18
**対象**: cfFormMailer v2.0

15年前の設計を見直し、現代的な開発体験（DX）とユーザー体験（UX）に改善します。

---

## 📋 目次

1. [現在の問題点](#現在の問題点)
2. [改善方針](#改善方針)
3. [提案1: HTML5ネイティブ検証との統合](#提案1-html5ネイティブ検証との統合)
4. [提案2: PHPでの検証ルール定義](#提案2-phpでの検証ルール定義)
5. [提案3: モダンなテンプレート記法](#提案3-モダンなテンプレート記法)
6. [提案4: JSON APIモード](#提案4-json-apiモード)
7. [提案5: フォームビルダーDSL](#提案5-フォームビルダーdsl)
8. [後方互換性の維持](#後方互換性の維持)

---

## 現在の問題点

### 問題1: HTML属性による検証定義が冗長

**現在 (v1.7.x)**:
```html
<!-- HTMLが汚れる -->
<input type="text" name="email" valid="1:email,len(-255)" id="email" />
<input type="text" name="age" valid="1:num,range(18~120)" />
<input type="text" name="tel" valid="1:tel" />
```

**課題**:
- HTML属性が冗長で読みづらい
- `valid="1:email,len(-255)"` の記法が直感的でない
- バリデーションロジックがHTMLに混在
- IDEのコード補完が効かない
- HTML5標準の`required`, `type="email"`等と重複

---

### 問題2: 独自のプレースホルダ記法

**現在 (v1.7.x)**:
```html
<!-- 独自記法 -->
[+name+]
[+email+]
[+errors|implodetag(li)+]
```

**課題**:
- MODXの`[[placeholder]]`とも異なる独自記法
- 他のテンプレートエンジン（Twig, Blade等）と非互換
- 学習コストが高い

---

### 問題3: カスタムHTMLタグ

**現在 (v1.7.x)**:
```html
<!-- 不正なHTML -->
<iferror.email>
  <p class="error">メールアドレスを正しく入力してください</p>
</iferror>
```

**課題**:
- HTMLとして不正（カスタムタグ）
- HTMLバリデータでエラーになる
- エディタの補完が効かない

---

### 問題4: テンプレート管理の複雑さ

**現在 (v1.7.x)**:
```ini
# config.ini
tmpl_input = @FILE:forms/sample/web_form.tpl.html
tmpl_conf = @FILE:forms/sample/web_confirm.tpl.html
tmpl_comp = @FILE:forms/sample/web_thanks.tpl.html
tmpl_mail_admin = @FILE:forms/sample/mail_receive.tpl.txt
tmpl_mail_reply = @FILE:forms/sample/mail_autoreply.tpl.txt
```

**課題**:
- 5つのテンプレートファイルを個別管理
- パスの管理が煩雑
- コンポーネント化できない

---

### 問題5: SPAフレームワークとの統合が困難

**課題**:
- Vue.js, React等との連携が困難
- JSON APIが提供されていない
- サーバーサイドレンダリング前提

---

## 改善方針

### 基本方針

1. **HTML5標準を優先する**
   - 標準属性（`required`, `type="email"`, `pattern`等）を最大限活用
   - カスタム属性は最小限に

2. **関心の分離**
   - 検証ロジックはPHPで定義
   - HTMLはマークアップに専念

3. **開発者体験（DX）の向上**
   - IDEのコード補完が効く
   - 型安全な記述が可能
   - 学習コストの低減

4. **段階的移行を可能に**
   - v1.7.xの記法も100%サポート（後方互換）
   - 新旧併用可能
   - プロジェクトごとに選択可能

---

## 提案1: HTML5ネイティブ検証との統合

### アプローチA: HTML5属性を優先解釈

**Before (v1.7.x)**:
```html
<input type="text" name="email" valid="1:email" />
```

**After (v2.0) - Option 1: HTML5標準**:
```html
<!-- HTML5標準属性だけで検証 -->
<input type="email" name="email" required maxlength="255" />
```

cfFormMailerが自動的に解釈:
- `type="email"` → メールアドレス形式を検証
- `required` → 入力必須
- `maxlength="255"` → 最大255文字

---

### アプローチB: data-*属性で拡張検証

```html
<!-- HTML5標準 + data-*属性で拡張 -->
<input
  type="text"
  name="age"
  required
  pattern="[0-9]+"
  data-validate="num,range(18~120)"
  data-error-message="18歳以上120歳以下の数値を入力してください"
/>
```

**メリット**:
- HTMLとして正規
- HTMLバリデータでエラーにならない
- IDEのサポートを受けられる
- `valid`属性も引き続きサポート（後方互換）

---

### アプローチC: クラスベースの検証指定

```html
<!-- クラスで検証ルールを指定 -->
<input
  type="email"
  name="email"
  class="validate-required validate-email validate-maxlen-255"
/>
```

CSSフレームワーク（Bootstrap, Tailwind等）との親和性が高い。

---

## 提案2: PHPでの検証ルール定義

HTMLを汚さず、プログラマティックに検証を定義します。

### アプローチA: 設定ファイルでの定義

**config/validation.php** (新規):
```php
<?php
return [
    'contact_form' => [
        'name' => [
            'required' => true,
            'maxLength' => 100,
            'label' => 'お名前',
        ],
        'email' => [
            'required' => true,
            'email' => true,
            'maxLength' => 255,
            'label' => 'メールアドレス',
        ],
        'age' => [
            'required' => true,
            'numeric' => true,
            'min' => 18,
            'max' => 120,
            'label' => '年齢',
        ],
        'tel' => [
            'required' => false,
            'tel' => true,
            'label' => '電話番号',
        ],
    ],
];
```

**使用例**:
```php
// スニペットコール
[!cfFormMailer?
  &config=`@FILE:forms/contact/config.ini`
  &validation=`contact_form`
!]
```

**HTMLはシンプルに**:
```html
<!-- 検証ルールは不要 -->
<input type="text" name="name" />
<input type="email" name="email" />
<input type="number" name="age" />
```

---

### アプローチB: フルイドバリデーション

**forms/contact/validation.php**:
```php
<?php
use CfFormMailer\Validation\FormValidator;

return function(FormValidator $validator) {
    $validator
        ->field('name')
            ->required()
            ->maxLength(100)
            ->label('お名前')
        ->field('email')
            ->required()
            ->email()
            ->maxLength(255)
            ->label('メールアドレス')
        ->field('age')
            ->required()
            ->numeric()
            ->min(18)
            ->max(120)
            ->label('年齢')
        ->field('tel')
            ->tel()
            ->label('電話番号');
};
```

**メリット**:
- IDEのコード補完が効く
- 型安全
- メソッドチェーンで直感的
- Laravel Validationに似た記法

---

### アプローチC: 属性クラスによる定義 (PHP 8.0+)

**forms/contact/ContactForm.php**:
```php
<?php
namespace App\Forms;

use CfFormMailer\Validation\Rules;

class ContactForm
{
    #[Rules\Required]
    #[Rules\MaxLength(100)]
    public string $name;

    #[Rules\Required]
    #[Rules\Email]
    #[Rules\MaxLength(255)]
    public string $email;

    #[Rules\Required]
    #[Rules\Numeric]
    #[Rules\Range(min: 18, max: 120)]
    public int $age;

    #[Rules\Tel]
    public ?string $tel = null;
}
```

**メリット**:
- 最もモダン
- 型安全性が最高
- 静的解析ツールのサポート

---

## 提案3: モダンなテンプレート記法

### オプション1: Twig対応

```twig
{# templates/contact/form.twig #}
<form action="{{ url }}" method="post">
  <div>
    <label for="name">お名前</label>
    <input type="text" name="name" id="name" value="{{ form.name }}" />

    {% if errors.name %}
      <ul class="errors">
        {% for error in errors.name %}
          <li>{{ error }}</li>
        {% endfor %}
      </ul>
    {% endif %}
  </div>

  <div>
    <label for="email">メールアドレス</label>
    <input type="email" name="email" id="email" value="{{ form.email }}" />

    {% if errors.email %}
      <ul class="errors">
        {% for error in errors.email %}
          <li>{{ error }}</li>
        {% endfor %}
      </ul>
    {% endif %}
  </div>

  <button type="submit">送信</button>
</form>
```

**メリット**:
- 業界標準のテンプレートエンジン
- 豊富な機能（継承、インクルード等）
- 他のフレームワークとの知識共有

---

### オプション2: MODXネイティブ記法

```html
<!-- 標準のMODX記法 -->
<input type="text" name="name" value="[[+form.name]]" />

[[+errors.name:notempty=`
  <ul class="errors">
    [[+errors.name]]
  </ul>
`]]
```

**メリット**:
- MODX標準の記法
- 学習コストが低い

---

### オプション3: Vue.js / Reactコンポーネント

```vue
<!-- ContactForm.vue -->
<template>
  <form @submit.prevent="handleSubmit">
    <div>
      <label for="name">お名前</label>
      <input
        type="text"
        v-model="form.name"
        :class="{ 'is-invalid': errors.name }"
      />
      <ul v-if="errors.name" class="errors">
        <li v-for="error in errors.name">{{ error }}</li>
      </ul>
    </div>

    <div>
      <label for="email">メールアドレス</label>
      <input
        type="email"
        v-model="form.email"
        :class="{ 'is-invalid': errors.email }"
      />
      <ul v-if="errors.email" class="errors">
        <li v-for="error in errors.email">{{ error }}</li>
      </ul>
    </div>

    <button type="submit">送信</button>
  </form>
</template>

<script setup>
import { ref } from 'vue'
import { useCfFormMailer } from '@cfformmailer/vue'

const { form, errors, submit } = useCfFormMailer('contact_form')

const handleSubmit = async () => {
  const result = await submit()
  if (result.success) {
    // 完了処理
  }
}
</script>
```

---

## 提案4: JSON APIモード

SPA / モダンフロントエンドとの統合を可能にします。

### API仕様

**エンドポイント**:
```
POST /assets/snippets/cfFormMailer/api/v2/forms/{formName}/validate
POST /assets/snippets/cfFormMailer/api/v2/forms/{formName}/submit
```

**リクエスト例**:
```javascript
// フロントエンド (Vue.js / React / 素のJS)
const response = await fetch('/assets/snippets/cfFormMailer/api/v2/forms/contact/validate', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-CSRF-Token': csrfToken,
  },
  body: JSON.stringify({
    name: 'John Doe',
    email: 'john@example.com',
    age: 30,
  }),
})

const result = await response.json()
```

**レスポンス例**:
```json
{
  "valid": false,
  "errors": {
    "email": [
      "メールアドレスの形式が正しくありません"
    ]
  },
  "fields": {
    "name": {
      "value": "John Doe",
      "valid": true
    },
    "email": {
      "value": "john@example.com",
      "valid": false
    }
  }
}
```

---

## 提案5: フォームビルダーDSL

コード不要でフォームを定義できるDSL (Domain Specific Language) を提供します。

### YAML形式

**forms/contact/form.yaml**:
```yaml
form:
  name: contact_form
  method: POST

fields:
  - name: name
    type: text
    label: お名前
    required: true
    maxLength: 100

  - name: email
    type: email
    label: メールアドレス
    required: true
    maxLength: 255

  - name: age
    type: number
    label: 年齢
    required: true
    min: 18
    max: 120

  - name: message
    type: textarea
    label: お問い合わせ内容
    required: true
    rows: 10
    maxLength: 5000

mail:
  admin:
    to: admin@example.com
    subject: お問い合わせがありました
    template: mail_admin

  autoreply:
    enabled: true
    subject: お問い合わせを受け付けました
    template: mail_autoreply

templates:
  input: templates/contact/input.twig
  confirm: templates/contact/confirm.twig
  complete: templates/contact/complete.twig
```

**使用**:
```php
[!cfFormMailer?&form=`@YAML:forms/contact/form.yaml`!]
```

自動的にHTMLフォームを生成。

---

## 提案6: プリセットテーマ

Bootstrap, Tailwind CSS等のCSSフレームワークに対応したプリセットを提供。

### 使用例

```php
// Bootstrap 5
[!cfFormMailer?
  &config=`contact_form`
  &theme=`bootstrap5`
!]

// Tailwind CSS
[!cfFormMailer?
  &config=`contact_form`
  &theme=`tailwind`
!]

// Material Design
[!cfFormMailer?
  &config=`contact_form`
  &theme=`material`
!]
```

自動的にクラス名が付与されたHTMLを生成。

---

## 後方互換性の維持

### v1.7.x記法も完全サポート

```html
<!-- v1.7.x記法（そのまま動作） -->
<input type="text" name="email" valid="1:email" />
[+email+]
<iferror.email>エラー</iferror>
```

### 新旧記法の併用も可能

```html
<!-- HTML5標準 -->
<input type="email" name="email" required />

<!-- v1.7.x記法 -->
<input type="text" name="tel" valid=":tel" />

<!-- data-*属性 -->
<input type="text" name="age" data-validate="num,range(18~120)" />
```

すべて同じフォーム内で動作します。

---

## 推奨される移行パス

### Phase 1: v1.7.x互換モード（既存ユーザー）

- 既存の記法をそのまま使用
- v2.0の新機能を段階的に採用

### Phase 2: ハイブリッドモード

- 新規フィールドはHTML5 + data-*属性で記述
- 既存フィールドはv1.7.x記法のまま

### Phase 3: 完全移行（新規プロジェクト）

- HTML5ネイティブ検証
- PHPでの検証ルール定義
- Twig / Vue.jsテンプレート

---

## 実装優先度

### 🔥 Phase 1 (必須)

- [ ] HTML5標準属性の自動解釈
- [ ] `data-validate`属性サポート
- [ ] PHPでの検証ルール定義（配列形式）
- [ ] v1.7.x完全互換モード

### ⭐ Phase 2 (推奨)

- [ ] Twigテンプレートエンジン対応
- [ ] フルイドバリデーション
- [ ] JSON APIモード
- [ ] プリセットテーマ（Bootstrap, Tailwind）

### 💡 Phase 3 (拡張)

- [ ] Vue.js / Reactコンポーネント
- [ ] PHP 8.0属性クラス
- [ ] YAML DSL
- [ ] GraphQL API

---

## 推奨事項

**v2.0で優先実装すべき機能**:

1. ✅ **HTML5属性の自動解釈** - 最も学習コストが低い
2. ✅ **`data-validate`属性** - HTML標準準拠
3. ✅ **PHPでの検証定義** - 関心の分離
4. ✅ **Twig対応** - 業界標準
5. ✅ **JSON APIモード** - SPAフレームワーク連携

**後回しでも良い機能**:

- Vue.js専用コンポーネント（需要次第）
- YAML DSL（YAMLパーサーの依存が増える）
- GraphQL API（オーバースペック）

---

## まとめ

15年前の設計を、現代の標準に合わせてアップデートします。

**重要な方針**:
- ✅ HTML5標準を優先
- ✅ 関心の分離（HTML/PHP）
- ✅ 100%後方互換性維持
- ✅ 段階的移行を可能に

これにより、既存ユーザーに影響を与えず、新規ユーザーには現代的な開発体験を提供できます。

---

**次のステップ**:
1. この提案書をレビュー
2. 実装する機能の優先順位を決定
3. ARCHITECTURE.mdで詳細設計
4. Phase 1から実装開始

---

**文書バージョン**: 1.0
**最終更新**: 2025-11-18
**作成者**: Claude (Sonnet 4.5)
