# Correctif AI Studio — Listes vides dans l'app Kotlin Learn&Quiz

> À coller dans la conversation AI Studio du projet mobile. Diagnostic réalisé
> sur le code réel de l'app ET sur les réponses réelles de l'API : le bootstrap
> échoue au **parsing Moshi** (exception avalée par un `catch` silencieux dans
> `SyncRepository`), donc Room reste vide → aucune liste ne s'affiche.

---

Corrige les problèmes suivants dans l'application, dans cet ordre.

## 1. DTOs trop stricts — cause principale des listes vides

L'API renvoie des `null` là où les DTOs déclarent des champs **non-nullables**.
Moshi (codegen) jette alors `JsonDataException: Non-null value '…' was null`,
tout le bootstrap échoue, et le `catch (e: Exception)` le masque.

Valeurs réelles observées sur l'API :
- `articles[].category` → peut être `null`
- `articles[].content` → peut être `null`
- `articles[].estimated_reading_time` → peut être `null`
- `quizzes[].duration` → peut être `null` (quiz sans limite de temps)
- `quizzes[].description` → peut être `null`

Dans `data/api/Models.kt`, rends **nullables avec valeur par défaut** tous les
champs non-identifiants :
```kotlin
@Json(name = "category") val category: String? = null,
@Json(name = "content") val content: String? = null,
@Json(name = "estimated_reading_time") val estimatedReadingTime: Int? = null,
@Json(name = "duration") val duration: Int? = null,
```
Règle générale à appliquer partout dans `Models.kt` : **seuls `id` et les
booléens/entiers garantis peuvent rester non-nullables** ; tout `String`,
tout nombre optionnel et toute date deviennent `Type? = null`. Adapte les
mappers vers les entités Room en conséquence (valeurs par défaut au mapping :
`dto.category ?: "Général"`, `dto.duration` reste nullable dans l'entité si
« sans limite » doit s'afficher `∞`).

## 2. Décimaux : accepter nombre OU chaîne (robustesse)

Le backend a été corrigé pour renvoyer les décimaux en **nombres JSON**
(`"score": 83.33` et non plus `"83.33"`). Garde néanmoins l'app robuste :
ajoute un adaptateur Moshi tolérant appliqué aux champs décimaux
(`score`, `points_earned`, `points_total`, `score_brut`, `score_total`,
`pourcentage`, `note_sur_vingt`, `easiness_factor`, `taux_reussite`) :

```kotlin
class FlexibleDoubleAdapter {
    @FromJson fun fromJson(reader: JsonReader): Double? = when (reader.peek()) {
        JsonReader.Token.NULL -> reader.nextNull()
        JsonReader.Token.STRING -> reader.nextString().toDoubleOrNull()
        else -> reader.nextDouble()
    }
    @ToJson fun toJson(value: Double?): Double? = value
}
```
(enregistre-le dans le `Moshi.Builder` via un qualifieur `@FlexibleDouble`
posé sur ces champs, ou en adaptateur de type `Double?` global).

## 3. BASE_URL périmée

`app/build.gradle.kts` contient une URL ngrok morte
(`https://5b91-102-67-126-233.ngrok-free.app` — les URLs ngrok gratuites
changent à chaque relance). Deux actions :
1. Mets à jour `BASE_URL` avec l'URL ngrok **courante**.
2. Ajoute un champ « URL du serveur » modifiable sur l'écran de connexion
   (pré-rempli avec `BuildConfig.BASE_URL`, persisté en DataStore, utilisé par
   `NetworkClient`) pour ne plus jamais recompiler quand l'URL change.
   `NetworkClient.resetClient()` doit être appelé quand l'URL change.

## 4. En-tête ngrok

Dans `AuthInterceptor`, ajoute systématiquement :
```kotlin
builder.header("ngrok-skip-browser-warning", "1")
```
Sans lui, ngrok peut servir sa page d'avertissement HTML à la place du JSON
(réponse 200 mais non-JSON → échec de parsing silencieux).

## 5. Ne plus avaler les erreurs — diagnostic visible

Dans `SyncRepository` :
- Remplace chaque `catch (_: Exception)` / `catch (e: Exception)` muet par un
  `Log.e("SyncRepository", "…", e)` **et** fais remonter un message exploitable
  dans `SyncStatus.Error` : au minimum `e.message` et, pour les réponses HTTP
  non-successful, le code + `errorBody()?.string()?.take(200)`.
- Dans `performBootstrap`, le cas `!response.isSuccessful` doit logguer le code
  HTTP au lieu de retourner `false` silencieusement.
- Affiche `SyncStatus.Error` dans l'UI (snackbar sur l'écran d'accueil) avec un
  bouton « Réessayer ».

## 6. Vérification attendue

Après ces corrections, avec l'URL ngrok à jour :
1. Connexion `a.lemaire@example.com` / `password` → OK.
2. Le bootstrap remplit Room : les listes Articles, Quiz (Entraînement),
   Decks et Examens affichent le contenu.
3. Couper le réseau → les listes restent affichées (lecture Room).
4. En cas d'échec de sync, une snackbar explicite apparaît (plus jamais un
   écran vide sans explication).
Ajoute un test unitaire qui parse un JSON de bootstrap contenant
`"category": null`, `"duration": null` et `"score": "83.33"` sans lever
d'exception.
