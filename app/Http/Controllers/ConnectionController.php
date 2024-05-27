<?php

namespace App\Http\Controllers;

use App\Models\Connection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Mockery\Exception;

class ConnectionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
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
            $connection->key = $request->key;
        } catch (Exception $exception) {
            //log and return
            Log::debug($exception->getMessage());
            return redirect()->route('connection.create')->with('failed', 'Failed reason: ' . $exception->getMessage());
        }

        //check if sftp or ftp
        $is_sftp = (is_null(Connection::makeSftpConnectionPassword($connection->host, $connection->port, $connection->username, Crypt::decryptString($connection->key->password)))) ? 0 : 1;
        $connection->update(['is_sftp' => $is_sftp]);

        //Redirect to connection show
        return redirect()->route('connection.show')->with('success', 'Connection added successfully');
    }

    /**
     * Display the specified resource.
     */
    public function show(Connection $connection)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Connection $connection)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Connection $connection)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Connection $connection)
    {
        //
    }
}
