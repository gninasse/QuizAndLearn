# Correctif AI Studio #2 — Contenu HTML invisible + ergonomie des questions

> Diagnostic réalisé directement sur le code de l'application. La cause des
> deux bugs signalés (articles et flashcards vides) est **identique et
> certaine** : elle est dans `ui/components/RichHtmlWebView.kt`.

---

## PARTIE 1 — Corriger le contenu invisible (articles + flashcards)

### 1.1 Cause racine : la WebView a une hauteur de 0 pixel

Dans `RichHtmlWebView.kt`, la WebView est déclarée ainsi :

```kotlin
AndroidView(modifier = modifier.fillMaxWidth(), factory = { WebView(ctx)... })
```

Elle reçoit une largeur mais **jamais de hauteur**. Or elle est utilisée :
- dans `ArticleDetailScreen` à l'intérieur d'une `Column` avec
  `verticalScroll(rememberScrollState())`
- dans `FlashcardStudyScreen` avec un simple `Modifier.fillMaxWidth()`

Dans un conteneur à défilement vertical, les enfants reçoivent une contrainte
de hauteur **infinie** : une `AndroidView` sans hauteur intrinsèque se mesure
alors à **0 px**. Le HTML est chargé mais rendu dans une vue de hauteur nulle
→ **écran vide**, exactement le symptôme observé sur les deux écrans.

**Correction** — mesurer la hauteur réelle du document et l'appliquer à la
WebView. Modifie `RichHtmlWebView` ainsi :

```kotlin
@Composable
fun RichHtmlWebView(
    htmlContent: String,
    modifier: Modifier = Modifier,
    fontSize: String = "medium",
    onScrollProgress: ((Float) -> Unit)? = null
) {
    // ... (préparation du HTML inchangée)

    // Hauteur mesurée du contenu, en pixels CSS
    var contentHeightPx by remember(htmlContent) { mutableStateOf(0) }
    val density = LocalDensity.current

    AndroidView(
        modifier = modifier
            .fillMaxWidth()
            .then(
                if (contentHeightPx > 0) Modifier.height(with(density) { contentHeightPx.toDp() })
                else Modifier.height(1.dp)   // hauteur minimale le temps de la mesure
            ),
        factory = { ctx ->
            WebView(ctx).apply {
                // ... configuration existante ...

                // Interface JS qui remonte la hauteur du document
                addJavascriptInterface(object {
                    @android.webkit.JavascriptInterface
                    fun onHeight(height: Int) {
                        post { contentHeightPx = height }
                    }
                }, "AndroidHeight")

                webViewClient = object : WebViewClient() {
                    override fun onPageFinished(view: WebView?, url: String?) {
                        // Mesure après rendu, puis re-mesure quand les images arrivent
                        view?.evaluateJavascript(
                            """
                            (function() {
                              function report() {
                                AndroidHeight.onHeight(
                                  Math.ceil(document.body.scrollHeight * window.devicePixelRatio)
                                );
                              }
                              report();
                              window.addEventListener('load', report);
                              new ResizeObserver(report).observe(document.body);
                              document.querySelectorAll('img').forEach(function(i){
                                i.addEventListener('load', report);
                              });
                            })();
                            """.trimIndent(), null
                        )
                    }

                    override fun shouldInterceptRequest(...) { /* inchangé */ }
                }
            }
        },
        update = { it.loadDataWithBaseURL(...) }
    )
}
```

Points de vigilance :
- `contentHeightPx` doit être **réinitialisé** quand `htmlContent` change
  (clé `remember(htmlContent)`), sinon une carte flashcard garde la hauteur
  de la précédente.
- `document.body.scrollHeight` est en pixels CSS : multiplie par
  `window.devicePixelRatio` pour obtenir des pixels physiques, puis convertis
  en `dp` côté Compose (comme ci-dessus).
- Le `ResizeObserver` et les écouteurs `load` des images sont indispensables :
  sans eux, la hauteur mesurée avant l'arrivée des images reste trop petite et
  le bas de l'article est tronqué.

### 1.2 Deuxième bug : les images pointent vers un domaine injoignable

Toujours dans `RichHtmlWebView` :

```kotlin
webView.loadDataWithBaseURL("https://quizandlearn.local/", preparedHtml, ...)
```

`quizandlearn.local` est le domaine **local du back-office**, inaccessible
depuis le téléphone. Toute URL relative du contenu (`/storage/demo/x.png`)
est donc résolue vers cette adresse morte. L'interception ne sauve que les
médias déjà en cache ; sinon `super.shouldInterceptRequest()` laisse la
WebView charger une URL qui échoue → image cassée.

**Correction** : utiliser l'URL réelle de l'API comme base.
```kotlin
webView.loadDataWithBaseURL(BuildConfig.BASE_URL, preparedHtml, "text/html", "UTF-8", null)
```
(ou mieux : l'URL configurée dynamiquement si tu as ajouté le champ
« URL du serveur » à l'écran de connexion).

### 1.3 Si la LISTE des articles est vide (et pas seulement le détail)

Alors Room ne contient rien : vérifie dans cet ordre et loggue chaque étape —
1. `SyncRepository.performBootstrap()` retourne-t-il `true` ? (log du code HTTP
   et du corps d'erreur en cas d'échec)
2. `articleDao().insertArticles()` reçoit-il une liste non vide ?
3. `getAllArticlesFlow()` émet-il ? (log de la taille dans le ViewModel)

Le backend a été corrigé depuis ton dernier build : les champs `options` vides
sont désormais des objets `{}` (et non `[]`) et les décimaux sont des nombres.
Vide les données de l'app (ou désinstalle/réinstalle) pour forcer un bootstrap
complet propre, puis relance la synchronisation.

### 1.4 Vérification attendue

- Un article s'ouvre et affiche **tout son texte**, ses **3 images** et son
  **lecteur audio** ; le défilement va jusqu'au bas du contenu.
- Une flashcard affiche recto puis verso, images comprises, et **la carte
  s'adapte à la hauteur du contenu** sans le tronquer.
- L'article « [DEV] Guide complet — Ergonomie et lois UX » (12 min) doit
  défiler sur plusieurs écrans et faire progresser la barre de lecture.

---

## PARTIE 2 — Ergonomie moderne des types de questions

Remplace les contrôles génériques par des interactions pensées pour le tactile.
Règle transverse : **aucune liste déroulante (`DropdownMenu` / `ExposedDropdown`)
dans le lecteur de quiz ou d'examen** — elles sont peu lisibles sur mobile et
masquent le contexte de la question.

### 2.1 Associations (`matching`) — remplacer le dropdown par une modale

C'est la demande prioritaire. Comportement attendu :

- Chaque terme est une **carte cliquable** occupant toute la largeur, affichant
  soit la réponse choisie, soit un état vide « Toucher pour associer ».
- Le toucher ouvre un **`ModalBottomSheet`** (Material 3) contenant :
  - un en-tête rappelant le terme à associer (« Associer : *Loi de Fitts* ») ;
  - la liste des définitions disponibles, chacune sur une ligne confortable
    (min. 56 dp), avec la définition **déjà attribuée à un autre terme**
    marquée et grisée (« déjà utilisée pour “Loi de Hick” ») — mais
    sélectionnable, ce qui libère alors l'autre terme ;
  - une option « Effacer le choix » si une valeur est déjà sélectionnée.
- À la sélection : fermeture animée de la modale, la carte du terme se remplit
  avec la définition et passe en style « rempli » (fond accentué, bordure
  colorée, icône ✓).
- Un indicateur de complétion sous la question : « 3 / 4 associations faites ».

### 2.2 Vrai / Faux — deux grandes cartes

Deux `Card` de taille égale côte à côte (ou empilées si le texte est long),
hauteur ≥ 88 dp, avec icône (✓ / ✗), libellé « Vrai » / « Faux », et un état
sélectionné franc : bordure 2 dp accentuée, fond teinté, élévation légère.

### 2.3 QCM (`mcq`) — pastilles lettrées

Chaque choix est une ligne avec une **pastille A / B / C / D** à gauche :
- non sélectionné : pastille grise, lettre visible ;
- sélectionné : pastille pleine de la couleur d'accent, lettre remplacée par
  un ✓ en choix multiple (la lettre reste en choix unique) ;
- en choix multiple, afficher au-dessus « Plusieurs réponses possibles ».
Animation de transition douce (150 ms) sur la couleur et l'élévation.

### 2.4 Texte à trous (`fill_blank`) — champs contextualisés

Plutôt que des champs anonymes empilés, présente chaque trou dans une carte
numérotée avec son contexte, un `OutlinedTextField` à coins arrondis, clavier
« Suivant » enchaînant automatiquement sur le trou suivant (`ImeAction.Next`,
`FocusRequester`), et un indicateur « 2 / 3 trous remplis ».

### 2.5 Remise en ordre (`ordering`) — glisser-déposer tactile

- Poignée ≡ à gauche de chaque ligne, saisie par appui long
  (`detectDragGesturesAfterLongPress`), l'élément saisi s'élève
  (élévation + légère mise à l'échelle) et suit le doigt ;
- les autres éléments s'écartent avec `animateItemPlacement()` ;
- **conserver les flèches ↑ / ↓** (44 dp) pour l'accessibilité ;
- numéros de position mis à jour en direct ;
- retour haptique léger (`HapticFeedbackType.LongPress`) à la saisie et au
  dépôt.

### 2.6 Réponse libre (`open_text`)

`OutlinedTextField` multiligne qui grandit avec le texte (min. 5 lignes),
compteur de caractères discret en bas à droite, et bouton « Effacer » quand
le champ n'est pas vide.

### 2.7 Cohérence transverse du lecteur

- **Une seule question par écran**, carte surélevée avec animation d'entrée
  (fondu + montée de 10 dp, 250 ms).
- En-tête : « Question 3 / 8 » + points, barre de progression fine.
- Barre d'actions **fixée en bas** (`BottomAppBar`) : Précédent / compteur
  « 5/8 répondues » / Suivant ou Terminer — elle ne doit jamais défiler.
- Chrono en haut à droite, passant en rouge sous 60 s (quiz) ou 120 s (examen)
  avec une pulsation discrète.
- Couleurs sémantiques : **bleu** pour les quiz, **ambre** pour les examens
  (déjà la convention de l'application web).

### 2.8 Vérification attendue

Teste avec les quiz de démonstration présents sur le compte
`a.lemaire@example.com` (préfixés `[DEV]`) : ils couvrent tous les types et
toutes les variantes — QCM unique/multiple/partiel, trous sensibles à la casse,
associations à 4 paires, deux questions de remise en ordre, réponse libre,
ainsi qu'un quiz avec images et audio dans les énoncés.
