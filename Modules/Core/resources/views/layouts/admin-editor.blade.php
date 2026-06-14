<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'Learn&Quiz - Admin Editor')</title>
  
  <!-- Fonts -->
  <link rel="stylesheet" href="{{ asset('plugins/source-sans-3/index.css') }}">
  
  <!-- Icon Libraries -->
  <link rel="stylesheet" href="{{ asset('plugins/bootstrap-icons/font/bootstrap-icons.min.css') }}">
  <link rel="stylesheet" href="{{ asset('plugins/fontawesome/css/all.min.css') }}">
  
  <!-- Bootstrap 5 (Core CSS is imported via AdminLTE/Plugins or standard) -->
  <link rel="stylesheet" href="{{ asset('adminlte/css/adminlte.css') }}">
  <link rel="stylesheet" href="{{ asset('plugins/sweetalert2/sweetalert2.css') }}">
  <link rel="stylesheet" href="{{ asset('plugins/select2/css/select2.min.css') }}">
  <link rel="stylesheet" href="{{ asset('plugins/select2/css/select2-bootstrap-5-theme.min.css') }}">
  
  <!-- Layout CSS -->
  <link rel="stylesheet" href="{{ asset('css/admin/theme-custom.css') }}">
  <link rel="stylesheet" href="{{ asset('css/admin/layout.css') }}">
  
  @stack('css')
</head>
<body>

  <!-- Backdrop for Mobile Sidebar -->
  <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

  <div class="admin-editor-wrapper">
    <!-- Sidebar -->
    <aside class="admin-sidebar" id="adminSidebar">
      <div class="sidebar-logo">
        <a href="{{ route('cores.dashboard') }}" class="logo-text">
          <i class="bi bi-mortarboard-fill"></i>
          <span>Learn&Quiz</span>
        </a>
      </div>
      
      <ul class="sidebar-menu">
        @hasSection('sidebar')
          @yield('sidebar')
        @else
          <li class="menu-item {{ request()->routeIs('cores.dashboard') ? 'active' : '' }}">
            <a href="{{ route('cores.dashboard') }}" class="menu-link">
              <i class="bi bi-speedometer2"></i>
              <span>Dashboard</span>
            </a>
          </li>
          <li class="menu-item {{ request()->routeIs('cores.quizzes.*') ? 'active' : '' }}">
            <a href="{{ route('cores.quizzes.index') }}" class="menu-link">
              <i class="bi bi-question-circle-fill"></i>
              <span>Quizzes</span>
            </a>
          </li>
          <li class="menu-item {{ request()->routeIs('cores.articles.*') ? 'active' : '' }}">
            <a href="{{ route('cores.articles.index') }}" class="menu-link">
              <i class="bi bi-file-earmark-richtext-fill"></i>
              <span>Articles</span>
            </a>
          </li>
        @endif
      </ul>
    </aside>

    <!-- Main Content Area -->
    <div class="admin-main">
      <!-- Topbar -->
      <header class="admin-topbar">
        <div class="d-flex align-items-center gap-3">
          <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle Sidebar">
            <i class="bi bi-list"></i>
          </button>
          <h4 class="mb-0 fw-bold d-none d-sm-block">@yield('page-title', 'Editeur')</h4>
        </div>
        
        <div class="topbar-right">
          @auth
            <span class="user-role-badge">
              {{ auth()->user()->roles->first()?->name ?? 'Utilisateur' }}
            </span>
          @endauth
          
          <div class="topbar-icons">
            <button class="topbar-btn" aria-label="Aide">
              <i class="bi bi-question-circle"></i>
            </button>
            <button class="topbar-btn" aria-label="Notifications">
              <i class="bi bi-bell"></i>
              <span class="badge-dot"></span>
            </button>
          </div>
          
          <div class="dropdown">
            <button class="user-avatar-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
              <img src="{{ auth()->user() && auth()->user()->avatar ? asset('storage/' . auth()->user()->avatar) : asset('adminlte/assets/img/avatar5.png') }}" alt="Avatar">
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2" style="border-radius: var(--radius-sm);">
              <li>
                <a class="dropdown-item py-2" href="{{ route('cores.profile') }}">
                  <i class="bi bi-person me-2"></i> Mon Profil
                </a>
              </li>
              <li><hr class="dropdown-divider"></li>
              <li>
                <form action="{{ route('logout') }}" method="POST" class="d-inline">
                  @csrf
                  <button type="submit" class="dropdown-item py-2 text-danger">
                    <i class="bi bi-box-arrow-right me-2"></i> Se déconnecter
                  </button>
                </form>
              </li>
            </ul>
          </div>
        </div>
      </header>

      <!-- Main Content Container -->
      <main class="admin-content-container">
        @yield('editor-content')
      </main>
    </div>
  </div>

  <!-- Modals Zone -->
  @yield('modals')

  <!-- Base Scripts -->
  <script src="{{ asset('plugins/jquery/jquery-3.7.1.js') }}"></script>
  <script src="{{ asset('plugins/popper/umd/popper.min.js') }}"></script>
  <script src="{{ asset('plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
  <script src="{{ asset('plugins/select2/js/select2.full.min.js') }}"></script>
  <script src="{{ asset('plugins/sweetalert2/sweetalert2.all.min.js') }}"></script>
  <script src="{{ asset('js/admin/utils.js') }}"></script>
  
  <!-- Sidebar Toggle Script -->
  <script>
    (function($) {
      $(function() {
        var $sidebar = $('#adminSidebar');
        var $backdrop = $('#sidebarBackdrop');
        var $toggle = $('#sidebarToggle');

        function toggleSidebar() {
          $sidebar.toggleClass('show');
          $backdrop.toggleClass('show');
        }

        $toggle.on('click', function(e) {
          e.stopPropagation();
          toggleSidebar();
        });

        $backdrop.on('click', function() {
          toggleSidebar();
        });
      });
    })(jQuery);
  </script>

  @stack('scripts')
  @stack('js')
</body>
</html>
