#!/bin/sh

# si les fichiers txt sont dans le repertoire chroniques
# plugins/entites_nommees/spip-cli/importertxt.sh chroniques

[ ! -d "import_spip" ] && mkdir "import_spip"

ls $1 | while read f 
do
	echo $f
	titre=$(cat $1/$f | head -1)
	contenu=$(cat $1/$f)
	texte=${contenu/$titre/}
	contenu="<ins class='titre'>$titre</ins>"
	contenu="$contenu\n<ins class='statut'>publie</ins>"
	contenu="$contenu\n"
	contenu="$contenu$texte"
	
	echo "$contenu" > "import_spip/$f"
done

