<?php

namespace App\Modules\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Users\Http\Requests\StoreUserRequest;
use App\Modules\Users\Http\Requests\UpdateUserRequest;
use App\Modules\Users\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $filters = $request->validate([
            'search'    => ['nullable', 'string', 'max:100'],
            'sort'      => ['nullable', 'in:name,email,created_at'],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);

        $search    = $filters['search']    ?? '';
        $sort      = $filters['sort']      ?? 'created_at';
        $direction = $filters['direction'] ?? 'desc';

        $users = User::query()->where('is_admin', true)
            ->when($search !== '', fn ($query) => $query->where(fn ($searchQuery) => $searchQuery
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $user) => $this->userData($user));

        return Inertia::render('modules/users/Index', [
            'users'   => $users,
            'filters' => [
                'search'    => $search,
                'sort'      => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('modules/users/Create');
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = User::create([...$request->validated(), 'is_admin' => true]);

        return to_route('users.show', $user)->with('success', 'User created successfully.');
    }

    public function show(User $user): Response
    {
        $this->authorize('view', $user);
        abort_unless($user->is_admin, 404);

        return Inertia::render('modules/users/Show', [
            'user' => $this->userData($user),
        ]);
    }

    public function edit(User $user): Response
    {
        $this->authorize('update', $user);
        abort_unless($user->is_admin, 404);

        return Inertia::render('modules/users/Edit', [
            'user' => $this->userData($user),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        abort_unless($user->is_admin, 404);
        $attributes = $request->validated();

        if ($attributes['password'] === null) {
            unset($attributes['password']);
        }

        $user->update($attributes);

        return to_route('users.show', $user)->with('success', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);
        abort_unless($user->is_admin, 404);

        if ($user->is_admin && User::query()->where('is_admin', true)->count() === 1) {
            return back()->withErrors(['user' => 'The last administrator cannot be deleted.']);
        }

        $user->delete();

        return to_route('users.index')->with('success', 'User deleted successfully.');
    }

    /**
     * @return array{id: int, name: string, email: string, is_admin: bool, created_at: string, updated_at: string}
     */
    private function userData(User $user): array
    {
        return [
            'id'         => $user->id,
            'name'       => $user->name,
            'email'      => $user->email,
            'is_admin'   => $user->is_admin,
            'created_at' => $user->created_at->toISOString(),
            'updated_at' => $user->updated_at->toISOString(),
        ];
    }
}
