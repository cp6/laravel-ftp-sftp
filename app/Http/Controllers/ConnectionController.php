<?php

namespace App\Http\Controllers;

use App\Models\Connection;
use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Mockery\Exception;

class ConnectionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): \Illuminate\Http\JsonResponse
    {
        return response()->json(Connection::all());
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('connection.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'host' => 'string|required|max:255',
            'port' => 'integer|required|min:1|max:999999',
            'username' => 'string|required|max:64',
            'password' => 'string|nullable|sometimes',
            'timeout' => 'integer|required|min:1|max:999',
            'log_actions' => 'boolean|required',
            'key' => 'string|nullable|sometimes'
        ]);

        try {
            $connection = new Connection();
            $connection->host = $request->host;
            $connection->port = $request->port;
            $connection->username = $request->username;
            $connection->password = (!is_null($request->password)) ? Crypt::encryptString($request->password) : null;
            $connection->timeout = $request->timeout;
            $connection->log_actions = $request->log_actions;
            $connection->is_sftp = 0;//Need to prove that it is!!
            $connection->key = (!is_null($request->key)) ? Crypt::encryptString(trim($request->key)) : null;
            $connection->save();

        } catch (Exception $exception) {
            //log and return
            Log::debug($exception->getMessage());
            return redirect()->route('connection.create')->with('failed', 'Failed reason: ' . $exception->getMessage());
        }

        //Try and connect with SFTP
        $sftp = Connection::makeSftpConnection($connection->host, $connection->port, $connection->username, $connection->password, 3, $connection->key);

        if (is_null($sftp)) {
            Log::debug('Connecting via SFTP failed');

            //Try and connect with FTP now
            $ftp = Connection::makeFtpConnection($connection->host, $connection->port, $connection->username, $connection->password, 3);

            if (is_null($ftp)) {
                Log::debug('Connecting via FTP failed');
                Log::debug('Deleting Connection');
                $connection->delete();
                return redirect()->route('connection.create')->with('failed', 'Failed to connect with SFTP and FTP');
            }
            //Connected via FTP
            $is_sftp = 0;
        } else {
            $is_sftp = 1;//SFTP
        }
        $connection->update(['is_sftp' => $is_sftp]);

        //Redirect to connection show
        return redirect()->route('connection.show', $connection)->with('success', 'Connection added successfully');
    }

    /**
     * Display the specified resource.
     */
    public function show(Connection $connection): \Illuminate\Http\JsonResponse
    {
        return response()->json($connection);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Connection $connection)
    {
        return view('connection.edit', ['connection' => $connection]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Connection $connection): bool
    {
        $request->validate([
            'host' => 'string|required|max:255',
            'port' => 'integer|required|min:1|max:999999',
            'username' => 'string|required|max:64',
            'password' => 'string|nullable|sometimes',
            'timeout' => 'integer|required|min:1|max:999',
            'log_actions' => 'boolean|required',
            'key' => 'string|nullable|sometimes'
        ]);


        return $connection->update($request->all());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Connection $connection)
    {
        //
    }
}
