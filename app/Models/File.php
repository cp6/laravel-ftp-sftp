<?php

namespace App\Models;

use App\Models\Scopes\UserOwnedScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use phpseclib3\Net\SFTP;

class File extends Model
{
    use HasFactory;

    protected $fillable = ['size_kb', 'ext', 'saved_to', 'saved_as', 'original_dir', 'original_name'];

    protected $with = ['connection', 'read'];

    protected static function boot(): void
    {
        parent::boot();
        static::addGlobalScope(new UserOwnedScope());
    }

    public function read(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ReadFile::class, 'file_id', 'id');
    }

    public function connection(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Connection::class, 'connection_id', 'id');
    }

    public static function createNew(int $connection_id, string $file_to_download, string $save_to, string $save_as): File
    {
        $file = new File();
        $file->sid = Str::random(12);
        $file->connection_id = $connection_id;
        $file->user_id = Auth::id();
        $file->size_kb = Storage::disk('public')->size($save_to . $save_as) / 1024;
        $file->ext = pathinfo($file_to_download, PATHINFO_EXTENSION);
        $file->original_name = basename($file_to_download);
        $file->original_dir = dirname($file_to_download);
        $file->saved_to = $save_to;
        $file->saved_as = $save_as;
        $file->save();
        return $file;
    }


    public static function downloadFtpFile(Connection $connection, string $file_to_download, string $save_to, string $save_as): bool
    {
        try {
            $con = ftp_connect($connection->host, $connection->port, $connection->timeout);
            if (false === $con) {
                return false;
            }

            (!is_null($connection->password)) ? $decrypted_password = Crypt::decryptString($connection->password) : $decrypted_password = '';

            if (@ftp_login($con, $connection->username, $decrypted_password)) {
                $handle = fopen('php://temp', 'wb+');
                if (!ftp_fget($con, $handle, $file_to_download, FTP_BINARY)) {
                    fclose($handle);
                    ftp_close($con);
                    return false;
                }

                fseek($handle, 0);
                $fileContents = stream_get_contents($handle);
                fclose($handle);

                if (Storage::disk('public')->put($save_to . $save_as, $fileContents)) {
                    $file = self::createNew($connection->id, $file_to_download, $save_to, $save_as);

                    $mime = Storage::disk('public')->mimeType($save_to . $save_as);
                    if (str_starts_with($mime, 'text/')) {
                        ReadFile::createNew($file->id);
                    }
                }

                ftp_close($con);

                return true;
            }

        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());
        }

        return false;
    }

    public static function downloadSftpFile(Connection $connection, string $file_to_download, string $save_to, string $save_as): bool
    {
        try {
            $sftp = new SFTP($connection->host, $connection->port, $connection->timeout);
            $decrypted_password = (!is_null($connection->password)) ? Crypt::decryptString($connection->password) : '';

            if ($sftp->login($connection->username, $decrypted_password)) {
                $fileContents = $sftp->get($file_to_download);

                if ($fileContents === false) {
                    return false;
                }

                if (Storage::disk('public')->put($save_to . $save_as, $fileContents)) {

                    $file = self::createNew($connection->id, $file_to_download, $save_to, $save_as);

                    $mime = Storage::disk('public')->mimeType($save_to . $save_as);
                    if (str_starts_with($mime, 'text/')) {
                        ReadFile::createNew($file->id);
                    }

                }

                return true;
            }

        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());
        }

        return false;
    }

    public static function renameFtpFile(Connection $connection, string $current_path, string $new_name): bool
    {
        try {
            $ftp = ftp_connect($connection->host, $connection->port, $connection->timeout);
            if (false === $ftp) {
                return false;
            }

            $decrypted_password = (!is_null($connection->password)) ? Crypt::decryptString($connection->password) : '';

            if (@ftp_login($ftp, $connection->username, $decrypted_password)) {
                $new_path = dirname($current_path) . '/' . $new_name;

                $file_exists = @ftp_size($ftp, $new_path) !== -1;

                if ($file_exists) {
                    return false;
                }

                if (@ftp_rename($ftp, $current_path, $new_path)) {
                    ftp_close($ftp);
                    return true;
                }

                return false;
            }

            return false;
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());
            if ($ftp) {
                ftp_close($ftp);
            }
            return false;
        }
    }

    public static function renameSftpFile(Connection $connection, string $current_path, string $new_name): bool
    {
        try {
            $sftp = new SFTP($connection->host, $connection->port, $connection->timeout);
            $decrypted_password = (!is_null($connection->password)) ? Crypt::decryptString($connection->password) : '';

            if ($sftp->login($connection->username, $decrypted_password)) {
                $new_path = dirname($current_path) . '/' . $new_name;

                if ($sftp->file_exists($new_path)) {
                    return false;
                }

                if ($sftp->rename($current_path, $new_path)) {
                    return true;
                }

            }

            return false;
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());
            return false;
        }
    }

    public static function outputSftpFileToBrowser(Connection $connection, string $file_path)
    {
        try {
            $sftp = new SFTP($connection->host, $connection->port, $connection->timeout);
            $decrypted_password = (!is_null($connection->password)) ? Crypt::decryptString($connection->password) : '';

            if ($sftp->login($connection->username, $decrypted_password)) {
                $fileContents = $sftp->get($file_path);

                if ($fileContents === false) {
                    abort(500, 'Failed to retrieve the file.');
                }

                return response($fileContents, 200)
                    ->header('Content-Type', 'text/plain')
                    ->header('Content-Disposition', 'inline; filename="' . basename($file_path) . '"');
            }

            abort(500, 'Failed to retrieve the file.');
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());
            abort(500, 'Failed to retrieve the file.');
        }
    }


    public static function moveFile(File $file, string $move_to): bool
    {
        try {
            $current_path = $file->saved_to . '/' . $file->saved_as;
            if (!Storage::disk('public')->exists($current_path)) {
                return false;
            }

            $new_path = $move_to . '/' . $file->saved_as;

            if (Storage::disk('public')->exists($new_path)) {
                return false;
            }

            if (Storage::disk('public')->move($current_path, $new_path)) {
                $file->update(['saved_to' => $move_to]);
                return true;
            }

            return false;
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());
            return false;
        }
    }

    public static function copyFile(File $file, string $copy_to): bool
    {
        try {
            $current_path = $file->saved_to . '/' . $file->saved_as;
            if (!Storage::disk('public')->exists($current_path)) {
                return false;
            }

            $new_path = $copy_to . '/' . $file->saved_as;

            if (Storage::disk('public')->exists($new_path)) {
                return false;
            }

            if (Storage::disk('public')->move($current_path, $new_path)) {
                return true;
            }

            return false;
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());
            return false;
        }
    }

    public static function renameFile(File $file, string $new_name): bool
    {
        try {
            if (!Storage::disk('public')->exists($file->saved_to . '/' . $file->saved_as)) {
                return false;
            }

            $new_path = dirname($file->saved_to) . '/' . $new_name;

            if (Storage::disk('public')->exists($new_path)) {
                return false;
            }

            if (Storage::disk('public')->move($file->saved_to . '/' . $file->saved_as, $new_path)) {
                $file->update(['saved_as' => $new_name]);
                return true;
            }

            return false;
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());
            return false;
        }
    }

    public static function deleteFile(File $file): bool
    {
        try {
            if (!Storage::disk('public')->exists($file->saved_to . '/' . $file->saved_as)) {
                return false;
            }

            if (Storage::disk('public')->delete($file->saved_to . '/' . $file->saved_as)) {
                $file->delete();
                return true;
            }

            return false;
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());
            return false;
        }
    }

    public static function downloadFile(File $file, string $save_as = '')
    {
        try {
            $file_path = $file->saved_to . '/' . $file->saved_as;
            if (!Storage::disk('public')->exists($file_path)) {
                return false;
            }

            $mimeType = Storage::disk('public')->mimeType($file_path);

            if ($save_as === '') {
                $save_as = basename($file_path);
            }

            return Storage::disk('public')->download($file_path, $save_as, ['Content-Type' => $mimeType]);
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());
            abort(404, 'File not found.');
        }
    }

}
