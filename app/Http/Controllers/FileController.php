<?php

namespace App\Http\Controllers;

use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): \Illuminate\Http\JsonResponse
    {
        return response()->json(File::all());
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(File $file): \Illuminate\Http\JsonResponse
    {
        return response()->json($file);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(File $file)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, File $file): bool
    {
        if (!File::fileExists($file)) {
            return false;
        }

        $size_kb = Storage::disk($file->disk)->size($file->saved_to . '/' . $file->saved_as) / 1024;

        return $file->update(['size_kb' => $size_kb]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(File $file): bool
    {
        if (!File::fileExists($file)) {
            return false;
        }

        $delete = Storage::disk($file->disk)->delete($file->saved_to . '/' . $file->saved_as);

        if ($delete) {
            return $file->delete();
        }

        return false;
    }
}
