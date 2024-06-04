<?php

namespace App\Models;

use App\Models\Scopes\UserOwnedScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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

    public static function readLinesFtp(Connection $connection, string $file_path, int $start = 0, int $end = 100): ?array
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
                if ($lineCount >= $start && $lineCount <= $end) {
                    $lines[] = $line;
                }
                if ($lineCount > $end) {
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


}
