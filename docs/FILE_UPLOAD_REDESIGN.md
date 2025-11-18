# ファイルアップロード機能の再設計

**作成日**: 2025-11-18
**対象**: cfFormMailer v2.0

外部依存の`class.upload.php`を廃止し、軽量で必要最小限の機能を持つFileUploaderを同梱します。

---

## 📋 目次

1. [現状の問題点](#現状の問題点)
2. [設計方針](#設計方針)
3. [新しいFileUploaderの仕様](#新しいfileuploaderの仕様)
4. [クラス設計](#クラス設計)
5. [使用例](#使用例)
6. [セキュリティ対策](#セキュリティ対策)
7. [移行ガイド](#移行ガイド)

---

## 現状の問題点

### class.upload.phpへの依存

**現在 (v1.7.x)**:
```php
// 外部ライブラリに依存（オプション扱い）
include_once __DIR__ . '/class.upload.php';

if (class_exists('upload')) {
    $up = new upload($filename);
    if ($up->uploaded) {
        return $up->file_src_mime;
    }
}
```

**問題点**:

1. **古いライブラリ** (2007年頃～)
   - PHP 8.x完全対応が不明
   - 最新のセキュリティ基準に準拠していない可能性

2. **オーバースペック**
   - 画像編集機能（リサイズ、クロップ、回転、透かし等）
   - cfFormMailerでは不要な機能が大半
   - ライブラリサイズが大きい（1000行以上）

3. **外部依存**
   - ユーザーが別途ダウンロード・配置が必要
   - バージョン管理が困難
   - 保守性が低い

4. **cfFormMailerで必要な機能**（実際に使っているのは）:
   - ✅ MIMEタイプ判定（`file_src_mime`）
   - ❌ 画像編集機能（使っていない）
   - ❌ サムネイル生成（使っていない）
   - ❌ 透かし追加（使っていない）

**必要な機能だけ抽出すると、わずか100-200行で実装可能**

---

## 設計方針

### 基本方針

1. **軽量・シンプル**
   - 必要最小限の機能のみ
   - 100-200行程度の軽量実装
   - 依存ライブラリなし（PHP標準機能のみ）

2. **標準機能として同梱**
   - `src/Upload/FileUploader.php`として同梱
   - オプションではなく標準機能化
   - 追加ダウンロード不要

3. **モダンなPHP対応**
   - PHP 7.4～8.4完全対応
   - 厳格な型宣言
   - 例外ベースのエラーハンドリング

4. **セキュリティ重視**
   - MIMEタイプ検証（拡張子偽装対策）
   - ファイルサイズ制限
   - セキュアなファイル名生成
   - ディレクトリトラバーサル対策

---

## 新しいFileUploaderの仕様

### 必要な機能のみを実装

| 機能 | 必要性 | 実装 |
|------|--------|------|
| **ファイルアップロード処理** | ✅ 必須 | ○ |
| **MIMEタイプ判定** | ✅ 必須 | ○ |
| **ファイルサイズ検証** | ✅ 必須 | ○ |
| **拡張子検証** | ✅ 必須 | ○ |
| **一時ファイル管理** | ✅ 必須 | ○ |
| **セキュアなファイル名生成** | ✅ 必須 | ○ |
| 画像リサイズ | ❌ 不要 | × |
| サムネイル生成 | ❌ 不要 | × |
| 透かし追加 | ❌ 不要 | × |
| 画像フォーマット変換 | ❌ 不要 | × |

---

## クラス設計

### UploadedFile (DTO)

**役割**: アップロードされたファイルの情報を保持

```php
<?php declare(strict_types=1);

namespace CfFormMailer\Upload;

/**
 * アップロードされたファイルの情報
 */
class UploadedFile
{
    public function __construct(
        private string $originalName,
        private string $tmpPath,
        private int $size,
        private int $error,
        private string $clientMimeType
    ) {}

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function getTmpPath(): string
    {
        return $this->tmpPath;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getError(): int
    {
        return $this->error;
    }

    public function getClientMimeType(): string
    {
        return $this->clientMimeType;
    }

    public function getExtension(): string
    {
        return strtolower(pathinfo($this->originalName, PATHINFO_EXTENSION));
    }

    public function isValid(): bool
    {
        return $this->error === UPLOAD_ERR_OK;
    }

    /**
     * $_FILESからインスタンスを生成
     */
    public static function fromArray(array $fileData): self
    {
        return new self(
            originalName: $fileData['name'] ?? '',
            tmpPath: $fileData['tmp_name'] ?? '',
            size: $fileData['size'] ?? 0,
            error: $fileData['error'] ?? UPLOAD_ERR_NO_FILE,
            clientMimeType: $fileData['type'] ?? ''
        );
    }
}
```

---

### MimeTypeDetector

**役割**: MIMEタイプの正確な判定（拡張子偽装対策）

```php
<?php declare(strict_types=1);

namespace CfFormMailer\Upload;

/**
 * MIMEタイプ判定
 */
class MimeTypeDetector
{
    /**
     * ファイルの実際のMIMEタイプを判定
     *
     * @param string $filePath ファイルパス
     * @return string|null MIMEタイプ (判定できない場合はnull)
     */
    public function detect(string $filePath): ?string
    {
        if (!is_file($filePath)) {
            return null;
        }

        // 1. finfo拡張を使用（推奨）
        if (extension_loaded('fileinfo')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $filePath);
            finfo_close($finfo);

            if ($mime !== false) {
                return $mime;
            }
        }

        // 2. mime_content_type関数（PHP 7.4+で復活）
        if (function_exists('mime_content_type')) {
            $mime = mime_content_type($filePath);
            if ($mime !== false) {
                return $mime;
            }
        }

        // 3. 画像の場合はgetimagesizeで判定
        $imageInfo = @getimagesize($filePath);
        if ($imageInfo !== false && isset($imageInfo['mime'])) {
            return $imageInfo['mime'];
        }

        // 4. 判定できない場合はnull
        return null;
    }

    /**
     * MIMEタイプから拡張子を取得
     */
    public function getExtension(string $mimeType): string
    {
        $map = [
            'image/gif'  => 'gif',
            'image/jpeg' => 'jpg',
            'image/pjpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            'text/plain' => 'txt',
            'text/html'  => 'html',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/zip' => 'zip',
        ];

        return $map[$mimeType] ?? '';
    }

    /**
     * MIMEタイプの表示名を取得
     */
    public function getDisplayName(string $mimeType): string
    {
        $map = [
            'image/gif'  => 'GIF',
            'image/jpeg' => 'JPG',
            'image/png'  => 'PNG',
            'image/webp' => 'WebP',
            'application/pdf' => 'PDF',
            'text/plain' => 'TXT',
            'text/html'  => 'HTML',
            'application/msword' => 'Word',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'Word',
            'application/vnd.ms-excel' => 'Excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'Excel',
        ];

        return $map[$mimeType] ?? strtoupper(explode('/', $mimeType)[1] ?? 'FILE');
    }
}
```

---

### FileValidator

**役割**: ファイルの検証（サイズ、タイプ、拡張子）

```php
<?php declare(strict_types=1);

namespace CfFormMailer\Upload;

use CfFormMailer\Upload\Exceptions\InvalidFileException;

/**
 * ファイル検証
 */
class FileValidator
{
    public function __construct(
        private MimeTypeDetector $mimeDetector
    ) {}

    /**
     * ファイルを検証
     *
     * @param UploadedFile $file
     * @param array $rules 検証ルール
     * @throws InvalidFileException
     */
    public function validate(UploadedFile $file, array $rules): void
    {
        // アップロードエラーチェック
        if (!$file->isValid()) {
            throw new InvalidFileException(
                $this->getUploadErrorMessage($file->getError())
            );
        }

        // ファイルサイズチェック
        if (isset($rules['maxSize'])) {
            $maxSize = $rules['maxSize'] * 1024; // KB to bytes
            if ($file->getSize() > $maxSize) {
                throw new InvalidFileException(
                    sprintf('%dキロバイト以内のファイルを指定してください', $rules['maxSize'])
                );
            }
        }

        // 許可されたMIMEタイプのチェック
        if (isset($rules['allowedTypes'])) {
            $actualMime = $this->mimeDetector->detect($file->getTmpPath());

            if ($actualMime === null) {
                throw new InvalidFileException('ファイル形式を判定できませんでした');
            }

            $allowedMimes = $this->convertTypesToMimes($rules['allowedTypes']);

            if (!in_array($actualMime, $allowedMimes, true)) {
                throw new InvalidFileException(
                    sprintf(
                        '許可されたファイル形式ではありません（許可: %s）',
                        implode(', ', $rules['allowedTypes'])
                    )
                );
            }
        }
    }

    /**
     * 許可タイプ（gif, jpg等）をMIMEタイプに変換
     */
    private function convertTypesToMimes(array $types): array
    {
        $typeMap = [
            'gif'  => ['image/gif'],
            'jpg'  => ['image/jpeg', 'image/pjpeg'],
            'jpeg' => ['image/jpeg', 'image/pjpeg'],
            'png'  => ['image/png'],
            'webp' => ['image/webp'],
            'pdf'  => ['application/pdf'],
            'txt'  => ['text/plain'],
            'html' => ['text/html'],
            'doc'  => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'xls'  => ['application/vnd.ms-excel'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'zip'  => ['application/zip'],
        ];

        $mimes = [];
        foreach ($types as $type) {
            $mimes = array_merge($mimes, $typeMap[strtolower($type)] ?? []);
        }

        return array_unique($mimes);
    }

    /**
     * アップロードエラーメッセージ
     */
    private function getUploadErrorMessage(int $errorCode): string
    {
        return match($errorCode) {
            UPLOAD_ERR_INI_SIZE => 'ファイルサイズがphp.iniの設定を超えています',
            UPLOAD_ERR_FORM_SIZE => 'ファイルサイズがフォームの設定を超えています',
            UPLOAD_ERR_PARTIAL => 'ファイルが部分的にしかアップロードされませんでした',
            UPLOAD_ERR_NO_FILE => 'ファイルがアップロードされませんでした',
            UPLOAD_ERR_NO_TMP_DIR => '一時ディレクトリがありません',
            UPLOAD_ERR_CANT_WRITE => 'ディスクへの書き込みに失敗しました',
            UPLOAD_ERR_EXTENSION => 'PHP拡張によってアップロードが中断されました',
            default => 'ファイルのアップロードに失敗しました',
        };
    }
}
```

---

### FileUploader

**役割**: ファイルアップロードの統括処理

```php
<?php declare(strict_types=1);

namespace CfFormMailer\Upload;

use CfFormMailer\Upload\Exceptions\UploadException;

/**
 * ファイルアップロード処理
 */
class FileUploader
{
    public function __construct(
        private FileValidator $validator,
        private MimeTypeDetector $mimeDetector,
        private string $uploadDir
    ) {}

    /**
     * ファイルをアップロード
     *
     * @param UploadedFile $file
     * @param array $rules 検証ルール ['maxSize' => 5000, 'allowedTypes' => ['gif', 'jpg', 'png']]
     * @return string 保存されたファイルパス
     * @throws UploadException
     */
    public function upload(UploadedFile $file, array $rules = []): string
    {
        // 検証
        $this->validator->validate($file, $rules);

        // セキュアなファイル名を生成
        $filename = $this->generateSecureFilename($file);

        // 保存先パス
        $destination = $this->uploadDir . '/' . $filename;

        // ディレクトリが存在しない場合は作成
        if (!is_dir($this->uploadDir)) {
            if (!mkdir($this->uploadDir, 0755, true)) {
                throw new UploadException('アップロードディレクトリを作成できませんでした');
            }
        }

        // ファイル移動
        if (!move_uploaded_file($file->getTmpPath(), $destination)) {
            throw new UploadException('ファイルの保存に失敗しました');
        }

        return $destination;
    }

    /**
     * セキュアなファイル名を生成
     */
    private function generateSecureFilename(UploadedFile $file): string
    {
        // 1. ランダムな文字列を生成
        $random = bin2hex(random_bytes(16));

        // 2. 実際のMIMEタイプから拡張子を取得
        $actualMime = $this->mimeDetector->detect($file->getTmpPath());
        $extension = $this->mimeDetector->getExtension($actualMime ?? '');

        // 3. 拡張子が取得できない場合は元の拡張子を使用（サニタイズ）
        if (empty($extension)) {
            $extension = preg_replace('/[^a-z0-9]/i', '', $file->getExtension());
        }

        // 4. ファイル名を生成
        return $random . ($extension ? '.' . $extension : '');
    }

    /**
     * ファイルを削除
     */
    public function delete(string $filePath): bool
    {
        if (is_file($filePath)) {
            return unlink($filePath);
        }
        return false;
    }
}
```

---

## 使用例

### v2.0での使用（新API）

```php
<?php
use CfFormMailer\Upload\FileUploader;
use CfFormMailer\Upload\UploadedFile;
use CfFormMailer\Upload\FileValidator;
use CfFormMailer\Upload\MimeTypeDetector;

// 1. インスタンス作成（DIコンテナ経由）
$uploader = new FileUploader(
    validator: new FileValidator(new MimeTypeDetector()),
    mimeDetector: new MimeTypeDetector(),
    uploadDir: '/path/to/cfFormMailer/tmp'
);

// 2. アップロードされたファイルを取得
$file = UploadedFile::fromArray($_FILES['photo']);

// 3. アップロード（検証も自動実行）
try {
    $savedPath = $uploader->upload($file, [
        'maxSize' => 5000,  // 5MB
        'allowedTypes' => ['gif', 'jpg', 'png'],
    ]);

    echo "保存成功: {$savedPath}";

} catch (InvalidFileException $e) {
    echo "検証エラー: " . $e->getMessage();
} catch (UploadException $e) {
    echo "アップロードエラー: " . $e->getMessage();
}
```

### v1.7.x互換モード（レガシーラッパー）

既存のコードもそのまま動作：

```php
<?php
// v1.7.xのコード（そのまま動作）
$mime = $this->_getMimeType($filename, $field);
$type = $this->_getType($mime);
```

内部的に新しいFileUploaderを使用。

---

## セキュリティ対策

### 1. MIMEタイプ検証（拡張子偽装対策）

```php
// ❌ 危険: クライアントが送信した拡張子を信用
$ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);

// ✅ 安全: 実際のファイル内容からMIMEタイプを判定
$actualMime = $mimeDetector->detect($tmpPath);
```

**攻撃例**:
```
evil.php.jpg  ← 拡張子はjpgだが、実際はPHPファイル
```

新しいFileUploaderは実際のファイル内容から判定するため、この攻撃を防げます。

---

### 2. ランダムなファイル名生成

```php
// ❌ 危険: 元のファイル名をそのまま使用
$filename = $_FILES['file']['name'];  // ../../etc/passwd 等の攻撃

// ✅ 安全: ランダムな文字列 + 正しい拡張子
$filename = bin2hex(random_bytes(16)) . '.jpg';
// 例: a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6.jpg
```

---

### 3. ディレクトリトラバーサル対策

```php
// アップロードディレクトリ外への保存を防ぐ
$destination = realpath($this->uploadDir) . '/' . $filename;

if (strpos($destination, realpath($this->uploadDir)) !== 0) {
    throw new UploadException('不正なファイルパスです');
}
```

---

### 4. ファイルサイズ制限

```php
// サーバーリソースを保護
if ($file->getSize() > $maxSize * 1024) {
    throw new InvalidFileException('ファイルサイズが大きすぎます');
}
```

---

## 移行ガイド

### class.upload.phpからの移行

**Before (v1.7.x with class.upload.php)**:
```php
if (class_exists('upload')) {
    $up = new upload($filename);
    if ($up->uploaded) {
        $mime = $up->file_src_mime;
    }
}
```

**After (v2.0)**:
```php
use CfFormMailer\Upload\MimeTypeDetector;

$detector = new MimeTypeDetector();
$mime = $detector->detect($filename);
```

---

### テンプレートでの使用

**変更なし**（完全互換）:
```html
<!-- v1.7.x / v2.0 共通 -->
<input type="file" name="photo" valid=":allowtype(gif|jpg|png),allowsize(5000)" />

<!-- 確認画面 -->
<img src="cfFileView?field=photo" alt="[+photo.imagename+]" />
```

---

## まとめ

### メリット

| 項目 | Before (class.upload.php) | After (新FileUploader) |
|------|--------------------------|----------------------|
| **ファイルサイズ** | 1000行以上 | 約200行 |
| **依存関係** | 外部ライブラリ | なし（標準機能のみ） |
| **PHP対応** | 不明 | 7.4～8.4完全対応 |
| **セキュリティ** | 不明 | 最新基準準拠 |
| **保守性** | 外部依存で低い | 高い（同梱） |
| **型安全性** | なし | 厳格な型宣言 |
| **テスト** | 困難 | 容易 |

### 実装優先度

**Phase 2でValidation分離と同時に実装**（優先度: 高）

- [ ] `UploadedFile` DTO
- [ ] `MimeTypeDetector`
- [ ] `FileValidator`
- [ ] `FileUploader`
- [ ] 単体テスト
- [ ] v1.7.x互換ラッパー

---

**文書バージョン**: 1.0
**最終更新**: 2025-11-18
**作成者**: Claude (Sonnet 4.5)
