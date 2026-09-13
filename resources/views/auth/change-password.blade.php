<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cambiar contrasena</title>
</head>
<body>
    <main>
        <h1>Cambie su contrasena</h1>
        <p>Su contrasena es temporal. Defina una nueva para continuar.</p>

        @if ($errors->any())
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ route('password.change.update') }}">
            @csrf
            <label for="password">Nueva contrasena</label>
            <input id="password" name="password" type="password" required autocomplete="new-password">

            <label for="password_confirmation">Confirme la contrasena</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">

            <button type="submit">Guardar</button>
        </form>
    </main>
</body>
</html>
