<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

[![Laravel - 11](https://img.shields.io/badge/Laravel-11-red)]()
[![PHP - 8.2](https://img.shields.io/badge/PHP-8.2-purple.svg)]()


<h1 align="center">Sample PHP Laravel File actions with FTP and SFTP</h1>

Laravel-ftp-sftp provides examples of using the FTP, SFTP and local storage protocols combined with Laravel to manage and action files.

Examples for SFTP, FTP and storage include:

- List directories
- List files and sizes
- List directories and files
- Upload file
- Download file
- Rename file
- Move file
- Copy file
- Delete file
- Writing to File
- Read files without downloading all of it
- Read files from the last line read
- Compare files
- Blade files to view/create/edit Connections

Uses phpseclib for SFTP and PHP-FTP for FTP.

## Connection

Used for creating SFTP and FTP connections

### SFTP connection:

```php
Connection::makeSftpConnection(string $host, int $port, string $user, ?string $password = '', int $timeout = 8, ?string $key = ''): ?SFTP
```

### FTP connection:

```php
Connection::makeFtpConnection(string $host, int $port, string $user, ?string $password = '', int $timeout = 8): ?\FTP\Connection
```

### SFTP file and directory methods:

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

```php
File::uploadFile(Connection $connection, string $local_disk, string $local_filepath, string $upload_as): bool
```

```php
File::outputSftpFileToBrowser(Connection $connection, string $file_path)
```

```php
File::deleteSftpFile(Connection $connection, string $file_to_delete): bool
```

```php
File::renameSftpFile(Connection $connection, string $current_path, string $new_path): bool

File::renameSftpFile($connection, 'files/images/dog.jpg', 'files/images/cat.jpg');
```

```php
File::compareModifiedTimeSftp(Connection $connection, File $file): array
```

### FTP file and directory methods:

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

```php
File::readLinesFtp(Connection $connection, string $file_path, int $start = 0, int $num_lines = 100): ?array
```

```php
File::renameFtpFile(Connection $connection, string $current_path, string $new_path): bool

File::renameFtpFile($connection, 'files/images/dog.jpg', 'files/images/cat.jpg');
```

```php
File::deleteFtpFile(Connection $connection, string $file_to_delete): bool

File::deleteFtpFile($connection, '/files/logs.txt');
```

```php
File::compareModifiedTimeFtp(Connection $connection, File $file): array
```

## File

File actions such as downloading, uploading, deleting, moving and reading.

Download a file and create a File entry in the DB. This uses teh Laravel Storage Facade:

```php
File::downloadFtpFile(Connection $connection, string $file_to_download, string $disk, string $save_to, string $save_as): bool

File::downloadFtpFile($connection, '/files/logs.txt', 'public'. '/downloaded', 'logs.txt');
```

```php
File::downloadSftpFile(Connection $connection, string $file_to_download, string $disk, string $save_to, string $save_as): bool

File::downloadSftpFile($connection, '/files/logs.txt', 'public'. '/downloaded', 'logs.txt');
```
## Local file and directory actions

```php
File::fileExists(File $file): bool
```

```php
File::moveFile(File $file, string $move_to, string $disk = ''): bool

File::moveFile($file, '/archived', 'public');
```

```php
File::copyFile(File $file, string $copy_to, string $disk = ''): bool
```

```php
File::renameFile(File $file, string $new_name): bool

File::renameFile($file, 'new.txt');
```

```php
File::deleteFile(File $file): bool
```

```php
File::downloadFileInBrowser(File $file, string $save_as = '')
//Prompts to download the file in the browser
```

```php
File::displayFileInBrowser(File $file)
//Displays the file in the browser
```

```php
File::readFileFromStorage(File $file, int $start = 0, int $end = 100): ?array
```


```php
File::listFilesInDirectory(string $disk, string $path): array
```

```php
File::listAllFilesInDirectory(string $disk, string $path): array
```

```php
File::listDirectoriesInDirectory(string $disk, string $path): array
```

```php
File::createDirectory(string $disk, string $path): bool
```

```php
File::deleteDirectory(string $disk, string $path): bool
```




## Reading a large local file

Uses SplFileObject, memory efficient it does not read through the whole file.

```php
File::readLines(File $file, int $number_of_lines = 100): ?array
```

readLines() will start from last line read in the DB and then update last line read and total lines.

```php
File::readLinesFromTo(File $file, int $from = 0, int $to = 100): ?array
```

readLinesFromTo() is for when you want to read specific lines rather than the sequential readLines().


```php
File::readLastLines(File $file, int $amount = 20): ?array
``````

readLastLines($file, 20) reads the last 20 lines in the file.

```php
File::readOneLine(File $file, int $line = 1): ?array
```

```php
File::readAllLines(File $file): ?array
```

Close the file pointer (If needed).

```php
File::closeSplFile();
```

## Writing to local file

```php
File::writeToFile(File $file, string $data, array $options => []): ?bool
``````

```php
File::appendToFile(File $file, string $data): ?bool
``````

```php
File::prependToFile(File $file, string $data): ?bool
```

```php
File::setFilePublic(File $file): bool
```

```php
File::setFilePrivate(File $file): bool
```

```php
File::getFileVisibility(File $file): string
```
