#!/bin/bash

# ou est le script ?
DIR=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )
cd "$DIR"

[ -f "../stats/entites_validees.txt" ] && touch "../stats/entites_validees.txt"

# prendre les personnalites publiees non deja validées
# Afficher les  lignes  se  trouvant  dans  fichier1  et  pas  dans fichier2
# trier avant tout

cat ../stats/personnalites.txt | sort | uniq > ../stats/personnalites.txt.tmp
mv ../stats/personnalites.txt.tmp ../stats/personnalites.txt

cat ../stats/entites_validees.txt | sort | uniq > ../stats/entites_validees.txt.tmp
mv ../stats/entites_validees.txt.tmp ../stats/entites_validees.txt

comm -2 -3 ../stats/personnalites.txt ../stats/entites_validees.txt > ../stats/personnalites_a_valider.txt

cat ../stats/personnalites_a_valider.txt | while read f
do
	
	# page fr de wikipedia pour $f
	
	original=$f
	
	f=${f// /_}
	url="https://fr.wikipedia.org/wiki/$f"
	
	#echo ">$url<"
	
	p=$(curl -s $url)
	
	str=$(echo "$p" | grep "Article biographique")
	if (( ${#str} > 0 ))
		then
			echo "$original validé !"
			echo "$original" >> ../stats/entites_validees.txt
			continue
	fi
	
	str=$(echo "$p" | grep "Wikipédia ne possède pas d'article avec ce nom")
	if (( ${#str} > 0 ))
		then
			echo "$original" >> ../listes_lexicales/Sujets/entites_inconnues_wikipedia.txt
			echo "$original" >> ../stats/entites_validees.txt
			continue
	fi
	
	# On a une entité connue qui n'est pas une personnalité
	# Chercher dans les catégories
	categories=$(echo "$p" | grep -Eo "catlinks.*" | sed -e "s/<li>/~>/g" | sed -e "s/<\/li>/<~/g" | tr "~" "\n" | grep -Eo "^>.*<$" | while read l ; do 
			echo $l | sed -e 's/^>//' -e 's/<$//' | grep -Eo ">.*<" | sed -e 's/^>//' -e 's/<$//'
			echo " | "
		done
	)
	categories=$(echo $categories | tr '\n' ' ')
	
	str=$(echo $categories | grep -Eo "Ville|Capitale|Commune|Municipalité")
	if (( ${#str} > 0 ))
		then
			echo "$original Ville"
			echo "$original" >> ../listes_lexicales/Geographie/villes_wikipedia.txt
			echo "$original" >> ../stats/entites_validees.txt
			continue
	fi
	
	str=$(echo $categories | grep -Eo "Entreprise")
	if (( ${#str} > 0 ))
		then
			echo "$original Entreprise"
			echo "$original" >> ../listes_lexicales/Entreprises/entreprises_wikipedia.txt
			echo "$original" >> ../stats/entites_validees.txt
			continue
	fi
	
	str=$(echo $categories | grep -Eo "terroriste")
	if (( ${#str} > 0 ))
		then
			echo "$original Groupes armés"
			echo "$original" >> ../listes_lexicales/Groupes_armes/groupes_armes_wikipedia.txt
			echo "$original" >> ../stats/entites_validees.txt
			continue
	fi
	
	str=$(echo $categories | grep -Eo "Pays|Comté|Quartier")
	if (( ${#str} > 0 ))
		then
			echo "$original géo"
			echo "$original" >> ../listes_lexicales/Geographie/geographie_wikipedia.txt
			echo "$original" >> ../stats/entites_validees.txt
			continue
	fi
	
	str=$(echo $categories | grep -Eo "Traité")
	if (( ${#str} > 0 ))
		then
			echo "$original Traité"
			echo "$original" >> ../listes_lexicales/Institutions/traites_wikipedia.txt
			echo "$original" >> ../stats/entites_validees.txt
			continue
	fi
	
	str=$(echo $categories | grep -Eo "Homonymie de personnes")
	if (( ${#str} > 0 ))
		then
			echo "$original personnalité"
			echo "$original" >> ../stats/entites_validees.txt
			continue
	fi

	str=$(echo $categories | grep -Eo "Groupe ethnique")
	if (( ${#str} > 0 ))
		then
			echo "$original peuple"
			echo "$original" >> ../listes_lexicales/Peuples/groupes_ethniques_wikipedia.txt
			echo "$original" >> ../stats/entites_validees.txt
			continue
	fi

	str=$(echo $categories | grep -Eo "Organisation non gouvernementale")
	if (( ${#str} > 0 ))
		then
			echo "$original ong"
			echo "$original" >> ../listes_lexicales/Institutions/ong_wikipedia.txt
			echo "$original" >> ../stats/entites_validees.txt
			continue
	fi
	
	str=$(echo $categories | grep -Eo "Presse|Journal|Média")
	if (( ${#str} > 0 ))
		then
			echo "$original presse"
			echo "$original" >> ../listes_lexicales/Medias/presse_wikipedia.txt
			echo "$original" >> ../stats/entites_validees.txt
			continue
	fi
	
	str=$(echo $categories | grep -Eo "Parti politique")
	if (( ${#str} > 0 ))
		then
			echo "$original partis politiques"
			echo "$original" >> ../listes_lexicales/Partis_politiques/partis_politiques_wikipedia.txt
			echo "$original" >> ../stats/entites_validees.txt
			continue
	fi
	
	# Les autres passent dans divers
	echo "$original" >> ../listes_lexicales/Sujets/divers_wikipedia.txt
	echo "$original" >> ../stats/entites_validees.txt
	
	exit 1
done
