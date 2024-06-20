<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

[![Laravel - 11](https://img.shields.io/badge/Laravel-11-red)]()
[![PHP - 8.2](https://img.shields.io/badge/PHP-8.2-purple.svg)]()


<h1 align="center">Example File actions with FTP and SFTP</h1>

Laravel-ftp-sftp provides examples of using the FTP and SFTP protocols combined with Laravel to manage files.

Examples include:

- List directories
- List files and sizes
- List directories and files
- Upload file
- Download file
- Rename file
- Move file
- Copy file
- Delete file
- Read files without downloading all of it
- Read files from the last line read
- Blade files to view/create/edit Connections


<h2>The main models and their roles</h2>

<h3>Connection</h3>
Used for creating SFTP and FTP connections

Make an SFTP connection:

```php
Connection::makeSftpConnection(string $host, int $port, string $user, ?string $password = '', int $timeout = 8, ?string $key = ''): ?SFTP
```

FTP connection:
```php
Connection::makeFtpConnection(string $host, int $port, string $user, ?string $password = '', int $timeout = 8): ?\FTP\Connection
```

SFTP file and directory methods:

```php
Connection::listSftpCurrentDirectorySize(Connection $connection, string $path = ''): ?array
```

```php
Connection::listSftpDirectories(Connection $connection, string $path = ''): ?array
```

```php
Connection::listSftpFiles(Connection $connection, string $path = ''): ?array
```

```php
Connection::listSftpFilesDirectories(Connection $connection, string $path = ''): ?array
```


FTP file and directory methods:

```php
Connection::listFtpDirectories(Connection $connection, string $path = ''): ?array
```

```php
Connection::listFtpFiles(Connection $connection, string $path = ''): ?array
```

```php
Connection::listFtpCurrentDirectorySize(Connection $connection, string $path = ''): ?array
```

```php
Connection::listFtpFilesDirectories(Connection $connection, string $path = ''): ?array
```


<h3>File</h3>
File actions such as downloading, uploading, deleting, moving and reading.
