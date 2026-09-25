<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        $users = User::orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'email', 'role', 'status', 'created_at']);

        return response()->json(['data' => $users]);
    }

    public function approve(int $id)
    {
        $user = User::findOrFail($id);
        $user->update(['status' => 'approved']);
        return response()->json(['message' => 'User disetujui']);
    }

    public function reject(int $id)
    {
        $user = User::findOrFail($id);
        $user->update(['status' => 'rejected']);
        return response()->json(['message' => 'User ditolak']);
    }

    public function destroy(int $id)
    {
        $user = User::findOrFail($id);
        if ($user->id === auth()->id()) {
            return response()->json(['message' => 'Tidak bisa menghapus akun sendiri'], 422);
        }
        $user->delete();
        return response()->json(['message' => 'User dihapus']);
    }
}