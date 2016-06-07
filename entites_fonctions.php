<?php

include('mots_courants.php');

// remonter la limite de taille d'une regexp
// essential for huge PCREs
// ini_set("pcre.backtrack_limit", "10000000000000");
// ini_set("pcre.recursion_limit", "10000000000000");

// http://fr.wikipedia.org/wiki/Table_des_caract%C3%A8res_Unicode/U0080
define("LETTRES","[a-zA-ZàáâãäåæçèéêëìíîïðñòóôõöøùúûüýþÿÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßœŒ-]");
define("LETTRES_CAPITALES","[A-ZÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßŒ-]");


// var_dump("<pre>",$types_entites,"</pre>");

// Isoler les entites connues (Institutions, Traités etc).
function trouver_entites($texte,$id_article=""){

	$fragments = array();
	$texte_original = $texte ;

	// Traiter les notes de bas de pages.
	// SOURCES
	if(preg_match_all("/\[\[(.*)\]\]/Umsu", $texte, $notes)){
		foreach($notes[1] as $note){
			if(preg_match_all("/\{((?!Cf|Ibid)[^,]+),?\}/uims",$note,$e)){
				foreach($e[1] as $s){
					// Trouver l'extrait
					preg_match("`\s(?:.{0,60})".trim(preg_quote($s))."(?:.{0,60})(?:\W)`u",$texte,$m);
					$extrait = trim($m[0]) ;
					$extrait_propre = str_replace($s,"",$extrait);
					$texte = str_replace($extrait, $extrait_propre , $texte);
					$fragments[] = $s . "|Sources|" . $id_article . "|" . $m[0];			
				}		
			}
		}
	}

	// types d'entites définis dans les listes txt.
	$types_entites =  generer_types_entites("multi");	

	// Isoler les entites connues (Institutions, Traités etc).
	$acronymes = "((?<!\.\s)[A-Z](?:". LETTRES ."|\s)+)\(([A-Z]+)\)";

	if(preg_match_all( "`" . $types_entites['Institutions'] . "`" . "ums" ,$texte,$e)){
			//var_dump($e);	
			foreach($e[0] as $s){

				// nettoyage des entités choppées avec une ,. ou autre.
				$s = trim(preg_replace("/\W+$/u", "", $s));
	
				// Trouver l'extrait
				preg_match("/\s(?:.{0,60})".trim(preg_quote($s))."(?:.{0,60})(?:\W)/u",$texte,$m);
				$extrait = trim($m[0]) ;

				// Virer l'entité dans cet extrait, puis dans le texte.
				if(!$m[0])
					$texte = str_replace($s, "" , $texte);
				else{
					$extrait_propre = str_replace($s,"",$extrait);
					$texte = str_replace($extrait, $extrait_propre , $texte);
				}

				// En cas d'accronyme, virer aussi la forme réduite
				if(preg_match("/$acronymes/Uu", $s, $r)){
					// En cas d'accronyme, virer aussi la forme réduite
					$texte = str_replace(trim($r[2]), "" , $texte);
					// et la forme moyenne
					$texte = str_replace(trim($r[1]), "" , $texte);
				}

				// Enregistrer l'entite
				$fragments[] = $s . "|Institutions|" . $id_article . "|" . $m[0];
			}
	}

	// itals spip
	$texte = str_replace("{", "", $texte);
	$texte = str_replace("}", "", $texte);

	// Isoler les entites inconnues de la forme : Conseil national pour la défense de la démocratie (CNDD)
	preg_match_all("/$acronymes/Uu", $texte, $entites_inconnues);

	//var_dump("<pre>");
	//var_dump($entites_inconnues);
	//var_dump("</pre>");

	foreach($entites_inconnues[0] as $s){
		
		// Trouver l'extrait
		preg_match("/\s(?:.{0,60})".trim(preg_quote($s))."(?:.{0,60})(?:\W)/u",$texte,$m);
		$extrait = trim($m[0]) ;

		// Virer l'entité dans cet extrait, puis dans le texte.
		if(!$m[0])
			$texte = str_replace($s, "" , $texte);
		else{
			$extrait_propre = str_replace($s,"",$extrait);
			$texte = str_replace($extrait, $extrait_propre , $texte);
		}

		if(preg_match("/$acronymes/Uu", $s, $r)){
			// En cas d'accronyme, virer aussi la forme réduite
			$texte = str_replace(trim($r[2]), "" , $texte);
			// et la forme moyenne
			$texte = str_replace(trim($r[1]), "" , $texte);
		}


		// Enregistrer l'entite
		$fragments[] = $s . "|Institutions|" . $id_article . "|" . $extrait ;

	}

	// defini par l'arbo de fichiers du répertoire listes_lexicales
	//$type_reg = array(
	//* PARTIS POLITIQUES
	//	"Villes" => "`" . VILLES . "`"
	//);


	/**/
	foreach($types_entites as $k => $v){

		//var_dump("type<hr>",$types_entites);

		// on ne refait pas le sinstitutions
		if($k == "Institutions"){
			continue;
		}
		if($v == "")
			continue;
			
		//var_dump("<br/><br/>$k<br/>$v");
		if(preg_match_all( "`" . $v . "`msu" ,$texte,$e)){
			// var_dump($e);
			foreach($e[0] as $s){
				
				// nettoyage des entités choppées avec une ,. ou autre.
				$s = trim(preg_replace("/\W+$/u", "", $s));
				$s = trim(preg_replace("/^\W+/u", "", $s));
					
				if($s == "")
					continue ;


				// Trouver l'extrait
				preg_match("/\s(?:.{0,60})".trim(preg_quote($s))."(?:.{0,60})(?:\W)/u",$texte,$m);
				$extrait = trim($m[0]) ;

				// Virer l'entité dans cet extrait, puis dans le texte.
				if(!$m[0])
					$texte = str_replace($s, "" , $texte);
				else{
					$extrait_propre = str_replace($s,"",$extrait);
					$texte = str_replace($extrait, $extrait_propre , $texte);
				}

				// En cas d'accronyme, virer aussi la forme réduite
				if(preg_match("/$acronymes/Uu", $s, $r)){
					// En cas d'accronyme, virer aussi la forme réduite
					$texte = str_replace(trim($r[2]), "" , $texte);
					// et la forme moyenne
					$texte = str_replace(trim($r[1]), "" , $texte);
				}

				// réguler les types avec plusieurs sous_chaines
				$type = preg_replace("/([^\d]+)\d+$/u", "$1", $k) ;

				if(preg_match(",\d,", $type)){
					var_dump($type);
					exit ;
				}

				// Enregistrer l'entite
				$fragments[] = $s . "|$type|" . $id_article . "|" . $m[0];
			}
		}
	}

	//var_dump($texte);
	// on essaie de virer des permier ministre du Luxembourg
	
	// Isoler les fonctions
	preg_match_all("/(" . FONCTIONS_PERSONNALITES . ")\s(?:du|de la|d'|des)\s(". LETTRES_CAPITALES . LETTRES ."+)/u", $texte, $fonctions);

	$i=0;
	foreach($fonctions[0] as $k => $s){
		if($s == "")
			continue ;
					
		// Trouver l'extrait
		preg_match("/(\s(?:.{0,60})$s(?:.{0,60})\s)/u",$texte,$m);
		// Virer l'entité dans cet extrait
		$extrait = $m[0] ;
		$extrait_propre = str_replace($s,"",$extrait);
		$texte = str_replace($extrait, $extrait_propre , $texte);

		// chercher si on a la forme longue et y raccorcher ce président.
		$fragments[] = $fonctions[0][$i] . "|Fonction|" . $id_article . "|" . $m[0];
		$i++;
	}
	
	// Dans le texte expurgé des entités connues, on passe la regexp qui trouve des noms.
	// a mettre en démo	

	$noms = trouver_noms($texte) ;

	array_walk($noms,"nettoyer_noms");

	// Trouver l'extrait
	// Virer l'entité dans cet extrait concerné (M. Kennedy)
	foreach($noms as $s){
		preg_match("/(\s(?:.{0,60})$s(?:.{0,60})\s)/u",$texte,$m);
		$extrait = $m[0] ;
		$extrait_propre = str_replace($s,"",$extrait);
		$texte = str_replace($extrait, $extrait_propre , $texte);
		$fragments[] = $s . "|Personnalités|" . $id_article . "|" . $m[0];

		// array pop du patronyme ?
	}

	// Isoler les présidents résiduels
	preg_match_all("/président\s([A-Z]". LETTRES ."+)/u", $texte, $presidents);

	$i=0;
	foreach($presidents[0] as $s){
		// Trouver l'extrait
		preg_match("/(\s(?:.{0,60})$s(?:.{0,60})\s)/u",$texte,$m);
		// Virer l'entité dans cet extrait
		$extrait = $m[0] ;
		$extrait_propre = str_replace($s,"",$extrait);
		$texte = str_replace($extrait, $extrait_propre , $texte);

		// chercher si on a la forme longue et y raccorcher ce président.

		$fragments[] = $presidents[1][$i] . "|Personnalités|" . $id_article . "|" . $m[0];
		$i++;
	}

	//var_dump("<pre>",$texte);


	// trouver des entites mono type

	// types d'entites définis dans les listes txt.
	$types_entites_mono =  generer_types_entites("mono");	

	foreach($types_entites_mono as $k => $v){

		if($v == "")
			continue;

		//var_dump("<br/><br/>$k<br/>$v");
		if(preg_match_all( "`" . $v . "`msu" ,$texte,$e)){
			// var_dump($e);
			foreach($e[0] as $s){
				
				// nettoyage des entités choppées avec une ,. ou autre.
				$s = trim(preg_replace("/\W+$/u", "", $s));
				$s = trim(preg_replace("/^\W+/u", "", $s));
					
				if($s == "")
					continue ;


				// Trouver l'extrait
				preg_match("/\s(?:.{0,60})".trim(preg_quote($s))."(?:.{0,60})(?:\W)/u",$texte,$m);
				$extrait = trim($m[0]) ;

				// Virer l'entité dans cet extrait, puis dans le texte.
				if(!$m[0])
					$texte = str_replace($s, "" , $texte);
				else{
					$extrait_propre = str_replace($s,"",$extrait);
					$texte = str_replace($extrait, $extrait_propre , $texte);
				}

				// réguler les types avec plusieurs sous_chaines
				$type = preg_replace("/(.*)\d+$/u", "$1", $k) ;

				// Enregistrer l'entite
				$fragments[] = $s . "|$type|" . $id_article . "|" . $m[0];
			}
		}
	}

	$entites_residuelles = trouver_entites_residuelles($texte);

	foreach($entites_residuelles as $s){
		// Trouver l'extrait
		preg_match("/(\s(?:.{0,60})$s(?:.{0,60})\s)/u",$texte,$m);
		// Virer l'entité dans cet extrait
		$extrait = $m[0] ;
		$extrait_propre = str_replace($s,"",$extrait);

		$texte = str_replace($extrait, $extrait_propre , $texte);
		$fragments[] = $s . "|INDETERMINE|" . $id_article . "|" . $m[0];
	}

	// fusionner les personnalités.

	foreach($fragments as $v){
		if(preg_match("/(.*)\|Personnalités\|/u",$v,$m))
			$personnalites[] = $m[1] ;
	}

	//var_dump("<pre>",$personnalites);

	if(is_array($personnalites))
		foreach($personnalites as $v){
			$a = explode(" ",$v) ;
			if(is_array($a)){
				$patronyme = array_pop($a);
				if(!$patronymes[$patronyme])
					$patronymes[$patronyme] = $v ;
			}
		}

	//var_dump("<pre>",$patronymes);

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


function trouver_noms($texte){

	// M. Joseph (« Joe ») Lhota. http://localhost:8888/diplo/?page=noms&id_article=50640

	// http://stackoverflow.com/questions/7653942/find-names-with-regular-expression

	// virer les débuts de phrases
	$reg =  "%(?:\W)". // un espace ou saut de ligne ou guillement ou apostrophe non capturé
			"(?!(?i)(?:". MOTS_DEBUT .")\s+)". // pas de mot de debut de phrase avec un capitale lambda ou en CAPS
			"(".
				"(?:(?<!\.)[A-Z](?!')(?:". LETTRES ."+|\.))". // Un mot avec une capitale non précédée d'un . (C.I.A. Le ...), suivie de lettres ou - ou d'un .
				"(?:\s+[A-Z](?:". LETTRES ."+|\.))*". // Des éventuels mots avec une capitale suivie de lettres ou - ou d'un . 
				"(?:\s+(?!(?:". MOTS_MILIEU ."))". LETTRES ."+){0,2}". // Un ou deux éventuels mots (van der), mais pas des mots courants
				"(?:(?:\s+|'|’)(?!". MOTS_FIN .")[A-Z]". LETTRES ."+)". // Un mot avec une capitale suivie de lettres ou - , mais pas des mots de fins
			"|". ENTITES_PERSO .")". // Personnalités à pseudo
	"%mu"	;

	preg_match_all($reg,$texte,$m);
	$noms = $m[1] ;
	//var_dump("<pre>",$m);

	array_walk($noms,"nettoyer_noms");

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

function agreger_fragments($fragments = array()){ // noms pondérés

	foreach($fragments as $f){
		
		$fragment = explode("|",$f);
	//var_dump("<pre>",$fragment);
		
		if(!$fragments_ponderes[$fragment[1]][$fragment[0]])
			$fragments_ponderes[$fragment[1]][$fragment[0]] = 1 ;
		else
			$fragments_ponderes[$fragment[1]][$fragment[0]] ++ ;	
	}

	//var_dump("<pre>",$fragments_ponderes);

	return $fragments_ponderes ;
}


function grouper_entites($entites = array()){ // noms pondérés

	foreach ($entites as $entite){
		$e = explode("|", $entite);
		// Réguler les types avec plusieurs sous chaines
		$type = preg_replace("/(.*)\d+$/u", "$1", $e[1]) ;
		if(!$entites_ponderes[$type][$e[0]])
			$entites_ponderes[$type][$e[0]] = 1 ;
		else
			$entites_ponderes[$type][$e[0]] ++ ;	
	}

	//var_dump("<pre>",$entites_ponderes,"</pre>");

	return $entites_ponderes ;

}


function afficher_noms($noms = array()){ // noms pondérés

	sort($noms);

	foreach ($noms as $nom){
		if(!$noms_ponderes[$nom])
			$noms_ponderes[$nom] = 1 ;
		else
			$noms_ponderes[$nom] ++ ;	
	}

	return $noms_ponderes ;

}

function trouver_entites_residuelles($texte){

	// mot avec une majuscule.	
	preg_match_all("/((?!(?i)(?:". MOTS_DEBUT .")\s+)[A-Z](?:". LETTRES ."+))\s+/Uu",$texte,$m);

	//var_dump("<pre>",$m);

	$entites_residuelles = array_unique($m[1]);		

	if(is_array($entites_residuelles))
		return $entites_residuelles ;
	else{ // ne pas merger avec un tableau NULL
		return array(0 => 'fail');
	}	
	
}

function trouver_extraits_entites_residuelles($texte, $noms=array()){
	
	// On cherche des mots avec une capitale pas courants qui restent et leur contexte.
	preg_match_all("/((?:.{50})(?!(?i)(?:". MOTS_DEBUT .")\s+)[A-Z](?:". LETTRES ."+)\s+(?:.{50}))/u",$texte,$m);

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
			var_dump($entite);
			exit ;
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
