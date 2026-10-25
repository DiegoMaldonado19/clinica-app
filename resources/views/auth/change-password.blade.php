<x-layouts.public title="Cambiar contraseña">
    <div class="mx-auto max-w-md px-5 py-12">
        <h1 class="font-serif text-2xl font-semibold md:text-3xl">Elige tu contraseña</h1>
        <p class="mt-2 text-muted">La que recibiste por correo es temporal. Define una nueva para continuar.</p>

        <form method="POST" action="{{ route('password.change.update') }}" class="mt-8 space-y-5 rounded-card border border-line bg-white p-6 shadow-card">
            @csrf
            <x-ui.input name="password" label="Nueva contraseña" type="password" required autocomplete="new-password" :help="'Mínimo '.$minLength.' caracteres.'" />
            <x-ui.input name="password_confirmation" label="Confirma la contraseña" type="password" required autocomplete="new-password" />

            <x-ui.button type="submit" class="w-full">Guardar y continuar</x-ui.button>
        </form>
    </div>
</x-layouts.public>
