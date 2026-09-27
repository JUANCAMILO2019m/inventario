@csrf

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-medium mb-1">Nombre *</label>
        <input type="text" name="name" value="{{ old('name', $user->name ?? '') }}"
               class="w-full rounded border border-gray-300 px-3 py-2" required>
        @error('name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Correo *</label>
        <input type="email" name="email" value="{{ old('email', $user->email ?? '') }}"
               class="w-full rounded border border-gray-300 px-3 py-2" required>
        @error('email') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">
            Contraseña {{ isset($user) ? '(dejar vacío para no cambiar)' : '*' }}
        </label>
        <input type="password" name="password" class="w-full rounded border border-gray-300 px-3 py-2"
               {{ isset($user) ? '' : 'required' }}>
        @error('password') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Rol *</label>
        <select name="role" class="w-full rounded border border-gray-300 px-3 py-2" required>
            <option value="personal" @selected(old('role', $user->role ?? '') === 'personal')>Personal (solo consulta)</option>
            <option value="admin" @selected(old('role', $user->role ?? '') === 'admin')>Admin</option>
            <option value="superadmin" @selected(old('role', $user->role ?? '') === 'superadmin')>SuperAdmin</option>
        </select>
        @error('role') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>
</div>

<div class="mt-6 flex gap-3">
    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded">Guardar</button>
    <a href="{{ route('users.index') }}" class="px-5 py-2 rounded border border-gray-300 hover:bg-gray-50">Cancelar</a>
</div>