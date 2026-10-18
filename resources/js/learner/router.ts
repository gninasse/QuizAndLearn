/**
 * Router History API : URLs propres (/quizzes/12/play), vues lazy-loadées
 * (code-splitting Vite), View Transitions quand le navigateur les supporte.
 */

export interface RouteMatch {
  params: Record<string, string>;
}

export interface RouteDefinition {
  /** Motif de chemin : segments fixes et `:param`. Ex. `/quizzes/:id/play` */
  path: string;
  /** Import lazy du module de vue ; il doit exposer `mount(el, params)`. */
  view: () => Promise<{ mount: (el: HTMLElement, params: Record<string, string>) => void }>;
  /** Titre affiché dans le header. */
  title: string;
  /** Route accessible sans session (login). */
  public?: boolean;
}

interface CompiledRoute extends RouteDefinition {
  regex: RegExp;
  paramNames: string[];
}

function compile(route: RouteDefinition): CompiledRoute {
  const paramNames: string[] = [];
  const pattern = route.path
    .split('/')
    .map((segment) => {
      if (segment.startsWith(':')) {
        paramNames.push(segment.slice(1));
        return '([^/]+)';
      }
      return segment.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    })
    .join('/');

  return { ...route, regex: new RegExp(`^${pattern}/?$`), paramNames };
}

export interface RouterOptions {
  routes: RouteDefinition[];
  /** Accesseur : l'outlet peut être recréé à chaque re-rendu du shell. */
  outlet: () => HTMLElement;
  /** Retourne true si une session est active. */
  isAuthenticated: () => boolean;
  /** Chemin de repli quand non authentifié. */
  loginPath: string;
  /** Chemin par défaut (404 / racine authentifiée). */
  defaultPath: string;
  onNavigate?: (route: RouteDefinition, params: Record<string, string>) => void;
}

/** Comment la résolution a été déclenchée — pilote le défilement. */
type NavigationKind = 'push' | 'pop' | 'refresh';

export class Router {
  private compiled: CompiledRoute[];
  /** Chemin de la dernière vue montée (la query — ex. ?tab= — est un état interne de la vue). */
  private lastKey: string | null = null;
  /** Position de défilement mémorisée par écran, pour le bouton Retour. */
  private scrollPositions = new Map<string, number>();

  constructor(private options: RouterOptions) {
    this.compiled = options.routes.map(compile);

    // Le routeur gère lui-même le défilement (retour → position d'origine).
    if ('scrollRestoration' in history) history.scrollRestoration = 'manual';

    window.addEventListener('popstate', () => {
      // Même écran : simple entrée d'historique d'un overlay (visionneuse
      // d'image…) — on ne remonte surtout pas la vue (quiz en cours !).
      if (this.key() === this.lastKey) return;
      this.rememberScroll();
      void this.resolve('pop');
    });

    // Interception des liens internes : <a data-link href="/...">
    document.addEventListener('click', (event) => {
      const anchor = (event.target as HTMLElement).closest('a[data-link]');
      if (!anchor) return;
      const href = anchor.getAttribute('href');
      if (!href || !href.startsWith('/')) return;
      event.preventDefault();
      void this.go(href);
    });
  }

  current(): string {
    return window.location.pathname;
  }

  async go(path: string, replace = false): Promise<void> {
    this.rememberScroll();
    if (replace) {
      history.replaceState(null, '', path);
    } else if (path !== this.current()) {
      history.pushState(null, '', path);
    }
    await this.resolve('push');
  }

  private key(): string {
    return window.location.pathname;
  }

  private rememberScroll(): void {
    if (this.lastKey !== null) this.scrollPositions.set(this.lastKey, window.scrollY);
  }

  /**
   * Restaure la position après montage. Les vues remplissent parfois leur
   * contenu en asynchrone (IndexedDB) : on réessaie quelques frames tant
   * que la page n'est pas assez haute pour atteindre la position voulue.
   */
  private restoreScroll(target: number): void {
    let tries = 0;
    const attempt = (): void => {
      window.scrollTo(0, target);
      if (Math.abs(window.scrollY - target) > 2 && tries++ < 30) {
        requestAnimationFrame(attempt);
      }
    };
    requestAnimationFrame(attempt);
  }

  /**
   * Monte la vue correspondant à l'URL courante.
   * `refresh` (données resynchronisées) conserve la position de défilement.
   */
  async resolve(kind: NavigationKind = 'refresh'): Promise<void> {
    const path = this.current();

    let matched: { route: CompiledRoute; params: Record<string, string> } | null = null;
    for (const route of this.compiled) {
      const match = path.match(route.regex);
      if (match) {
        const params: Record<string, string> = {};
        route.paramNames.forEach((name, i) => {
          params[name] = decodeURIComponent(match[i + 1] ?? '');
        });
        matched = { route, params };
        break;
      }
    }

    if (!matched) {
      await this.go(
        this.options.isAuthenticated() ? this.options.defaultPath : this.options.loginPath,
        true,
      );
      return;
    }

    if (!matched.route.public && !this.options.isAuthenticated()) {
      await this.go(this.options.loginPath, true);
      return;
    }

    const { route, params } = matched;
    const module = await route.view();

    const key = this.key();
    const keepScroll = kind === 'refresh' && key === this.lastKey ? window.scrollY : null;

    const apply = (): void => {
      const outlet = this.options.outlet();
      outlet.innerHTML = '';
      module.mount(outlet, params);
      this.lastKey = key;
      this.options.onNavigate?.(route, params);

      if (kind === 'pop') {
        this.restoreScroll(this.scrollPositions.get(key) ?? 0);
      } else if (keepScroll !== null) {
        this.restoreScroll(keepScroll);
      } else {
        window.scrollTo(0, 0);
      }
    };

    // View Transitions API avec repli prefers-reduced-motion / non-support.
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (!reduceMotion && document.startViewTransition) {
      document.startViewTransition(apply);
    } else {
      apply();
    }
  }
}
