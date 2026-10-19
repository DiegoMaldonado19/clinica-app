<x-layouts.public title="Cambiar contraseña">
    <div class="mx-auto max-w-md px-5 py-12">
        <h1 class="font-serif text-2xl font-semibold md:text-3xl">Elige tu contraseña</h1>
        <p class="mt-2 text-muted">La que recibiste por correo es temporal. Define una nueva para continuar.</p>

        <form method="POST" action="{{ route('password.change.update') }}" class="mt-8 space-y-5 rounded-card border border-line bg-white p-6 shadow-card">
            @csrf
            <div>
                <label for="password" class="block text-sm font-medium">Nueva contraseña</label>
                <input id="password" name="password" type="password" required autocomplete="new-password" aria-describedby="password-rule"
                    @class(['mt-1 h-12 w-full rounded-card border bg-white px-4', 'border-error' => $errors->has('password'), 'border-line' => ! $errors->has('password')])>
                <p id="password-rule" class="mt-1 text-sm text-muted">Mínimo {{ $minLength }} caracteres.</p>
                @error('password')
                    <p class="mt-1 text-sm text-error">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium">Confirma la contraseña</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                    class="mt-1 h-12 w-full rounded-card border border-line bg-white px-4">
            </div>

            <button type="submit" class="min-h-12 w-full rounded-btn bg-primary px-6 font-medium text-white hover:bg-primary/90">Guardar y continuar</button>
        </form>
    </div>
</x-layouts.public>
