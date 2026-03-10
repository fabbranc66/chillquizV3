<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ChillQuiz - Accesso Admin</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, #14052f, #2f0b76 55%, #0d8ddb);
            font-family: "Segoe UI", system-ui, sans-serif;
            color: #fff;
        }
        .login-card {
            width: min(420px, calc(100vw - 32px));
            padding: 28px;
            border-radius: 20px;
            background: rgba(15, 10, 45, 0.92);
            border: 1px solid rgba(255,255,255,0.18);
            box-shadow: 0 18px 50px rgba(0,0,0,0.35);
        }
        h1 {
            margin: 0 0 6px;
            font-size: 28px;
        }
        p {
            margin: 0 0 18px;
            color: rgba(255,255,255,0.78);
        }
        label {
            display: block;
            margin: 0 0 6px;
            font-weight: 700;
        }
        input {
            width: 100%;
            box-sizing: border-box;
            margin: 0 0 14px;
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.22);
            background: rgba(255,255,255,0.08);
            color: #fff;
            font-size: 15px;
        }
        input::placeholder {
            color: rgba(255,255,255,0.48);
        }
        button {
            width: 100%;
            padding: 12px 14px;
            border: 0;
            border-radius: 12px;
            background: linear-gradient(135deg, #00c2ff, #22c55e);
            color: #06223a;
            font-weight: 800;
            cursor: pointer;
            font-size: 15px;
        }
        .error {
            margin: 0 0 14px;
            padding: 10px 12px;
            border-radius: 10px;
            background: rgba(226, 27, 60, 0.18);
            border: 1px solid rgba(226, 27, 60, 0.4);
            color: #ffd7de;
        }
        .hint {
            margin-top: 12px;
            font-size: 12px;
            color: rgba(255,255,255,0.55);
        }
    </style>
</head>
<body>
    <form method="post" class="login-card" action="<?= htmlspecialchars(chillquiz_public_url('index.php?url=admin/login'), ENT_QUOTES, 'UTF-8') ?>">
        <h1>Accesso Admin</h1>
        <p>Inserisci le credenziali per accedere alla regia.</p>

        <?php if (!empty($error)): ?>
            <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <label for="username">Utente</label>
        <input id="username" name="username" type="text" value="<?= htmlspecialchars($defaultUsername ?? 'admin', ENT_QUOTES, 'UTF-8') ?>" required autocomplete="username">

        <label for="password">Password</label>
        <input id="password" name="password" type="password" required autocomplete="current-password">

        <button type="submit">Entra</button>
        <div class="hint">Puoi sovrascrivere credenziali con `ADMIN_USERNAME`, `ADMIN_PASSWORD` o `ADMIN_PASSWORD_HASH`.</div>
    </form>
</body>
</html>
