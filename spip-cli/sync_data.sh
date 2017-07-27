#!/bin/sh

function typoDiploBrut {
	d=$(curl "$1")
	# virer les 15 premiere lignes, remplacer les <br> par des sauts de lignes, et virer le HTML
	echo "$d" | tail -n +17 | sed -e :a -e 's,<a href="\(http.*\)">,\1~// ,g;s/<br *\/*>/~/g;s/~~*/~/g;s/<[^>]*>//g;/</N;//ba' | tr "\n" "~" | sed -e "s/~~~*/~~/g" | tr '~' "\n" 
}

echo "Mise à jour des dictionnaires." ;
cd plugins/entites_nommees/

# Synchro Google Sheet

echo "Alias sur Google spreadsheets"
curl "https://docs.google.com/spreadsheets/d/1ks_VyPlc3dAzGjrV08j1OaP6bKh4umuOjE92jCqfhAo/pub?gid=0&single=true&output=tsv" > recaler-gsp.txt 
new=$(cat recaler-gsp.txt | tr '\r' '\n' | tr -s '\n') # nettoyer les sauts de lignes
echo "$new" > recaler.txt
rm recaler-gsp.txt
git diff HEAD -- recaler.txt > diff-recaler.txt
echo "Mise à jour des alias"
cat diff-recaler.txt


# Synchro TypoDiplo

echo "/* Mise à jour typoDiplo */"
cd listes_lexicales

echo "Mise à jour des partis"
typoDiploBrut "http://typo.mondediplo.net/?page=entites_nommees&entite=partis_formations_politiques" > Partis_politiques/partis_typo.txt


# Diff

cd ../
git diff  HEAD -- listes_lexicales/ > listes_lexicales/diff.txt
cat listes_lexicales/diff.txt

echo "\n Valider le diff et mettre à jour la BDD ? [o/n]"
read valider
[[ $valider == "o" ]] && echo "C'est parti !\n" || exit