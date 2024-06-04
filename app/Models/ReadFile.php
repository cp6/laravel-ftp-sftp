<?php

namespace App\Models;

use App\Models\Scopes\UserOwnedScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
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
        (!is_null($connection->password)) ? $decrypted_password = Crypt::decryptString($connection->password) : $decrypted_password = '';

        $ftp_con = Connection::makeFtpConnection($connection->host, $connection->port, $connection->username, $decrypted_password);

        if (is_null($ftp_con)) {
            Log::debug('Connection could not be made');
            return null;
        }

        $tempStream = fopen('php://temp', 'rb+');

        $result = ftp_nb_fget($ftp_con, $tempStream, $file_path, FTP_ASCII);

        $lines = [];
        $lineCount = 0;

        while ($result === FTP_MOREDATA) {
            $result = ftp_nb_continue($ftp_con);

            rewind($tempStream);
            while (!feof($tempStream)) {
                $line = fgets($tempStream);
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
            ftruncate($tempStream, 0);
        }

        fclose($tempStream);

        return $lines;
    }

    public static function readFileFromStorage(string $file_directory, string $file, int $start = 0, int $end = 100): ?array
    {
        if (!Storage::disk('public')->fileExists($file_directory . $file)) {
            return null;
        }

        $read = self::firstOrCreate(['directory' => $file_directory, 'file' => $file], []);

        $lines = [];
        $lineNumber = 0;

        $handle = Storage::disk('public')->readStream($file_directory . $file);

        if ($handle !== false) {
            while (($line = fgets($handle)) !== false) {
                if ($lineNumber >= $start && $lineNumber <= $end) {
                    $lines[] = $line;
                }
                if ($lineNumber > $end) {
                    break;
                }
                $lineNumber++;
            }

            fclose($handle);
        }

        return $lines;
    }


}
