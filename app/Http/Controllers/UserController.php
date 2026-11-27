<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    private const ROLES = ['admin', 'operator', 'customer'];

    public function index(Request $request)
    {
        $users = User::query()
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->input('role')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . trim($request->input('q')) . '%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('users.index', ['users' => $users, 'roles' => self::ROLES]);
    }

    public function create()
    {
        return view('users.create', ['roles' => self::ROLES]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::min(8)],
            'role'     => ['required', Rule::in(self::ROLES)],
        ]);

        $user = new User(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
        // role tidak ada di $fillable, jadi diisi secara eksplisit oleh admin
        $user->forceFill(['role' => $data['role']])->save();

        $this->audit($request, 'user.created', $user, null, ['email' => $user->email, 'role' => $user->role]);

        return redirect()->route('users.index')->with('status', 'User berhasil dibuat.');
    }

    public function updateRole(Request $request, User $user)
    {
        $data = $request->validate(['role' => ['required', Rule::in(self::ROLES)]]);

        // Mencegah admin menurunkan dirinya sendiri; dengan begitu selalu ada minimal satu admin
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['role' => 'Anda tidak bisa mengubah role akun sendiri.']);
        }

        $old = $user->role;
        $user->forceFill(['role' => $data['role']])->save();

        $this->audit($request, 'user.role_changed', $user, ['role' => $old], ['role' => $user->role]);

        return back()->with('status', "Role {$user->name} diubah menjadi {$user->role}.");
    }

    private function audit(Request $request, string $action, User $user, ?array $old, ?array $new): void
    {
        AuditLog::create([
            'user_id'     => $request->user()->id,
            'action'      => $action,
            'entity_type' => 'user',
            'entity_id'   => $user->id,
            'old_values'  => $old,
            'new_values'  => $new,
        ]);
    }
}