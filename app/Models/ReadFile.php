<?php

namespace App\Models;

use App\Models\Scopes\UserOwnedScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use SplFileObject;

class ReadFile extends Model
{
    use HasFactory;

    protected $fillable = ['file_id', 'last_line_read', 'total_lines'];

    protected static function boot(): void
    {
        parent::boot();
        static::addGlobalScope(new UserOwnedScope());
    }

    public function file(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(File::class, 'file_id', 'id');
    }

    public static function createNew(int $file_id): bool
    {
        $read_file = new ReadFile();
        $read_file->file_id = $file_id;
        return $read_file->save();
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
        if (!File::fileExists($file)) {
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
        if (!File::fileExists($file)) {
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

        (!isset($file->read->last_line_read)) ? $file_to_read->seek(0) : $file_to_read->seek($file->read->last_line_read - 1);

        $lines = [];

        for ($line_number = 0; $line_number < $number_of_lines && !$file_to_read->eof(); $line_number++) {
            $lines[] = $file_to_read->current();
            $file_to_read->next();
        }

        $file_to_read->seek($file_to_read->getSize());

        $file->read->update([
            'last_line_read' => $line_number + $file->read->last_line_read,
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
