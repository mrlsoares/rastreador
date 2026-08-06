<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    #[OA\Post(
        path: '/api/v1/login',
        summary: 'Autentica um usuário e emite um token Sanctum (Bearer)',
        tags: ['Autenticação']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['email', 'password'],
            properties: [
                new OA\Property(property: 'email',    type: 'string', format: 'email', example: 'admin@exemplo.com'),
                new OA\Property(property: 'password', type: 'string', format: 'password', example: 'segredo'),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Token emitido')]
    #[OA\Response(response: 422, description: 'Credenciais inválidas')]
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $credentials['email'])->first();

        // Verificação em tempo (quase) constante: sempre executa Hash::check.
        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Credenciais inválidas.'],
            ]);
        }

        // Revoga tokens antigos deste cliente antes de emitir um novo.
        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'success' => true,
            'token'   => $token,
            'user'    => [
                'id'         => $user->id,
                'name'       => $user->name,
                'email'      => $user->email,
                'empresa_id' => $user->empresa_id,
                'roles'      => $user->getRoleNames(),
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/v1/logout',
        summary: 'Revoga o token atual',
        security: [['bearerAuth' => []]],
        tags: ['Autenticação']
    )]
    #[OA\Response(response: 200, description: 'Token revogado')]
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['success' => true, 'message' => 'Sessão encerrada.']);
    }
}
