#!/bin/sh

function typoDiploBrut {
	d=$(curl --silent "$1")
	# virer les 15 premiere lignes, remplacer les <br> par des sauts de lignes, et virer le HTML
	echo "$d" | tail -n +17 | sed -e :a -e 's,<a href="\(http.*\)">,\1~// ,g;s/<br *\/*>/~/g;s/~~*/~/g;s/<[^>]*>//g;/</N;//ba' | tr "\n" "~" | sed -e "s/~~~*/~~/g" | tr '~' "\n" 
}

echo "Mise à jour des dictionnaires.\n" ;
cd plugins/entites_nommees/

# Synchro Google Sheet

echo "Mise à jour des alias sur Google spreadsheets"
curl --silent "https://docs.google.com/spreadsheets/d/1ks_VyPlc3dAzGjrV08j1OaP6bKh4umuOjE92jCqfhAo/pub?gid=0&single=true&output=tsv" > recaler-gsp.txt 
new=$(cat recaler-gsp.txt | tr '\r' '\n' | tr -s '\n') # nettoyer les sauts de lignes
echo "$new" > recaler.txt
rm recaler-gsp.txt
git diff HEAD -- recaler.txt > diff-recaler.txt
cat diff-recaler.txt


# Synchro TypoDiplo

echo "Mise à jour les listes TypoDiplo\n"
cd listes_lexicales

typoDiploBrut "http://typo.mondediplo.net/?page=entites_nommees&entite=institutions" > Institutions/institutions_typo.txt
typoDiploBrut "http://typo.mondediplo.net/?page=entites_nommees&entite=partis_formations_politiques" > Partis_politiques/partis_typo.txt
typoDiploBrut "http://typo.mondediplo.net/?page=entites_nommees&entite=pays" > Pays/pays_typo.txt
typoDiploBrut "http://typo.mondediplo.net/?page=entites_nommees&entite=villes" > Villes/villes_typo.txt
typoDiploBrut "http://typo.mondediplo.net/?page=entites_nommees&entite=lieux" > Geographie/geographie_typo.txt
typoDiploBrut "http://typo.mondediplo.net/?page=entites_nommees&entite=medias" > Journaux/journaux_typo.txt
typoDiploBrut "http://typo.mondediplo.net/?page=entites_nommees&entite=habitants" > Peuples/peuples_typo.txt
typoDiploBrut "http://typo.mondediplo.net/?page=entites_nommees&entite=sujets" > Sujets/sujets_diplo.txt

# Diff

cd ../
git diff  HEAD -- listes_lexicales/ > listes_lexicales/diff.txt
cat listes_lexicales/diff.txt

echo "Valider le diff et mettre à jour la BDD ? [o/n]"

read valider
[[ $valider == "o" ]] && echo "C'est parti !\n" || exit
