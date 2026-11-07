<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f4faf7">
    <title>Sign in · AgriQR</title>
    <link rel="icon" type="image/png" href="{{ asset('images/agriqr-icon-light.png') }}" id="app-favicon" data-light-icon="{{ asset('images/agriqr-icon-light.png') }}" data-dark-icon="{{ asset('images/agriqr-icon-dark.png') }}">
    <script>
        try {
            const theme = localStorage.getItem('agriqr-theme');
            document.documentElement.dataset.theme = theme || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        } catch {
            document.documentElement.dataset.theme = 'light';
        }
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="login-page">
    <main class="login-layout">
        <section class="login-story" aria-labelledby="login-welcome">
            <a class="brand login-brand" href="{{ route('login') }}" aria-label="AgriQR home">
                <img
                    class="brand-logo"
                    src="{{ asset('images/agriqr-logo-light.svg') }}"
                    data-theme-logo
                    data-light-logo="{{ asset('images/agriqr-logo-light.svg') }}"
                    data-dark-logo="{{ asset('images/agriqr-logo-dark.svg') }}"
                    alt="AgriQR — Smart Agriculture. Stronger Tomorrow."
                >
            </a>
            <div class="story-copy">
                <span class="eyebrow">MUNICIPAL AGRICULTURE OFFICE</span>
                <h1 id="login-welcome">Better support<br>for every <em>harvest.</em></h1>
                <p>A simpler way to support farmers, manage agricultural programs and keep essential supplies moving.</p>
            </div>
        </section>

        <section class="login-panel" aria-labelledby="login-title">
            <div class="login-panel-top">
                <span class="login-panel-label">AGRIQR · SECURE STAFF ACCESS</span>
                <button class="login-theme-toggle" type="button" data-theme-toggle aria-label="Switch to dark mode" aria-pressed="false">
                    <svg class="theme-icon theme-icon-sun" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"/></svg>
                    <svg class="theme-icon theme-icon-moon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20.2 15.2A8.6 8.6 0 0 1 8.8 3.8 8.8 8.8 0 1 0 20.2 15.2Z"/></svg>
                    <span data-theme-label>Dark mode</span>
                </button>
            </div>

            <div class="login-card">
                <div class="login-card-heading">
                    <span class="login-kicker">WELCOME BACK</span>
                    <h2 id="login-title">Sign in to your account</h2>
                    <p>Please enter your details to continue.</p>
                </div>

                @if ($errors->any())
                    <div class="login-error" role="alert">{{ $errors->first() }}</div>
                @endif

                <form method="post" action="{{ route('login.store') }}" class="login-form">
                    @csrf
                    <label class="login-field" for="login">
                        <span>Email or username</span>
                        <span class="login-input-wrap">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 21a8 8 0 0 0-16 0m8-10a5 5 0 1 0 0-10 5 5 0 0 0 0 10Z"/></svg>
                            <input id="login" type="text" name="login" value="{{ old('login') }}" placeholder="Enter your email or username" autocomplete="username" required>
                        </span>
                    </label>
                    <label class="login-field" for="password">
                        <span>Password</span>
                        <span class="login-input-wrap">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 1 1 8 0v3m-4 5v2"/></svg>
                            <input id="password" type="password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
                            <button class="password-visibility" type="button" data-password-toggle aria-label="Show password" aria-pressed="false">
                                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path class="eye-slash" d="m4 20 16-16"/></svg>
                            </button>
                        </span>
                    </label>
                    <label class="login-remember">
                        <input type="checkbox" name="remember" value="1">
                        <span>Remember me</span>
                    </label>
                    <button class="login-submit" type="submit">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 3h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5m-4-4 4-5-4-5m4 5H3"/></svg>
                        <span>Sign in</span>
                    </button>
                </form>

                <div class="login-assurance">
                    <span class="assurance-icon" aria-hidden="true">
                        <svg viewBox="0 0 20 20" fill="none"><path d="m5 10 3.2 3.2L15.5 6" /></svg>
                    </span>
                    <span>Authorized personnel only</span>
                </div>
            </div>
            <p class="login-footnote">AgriQR · Supporting the people who grow our future</p>
        </section>
    </main>
    <script>
        document.querySelector('[data-password-toggle]').addEventListener('click', (event) => {
            const toggle = event.currentTarget;
            const password = document.querySelector('#password');
            const isVisible = password.type === 'text';

            password.type = isVisible ? 'password' : 'text';
            toggle.setAttribute('aria-label', isVisible ? 'Show password' : 'Hide password');
            toggle.setAttribute('aria-pressed', String(!isVisible));
        });
    </script>
</body>
</html>
