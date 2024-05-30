<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use phpseclib3\Net\SFTP;
use phpseclib3\Net\SFTP\Stream;

class ReadFile extends Model
{
    use HasFactory;


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


}
