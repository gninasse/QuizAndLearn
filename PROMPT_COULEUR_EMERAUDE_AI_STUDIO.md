# Prompt AI Studio — remplacer le violet par l'émeraude (app Android Learn&Quiz)

> À coller tel quel dans AI Studio, sur le projet Kotlin / Jetpack Compose `learn_and_quiz`.

---

Dans l'application Android Learn&Quiz, la couleur **violette** (`Violet50` … `Violet800`, `#7C3AED`) ne doit plus être utilisée. Remplace-la partout par l'**émeraude**, déjà définie dans `ui/theme/Color.kt`. La PWA web vient de faire le même changement : les deux applications doivent rester identiques.

## Correspondance à appliquer

| Avant | Après |
|---|---|
| `Violet50` | `Emerald50` |
| `Violet100` | `Emerald100` |
| `Violet400` | `Emerald400` |
| `Violet600` | `Emerald600` |
| `Violet700` | `Emerald700` |
| `Violet800` | `Emerald800` |

Les couleurs `Emerald50`, `Emerald100`, `Emerald400`, `Emerald600`, `Emerald700` et `Emerald800` existent déjà dans `ui/theme/Color.kt`. N'en crée pas de nouvelles.

## Fichiers et lignes concernés

Les chemins sont relatifs à `app/src/main/java/com/example/` :

- `ui/screens/TrainingScreen.kt` (lignes 143-145 et 159) : onglet actif « Flashcards » (`activeContainerColor`, `activeContentColor`, `activeBorderColor`) et pastille du nombre de cartes.
- `ui/screens/DecksListScreen.kt` (ligne 209) : bouton « Réviser ».
- `ui/screens/FlashcardStudyScreen.kt` (lignes 121, 217 et 511) : boutons et barre de l'écran de révision.
- `ui/screens/QuizResultScreen.kt` (lignes 117 et 121) : encadré « Rejouer mes erreurs » (fond `Violet100`, texte `Violet800`).
- `ui/screens/ExamResultScreen.kt` (lignes 103, 107 et 122) : encadré et bouton.
- `ui/screens/ExamsListScreen.kt` (ligne 242) : bouton.
- `ui/screens/LeaderboardScreen.kt` (ligne 235) : texte en `Violet700`.
- `ui/components/UserAvatar.kt` (ligne 23) : dans la palette des avatars, remplace la paire violette `Pair(Color(0xFF7C3AED), Color(0xFF4C1D95))` par la paire sarcelle-cyan `Pair(Color(0xFF14B8A6), Color(0xFF0891B2))`, comme sur la PWA. Ne mets pas d'émeraude ici, pour ne pas avoir deux avatars verts. Garde le même nombre de paires et le même ordre, sinon chaque utilisateur changerait de couleur d'avatar.

## Pour finir

1. Une fois les remplacements faits, supprime les constantes `Violet50` … `Violet800` de `ui/theme/Color.kt`, à condition qu'elles ne servent plus nulle part.
2. Recherche « Violet » et « 7C3AED » dans tout le projet : il ne doit rester aucune occurrence.
3. Vérifie que le mode sombre reste lisible : texte `Emerald400` sur fond sombre, à la place des anciens violets clairs.
4. Ne change rien d'autre : ni le texte, ni la disposition, ni la logique.
5. Le projet doit compiler sans erreur ni nouvel avertissement.
