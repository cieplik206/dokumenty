<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $users = User::query()
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => $user->is_admin,
                'appearance' => $user->appearance,
                'two_factor_enabled' => $user->two_factor_confirmed_at !== null,
            ]);

        return Inertia::render('users/Index', [
            'users' => $users,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('users/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'is_admin' => $request->boolean('is_admin'),
            'appearance' => $validated['appearance'],
        ]);

        return redirect()->route('users.index');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user): Response
    {
        return Inertia::render('users/Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => $user->is_admin,
                'appearance' => $user->appearance,
                'two_factor_enabled' => $user->two_factor_confirmed_at !== null,
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();
        $isAdmin = $request->boolean('is_admin');

        if ($user->is($request->user()) && ! $isAdmin) {
            return back()->withErrors([
                'user' => 'Nie mozesz odebrac sobie uprawnien administratora.',
            ]);
        }

        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'is_admin' => $isAdmin,
            'appearance' => $validated['appearance'],
        ];

        if (! empty($validated['password'])) {
            $payload['password'] = $validated['password'];
        }

        $user->update($payload);

        return redirect()->route('users.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user): RedirectResponse
    {
        if ($user->is(request()->user())) {
            return back()->withErrors([
                'user' => 'Nie mozesz usunac swojego konta.',
            ]);
        }

        $user->delete();

        return redirect()->route('users.index');
    }
}
