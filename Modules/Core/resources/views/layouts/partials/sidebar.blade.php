      <!--begin::Sidebar-->
      <aside class="app-sidebar bg-body shadow" data-bs-theme="light">
        <!--begin::Sidebar Brand-->
        <div class="sidebar-brand">
          <!--begin::Brand Link-->
          <a href="{{ url('/') }}" class="brand-link">
            <!--begin::Brand Image-->
            <img
              src="{{ asset('adminlte/assets/img/AdminLTELogo.png') }}"
              alt="AdminLTE Logo"
              class="brand-image opacity-75 shadow"
            />
            <!--end::Brand Image-->
            <!--begin::Brand Text-->
            <span class="brand-text fw-light">Learn&Quiz</span>
            <!--end::Brand Text-->
          </a>
          <!--end::Brand Link-->
        </div>
        <!--end::Sidebar Brand-->
        <!--begin::Sidebar Wrapper-->
        <div class="sidebar-wrapper">
          <nav class="mt-2">
            <!--begin::Sidebar Menu-->
            <ul
              class="nav sidebar-menu flex-column"
              data-lte-toggle="treeview"
              role="navigation"
              aria-label="Main navigation"
              data-accordion="false"
              id="navigation"
            >
              <li class="nav-item menu-open">
                <a href="#" class="nav-link active">
                  <i class="nav-icon bi bi-speedometer"></i>
                  <p>
                    Learn&Quiz
                    <i class="nav-arrow bi bi-chevron-right"></i>
                  </p>
                </a>
                <ul class="nav nav-treeview">
                  @can('cores.dashboard.view')
                  <li class="nav-item">
                    <a href="{{ route('cores.dashboard') }}" class="nav-link {{ request()->routeIs('cores.dashboard') ? 'active' : '' }}">
                      <i class="nav-icon bi bi-speedometer2"></i>
                      <p>Dashboard</p>
                    </a>
                  </li>
                  @endcan
                  @can('cores.users.index')
                  <li class="nav-item">
                    <a href="{{ route('cores.users.index') }}" class="nav-link {{ request()->routeIs('cores.users.*') ? 'active' : '' }}">
                      <i class="nav-icon bi bi-people"></i>
                      <p>Utilisateurs</p>
                    </a>
                  </li>
                  @endcan
                  @can('cores.admins.index')
                  <li class="nav-item">
                    <a href="{{ route('cores.admins.index') }}" class="nav-link {{ request()->routeIs('cores.admins.*') ? 'active' : '' }}">
                      <i class="nav-icon bi bi-person-badge"></i>
                      <p>Administrateurs</p>
                    </a>
                  </li>
                  @endcan
                  @can('cores.trainers.index')
                  <li class="nav-item">
                    <a href="{{ route('cores.trainers.index') }}" class="nav-link {{ request()->routeIs('cores.trainers.*') ? 'active' : '' }}">
                      <i class="nav-icon bi bi-person-workspace"></i>
                      <p>Formateurs</p>
                    </a>
                  </li>
                  @endcan
                  @can('cores.learners.index')
                  <li class="nav-item">
                    <a href="{{ route('cores.learners.index') }}" class="nav-link {{ request()->routeIs('cores.learners.*') ? 'active' : '' }}">
                      <i class="nav-icon bi bi-mortarboard"></i>
                      <p>Apprenants</p>
                    </a>
                  </li>
                  @endcan
                  @can('cores.groups.index')
                  <li class="nav-item">
                    <a href="{{ route('cores.groups.index') }}" class="nav-link {{ request()->routeIs('cores.groups.*') ? 'active' : '' }}">
                      <i class="nav-icon bi bi-collection"></i>
                      <p>Groupes</p>
                    </a>
                  </li>
                  @endcan
                  @can('cores.quizzes.index')
                  <li class="nav-item">
                    <a href="{{ route('cores.quizzes.index') }}" class="nav-link {{ request()->routeIs('cores.quizzes.*') ? 'active' : '' }}">
                      <i class="nav-icon bi bi-question-circle"></i>
                      <p>Quiz</p>
                    </a>
                  </li>
                  @endcan
                  @can('cores.articles.index')
                  <li class="nav-item">
                    <a href="{{ route('cores.articles.index') }}" class="nav-link {{ request()->routeIs('cores.articles.*') ? 'active' : '' }}">
                      <i class="nav-icon bi bi-file-earmark-richtext"></i>
                      <p>Articles</p>
                    </a>
                  </li>
                  @endcan

                  @can('cores.roles.index')
                  <li class="nav-item">
                    <a href="{{ route('cores.roles.index') }}" class="nav-link {{ request()->routeIs('cores.roles.*') ? 'active' : '' }}">
                      <i class="nav-icon bi bi-shield-lock"></i>
                      <p>Rôles</p>
                    </a>
                  </li>
                  @endcan
                  @can('cores.permissions.index')
                  <li class="nav-item">
                    <a href="{{ route('cores.permissions.index') }}" class="nav-link {{ request()->routeIs('cores.permissions.*') ? 'active' : '' }}">
                      <i class="nav-icon bi bi-key"></i>
                      <p>Permissions</p>
                    </a>
                  </li>
                  @endcan
                  @can('cores.modules.index')
                  <li class="nav-item">
                    <a href="{{ route('cores.modules.index') }}" class="nav-link {{ request()->routeIs('cores.modules.*') ? 'active' : '' }}">
                      <i class="nav-icon bi bi-box-seam"></i>
                      <p>Modules</p>
                    </a>
                  </li> 
                  @endcan
                  @can('cores.activities.index')
                  <li class="nav-item">
                    <a href="{{ route('cores.activities.index') }}" class="nav-link {{ request()->routeIs('cores.activities.*') ? 'active' : '' }}">
                      <i class="nav-icon bi bi-clock-history"></i>
                      <p>Activités</p>
                    </a>
                  </li>
                  @endcan
                </ul>
              </li>
               
            </ul>
            <!--end::Sidebar Menu-->
          </nav>
        </div>
        <!--end::Sidebar Wrapper-->
      </aside>
      <!--end::Sidebar-->
