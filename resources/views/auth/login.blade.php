<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · AgriQR</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="login-page">
    <main class="login-layout">
        <section class="login-story">
            <a class="brand brand-light" href="{{ route('login') }}">
                <span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 32 32" fill="none"><path d="M7 25V14.5L16 7l9 7.5V25H7Z" stroke="currentColor" stroke-width="2"/><path d="M12 25v-7h8v7M16 7V3m0 0c-3 0-5 2-5 5 3 0 5-2 5-5Zm0 3c0-2 2-4 5-4 0 3-2 5-5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                <span class="brand-name">Agri<span>QR</span><small>FIELD OPERATIONS</small></span>
            </a>
            <div class="story-copy">
                <span class="eyebrow"><span class="eyebrow-dot"></span> MUNICIPAL AGRICULTURE OFFICE</span>
                <h1>Better support<br>for every <em>harvest.</em></h1>
                <p>A clear, reliable workspace for farmer records, agricultural programs and supply distribution.</p>
            </div>
            <div class="story-footer"><span>Supporting the people who grow our future.</span><span>AGRIQR · FIELD OPERATIONS</span></div>
        </section>

        <section class="login-panel">
            <div class="login-card">
                <span class="login-kicker">STAFF PORTAL</span>
                <h2>Welcome back</h2>
                <p class="muted">Sign in with your AgriQR account to continue.</p>

                @if ($errors->any())
                    <div class="alert alert-error" role="alert">{{ $errors->first() }}</div>
                @endif

                <form method="post" action="{{ route('login.store') }}" class="stack-form">
                    @csrf
                    <label class="field">
                        <span>Email or username</span>
                        <input type="text" name="login" value="{{ old('login') }}" autocomplete="username" required autofocus>
                    </label>
                    <label class="field">
                        <span>Password</span>
                        <input type="password" name="password" autocomplete="current-password" required>
                    </label>
                    <label class="check-line"><input type="checkbox" name="remember" value="1"><span>Keep me signed in</span></label>
                    <button class="button button-primary button-wide" type="submit">Sign in <span aria-hidden="true">→</span></button>
                </form>
                <div class="login-security"><span class="security-mark">✓</span><span>Account access is managed by your AgriQR administrator.</span></div>
            </div>
        </section>
    </main>
</body>
</html>
