<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Error')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
        }

        .container {
            text-align: center;
            max-width: 500px;
        }

        h1 {
            font-size: 80px;
            margin: 0;
            color: #38bdf8;
        }

        h2 {
            margin: 10px 0;
            font-weight: normal;
        }

        p {
            color: #94a3b8;
        }

        .buttons {
            margin-top: 20px;
        }

        a {
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 6px;
            margin: 5px;
            display: inline-block;
        }

        .btn-primary {
            background: #38bdf8;
            color: #0f172a;
        }

        .btn-secondary {
            border: 1px solid #38bdf8;
            color: #38bdf8;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>@yield('code')</h1>
    <h2>@yield('message')</h2>
    <p>@yield('description')</p>

    <div class="buttons">
        <a href="{{ url('/') }}" class="btn-primary">🏠 Ir al inicio</a>
        <a href="{{ url()->previous() }}" class="btn-secondary">⬅ Volver</a>
    </div>
</div>

</body>
</html>
