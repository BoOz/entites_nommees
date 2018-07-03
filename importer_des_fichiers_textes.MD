# Pour déterminer les entités nommées dans un ensemble de de fichiers au format texte

Importer les fichiers textes dans spip puis faire tourner les entités nommées.

## installer spip-cli

## Installer spip sur une base de donnée mysql

**Télécharger spip**
`spip dl`

**Créer une base mysql**
```
mysql
create table entites
exit
```

**Installer spip**

```
spip install --db-server mysql --db-host localhost --db-login root --db-pass root --db-database entites --admin-login admin --admin-pass admin
```

**Télécharger les plugins utiles**

```
mkdir plugins
cd plugins
svn co svn://zone.spip.org/spip-zone/_plugins_/convertisseur/trunk convertisseur
git clone https://github.com/BoOz/entites_nommees.git
chmod -R g+rwX entites_nommees
chmod +x entites_nommees/spip-cli/sync_data.sh
```

**Activer les plugins**
```
spip plugins:activer entites_nommees convertisseur
```

## Importer les fichiers textes
...
