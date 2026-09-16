<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio de sesión | Bemuss Booking</title>
    <style>
        :root {
            color-scheme: dark;
            --bg: #07111f;
            --panel: rgba(16, 29, 51, 0.96);
            --border: rgba(255, 255, 255, 0.1);
            --text: #f5f7fb;
            --muted: #94a3b8;
            --accent: #f4c66a;
            --danger: #fb7185;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            background:
                radial-gradient(circle at top left, rgba(244, 198, 106, 0.12), transparent 30%),
                radial-gradient(circle at bottom right, rgba(125, 211, 252, 0.10), transparent 28%),
                linear-gradient(180deg, #07111f 0%, #050b13 100%);
            color: var(--text);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            padding: 24px;
        }

        .card {
            width: 100%;
            max-width: 440px;
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 28px;
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.35);
        }

        .eyebrow {
            color: var(--accent);
            text-transform: uppercase;
            letter-spacing: .16em;
            font-size: 12px;
        }

        h1 {
            margin: 10px 0 8px;
            font-size: 30px;
            line-height: 1.1;
        }

        p {
            margin: 0 0 22px;
            color: var(--muted);
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #dbe4f0;
            font-size: 13px;
        }

        .field { margin-bottom: 16px; }

        input {
            width: 100%;
            border-radius: 14px;
            border: 1px solid var(--border);
            background: rgba(255,255,255,0.04);
            color: var(--text);
            padding: 12px 14px;
            font: inherit;
            outline: none;
        }

        .error {
            margin-top: 8px;
            color: var(--danger);
            font-size: 12px;
        }

        .row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin: 8px 0 20px;
            color: var(--muted);
            font-size: 13px;
        }

        .button {
            width: 100%;
            border: 0;
            border-radius: 14px;
            padding: 13px 16px;
            background: linear-gradient(135deg, var(--accent), #f7dca2);
            color: #181818;
            font-weight: 700;
            cursor: pointer;
        }

        .notice {
            margin-bottom: 16px;
            padding: 12px 14px;
            border-radius: 14px;
            border: 1px solid rgba(244, 198, 106, 0.22);
            background: rgba(244, 198, 106, 0.08);
            color: #f8e8bf;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="eyebrow">Bemuss Booking</div>
        <h1>Acceso Super Admin</h1>
        <p>Inicio de sesión local para panel web con sesión Laravel.</p>

        @if ($errors->any())
            <div class="notice">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login.store') }}">
            @csrf
            <div class="field">
                <label for="email">Correo</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required>
                @error('email') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="field">
                <label for="password">Contraseña</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required>
                @error('password') <div class="error">{{ $message }}</div> @enderror
            </div>

            <div class="row">
                <label style="display:flex; align-items:center; gap:8px; margin:0;">
                    <input type="checkbox" name="remember" value="1" style="width:auto;">
                    Recordarme
                </label>
                <span>Solo uso local</span>
            </div>

            <button class="button" type="submit">Entrar</button>
        </form>

    </div>
</body>
</html>
