<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use phpseclib3\Net\SFTP;

class Connection extends Model
{
    use HasFactory;

    protected $fillable = ['is_sftp', 'host', 'username', 'password', 'port', 'timeout', 'log_actions', 'key'];

    protected static function booted(): void
    {
        static::creating(function (Connection $connection) {
            $connection->sid = Str::random(12);
            $connection->user_id = Auth::id();
        });
    }

    public static function makeSftpConnectionPassword(string $host, int $port, string $user, string $password, int $timeout = 8): ?SFTP
    {
        $sftp = new SFTP($host, $port, $timeout);

        try {
            $sftp->login($user, $password);
        } catch (\Exception $exception) {
            return null;
        }

        return $sftp;
    }


}
