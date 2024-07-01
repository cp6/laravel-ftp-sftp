<?php

namespace App\Models;

use App\Models\Scopes\UserOwnedScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use phpseclib3\Net\SFTP;

class Connection extends Model
{
    use HasFactory;

    protected $fillable = ['is_sftp', 'host', 'username', 'password', 'port', 'timeout', 'log_actions', 'key'];

    protected static function boot(): void
    {
        parent::boot();
        static::addGlobalScope(new UserOwnedScope());
    }

    protected static function booted(): void
    {
        static::creating(function (Connection $connection) {
            $connection->sid = Str::random(12);
            $connection->user_id = Auth::id();
        });
    }

    public static function makeSftpConnection(string $host, int $port, string $user, ?string $password = '', int $timeout = 8, ?string $key = ''): ?SFTP
    {
        $sftp = new SFTP($host, $port, $timeout);

        try {
            if (!is_null($password) && is_null($key)) {
                $sftp->login($user, Crypt::decryptString($password));//Has password set
            } elseif (is_null($password) && is_null($key)) {
                $sftp->login($user);//Has no password or key set
            } elseif (!is_null($key) && !is_null($password)) {
                $sftp->login($user, Crypt::decryptString($key), Crypt::decryptString($password));//Has key set
            } else {
                $sftp->login($user, Crypt::decryptString($key));//Has key set
            }
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());
            Log::debug($sftp->getLastError());
            Log::debug($sftp->getLastSFTPError());
            return null;
        }

        return $sftp;
    }

    public static function makeFtpConnection(string $host, int $port, string $user, ?string $password = '', int $timeout = 8): ?\FTP\Connection
    {
        try {
            $con = ftp_connect($host, $port, $timeout);
            if (false === $con) {
                return null;
            }

            if (!is_null($password)) {//Has password set
                $password = Crypt::decryptString($password);
            }

            $ftp_login = ftp_login($con, $user, $password);

            if (false === $ftp_login) {
                return null;
            }

            return $con;
        } catch (\Exception $e) {
            Log::debug($e->getMessage());
            return null;
        }
    }

    public static function listFtpDirectories(Connection $connection, string $path = ''): ?array
    {
        try {
            $con = self::makeFtpConnection($connection->host, $connection->port, $connection->username, $connection->password);

            if ($con) {
                $contents = ftp_nlist($con, $path);

                $directories = [];
                foreach ($contents as $item) {
                    if (ftp_size($con, $item) === -1) {
                        $directories[] = $item;
                    }
                }

                ftp_close($con);

                return $directories;

            }

            return null;

        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());
        }
        return null;
    }

    public static function listFtpFiles(Connection $connection, string $path = ''): ?array
    {
        try {
            $con = self::makeFtpConnection($connection->host, $connection->port, $connection->username, $connection->password);

            if ($con) {
                $contents = ftp_nlist($con, $path);

                $files = [];
                foreach ($contents as $item) {
                    $size = ftp_size($con, $item);

                    if ($size !== -1) {
                        $files[] = [
                            'name' => $item,
                            'size' => $size,
                            'size_kb' => $size / 1024,
                            'size_mb' => $size / 1024 / 1024
                        ];
                    }
                }

                ftp_close($con);

                return $files;
            }

            return null;
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());
        }

        return null;
    }

    public static function listFtpCurrentDirectorySize(Connection $connection, string $path = ''): ?array
    {
        try {
            $con = self::makeFtpConnection($connection->host, $connection->port, $connection->username, $connection->password);

            if ($con) {
                $contents = ftp_nlist($con, $path);

                $files = $size = 0;
                foreach ($contents as $item) {
                    ++$files;
                    $size += ftp_size($con, $item);
                }

                ftp_close($con);

                return [
                    'files' => $files,
                    'size' => $size,
                    'size_mb' => $size / 1024 / 1024,
                    'size_gb' => $size / 1024 / 1024 / 1024,
                ];
            }

            return null;
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());
        }

        return null;
    }

    public static function listSftpCurrentDirectorySize(Connection $connection, string $path = ''): ?array
    {
        try {
            $sftp = self::makeSftpConnection($connection->host, $connection->port, $connection->username, $connection->password, $connection->timeout, $connection->key);

            if (!$sftp) {
                return null;
            }

            $contents = $sftp->nlist($path);

            if ($contents === false) {
                return null;
            }

            $files = $size = 0;
            foreach ($contents as $item) {
                if ($item !== '.' && $item !== '..') {
                    ++$files;
                    $size += $sftp->filesize($path . '/' . $item);
                }
            }

            return [
                'files' => $files,
                'size' => $size,
                'size_mb' => $size / 1024 / 1024,
                'size_gb' => $size / 1024 / 1024 / 1024,
            ];

        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());
        }

        return null;
    }

    public static function listFtpFilesDirectories(Connection $connection, string $path = ''): ?array
    {
        try {
            $con = self::makeFtpConnection($connection->host, $connection->port, $connection->username, $connection->password);

            if ($con) {
                $contents = ftp_nlist($con, $path);

                $items = [];

                foreach ($contents as $item) {
                    $size = ftp_size($con, $item);

                    if ($size !== -1) {
                        $items[] = [
                            'name' => $item,
                            'size' => $size,
                            'size_kb' => $size / 1024,
                            'size_mb' => $size / 1024 / 1024,
                            'is_file' => true
                        ];
                    } else {
                        $items[] = [
                            'name' => $item,
                            'size' => null,
                            'size_kb' => null,
                            'size_mb' => null,
                            'is_file' => false
                        ];
                    }
                }

                ftp_close($con);

                return $items;
            }

            return null;
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());
        }

        return null;
    }

    public static function listSftpDirectories(Connection $connection, string $path = ''): ?array
    {
        $sftp = self::makeSftpConnection($connection->host, $connection->port, $connection->username, $connection->password, $connection->timeout, $connection->key);

        if (!$sftp) {
            return null;
        }

        try {
            $directories = $sftp->nlist($path);
            $directoryList = [];
            foreach ($directories as $directory) {
                if ($sftp->is_dir($path . '/' . $directory)) {
                    $directoryList[] = $directory;
                }
            }

            return $directoryList;
        } catch (\Exception $exception) {
            return null;
        }
    }

    public static function listSftpFiles(Connection $connection, string $path = ''): ?array
    {
        $sftp = self::makeSftpConnection($connection->host, $connection->port, $connection->username, $connection->password, $connection->timeout, $connection->key);

        if (!$sftp) {
            return null;
        }

        try {
            $files = $sftp->nlist($path);

            $fileList = [];
            foreach ($files as $file) {
                $filePath = $path . '/' . $file;
                if ($sftp->is_file($filePath)) {
                    $stat = $sftp->stat($filePath);
                    $size = $stat['size'];
                    $fileList[] = [
                        'name' => $file,
                        'size' => $size,
                        'size_kb' => $size / 1024,
                        'size_mb' => $size / 1024 / 1024,
                        'last_access' => $stat['atime'],
                        'last_modified' => $stat['mtime'],
                    ];
                }
            }

            return $fileList;
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());
            return null;
        }
    }

    public static function listSftpFilesDirectories(Connection $connection, string $path = ''): ?array
    {
        $sftp = self::makeSftpConnection($connection->host, $connection->port, $connection->username, $connection->password, $connection->timeout, $connection->key);

        if (!$sftp) {
            return null;
        }

        try {
            $files = $sftp->nlist($path);

            $list = [];
            foreach ($files as $file) {
                $filePath = $path . '/' . $file;
                if ($sftp->is_file($filePath)) {
                    $stat = $sftp->stat($filePath);
                    $size = $stat['size'];
                    $list[] = [
                        'name' => $file,
                        'size' => $size,
                        'size_kb' => $size / 1024,
                        'size_mb' => $size / 1024 / 1024,
                        'last_access' => $stat['atime'],
                        'last_modified' => $stat['mtime'],
                        'is_file' => true
                    ];
                } else {
                    $list[] = [
                        'name' => $file,
                        'size' => null,
                        'size_kb' => null,
                        'size_mb' => null,
                        'last_access' => null,
                        'last_modified' => null,
                        'is_file' => false
                    ];
                }
            }

            return $list;
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());
            return null;
        }
    }

}
