---
name: spiral-storage
description: >-
  Справочник по Storage (файловое хранилище) в Spiral Framework.
  Используй при настройке S3/MinIO/local хранилища, загрузке и чтении файлов,
  работе с bucket-ами и Distribution (публичные URL).
user-invocable: false
---

# Spiral Framework: Storage и Distribution

Компонент `spiral/storage` — абстракция над Flysystem для работы с файлами.

## Конфигурация

Файл: `app/config/storage.php`

### Local Server

```php
return [
    'default' => 'uploads',
    'servers' => [
        'local' => [
            'adapter' => 'local',
            'directory' => '/app/storage/uploads',
            'visibility' => [
                'public' => ['file' => 0644, 'dir' => 0755],
                'private' => ['file' => 0600, 'dir' => 0700],
                'default' => 'public',
            ],
        ],
    ],
    'buckets' => [
        'uploads' => [
            'server' => 'local',
            'prefix' => 'media',
        ],
    ],
];
```

### S3 / MinIO Server

Требуется: `composer require league/flysystem-aws-s3-v3 ^2.0`

```php
return [
    'servers' => [
        's3' => [
            'adapter' => 's3',
            'region' => env('S3_REGION'),
            'version' => env('S3_VERSION', 'latest'),
            'bucket' => env('S3_BUCKET'),
            'key' => env('S3_KEY'),
            'secret' => env('S3_SECRET'),
            'endpoint' => env('S3_ENDPOINT', null),
            'options' => [
                'use_path_style_endpoint' => true,  // Для MinIO
            ],
        ],
    ],
    'buckets' => [
        'media' => [
            'server' => 's3',
            'visibility' => env('S3_VISIBILITY', 'public'),
            'bucket' => env('S3_BUCKET', null),
            'prefix' => 'media',
        ],
    ],
];
```

## Операции с файлами

### Запись

```php
use Spiral\Storage\BucketInterface;

public function upload(BucketInterface $bucket): void
{
    // Создание пустого файла
    $file = $bucket->create('file.txt');

    // Запись строки
    $file = $bucket->write('file.txt', 'content');

    // Запись потока
    $file = $bucket->write('file.txt', fopen('/path/to/file', 'rb+'));
}
```

### Чтение

```php
$string = $bucket->getContents('text.txt');
$resource = $bucket->getStream('music.mp3');
```

### Проверка существования

```php
$exists = $bucket->exists('file.txt');
```

### Метаданные

```php
$bytes = $bucket->getSize('file.txt');
$timestamp = $bucket->getLastModified('file.txt');
$mime = $bucket->getMimeType('file.txt');
```

### Копирование и перемещение

```php
// Внутри bucket-а
$backup = $bucket->copy('from.txt', 'backup.txt');

// Между bucket-ами
$moved = $firstBucket->move('file.txt', 'file.txt', $secondBucket);
```

### Удаление

```php
$bucket->delete('file.txt');
```

### Видимость

```php
use League\Flysystem\Visibility;

$visibility = $bucket->getVisibility('file.txt');

if ($visibility === Visibility::VISIBILITY_PRIVATE) {
    $bucket->setVisibility('file.txt', Visibility::VISIBILITY_PUBLIC);
}
```

## Уровни доступа

```php
use Spiral\Storage\StorageInterface;

class UploadController
{
    public function upload(StorageInterface $storage): void
    {
        // Уровень Storage — полный путь
        $storage->create('bucket://example.txt');

        // Уровень Bucket
        $storage->bucket('media')->create('example.txt');

        // Уровень File
        $storage->bucket('media')->file('example.txt')->create();
    }
}
```

### Инъекция default bucket

```php
use Spiral\Storage\BucketInterface;

class UploadController
{
    public function upload(BucketInterface $bucket): void
    {
        // Используется bucket из конфига 'default'
        $bucket->write('file.txt', 'content');
    }
}
```

## Distribution (публичные URL)

Компонент `spiral/distribution` генерирует публичные HTTP-ссылки.

### Конфигурация

```php
// app/config/distribution.php
return [
    'default' => env('DISTRIBUTION_RESOLVER', 'local'),
    'resolvers' => [
        'local' => [
            'type' => 'static',
            'uri' => env('APP_URL', 'http://localhost'),
        ],
        's3' => [
            'type' => 's3',
            'region' => env('S3_REGION'),
            'bucket' => env('S3_BUCKET'),
            'key' => env('S3_KEY'),
            'secret' => env('S3_SECRET'),
        ],
    ],
];
```

### Использование

```php
use Spiral\Distribution\UriResolverInterface;

class FileController
{
    public function getUrl(UriResolverInterface $resolver): string
    {
        return (string) $resolver->resolve('media/image.jpg');
    }
}
```

### Интеграция Storage + Distribution

```php
// В конфиге storage
'buckets' => [
    'uploads' => [
        'server' => 's3',
        'distribution' => 'local',  // имя distribution resolver
    ],
],

// Использование
$uri = $bucket->file('picture.jpg')->toUri();
```
