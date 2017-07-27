#!/bin/sh

function typoDiploBrut {
	d=$(curl "$1")
	# virer les 15 premiere lignes, remplacer les <br> par des sauts de lignes, et virer le HTML
	echo "$d" | tail -n +17 | sed -e :a -e 's,<a href="\(http.*\)">,\1~// ,g;s/<br *\/*>/~/g;s/~~*/~/g;s/<[^>]*>//g;/</N;//ba' | tr "\n" "~" | sed -e "s/~~~*/~~/g" | tr '~' "\n" 
}

echo "Mise à jour des dictionnaires." ;
cd plugins/entites_nommees/

echo $PWD

echo "Alias sur Google spreadsheets"

curl "https://docs.google.com/spreadsheets/d/1ks_VyPlc3dAzGjrV08j1OaP6bKh4umuOjE92jCqfhAo/pub?gid=0&single=true&output=tsv" > recaler-gsp.txt 

# nettoyer les sauts de lignes
new=$(cat recaler-gsp.txt | tr '\r' '\n' | tr -s '\n')
echo "$new" > recaler.txt
rm recaler-gsp.txt

git diff > diff-recaler.txt

echo "Mise à jour des alias"
cat diff-recaler.txt

echo "/* Mise à jour typoDiplo */"

echo "Mise à jour des partis"
typoDiploBrut "http://typo.mondediplo.net/?page=entites_nommees&entite=partis_formations_politiques" > listes_lexicales/Partis_politiques/partis_typo.txt

