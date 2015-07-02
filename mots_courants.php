<?php

/* Listes de MOTS COURANTS qui matchent dans la regexp qui trouve les personnalités, par exemple parce qu'ils sont en début de phrase avec une majuscule. On va isoler les echapper de la regexp  */

$adverbes = "Encore|Autant|Après|Autre|Cela|Ceci|Sauf|Voici|Voilà|Pour|Parce|Cher|Chère|Comme|De|Selon|Si|Mais|En|Ainsi|Même|Avec|Tout|tous|toutes|Sans|Avant|Quels?|malgré|Chaque|Parmi|Ne|Non|Pas|Toujours|Nul|Tant|Celle-ci|Celui-ci|Peut-être|Grâce|Contrairement|Presque" ;
$pronoms = "Les?|La|Aux?|Ces?|Cet+e?|Celui|celles?|ceux|Tous|toute?s?|Des|Aucune?|Du" ;
$verbes = "Lire|Voir|Etant|Est(?:-ce)?|se|Peut-on|Reste|A-t-elle|A-t-il" ;
$conjonctions = "Mais|Ou|Et|Donc|Or|Ni|Car" ;
$conj = "Je|Tu|Il|elles?|on|Nous|Vous|Ils|Lui" ;
$compt = "Une?|Deux|Trois|Quatre|Cinq|Six|Sept|Huit|Neuf|Dix|Plusieurs|Premier|Première|Deuxième|Troisième|Certaines|Certains?|Beaucoup|Laquelle|Les?quels?|Quelque" ;
$coord = "De|Par|Pour|Sans" ;
$coord2 = "Pourquoi|Hormis" ;
$poss = "Notre|Votre|Vos|mon|Ma|ton|son|sa|ses|nos|Leurs?";
$autres = "Qui|Que|quoi|dont|Où|Quant|Quel|Quelles?" ;
$loc = "Sous|sur|dans|Près|Loin|Là|Ici|Devant|Au-delà|Face|Derrière|Contre";
$quant = "Quelques|Nombre|Très|Peu|plus|moins|Certains";
$temps = "Quand|Hier|aujourd|Lors|Depuis|avant|après|pendant";
$jargon = "Cité|Originaire|Né|Née|Mort";
$singleton = "Internet|Dieu|Eglise|Djihad|Prophète|Toile";

define("MOTS_DEBUT", $adverbes . 
					"|" . $pronoms . 
					"|" . $verbes . 
					"|" . $conjonctions . 
					"|" . $conj . 
					"|" . $compt . 
					"|" . $coord . 
					"|" . $coord2 .
					"|" . $poss .
					"|" . $autres .	
					"|" . $loc .															 										
					"|" . $quant .
					"|" . $temps .										
					"|" . $jargon .										
					"|" . $singleton .										
					"|" .  "Bien|Monsieur|Mme|Cependant|Lorsque|L|C|Tandis|Ensuite|Puisque|Rien|Comment|Alors|Tel|Député|Editions|Prix|Commission|Etats?|Organisation|Nièce|Montagnes|Sénat|Vieux|Saint|Moyen|Dès|Naguère|Assemblée|Union|Société|Puis|Occidentaux|Enfin|Directeur|Aussi|Outre|Jamais|Toutefois|Programme|Jeune|Même|Déjà|Entre|Nombreux|Pourtant|Seule?s?|Cet|Non|Certes|Chez|Chacun|Notamment|Nouve(?:au|l)|Préface|AUJOURD|Parallèlement|Dernière|The");

define("MOTS_MILIEU" , "et|dans|à|pour|où|comme|ou|aux|au|and|par|est|ces|selon|sur|avec|contre|ni|une|sans|entre|depuis|jusqu'(?:au|à)|que|devant|sous|d'après|après|rue|\-\-|Dieu|derrière") ;

define("MOTS_FIN" , "Cedex|Parti|Dieu") ;

// Personnalités à Pseudo.

define("ENTITES_PERSO","Machiavel|Molière|Mirabeau|Staline|Lénine|Mao|Bono|Mussolini|Voltaire|Sadate|Hitler|Marx|Lula|Pinochet|Allende");

/* Patterns pour isoler des entités restées parmis des personnalités */

define("ENTITES_LIEUX_HEURISTIQUE","Sud$|^Nord-|Nord$|Est$|Ouest$|Côte|Congo|République|Etats|City");
define("ENTITES_INSTITUTIONS_HEURISTIQUE","Nation|Ambassade|Conseil|Fondation|Foreign|olympique|Culture|Report|Nouvelle|^The|Parti|Patriot|Musée|Parlement|Press$|^Presses|Agence|University|Agreement|Observatory|Company|Fédération|Edition|News|Centre|Accords|démocrat|America|Association|Public|Chambre|Air|Watch$|United|diplomati|Comité|Corporation|Center|Administration");

