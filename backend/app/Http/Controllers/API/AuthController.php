<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            "email" => "required|email",
            "password" => "required",
        ]);

        $user = User::where("email", $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                "email" => ["Invalid credentials"],
            ]);
        }

        $token = $user->createToken("pos-token")->plainTextToken;

        return response()->json([
            "success" => true,
            "user" => $user->only(["id", "name", "email", "role_id", "branch_id"]),
            "token" => $token,
            "role" => $user->role->name ?? "user"
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(["success" => true, "message" => "Logged out"]);
    }

    public function user(Request $request)
    {
        return response()->json([
            "success" => true,
            "user" => $request->user()->load("role", "branch")
        ]);
    }
}
