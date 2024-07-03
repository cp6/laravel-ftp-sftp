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

    public ?SplFileObject $load_file;

    public array $line_contents = [];

    protected $fillable = ['size_kb', 'ext', 'disk', 'saved_to', 'saved_as', 'original_dir', 'original_name', 'last_line_read', 'total_lines', 'mime'];

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
                    $file->update(['mime' => Storage::disk($disk)->mimeType($save_to . '/' . $save_as)]);

                    ftp_close($con);
                    return true;
                }

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
                    $file->update(['mime' => Storage::disk($disk)->mimeType($save_to . '/' . $save_as)]);
                    return true;
                }
            }

        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());
        }

        return false;
    }

    public static function renameFtpFile(Connection $connection, string $current_path, string $new_path): bool
    {
        try {
            $ftp = Connection::makeFtpConnection($connection->host, $connection->port, $connection->username, $connection->password);

            if ($ftp) {

                if (@ftp_size($ftp, $new_path) !== -1) {
                    return false;
                }

                if (@ftp_rename($ftp, $current_path, $new_path)) {
                    ftp_close($ftp);
                    return true;
                }

                return false;
            }

        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());
        }

        return false;
    }

    public static function renameSftpFile(Connection $connection, string $current_path, string $new_path): bool
    {
        try {
            $sftp = Connection::makeSftpConnection($connection->host, $connection->port, $connection->username, $connection->password);

            if ($sftp && $sftp->file_exists($new_path)) {
                return $sftp->rename($current_path, $new_path);
            }

        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());
        }

        return false;
    }

    public static function deleteFtpFile(Connection $connection, string $file_to_delete): bool
    {
        try {
            $ftp = Connection::makeFtpConnection($connection->host, $connection->port, $connection->username, $connection->password, $connection->timeout);

            if ($ftp) {
                if (ftp_delete($ftp, $file_to_delete)) {
                    ftp_close($ftp);
                    return true;
                }

                ftp_close($ftp);
                return false;
            }

        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());
        }

        return false;
    }

    public static function deleteSftpFile(Connection $connection, string $file_to_delete): bool
    {
        try {
            $sftp = Connection::makeSftpConnection($connection->host, $connection->port, $connection->username, $connection->password, $connection->timeout, $connection->key);

            if ($sftp) {
                return $sftp->delete($file_to_delete);
            }
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());
        }

        return false;
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

        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());
        }

        return false;
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

        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());
        }

        return false;
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

        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());
        }

        return false;
    }

    public static function deleteFile(File $file): bool
    {
        try {
            if (!self::fileExists($file)) {
                return false;
            }

            if (Storage::disk($file->disk)->delete($file->saved_to . '/' . $file->saved_as)) {
                return $file->delete();
            }

        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());
        }

        return false;
    }

    public static function downloadFileInBrowser(File $file, string $save_as = '')
    {
        try {

            if (!self::fileExists($file)) {
                return false;
            }

            $mimeType = Storage::disk($file->disk)->mimeType($file->saved_to . '/' . $file->saved_as);

            if ($save_as === '') {
                $save_as = basename($file->saved_to . '/' . $file->saved_as);
            }

            return Storage::disk($file->disk)->download($file->saved_to . '/' . $file->saved_as, $save_as, ['Content-Type' => $mimeType]);
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());
            abort(404, 'File not found.');
        }
    }

    public static function displayFileInBrowser(File $file)
    {
        try {
            if (!self::fileExists($file)) {
                return false;
            }

            $mime_type = Storage::disk($file->disk)->mimeType($file->saved_to . '/' . $file->saved_as);

            return response(Storage::disk($file->disk)->get($file->saved_to . '/' . $file->saved_as), 200)
                ->header('Content-Type', $mime_type)
                ->header('Content-Disposition', 'inline; filename="' . basename($file->saved_to . '/' . $file->saved_as) . '"');
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());
            abort(404, 'File not found.');
        }
    }

    public static function uploadFile(Connection $connection, string $local_disk, string $local_filepath, string $upload_as): bool
    {
        if ($connection->is_sftp === 1) {
            try {
                $sftp = Connection::makeSftpConnection($connection->host, $connection->port, $connection->username, $connection->password, $connection->timeout, $connection->key);

                if ($sftp) {
                    $fileContents = Storage::disk($local_disk)->get($local_filepath);

                    if ($fileContents === false) {
                        return false;
                    }

                    if ($sftp->put($upload_as, $fileContents)) {
                        return true;
                    }
                }
            } catch (\Exception $exception) {
                Log::debug($exception->getMessage());
            }
            return false;
        }

        try {
            $con = Connection::makeFtpConnection($connection->host, $connection->port, $connection->username, $connection->password, $connection->timeout);

            if ($con) {
                $fileContents = Storage::disk($local_disk)->get($local_filepath);
                if ($fileContents === false) {
                    ftp_close($con);
                    return false;
                }

                $handle = fopen('php://temp', 'rb+');
                fwrite($handle, $fileContents);
                rewind($handle);

                if (!ftp_fput($con, $upload_as, $handle, FTP_BINARY)) {
                    fclose($handle);
                    ftp_close($con);
                    return false;
                }

                fclose($handle);
                ftp_close($con);

                return true;
            }
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());
        }

        return false;
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

    protected function setSplFile(File $file): ?SplFileObject
    {
        if (!self::fileExists($file)) {
            return null;
        }

        try {
            $this->load_file = new SplFileObject(Storage::disk($file->disk)->path($file->saved_to . '/' . $file->saved_as), 'r');
        } catch (\Exception $exception) {
            Log::debug($exception->getMessage());
            return null;
        }

        return $this->load_file;
    }

    protected function closeSplFile(): null
    {//Closes the file pointer
        return $this->load_file = null;
    }

    public function readLines(File $file, int $number_of_lines = 100): ?array
    {
        if (is_null($this->setSplFile($file))) {
            return null;
        }

        (is_null($file->last_line_read)) ? $this->load_file->seek(0) : $this->load_file->seek($file->last_line_read - 1);

        for ($line_number = 0; $line_number < $number_of_lines && !$this->load_file->eof(); $line_number++) {
            $this->line_contents[] = $this->load_file->current();
            $this->load_file->next();
        }

        $this->load_file->seek($this->load_file->getSize());

        $file->update([
            'last_line_read' => $line_number + $file->last_line_read,
            'total_lines' => ($this->load_file->key() + 1)
        ]);

        return $this->line_contents;
    }

    public function readLinesFromTo(File $file, int $from = 0, int $to = 100): ?array
    {
        if (is_null($this->setSplFile($file))) {
            return null;
        }

        $this->load_file->seek($from);

        for ($line_number = $from; $line_number < $to && !$this->load_file->eof(); $line_number++) {
            $this->line_contents[] = $this->load_file->current();
            $this->load_file->next();
        }

        return $this->line_contents;
    }

    public function readLastLines(File $file, int $amount = 20): ?array
    {
        if (is_null($this->setSplFile($file))) {
            return null;
        }

        $this->load_file->seek($this->load_file->getSize());

        $total_lines = ($this->load_file->key() + 1);
        $read_from = ($total_lines - $amount);

        if ($amount > $total_lines) {
            $read_from = 0;
        }

        for ($i = $read_from; $i <= $total_lines; $i++) {

            if ($i === $total_lines) {//Stop
                break;
            }

            $this->load_file->seek($i);

            $this->line_contents[] = $this->load_file->current();

            if (!$this->load_file->current()) {//End of file
                break;
            }
        }

        return array_reverse($this->line_contents);
    }

    public function readOneLine(File $file, int $line = 1): ?array
    {
        if (is_null($this->setSplFile($file))) {
            return null;
        }

        $this->load_file->seek($line);
        return $this->line_contents[] = [$this->load_file->current()];
    }

    public static function appendToFile(File $file, string $data): ?bool
    {
        if (!self::fileExists($file)) {
            return null;
        }

        return Storage::disk($file->disk)->append($file->saved_to . '/' . $file->saved_as, $data);
    }

    public static function prependToFile(File $file, string $data): ?bool
    {
        if (!self::fileExists($file)) {
            return null;
        }

        return Storage::disk($file->disk)->prepend($file->saved_to . '/' . $file->saved_as, $data);
    }

    public static function listFilesInDirectory(string $disk, string $path): array
    {
        return Storage::disk($disk)->files($path);
    }

    public static function listAllFilesInDirectory(string $disk, string $path): array
    {
        return Storage::disk($disk)->allFiles($path);
    }

    public static function listDirectoriesInDirectory(string $disk, string $path): array
    {
        return Storage::disk($disk)->allDirectories($path);
    }

    public static function createDirectory(string $disk, string $path): bool
    {
        return Storage::disk($disk)->makeDirectory($path);
    }

    public static function deleteDirectory(string $disk, string $path): bool
    {
        return Storage::disk($disk)->deleteDirectory($path);
    }

}
