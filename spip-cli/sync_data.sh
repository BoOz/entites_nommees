#!/bin/sh

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
