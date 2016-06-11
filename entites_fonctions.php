<?php

include('mots_courants.php');

// remonter la limite de taille d'une regexp
// essential for huge PCREs
// ini_set("pcre.backtrack_limit", "10000000000000");
// ini_set("pcre.recursion_limit", "10000000000000");

// http://fr.wikipedia.org/wiki/Table_des_caract%C3%A8res_Unicode/U0080
define("LETTRES","[a-zA-ZàáâãäåæçèéêëìíîïðñòóôõöøùúûüýþÿÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßœŒ-]");

// http://www.regular-expressions.info/unicode.html
// \p{L} lettres
// \p{Lu} or \p{Uppercase_Letter}: an uppercase letter that has a lowercase variant. 
define("LETTRE_CAPITALE","\p{Lu}");


// var_dump("<pre>",$types_entites,"</pre>");

// Isoler les entites connues (Institutions, Traités etc).
function trouver_entites($texte,$id_article){

	$fragments = array();
	$texte_original = $texte ;

	// Traiter les notes de bas de pages.
	// SOURCES
	if(preg_match_all("/\[\[(.*)\]\]/Umsu", $texte, $notes)){
		foreach($notes[1] as $note){
			if(preg_match_all("/\{((?!Cf|Ibid)[^,]+),?\}/uims",$note,$e)){
				$recolte = traiter_fragments($e[1], "Sources", $texte, $fragments, $id_article, $texte_original)	;
				$fragments = $recolte['fragments'];
				$texte = $recolte['texte'];
			}
		}
	}

	//var_dump($fragments,"<hr><hr>");

	// types d'entites définis dans les listes txt.
	$types_entites =  generer_types_entites("multi");	

	// Isoler les entites connues (Institutions, Traités etc).
	$recolte = recolter_fragments("Institutions", $types_entites['Institutions'], $texte, $fragments, $id_article, $texte_original);
	$fragments = $recolte['fragments'];
	$texte = $recolte['texte'];

	//var_dump($fragments,"<hr><hr>");

	// itals spip
	$texte = str_replace("{", "", $texte);
	$texte = str_replace("}", "", $texte);

	// Isoler les entites inconnues de la forme : Conseil national pour la défense de la démocratie (CNDD)
	$acronymes = "((?<!\.\s)" . LETTRE_CAPITALE . "(?:". LETTRES ."|\s)+)\((" . LETTRE_CAPITALE . "+)\)";
	$recolte = recolter_fragments("Institutions", $acronymes, $texte, $fragments, $id_article, $texte_original);
	$fragments = $recolte['fragments'];
	$texte = $recolte['texte'];
	
	//var_dump($fragments,"<hr><hr>");
	
	/* regex automatisées depuis les fichiers de listes */
	foreach($types_entites as $type => $regex){

		// on ne refait pas les institutions
		if($type == "Institutions"){
			continue;
		}
		if($regex == "")
			continue;
		
		$recolte = recolter_fragments($type, $regex, $texte, $fragments, $id_article, $texte_original);
		$fragments = $recolte['fragments'];
		$texte = $recolte['texte'];
	}

	// on enlève des FONCTIONS de QUELQUEPART : premier ministre du Luxembourg etc
	$recolte = recolter_fragments("Fonctions", "(" . FONCTIONS_PERSONNALITES . ")\s(?:du|de la|d'|des)\s(". LETTRE_CAPITALE . LETTRES ."+)", $texte, $fragments, $id_article, $texte_original);
	$fragments = $recolte['fragments'];
	$texte = $recolte['texte'];
	
	// Dans le texte expurgé des entités connues, on passe la regexp qui trouve des noms.
	// a mettre en démo	

	$noms = trouver_noms($texte) ;

	$recolte = traiter_fragments($noms, "Personnalités", $texte, $fragments, $id_article, $texte_original) ;
	$fragments = $recolte['fragments'];
	$texte = $recolte['texte'];

	// Isoler les présidents résiduels
	$recolte  = recolter_fragments("Personnalités", "présidente?\s(" . LETTRE_CAPITALE . LETTRES ."+)" , $texte, $fragments, $id_article, $texte_original) ;
	$fragments = $recolte['fragments'];
	$texte = $recolte['texte'];
	
	// trouver des entites constituées d'un seul mot

	// types d'entites définis dans les listes txt.
	$types_entites_mono =  generer_types_entites("mono");	

	/* regex automatisées depuis les fichiers de listes */
	foreach($types_entites_mono as $type => $regex){
		if($regex == "")
			continue;

		$recolte = recolter_fragments($type, $regex, $texte, $fragments, $id_article, $texte_original);
		$fragments = $recolte['fragments'];
		$texte = $recolte['texte'];

	}

	// on cherche des termes qui ont des majuscules dans le texte restant.
	$entites_residuelles = trouver_entites_residuelles($texte);

	// var_dump("<pre>",$entites_residuelles,"</pre><hr>zou<pre>",$fragments,"<hr>",$texte,"<hr>");

	// attention que les patronymes simple genre Obama vont faire reprendre des extraits deja connus dans le texte intégral.
	// On vire donc les noms complets du texte intégral au rique de flinguer quelques extraits.

	$texte_original_sans_noms = $texte_original ;
	if(is_array($noms))
		foreach(array_unique($noms) as $nom){
			$texte_original_sans_noms = str_replace($nom, "", $texte_original_sans_noms);
		}
		
	$recolte = traiter_fragments($entites_residuelles, "INDETERMINE", $texte, $fragments, $id_article, $texte_original_sans_noms) ;
	$fragments = $recolte['fragments'];
	$texte = $recolte['texte'];

	// fusionner les personnalités Barack Obama + Mr Obama => Barack Obama
	foreach($fragments as $v){
		if(preg_match("/(.*)\|Personnalités\|/u",$v,$m))
			$personnalites[] = $m[1] ;
	}
	// var_dump("<pre>",$personnalites,"</pre><hr>");
	if(is_array($personnalites))
		foreach($personnalites as $v){
			$a = explode(" ",$v) ;
			if(sizeof($a) > 1){
				// on suppose que le nom de famille est a la fin (mais ce n'est pas toujours le cas cf les personnalités asiatiques)
				$patronyme = array_pop($a);
				if(!$patronymes[$patronyme])
					$patronymes[$patronyme] = $v ;
			}
		}

	//var_dump("<pre>",$patronymes);
	//var_dump("<pre>",$fragments,"</pre><hr>");

	foreach($fragments as $v){
		if(preg_match("/^(.*)\|(Personnalités|INDETERMINE)\|/u",$v,$m)){
			// attention aux noms de plus de deux mots
			$noms = explode(" ",$m[1]) ;
			$nom = array_pop($noms);
			//var_dump("<pre>",$m[1],$nom);
			if($patronymes[$nom]){
				$f = preg_replace("/^".$m[1]."/u",$patronymes[$nom],$v) ;
				$f = preg_replace("/\|".$m[2]."\|/u","|Personnalités|",$f) ;
				$fragments_fusionnes[] = $f ;
			}
			else
				$fragments_fusionnes[] = $v ;
		}
		else
				$fragments_fusionnes[] = $v ;
	}
	
	//var_dump("<pre>",$fragments_fusionnes);

	if(!is_array($fragments_fusionnes))
		$fragments_fusionnes = array(0 => "PASDENTITE|PASDENTITE|$id_article|");

	// requalifier les Personnalités qui n'en sont pas par heuristique.

	$lieux = ENTITES_LIEUX_HEURISTIQUE ;
	$institutions = ENTITES_INSTITUTIONS_HEURISTIQUE ;

	foreach($fragments_fusionnes as $v){
		if(preg_match("/^(.*)\|(Personnalités)\|/u",$v,$m)){
			if(preg_match("/$lieux/Uu",$m[1])){
				$f = preg_replace("/\|".$m[2]."\|/u","|Géographie (auto)|",$v) ;
				$fragments_traites[] = $f ;
			}elseif(preg_match("/$institutions/Uu",$m[1])){
				$f = preg_replace("/\|".$m[2]."\|/u","|Institutions automatiques|",$v) ;
				$fragments_traites[] = $f ;
			}else{
				$fragments_traites[] = $v ;
			}			
		}else{
			$fragments_traites[] = $v ;
		}
	}

	//var_dump("<pre>",$patronymes,$fragments_fusionnes);
	return $fragments_traites ;
}


function recolter_fragments($type_entite, $regex, $texte, $fragments, $id_article, $texte_original){

	// trouver toutes les occurences d'une entité
	if(preg_match_all( "`" . $regex . "`u" , $texte ,$e)){		
		$entites = $e[0];
		$recolte = traiter_fragments($entites, $type_entite, $texte, $fragments, $id_article, $texte_original);
		$fragments = $recolte["fragments"];
		$texte = $recolte["texte"];
	}
	return array("texte" => $texte, "fragments" => $fragments) ;
}

function traiter_fragments($entites, $type_entite, $texte, $fragments, $id_article, $texte_original){

	// On recupere un tableau d'entites possiblement redondantes
	// nettoyer un peu
	// array_walk($entites,"nettoyer_entite_nommee"); // marche pas
	if(is_array($entites) and sizeof($entites) >= 1)
		array_walk($entites,"nettoyer_entite_nommee");
	else	
		return array("texte" => $texte, "fragments" => $fragments);
		
	// On enregistre les extraits. Tout en isolant des formulations réduites.
	$entites_uniques = array_unique($entites);

	if(is_array($entites_uniques))
		foreach($entites_uniques as $entite){

			if($entite == "")
				continue ;

			// En cas d'accronyme, virer aussi la forme réduite
			$acronymes = "((?<!\.\s)" . LETTRE_CAPITALE . "(?:". LETTRES ."|\s)+)\((" . LETTRE_CAPITALE . "+)\)";
			if(preg_match("/$acronymes/Uu", $entite, $r)){
				// En cas d'accronyme, virer aussi la forme réduite ou moyenne
				$reduite[trim($r[2])] = trim($r[1]) ;
			}
	
			// Trouver les extraits ou apparaissent l'entite dans le texte original
			preg_match_all("`(?:\W)((?:.{0,60})\W" . preg_quote($entite) . "\W(?:.{0,60}))(?:\W)`u", $texte_original, $m);
	
			foreach($m[1] as $extrait){
				$extrait = preg_replace(",\R,","",trim($extrait));
	
				// réguler les types avec plusieurs sous_chaines
				$type = preg_replace("/([^\d]+)\d+$/u", "$1", $type_entite);
				$type = str_replace("_", " ", $type);	
			
				// Enregistrer l'entite
				$fragments[] = $entite . "|$type|" . $id_article . "|" . $extrait ;
	
			}
		}

	// Chasse aux entites ouverte !
	// On supprime les entites du texte pour les chasser toutes à la fin ou ne reste plus qu'un texte sans entites.
	foreach($entites as $entite){
			
		if($entite == "")
			continue ;

		// Trouver l'extrait dans le texte débité
		preg_match("`(?:\W)(?:.{0,60})" . preg_quote($entite) . "(?:.{0,60})(?:\W)`u", $texte, $m);
		$extrait = preg_replace(",\R,","",trim($m[0]));

		// Virer l'entité dans cet extrait, puis dans le texte débité.
		if(!$m[0])
			$texte = str_replace($entite, "" , $texte);
		else{
			$extrait_propre = str_replace($entite,"",$extrait);
			$texte = str_replace($extrait, $extrait_propre , $texte);
		}
	}

	// trouver des formes réduites résiduelles
	// if(is_array($reduite))
	//	var_dump("<pre>", $reduite ,"</pre><hr>");

	return array("texte" => $texte, "fragments" => $fragments);
}


function trouver_noms($texte){

	// M. Joseph (« Joe ») Lhota. http://localhost:8888/diplo/?page=noms&id_article=50640

	// http://stackoverflow.com/questions/7653942/find-names-with-regular-expression

	// virer les débuts de phrases
	$reg =  "%(?:\W)". // un espace ou saut de ligne ou guillement ou apostrophe non capturé
			"(?!(?i)(?:". MOTS_DEBUT .")\s+)". // pas de mot de debut de phrase avec un capitale lambda ou en CAPS
			"(".
				"(?:(?<!\.)" . LETTRE_CAPITALE . "(?!')(?:". LETTRES ."+|\.))". // Un mot avec une capitale non précédée d'un . (C.I.A. Le ...), suivie de lettres ou - ou d'un .
				"(?:\s+" . LETTRE_CAPITALE . "(?:". LETTRES ."+|\.))*". // Des éventuels mots avec une capitale suivie de lettres ou - ou d'un . 
				"(?:\s+(?!(?:". MOTS_MILIEU ."))". LETTRES ."+){0,2}". // Un ou deux éventuels mots (van der), mais pas des mots courants
				"(?:(?:\s+|'|’)(?!". MOTS_FIN .")" . LETTRE_CAPITALE . LETTRES ."+)". // Un mot avec une capitale suivie de lettres ou - , mais pas des mots de fins
			"|". ENTITES_PERSO .")". // Personnalités à pseudo
	"%mu"	;

	preg_match_all($reg,$texte,$m);
	$noms = $m[1] ;
	//var_dump("<pre>",$m);

	array_walk($noms,"nettoyer_noms");
	
	//var_dump("<pre>",$noms);

	return $noms ;
}

// renvoyer en fait patronyme => forme longue
function nettoyer_noms(&$item1, $key){
	// (^M\.|^Mme|^Mgr|^Dr|^Me) \s+
	$item1 = preg_replace("/(^M\.|^Mme|^Mgr|^Dr|^Me)\s+/Uu","",$item1); // pas les civilités
	$item1 = preg_replace("/-$/u"," ",$item1);
	
	// http://archives.mondediplo.com/ecrire/?exec=articles&id_article=8337
	$item1 = preg_replace("/\R/"," ",$item1);
	
	$item1 = trim($item1);

}

function nettoyer_entite_nommee(&$entite, $key){

	// nettoyage des entités choppées avec une ,. ou autre.
	if(strpos($entite, "("))
		$entite = trim(preg_replace("/(?!\))\W+$/u", "", $entite));
	else
		$entite = trim(preg_replace("/\W+$/u", "", $entite));

	$entite = trim(preg_replace("/^\W+/u", "", $entite));

}

function entites_nommees($noms = array()){

	$lieux = ENTITES_LIEUX_EURISTIQUE ;
	$institutions = ENTITES_INSTITUTIONS_EURISTIQUE ;

	foreach($noms as $k => $v){
		if(preg_match("/$lieux/Uu",$k))
			$entites_nommees['lieux'][$k] = $v ;
		elseif(preg_match("/$institutions/Uu",$k))
			$entites_nommees['institutions'][$k] = $v ;	
		else
			$entites_nommees['personnalites'][$k] = $v ;		 
		
	}

	return $entites_nommees ;
}

function preparer_texte($texte){

	//$texte = "Philippe Lucas et Jean-Claude Vatin, Maspero, Paris, 1975."; << merde car pas d'espace au debut

	//http://archives.mondediplo.com/?page=noms&id_article=2488
	//$texte = "Dans l’article d’Eric Rouleau « Ce pouvoir si pesant des militaires turcs »" ; << '

	// M. François d’Orcival http://archives.mondediplo.com/?page=noms&id_article=38507&var_mode=recalcul
	//$texte = "M. François d'Orcival" ;
	// $texte = "Fondé en 1954, sur l’initiative de M. François Honti, par M. Beuve-Méry, le Monde diplomatique atteint";
	// insecables utf-8

	$texte = str_replace("\xC2\xA0", " ", $texte);
	$texte = str_replace("’", "'", $texte);
	$texte = str_replace("~", " ", $texte);

	// Nettoyer les inters et itals spip.
	$texte = str_replace("}}}", ". ", $texte);
	// gras spip
	$texte = str_replace("{{", "", $texte);
	$texte = str_replace("}}", "", $texte);

	include_spip("inc/filtres");
	$texte= " " . filtrer_entites($texte) ; // ne pas louper un nom en début de texte.

	return $texte ;

}

function trouver_entites_residuelles($texte){

	// mots avec une majuscule.
	preg_match_all("`((?!(?i)(?:". MOTS_DEBUT .")\s+)" . LETTRE_CAPITALE ."(?:". LETTRES ."+))\s+`u", $texte, $m);

	//var_dump("<pre>",$m);

	$entites_residuelles = array_unique($m[1]);		

	if(is_array($entites_residuelles))
		return $entites_residuelles ;
	else{ // ne pas merger avec un tableau NULL
		return array(0 => 'fail');
	}	
	
}

function enregistrer_entites($entites = array(), $id_article){
		
	// effacer les entites deja enregistrées pour cet article (maj)
	include_spip("base/abstract_sql");
	//var_dump("delete from entites_nommees where id_article=$id_article");
	sql_query("delete from entites_nommees where id_article=$id_article");

	foreach($entites as $entite){

		if(preg_match("/^\|/", $entite)){
			var_dump("alert pas d'entite",$entite,$entites);
			continue ;
		}

		$e = explode("|", $entite);

		$id_article_entite = $e[2];
		$extrait = _q($e[3]);
		$entite = _q($e[0]);

		$type_entite = _q($e[1]);
		$date = _q(sql_getfetsel("date_redac","spip_articles","id_article=$id_article"));
		//var_dump($date);
		$req = "INSERT INTO entites_nommees (entite, type_entite, id_article, extrait, date) VALUES ($entite,$type_entite,$id_article_entite,$extrait,$date)" ;
		sql_query($req);
		//var_dump("<pre>",$req,"</pre>");
	}

}

/**/
function generer_types_entites($nb_mots="multi"){
	// Générer des catégories d'entités à partir de l'arborescence de fichiers du répertoire listes_lexicales.
	include_spip('iterateur/data');
	$types_entites_repertoires = inc_ls_to_array_dist(_DIR_RACINE . 'plugins/entites_nommees/listes_lexicales/*') ;

	/* define des entités connues à partir des listes texte.
	*/

	foreach($types_entites_repertoires as $type){

		$entites_multi = array();
		$entites_mono = array();
		$ajout_entites = array();
		$entites_regexp = "" ;

		// pour chaque repertoire
		$t_entite = $type['file'] ;
		// var_dump($t_entite);
	
		// on liste les fichiers
		$sous_categories = inc_ls_to_array_dist(_DIR_RACINE . "plugins/entites_nommees/listes_lexicales/$t_entite/*.txt");
		/**/// creer un type d'entite si le répertoire contient des recettes au format txt.
		if( sizeof($sous_categories) >= 1){
			//var_dump(strtoupper($t_entite));

			// pour chaque fichier
			foreach($sous_categories as $sous_categorie){
				$sous_categorie_type = $sous_categorie['file'] ;
				// var_dump("<hr />-- $t_entite /" . $sous_categorie_type);
				//exit ;
				$sous_cat_ent = file_get_contents(_DIR_RACINE . "plugins/entites_nommees/listes_lexicales/$t_entite/$sous_categorie_type");
				$sous_cat_entites = inc_file_to_array_dist(trim($sous_cat_ent)) ;
				
				foreach($sous_cat_entites as $k => $ligne){
					$ligne = trim($ligne) ;
					//pas de ligne vides ou de // commentaires 
					if( preg_match(",^\/\/|^$,",$ligne) || $ligne == "")
						continue ;
					// entites multi-mots
					if(strpos($ligne," ")){ 
						$entites_multi[] =  $ligne ;
					}else{
						$entites_mono[] =  $ligne ;	
					}
				}
			
				
				//exit ;

			}
			
			
			// Mono ou multi mots ?
			if($nb_mots == "mono")
				$ajout_entites = $entites_mono ;
			else
				$ajout_entites = $entites_multi ;

			//var_dump($ajout_entites);

			// si on a des lignes dans un fichier texte bien rangé
			if( sizeof($ajout_entites) >= 1){
				foreach($ajout_entites as $entite_unique){

					// nettoyer
					$entite_unique = preg_quote($entite_unique);
					
					// gérer les accents
					$entite_unique = preg_replace("/E|É/u", "(?:É|E)", $entite_unique);
					
					// forme développée ou pas
					//$entite_unique = preg_replace("/\(\)/", "", $entite_unique);
					
					$entites_regexp .=  "\W". $entite_unique . "\W|" ; // ne doit pas etre trop long car les regexp ont une limite à 1000000.	

				}
			}

			// pas de | final ni de / 
			$entites_regexp = preg_replace("/\|$|\//","",$entites_regexp);
			
			// on fait des paquets de maximum 10000 de long.
			$longueur = strlen($entites_regexp) ;
			if ($longueur > 10000){
				$nb = ceil($longueur / 10000) ;
				// echo $t_entite . "est $nb fois trop long : " . strlen($entites_regexp) . "<br>" ;
				// position du dernier | avant 40000 char
				$i=1 ;
				$chaine = $entites_regexp ;
				$sous_chaine = array();
				while($i <= $nb){
					$pos = strrpos(substr($chaine, 0, 10000), "\W|") ;
					//echo "dernier | du paquet $i à la pos : $pos" ;
					$s_chaine = substr($chaine,0,$pos) ;
					$types_entites[$t_entite.$i] = $s_chaine . "\W" ;
					//echo $type_entite.$i ." = " . $types_entites[$type_entite.$i] ;
					$chaine = str_replace($s_chaine . "\W|" ,"", $chaine);
					$i ++ ;
				}			
			}else{
				$types_entites[$t_entite] = $entites_regexp ;
			}
		}
	}

	return $types_entites ;


}

function nuage_mot($poids, $max){
	$score = $poids/$max; # entre 0 et 1

	$p = ($unite=floor($score += 0.900001)) . floor(10*($score - $unite)); // technique de rastapopoulos de 0 Ã  10
	$p -= 9;

	$class = ($p >= 8) ? '2'
	   		       : (($p > 4) ? '1.8'
	               : (($p >= 2) ? '1.2' : '1'));

	return $class ;
}
