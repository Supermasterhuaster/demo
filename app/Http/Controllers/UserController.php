<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UserController extends Controller
{
    /**
     * Create a new user.
     */
    public function create(Request $request)
    {
        try {
            $validated = $request->validate([
                'full_name' => 'required|string|max:255',
                'role' => 'required|string|max:255',
                'efficiency' => 'required|integer|min:0',
            ]);

            $user = User::create($validated);

            return response()->json([
                'success' => true,
                'result' => ['id' => $user->id],
            ], Response::HTTP_CREATED);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'result' => ['error' => $e->errors()],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'result' => ['error' => $e->getMessage()],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    /**
     * Retrieve user or users.
     */
    public function get(Request $request, $id = null)
    {
        try {
            if ($id) {
                $user = User::find($id);

                if (!$user) {
                    return response()->json([
                        'success' => false,
                        'result' => ['error' => 'User not found'],
                    ], Response::HTTP_NOT_FOUND);
                }

                return response()->json([
                    'success' => true,
                    'result' => ['users' => [$user]],
                ]);

            } else {
                $query = User::query();

                if ($request->has('role')) {
                    $query->where('role', $request->query('role'));
                }

                $users = $query->get();

                return response()->json([
                    'success' => true,
                    'result' => ['users' => $users],
                ]);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'result' => ['error' => $e->getMessage()],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update a user.
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'full_name' => 'nullable|string|max:255',
            'role' => 'nullable|string|max:255',
            'efficiency' => 'nullable|integer|min:0',
        ]);

        try {
            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'result' => ['error' => 'User not found'],
                ], Response::HTTP_NOT_FOUND);
            }

            $user->update($validated);

            return response()->json([
                'success' => true,
                'result' => [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'role' => $user->role,
                    'efficiency' => $user->efficiency,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'result' => ['error' => $e->getMessage()],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete a user or all users.
     */
    public function delete($id = null)
    {
        try {
            if ($id) {
                $user = User::find($id);

                if (!$user) {
                    return response()->json([
                        'success' => false,
                        'result' => ['error' => 'User not found'],
                    ], Response::HTTP_NOT_FOUND);
                }

                $user->delete();

                return response()->json([
                    'success' => true,
                    'result' => [
                        'id' => $user->id,
                        'full_name' => $user->full_name,
                        'role' => $user->role,
                        'efficiency' => $user->efficiency,
                    ],
                ]);

            } else {
                User::truncate();

                return response()->json([
                    'success' => true,
                ]);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'result' => ['error' => $e->getMessage()],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
