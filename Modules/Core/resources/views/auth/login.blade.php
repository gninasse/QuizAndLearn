<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion | {{ config('app.name') }}</title>

    <link rel="stylesheet" href="{{ asset('plugins/fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('plugins/icheck-bootstrap/icheck-bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('adminlte/css/adminlte.css') }}">
</head>
<body class="login-page bg-body-secondary">
<div class="login-box">
    <div class="card card-outline card-primary">
        <div class="card-header text-center">
            <a href="#" class="h1"><b>KEYSTONE</b></a>
        </div>
        <div class="card-body">
            <p class="login-box-msg">Connectez-vous pour ouvrir votre session</p>

            <form action="{{ route('login.post') }}" method="post">
                @csrf
                <div class="input-group mb-3">
                    <input type="text" name="login" class="form-control" placeholder="Email ou Nom d'utilisateur" value="{{ old('login') }}" required autofocus>
                    <div class="input-group-text">
                        <span class="fas fa-envelope"></span>
                    </div>
                </div>
                <div class="input-group mb-3 card-password">
                    <input type="password" name="password" id="password" class="form-control" placeholder="Mot de passe" required>
                    <div class="input-group-text" style="cursor: pointer;" id="togglePassword">
                        <span class="fas fa-eye"></span>
                    </div>
                </div>
                
                @error('login_error')
                    <div class="alert alert-danger text-center">
                        {{ 'Identifiant ou mot de passe incorrect' }}
                    </div>
                @enderror

                <div class="row"> 
                    <div class="col-12">
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Connexion</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="{{ asset('plugins/jquery/jquery-3.7.1.js') }}"></script>
<script src="{{ asset('plugins/bootstrap/js/bootstrap.bundle.js') }}"></script>
<script src="{{ asset('adminlte/js/adminlte.js') }}"></script>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');
        const icon = togglePassword.querySelector('span');

        togglePassword.addEventListener('click', function (e) {
            // toggle the type attribute
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            
            // toggle the eye slash icon
            if (type === 'text') {
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });
</script>
</body>
</html>