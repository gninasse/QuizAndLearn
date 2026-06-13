<!DOCTYPE html>
<html lang="fr">
  <!--begin::Head-->
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>@yield('title', 'CHU-YO-KEYSTONE')</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!--begin::Accessibility Meta Tags-->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
    <meta name="color-scheme" content="light dark" />
    <meta name="theme-color" content="#007bff" media="(prefers-color-scheme: light)" />
    <meta name="theme-color" content="#1a1a1a" media="(prefers-color-scheme: dark)" />
    <!--end::Accessibility Meta Tags-->
    <!--begin::Primary Meta Tags-->
    <meta name="title" content="CHU-YO-KEYSTONE" />
    <meta name="author" content="ibrahim" />
    <meta
      name="description"
      content="CHU-YO-KEYSTONE"
    />
    <meta
      name="keywords"
      content="CHU-YO-KEYSTONE"
    />
    <!--end::Primary Meta Tags-->
    <!--begin::Accessibility Features-->
    <!-- Skip links will be dynamically added by accessibility.js -->
    <meta name="supported-color-schemes" content="light dark" />
    <!--end::Accessibility Features-->
    <!--begin::Fonts-->
    <link
      rel="stylesheet"
      href="{{ asset('plugins/source-sans-3/index.css') }}"
    />
    <!--end::Fonts-->
    <!--begin::Third Party Plugin(OverlayScrollbars)-->
    <link
      rel="stylesheet"
      href="{{ asset('plugins/overlayscrollbars/styles/overlayscrollbars.min.css') }}"
    />
    <!--end::Third Party Plugin(OverlayScrollbars)-->
    <!--begin::Third Party Plugin(Bootstrap Icons)-->
    <link
      rel="stylesheet"
      href="{{ asset('plugins/bootstrap-icons/font/bootstrap-icons.min.css') }}"
    />
    <!--end::Third Party Plugin(Bootstrap Icons)-->
    <!--begin::Third Party Plugin(font-awesome)-->
    <link
      rel="stylesheet"
        href="{{ asset('plugins/fontawesome/css/all.min.css') }}"
    />
    <!--end::Third Party Plugin(sweetalert2)-->
    <!--begin::Third Party Plugin(sweetalert2)-->
    <link
      rel="stylesheet"
        href="{{ asset('plugins/sweetalert2/sweetalert2.css') }}"
    />
    <!--end::Third Party Plugin(sweetalert2)-->
    <!--begin::Required Plugin(AdminLTE)-->
    <link rel="stylesheet" href="{{ asset('adminlte/css/adminlte.css') }}" />
    <!--end::Required Plugin(AdminLTE)-->
    <!--begin::Required Plugin(tools)-->
    <link rel="stylesheet" href="{{ asset('plugins/tools/tools.css') }}" />
    <!--end::Required Plugin(tools)-->
    
    @stack('css')
  </head>
  <!--end::Head-->
  <!--begin::Body-->
  <body class="layout-fixed sidebar-expand-lg sidebar-open bg-body-tertiary">
    <!--begin::App Wrapper-->
    <div class="app-wrapper">
      
      @include('core::layouts.partials.navbar')

      @include('core::layouts.partials.sidebar')

      <!--begin::App Main-->
      <main class="app-main">
        <!--begin::App Content Header-->
        <div class="app-content-header">
          <!--begin::Container-->
          <div class="container-fluid">
            <!--begin::Row-->
            <div class="row">
              <div class="col-sm-6"><h3 class="mb-0">@yield('header', 'Dashboard')</h3></div>
              <div class="col-sm-6">
                <ol class="breadcrumb float-sm-end">
                  @yield('breadcrumb', '')
                </ol>
              </div>
            </div>
            <!--end::Row-->
          </div>
          <!--end::Container-->
        </div>
        <!--end::App Content Header-->
        <!--begin::App Content-->
        <div class="app-content">
          <!--begin::Container-->
          <div class="container-fluid">
             @yield('content')
          </div>
          <!--end::Container-->
        </div>
        <!--end::App Content-->
      </main>
      <!--end::App Main-->
      <!--begin::Footer-->
      <footer class="app-footer">
        <!--begin::To the end-->
        <div class="float-end d-none d-sm-inline">CHU-YO</div>
        <!--end::To the end-->
        <!--begin::Copyright-->
        <strong>
          Copyright &copy; 2025&nbsp;
          <a href="#" class="text-decoration-none">CHU-YO</a>.
        </strong>
        tous droits reservés.
        <!--end::Copyright-->
      </footer>
      <!--end::Footer-->
    </div>
    <!--end::App Wrapper-->
    <!--begin::Script-->
    <!--begin::Third Party Plugin(jquery)-->
    <script
      src="{{ asset('plugins/jquery/jquery-3.7.1.js') }}"
    ></script>
    <script>
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });
    </script>
    <!--end::Third Party Plugin(jquery)--><!--begin::Third Party Plugin(OverlayScrollbars)-->
    <script
      src="{{ asset('plugins/overlayscrollbars/browser/overlayscrollbars.browser.es6.min.js') }}"
    ></script>
    <!--end::Third Party Plugin(OverlayScrollbars)--><!--begin::Required Plugin(popperjs for Bootstrap 5)-->
    <script
      src="{{ asset('plugins/popper/umd/popper.min.js') }}"
    ></script>
    <!--end::Required Plugin(popperjs for Bootstrap 5)--><!--begin::Required Plugin(Bootstrap 5)-->
    <script
      src="{{ asset('plugins/bootstrap/js/bootstrap.bundle.min.js') }}"
      crossorigin="anonymous"
    ></script>
    <!--end::Required Plugin(Bootstrap 5)--><!--begin::Required Plugin(AdminLTE)-->
    <script src="{{ asset('adminlte/js/adminlte.js') }}"></script>
    <!--end::Required Plugin(AdminLTE)-->
    <!--begin::Third Party Plugin(sweetalert2)-->
    <script src="{{ asset('plugins/sweetalert2/sweetalert2.all.min.js') }}"></script>
    <!--end::Third Party Plugin(sweetalert2)-->
    <!--begin::OverlayScrollbars Configure-->
    <script>
      const SELECTOR_SIDEBAR_WRAPPER = '.sidebar-wrapper';
      const Default = {
        scrollbarTheme: 'os-theme-light',
        scrollbarAutoHide: 'leave',
        scrollbarClickScroll: true,
      };
      document.addEventListener('DOMContentLoaded', function () {
        const sidebarWrapper = document.querySelector(SELECTOR_SIDEBAR_WRAPPER);
        if (sidebarWrapper && OverlayScrollbarsGlobal?.OverlayScrollbars !== undefined) {
          OverlayScrollbarsGlobal.OverlayScrollbars(sidebarWrapper, {
            scrollbars: {
              theme: Default.scrollbarTheme,
              autoHide: Default.scrollbarAutoHide,
              clickScroll: Default.scrollbarClickScroll,
            },
          });
        }
      });
    </script>
    <!--end::OverlayScrollbars Configure-->
    
    <!--begin::Ziggy Routes-->
    @routes
    <!--end::Ziggy Routes-->
    
    @stack('js')
  </body>
  <!--end::Body-->
</html>