<?php

include('mots_courants.php');

// http://fr.wikipedia.org/wiki/Table_des_caract%C3%A8res_Unicode/U0080
define("LETTRES","[a-zA-ZàáâãäåæçèéêëìíîïðñòóôõöøùúûüýþÿÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßœŒ-]");

// Générer des catégories d'entités à partir de l'arborescence de fichiers du répertoire listes_lexicales.
include_spip('iterateur/data');
$types_entites_repertoires = inc_ls_to_array_dist('plugins/entites_nommees/listes_lexicales/*') ;

/**/

// define des entités connues à partir des listes texte.
foreach($types_entites_repertoires as $type){
	$type_entite = $type['file'] ;
	$sous_categories = inc_ls_to_array_dist("plugins/entites_nommees/listes_lexicales/$type_entite/*.txt");
	/**/// creer un type d'entite si le répertoire contient des recettes au format txt.
	if( sizeof($sous_categories) >= 1){
		// var_dump(strtoupper($type_entite));
		$entites_regexp = "" ;
		foreach($sous_categories as $sous_categorie){
			$sous_categorie_entites = $sous_categorie['file'] ;
			//var_dump("-- " . $sous_categorie_entites);
			$sous_cat_ent = file_get_contents("plugins/entites_nommees/listes_lexicales/$type_entite/$sous_categorie_entites");
			$sous_cat_lol = inc_file_to_array_dist(trim($sous_cat_ent)) ;
			
			foreach($sous_cat_lol as $k => $ligne){
				//pas de ligne vides ou de // commentaires 
				if( preg_match(",^\/\/|^$,",$ligne) )
					unset($sous_cat_lol[$k]);
			}

			if( sizeof($sous_cat_lol) >= 1)
				foreach($sous_cat_lol as $entite_unique){
					$entites_regexp .=  preg_quote($entite_unique) . "|" ;
				}
		}
		$entites_regexp = preg_replace("/\|$|\//","",$entites_regexp);
		define(strtoupper($type_entite),$entites_regexp);
	}
}

// var_dump("<pre>",PAYS,"</pre>");

// Isoler les entites connues (Institutions, Traités etc).
function trouver_entites($texte,$id_article=""){

	$fragments = array();
	$texte_original = $texte ;

	// Traiter les notes de bas de pages.
	//* SOURCES
	if(preg_match_all("/\[\[(.*)\]\]/Ums", $texte, $notes)){
		foreach($notes[1] as $note){
			if(preg_match_all("/\{((?!Cf|Ibid)[^,]+),?\}/ims",$note,$e)){
				foreach($e[1] as $s){
					// Trouver l'extrait
					preg_match("/(\s(?:.{0,60})$s(?:.{0,60})\s)/",$texte,$m);
					// Virer dans cet extrait
					$extrait = $m[0] ;
					$extrait_propre = str_replace($s,"",$extrait);
					$texte = str_replace($extrait, $extrait_propre , $texte);
					$fragments[] = $s . "|Sources|" . $id_article . "|" . $m[0];			
				}		
			}
		}
	}

	// Isoler les entites connues (Institutions, Traités etc).
	$acronymes = "((?<!\.\s)[A-Z](?:". LETTRES ."|\s)+)\(([A-Z]+)\)";

	if(preg_match_all( "/" . INSTITUTIONS . "/ms" ,$texte,$e)){
			//var_dump($e);	
			foreach($e[0] as $s){
				// Trouver l'extrait
				preg_match("/\s(?:.{0,60})".trim($s)."(?:.{0,60})(?:\s|,|\.)/",$texte,$m);
				$extrait = trim($m[0]) ;

				// Virer l'entité dans cet extrait, puis dans le texte.
				if(!$m[0])
					$texte = str_replace($s, "" , $texte);
				else{
					$extrait_propre = str_replace($s,"",$extrait);
					$texte = str_replace($extrait, $extrait_propre , $texte);
				}

				// En cas d'accronyme, virer aussi la forme réduite
				if(preg_match("/$acronymes/U", $s, $r)){
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
	preg_match_all("/$acronymes/U", $texte, $entites_inconnues);

	//var_dump("<pre>");
	//var_dump($entites_inconnues);
	//var_dump("</pre>");

	foreach($entites_inconnues[0] as $s){
		
		// Trouver l'extrait
		preg_match("/\s(?:.{0,60})$s(?:.{0,60})(?:\s|,|\.)/",$texte,$m);

		$extrait = trim($m[0]) ;

		// Virer l'entité dans cet extrait, puis dans le texte.
		if(!$m[0])
			$texte = str_replace($s, "" , $texte);
		else{
			$extrait_propre = str_replace($s,"",$extrait);
			$texte = str_replace($extrait, $extrait_propre , $texte);
		}

		if(preg_match("/$acronymes/U", $s, $r)){
			// En cas d'accronyme, virer aussi la forme réduite
			$texte = str_replace(trim($r[2]), "" , $texte);
			// et la forme moyenne
			$texte = str_replace(trim($r[1]), "" , $texte);
		}

		// Enregistrer l'entite
		$fragments[] = $s . "|Institutions|" . $id_article . "|" . $extrait ;

	}

	// defini par l'arbo de fichiers du répertoire listes_lexicales
	$type_reg = array(
	//* PARTIS POLITIQUES
		"Partis politiques" => "/" . PARTIS_POLITIQUES . "/",
	//* PEUPLES
		"Peuples" => "/" . PEUPLES . "/",
	//* LIEUX
	// Pays
		"Pays" => "/" . PAYS . "/",
	// Villes
		"Villes" => "/" . VILLES . "/",
	// Villes
		"Geographie" => "/" . GEOGRAPHIE . "/",
	// Medias
		"Journaux" => "/" . JOURNAUX . "/"
	);

	// defini par l'arbo de fichiers du répertoire listes_lexicales
	//$type_reg = array(
	//* PARTIS POLITIQUES
	//	"Villes" => "/" . VILLES . "/"
	//);

	foreach($type_reg as $k => $v){
		//var_dump("<br/><br/>$k<br/>",$v . "ms");
		if(preg_match_all( $v . "ms" ,$texte,$e)){
			//var_dump($e);
			foreach($e[0] as $s){
				// Trouver l'extrait
				preg_match("/\s(?:.{0,60})".trim($s)."(?:.{0,60})(?:\s|,|\.)/",$texte,$m);
				$extrait = trim($m[0]) ;

				// Virer l'entité dans cet extrait, puis dans le texte.
				if(!$m[0])
					$texte = str_replace($s, "" , $texte);
				else{
					$extrait_propre = str_replace($s,"",$extrait);
					$texte = str_replace($extrait, $extrait_propre , $texte);
				}

				// En cas d'accronyme, virer aussi la forme réduite
				if(preg_match("/$acronymes/U", $s, $r)){
					// En cas d'accronyme, virer aussi la forme réduite
					$texte = str_replace(trim($r[2]), "" , $texte);
					// et la forme moyenne
					$texte = str_replace(trim($r[1]), "" , $texte);
				}

				// Enregistrer l'entite
				$fragments[] = $s . "|$k|" . $id_article . "|" . $m[0];
			}
		}
	}

	//var_dump($texte);

	// Dans le texte expurgé des entités connues, on passe la regexp qui trouve des noms.
	// a mettre en démo	

	$noms = trouver_noms($texte) ;

	array_walk($noms,"nettoyer_noms");

	// Trouver l'extrait
	// Virer l'entité dans cet extrait concerné (M. Kennedy)
	foreach($noms as $s){
		preg_match("/(\s(?:.{0,60})$s(?:.{0,60})\s)/",$texte,$m);
		$extrait = $m[0] ;
		$extrait_propre = str_replace($s,"",$extrait);
		$texte = str_replace($extrait, $extrait_propre , $texte);
		$fragments[] = $s . "|Personnalités|" . $id_article . "|" . $m[0];

		// array pop du patronyme ?
	}

	// Isoler les présidents résiduels
	preg_match_all("/président\s([A-Z]". LETTRES ."+)/", $texte, $presidents);

	$i=0;
	foreach($presidents[0] as $s){
		// Trouver l'extrait
		preg_match("/(\s(?:.{0,60})$s(?:.{0,60})\s)/",$texte,$m);
		// Virer l'entité dans cet extrait
		$extrait = $m[0] ;
		$extrait_propre = str_replace($s,"",$extrait);
		$texte = str_replace($extrait, $extrait_propre , $texte);

		// chercher si on a la forme longue et y raccorcher ce président.

		$fragments[] = $presidents[1][$i] . "|Personnalités|" . $id_article . "|" . $m[0];
		$i++;
	}

	//var_dump("<pre>",$texte);

	$entites_residuelles = trouver_entites_residuelles($texte);

	foreach($entites_residuelles as $s){
		// Trouver l'extrait
		preg_match("/(\s(?:.{0,60})$s(?:.{0,60})\s)/",$texte,$m);
		// Virer l'entité dans cet extrait
		$extrait = $m[0] ;
		$extrait_propre = str_replace($s,"",$extrait);

		$texte = str_replace($extrait, $extrait_propre , $texte);
		$fragments[] = $s . "|INDETERMINE|" . $id_article . "|" . $m[0];
	}

	// fusionner les personnalités.

	foreach($fragments as $v){
		if(preg_match("/(.*)\|Personnalités\|/",$v,$m))
			$personnalites[] = $m[1] ;
	}

	foreach($personnalites as $v){
		$patronyme = array_pop(explode(" ",$v));
		if(!$patronymes[$patronyme])
			$patronymes[$patronyme] = $v ;
	}

	//var_dump($patronymes);

	foreach($fragments as $v){
		if(preg_match("/^(.*)\|(Personnalités|INDETERMINE)\|/",$v,$m)){
			if($patronymes[$m[1]]){
				$f = preg_replace("/^".$m[1]."/",$patronymes[$m[1]],$v) ;
				$f = preg_replace("/\|".$m[2]."\|/","|Personnalités|",$f) ;
				$fragments_fusionnes[] = $f ;
			}
			else
				$fragments_fusionnes[] = $v ;
		}
		else
				$fragments_fusionnes[] = $v ;
	}
	
	if(!is_array($fragments_fusionnes))
		$fragments_fusionnes = array(0 => "PASDENTITE|PASDENTITE|$id_article|");

	// requalifier les Personnalités qui n'en sont pas par heuristique.

	$lieux = ENTITES_LIEUX_HEURISTIQUE ;
	$institutions = ENTITES_INSTITUTIONS_HEURISTIQUE ;

	foreach($fragments_fusionnes as $v){
		if(preg_match("/^(.*)\|(Personnalités)\|/",$v,$m)){
			if(preg_match("/$lieux/U",$m[1])){
				$f = preg_replace("/\|".$m[2]."\|/","|Lieux automatiques|",$v) ;
				$fragments_traites[] = $f ;
			}elseif(preg_match("/$institutions/U",$m[1])){
				$f = preg_replace("/\|".$m[2]."\|/","|Institutions automatiques|",$v) ;
				$fragments_traites[] = $f ;
			}else{
				$fragments_traites[] = $v ;
			}			
		}else{
			$fragments_traites[] = $v ;
		}
	}

	//var_dump("<pre>",$patronymes,$fragments_fusionnes);
	enregistrer_entites($fragments_traites, $id_article);

	return $fragments_traites ;
}


function trouver_noms($texte){

	// M. Joseph (« Joe ») Lhota. http://localhost:8888/diplo/?page=noms&id_article=50640

	// http://stackoverflow.com/questions/7653942/find-names-with-regular-expression

	// virer les débuts de phrases
	$reg =  "%(?:\s|\n|\"|\')". // un espace ou saut de ligne ou guillement ou apostrophe non capturé
			"(?!(?i)(?:". MOTS_DEBUT .")\s+)". // pas de mot de debut de phrase avec un capitale lambda ou en CAPS
			"(".
				"(?:(?<!\.)[A-Z](?!')(?:". LETTRES ."+|\.))". // Un mot avec une capitale non précédée d'un . (C.I.A. Le ...), suivie de lettres ou - ou d'un .
				"(?:\s+[A-Z](?:". LETTRES ."+|\.))*". // Des éventuels mots avec une capitale suivie de lettres ou - ou d'un . 
				"(?:\s+(?!(?:". MOTS_MILIEU ."))". LETTRES ."+){0,2}". // Un ou deux éventuels mots (van der), mais pas des mots courants
				"(?:(?:\s+|'|’)(?!". MOTS_FIN .")[A-Z]". LETTRES ."+)". // Un mot avec une capitale suivie de lettres ou - , mais pas des mots de fins
			"|". ENTITES_PERSO .")". // Personnalités à pseudo
	"%m"	;

	preg_match_all($reg,$texte,$m);
	$noms = $m[1] ;
	//var_dump("<pre>",$m);

	array_walk($noms,"nettoyer_noms");

	return $noms ;
}

// renvoyer en fait patronyme => forme longue
function nettoyer_noms(&$item1, $key){
	// (^M\.|^Mme|^Mgr|^Dr|^Me) \s+
	$item1 = preg_replace("/(^M\.|^Mme|^Mgr|^Dr|^Me)\s+/U","",$item1); // pas les civilités
	$item1 = preg_replace("/-$/"," ",$item1);
	
	// http://archives.mondediplo.com/ecrire/?exec=articles&id_article=8337
	$item1 = preg_replace("/\R/"," ",$item1);
	
	$item1 = trim($item1);

}

function entites_nommees($noms = array()){

	$lieux = ENTITES_LIEUX_EURISTIQUE ;
	$institutions = ENTITES_INSTITUTIONS_EURISTIQUE ;

	foreach($noms as $k => $v){
		if(preg_match("/$lieux/U",$k))
			$entites_nommees['lieux'][$k] = $v ;
		elseif(preg_match("/$institutions/U",$k))
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

	foreach ($fragments as $f){
		
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

		if(!$entites_ponderes[$e[1]][$e[0]])
			$entites_ponderes[$e[1]][$e[0]] = 1 ;
		else
			$entites_ponderes[$e[1]][$e[0]] ++ ;	
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


	preg_match_all("/((?!(?i)(?:". MOTS_DEBUT .")\s+)[A-Z](?:". LETTRES ."+))\s+/U",$texte,$m);

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
	preg_match_all("/((?:.{50})(?!(?i)(?:". MOTS_DEBUT .")\s+)[A-Z](?:". LETTRES ."+)\s+(?:.{50}))/",$texte,$m);

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


