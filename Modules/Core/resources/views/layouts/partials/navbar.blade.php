      <!--begin::Header-->
      <nav class="app-header navbar navbar-expand bg-body">
        <!--begin::Container-->
        <div class="container-fluid">
          <!--begin::Start Navbar Links-->
          <ul class="navbar-nav">
            <li class="nav-item">
              <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button" aria-label="Afficher ou masquer le menu" title="Afficher ou masquer le menu">
                <i class="bi bi-list"></i>
              </a>
            </li>
          </ul>
          <!--end::Start Navbar Links-->
          <!--begin::End Navbar Links-->
          <ul class="navbar-nav ms-auto">
            <!--end::Notifications Dropdown Menu-->
            <!--begin::Fullscreen Toggle-->
            <li class="nav-item">
              <a class="nav-link" href="#" data-lte-toggle="fullscreen" role="button" aria-label="Plein écran" title="Plein écran">
                <i data-lte-icon="maximize" class="bi bi-arrows-fullscreen"></i>
                <i data-lte-icon="minimize" class="bi bi-fullscreen-exit" style="display: none"></i>
              </a>
            </li>
            <!--end::Fullscreen Toggle-->
            <!--begin::User Menu Dropdown-->
            <li class="nav-item dropdown user-menu">
              <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                <img
                  src="{{ Auth::user()->avatar_url }}"
                  class="user-image rounded-circle shadow"
                  alt="Photo de profil de {{ Auth::user()->name }}"
                />
                <span class="d-none d-md-inline">{{ Auth::user()->name }}</span>
              </a>
              <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                <!--begin::User Image-->
                <li class="user-header text-bg-primary">
                  <img
                    src="{{ Auth::user()->avatar_url }}"
                    class="rounded-circle shadow"
                    alt="Photo de profil de {{ Auth::user()->name }}"
                  />
                  <p>
                    {{ Auth::user()->name }}
                    <small>Actif depuis : {{ Auth::user()->created_at->locale('fr')->translatedFormat('d F Y')}}</small>
                  </p>
                </li>
                <!--end::User Image-->
                
                <!--begin::Menu Footer-->
                <li class="user-footer">
                  <a href="{{ route('cores.profile') }}" class="btn btn-default btn-flat"><i class="bi bi-person"></i> Mon profil</a>
                  <a href="#" class="btn btn-default btn-flat float-end"
                     onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                     <i class="bi bi-box-arrow-right"></i> Se déconnecter
                  </a>
                  <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                      @csrf
                  </form>
                </li>
                <!--end::Menu Footer-->
              </ul>
            </li>
            <!--end::User Menu Dropdown-->
          </ul>
          <!--end::End Navbar Links-->
        </div>
        <!--end::Container-->
      </nav>
      <!--end::Header-->
