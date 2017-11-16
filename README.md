# Entités nommées

Plugin pour SPIP qui permet de découvrir des entités nommées dans un corpus. On essaie notamment de trouver les personnalités évoquées par période de temps.

On peut trouver des noms de personnalités dans un texte en recherchant le masque suivant : Xxx Xxx xx xx Xxx

Ce masque est cependant perturbé par les mots courants de la langue francaise en début de phrases, et les pays, les villes et les institutions qui font également usage des capitales.

On essaie donc d'isoler ces entités nommées connues avant de rechercher le masque sur le reliquat du texte.

On s'appuie pour cela sur des listes issues du site typo.mondediplo.net, ou monde-diplomatique.fr
- Lieux
-- Pays
-- Capitales
-- Villes

- Institutions
-- Entreprises
-- ONG
-- Institutions publiques
-- Partis politiques

On cherche ensuite en Regexp le masque Xxx Xxx xx xx Xxx sur le reliquat du texte, en ignorant les mots de la langue française les plus fréquents constatés. fichier stop word  ``mots_courants.php``.

On obtient :
-> Des Personnalités
-> D'autres entités pour gonfler les listes prédéfinies.

# Usage

Lancer la commande spip-cli `spip entites` puis `spip entites -m oui`. La commande `spip entites` traite 1 000 articles. On peut la relancer plusieurs fois si il faut traiter plus que 1 000 articles, voire carrément envoyer `for i in {1..50} ; do spip entites ; done`.

Après une une indexation passer les traitements d'optimisations des données à posteriori avec `spip entites -m oui`

On peut ensuite voir les entités nommées sur la page `/?page=explorer`.

# Toutes les commandes

```
spip entites // pour indexer 1 000 articles.
spip entites -m oui // pour optimiser après une indexation
spip entites -r oui // pour recommencer à zéro l'indexation
```

# Installation dans SPIP

Installer un spip avec une base de données d'articles + le plugin `spip-cli` (https://contrib.spip.net/SPIP-Cli) , puis

```
cd plugins/
git clone https://github.com/BoOz/entites_nommees.git
chmod -R g+rwX entites_nommees
chmod +x entites_nommees/spip-cli/sync_data.sh
```
Copier les fichiers du répertoire `/squelettes`dans votre propre répertoire `/squelettes` ou `/squelettes/contenu`.

Activer ensuite le plugin `entites_nommees` dans l'admin de SPIP.

# Configuration

Définir dans `config/mes_options.php` le secteur dans lequel prendre les articles pour trouver des entités. Par défaut `1`.
```
define('_SECTEUR_ENTITES',1);
define('_SECTEUR_ENTITES',"919, 1379,1723,901"); // pour plusieurs secteurs
```
Note : en SPIP 2, installer aussi le plugin `iterateurs` : https://contrib.spip.net/Iterateurs

# RewriteRule `references`

```
RewriteRule ^references$  spip.php?page=explorer [QSA,L]
RewriteRule ^references/([a-zA-Z0-9._%\ -]+)/?$  spip.php?page=entite&entite=$1 [QSA,L]
```

# Détail de l'optimisation a posteriori les entites enregistrées

```
spip entites -m oui // pour optimiser après une indexation
```

Le fichier `recaler.txt` permet de réformater des entités mal indexées. 

format : 
```
// entite actuelle 	entite dans l'extrait	type_entite	entite
```

Sont reindexées aussi les entités indéterminées ultérieurement ajoutées dans les fichiers `` *_ajouts`` dans chaque sous répertoires de listes lexicales (mise à jour du type d'entités).

Enfin on efface les mots de ``mots_courants.php`` qui ont été enregistrés par erreur.



