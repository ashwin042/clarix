<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('unit')->where('id', '!=', auth()->id())->latest()->paginate(20);
        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        $units = Unit::orderBy('name')->get();
        return view('admin.users.create', compact('units'));
    }

    public function store(StoreUserRequest $request)
    {
        User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'role'     => $request->role,
            'unit_id'  => $request->role === 'pm' ? $request->unit_id : null,
        ]);

        return redirect()->route('admin.users.index')->with('success', 'User created.');
    }

    public function edit(User $user)
    {
        $units = Unit::orderBy('name')->get();
        return view('admin.users.edit', compact('user', 'units'));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name'    => 'required|string|max:255',
            'email'   => 'required|email|unique:users,email,' . $user->id,
            'role'    => 'required|in:admin,pm,writer',
            'unit_id' => 'nullable|exists:units,id',
        ]);

        $user->update([
            'name'    => $data['name'],
            'email'   => $data['email'],
            'role'    => $data['role'],
            'unit_id' => $data['role'] === 'pm' ? $data['unit_id'] : null,
        ]);

        return redirect()->route('admin.users.index')->with('success', 'User updated.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $user->delete();
        return redirect()->route('admin.users.index')->with('success', 'User deleted.');
    }
}
