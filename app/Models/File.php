<?php

namespace App\Models;

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
                        Log::debug(5);
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

}
