<?php

namespace App\Models;

use App\Models\Scopes\UserOwnedScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SplFileObject;

class File extends Model
{
    use HasFactory;

    protected $fillable = ['size_kb', 'ext', 'disk', 'saved_to', 'saved_as', 'original_dir', 'original_name', 'last_line_read', 'total_lines'];

    protected $with = ['connection'];

    protected static function boot(): void
    {
        parent::boot();
        static::addGlobalScope(new UserOwnedScope());
    }

    public function connection(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Connection::class, 'connection_id', 'id');
    }

    public static function createNew(int $connection_id, string $file_to_download, string $disk, string $save_to, string $save_as): File
    {
        $file = new File();
        $file->sid = Str::random(12);
        $file->connection_id = $connection_id;
        $file->user_id = Auth::id();
        $file->size_kb = Storage::disk('public')->size($save_to . $save_as) / 1024;
        $file->ext = pathinfo($file_to_download, PATHINFO_EXTENSION);
        $file->original_name = basename($file_to_download);
        $file->original_dir = dirname($file_to_download);
        $file->disk = $disk;
        $file->saved_to = $save_to;
        $file->saved_as = $save_as;
        $file->save();
        return $file;
    }


    public static function downloadFtpFile(Connection $connection, string $file_to_download, string $disk, string $save_to, string $save_as): bool
    {
        try {
            $con = Connection::makeFtpConnection($connection->host, $connection->port, $connection->username, $connection->password);
            if (false === $con) {
                return false;
            }

            if ($con) {
                $handle = fopen('php://temp', 'wb+');
                if (!ftp_fget($con, $handle, $file_to_download, FTP_BINARY)) {
                    fclose($handle);
                    ftp_close($con);
                    return false;
                }

                fseek($handle, 0);
                $fileContents = stream_get_contents($handle);
                fclose($handle);

                if (Storage::disk($disk)->put($save_to . $save_as, $fileContents)) {
                    $file = self::createNew($connection->id, $file_to_download, $disk, $save_to, $save_as);
                }

                ftp_close($con);

                return true;
            }

        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());
        }

        return false;
    }

    public static function downloadSftpFile(Connection $connection, string $file_to_download, string $disk, string $save_to, string $save_as): bool
    {
        try {
            $sftp = Connection::makeSftpConnection($connection->host, $connection->port, $connection->username, $connection->password);

            if ($sftp) {
                $fileContents = $sftp->get($file_to_download);

                if ($fileContents === false) {
                    return false;
                }

                if (Storage::disk($disk)->put($save_to . $save_as, $fileContents)) {
                    $file = self::createNew($connection->id, $file_to_download, $disk, $save_to, $save_as);
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
            $ftp = Connection::makeFtpConnection($connection->host, $connection->port, $connection->username, $connection->password);
            if (false === $ftp) {
                return false;
            }

            if ($ftp) {
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
            return false;
        }
    }

    public static function renameSftpFile(Connection $connection, string $current_path, string $new_name): bool
    {
        try {
            $sftp = Connection::makeSftpConnection($connection->host, $connection->port, $connection->username, $connection->password);

            if ($sftp) {
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
            $sftp = Connection::makeSftpConnection($connection->host, $connection->port, $connection->username, $connection->password);

            if ($sftp) {
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

    public static function fileExists(File $file): bool
    {
        return Storage::disk($file->disk)->exists($file->saved_to . '/' . $file->saved_as);
    }

    public static function moveFile(File $file, string $move_to, string $disk = ''): bool
    {
        try {
            if (!self::fileExists($file)) {
                return false;
            }

            if ($disk === '') {
                $disk = $file->disk;
            }

            if (Storage::disk($disk)->exists($move_to . '/' . $file->saved_as)) {
                return false;
            }

            if (Storage::disk($disk)->move($file->saved_to . '/' . $file->saved_as, $move_to . '/' . $file->saved_as)) {
                $file->update(['saved_to' => $move_to, 'disk' => $disk]);
                return true;
            }

            return false;
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());
            return false;
        }
    }

    public static function copyFile(File $file, string $copy_to, string $disk = ''): bool
    {
        try {
            if (!self::fileExists($file)) {
                return false;
            }

            if ($disk === '') {
                $disk = $file->disk;
            }

            if (Storage::disk($disk)->exists($copy_to . '/' . $file->saved_as)) {
                return false;
            }

            if (Storage::disk($disk)->move($file->saved_to . '/' . $file->saved_as, $copy_to . '/' . $file->saved_as)) {
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
            if (!self::fileExists($file)) {
                return false;
            }

            if (Storage::disk($file->disk)->exists(dirname($file->saved_to) . '/' . $new_name)) {
                return false;
            }

            if (Storage::disk($file->disk)->move($file->saved_to . '/' . $file->saved_as, dirname($file->saved_to) . '/' . $new_name)) {
                $file->update(['saved_as' => dirname($file->saved_to) . '/' . $new_name]);
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
            if (!self::fileExists($file)) {
                return false;
            }

            if (Storage::disk($file->disk)->delete($file->saved_to . '/' . $file->saved_as)) {
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
            if (!Storage::disk($file->disk)->exists($file_path)) {
                return false;
            }

            $mimeType = Storage::disk($file->disk)->mimeType($file_path);

            if ($save_as === '') {
                $save_as = basename($file_path);
            }

            return Storage::disk($file->disk)->download($file_path, $save_as, ['Content-Type' => $mimeType]);
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());
            abort(404, 'File not found.');
        }
    }

    public static function uploadFile(Connection $connection, string $local_disk, string $local_filepath, string $upload_as): bool
    {

    }

    public static function readLinesFtp(Connection $connection, string $file_path, int $start = 0, int $num_lines = 100): ?array
    {
        $ftp_con = Connection::makeFtpConnection($connection->host, $connection->port, $connection->username, $connection->password);

        if (is_null($ftp_con)) {
            Log::debug('Connection could not be made');
            return null;
        }

        $temp_stream = fopen('php://temp', 'rb+');

        $result = ftp_nb_fget($ftp_con, $temp_stream, $file_path, FTP_ASCII);

        $lines = [];
        $lineCount = 0;

        while ($result === FTP_MOREDATA) {
            $result = ftp_nb_continue($ftp_con);

            rewind($temp_stream);
            while (!feof($temp_stream)) {
                $line = fgets($temp_stream);
                if ($line === false) {
                    break;
                }
                $lineCount++;
                if ($lineCount >= $start && $lineCount < $start + $num_lines) {
                    $lines[] = $line;
                }
                if ($lineCount >= $start + $num_lines) {
                    break 2;
                }
            }
            ftruncate($temp_stream, 0);
        }

        fclose($temp_stream);

        return $lines;
    }

    public static function readFileFromStorage(File $file, int $start = 0, int $end = 100): ?array
    {
        if (!self::fileExists($file)) {
            return null;
        }

        $read_file = self::firstOrCreate(['directory' => $file->saved_to, 'file' => $file->saved_as], []);

        $lines = [];
        $line_number = 0;

        $handle = Storage::disk($file->disk)->readStream($file->saved_to . '/' . $file->saved_as);

        if ($handle !== false) {
            while (($line = fgets($handle)) !== false) {
                if ($line_number >= $start && $line_number <= $end) {
                    $lines[] = $line;
                }
                if ($line_number > $end) {
                    break;
                }
                $line_number++;
            }

            fclose($handle);

            $read_file->update(['last_line_read' => $line_number]);
        }

        return $lines;
    }

    public static function setSplFile(File $file): ?SplFileObject
    {
        if (!self::fileExists($file)) {
            return null;
        }

        try {
            $file_to_read = new SplFileObject(Storage::disk($file->disk)->path($file->saved_to . '/' . $file->saved_as), 'r');
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());
            return null;
        }

        return $file_to_read;
    }

    public static function readLines(File $file, int $number_of_lines = 100): ?array
    {
        $file_to_read = self::setSplFile($file);

        if (is_null($file_to_read)) {
            return null;
        }

        (is_null($file->last_line_read)) ? $file_to_read->seek(0) : $file_to_read->seek($file->last_line_read - 1);

        $lines = [];

        for ($line_number = 0; $line_number < $number_of_lines && !$file_to_read->eof(); $line_number++) {
            $lines[] = $file_to_read->current();
            $file_to_read->next();
        }

        $file_to_read->seek($file_to_read->getSize());

        $file->update([
            'last_line_read' => $line_number + $file->last_line_read,
            'total_lines' => ($file_to_read->key() + 1)
        ]);

        return $lines;
    }

    public static function readLinesFromTo(File $file, int $from = 0, int $to = 100): ?array
    {
        $file_to_read = self::setSplFile($file);

        if (is_null($file_to_read)) {
            return null;
        }

    }

    public static function readLastLines(File $file, int $amount = 20): ?array
    {
        $file_to_read = self::setSplFile($file);

        if (is_null($file_to_read)) {
            return null;
        }

    }

    public static function readOneLine(File $file, int $line = 1): ?array
    {
        $file_to_read = self::setSplFile($file);

        if (is_null($file_to_read)) {
            return null;
        }

    }

}
