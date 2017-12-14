<?php

// Charger les fonction de décourverte d'entites nommees
include_spip("inc/entites_nommees") ;





// File system d'entités nommées

/*
	Tableaux de regexp par types d'entites à partir de fichiers dictionnaires au format texte.
	
	Les fichiers textes sont normés : repertoire_type/liste_entites.txt
	
	On parcourt les répertoires pour lister les types, et les fichiers pour lister les entites en regexp.
	
	On procède en deux passes, la premiere pour les termes qui ont plusieurs mots (mode multi)
	ensuite une seconde passe pour les entités d'un seul mot (mode mono) qui restent dans le texte après extraction des multi.
	
	On renvoie un tableau [type] => Regexp d'entites.
	
	TODO : 
		- renommer la fonction de maniere plus explicite.
		- ajouter memoization enfonction de la date de maj du fichier le plus récent pour ne pas relire les fichiers en permanance lors du calcul par lot des entites.
*/

function generer_types_entites($nb_mots="multi"){
	include_spip('iterateur/data');
	include_spip("inc/entites_nommees");
	
	// mettre en cache à la date de modif du dernier fichier modif.
	$fichiers = inc_ls_to_array_dist(_DIR_RACINE . 'plugins/entites_nommees/listes_lexicales/*/*') ;
	foreach($fichiers as $k => $v)
		$mtime[$v['basename']] = $v['mtime'] ;
	
	$date_modif_fichiers = max($mtime);
	
	$key = "$nb_mots, " . $date_modif_fichiers ;
	if (function_exists('cache_get')
		and $c = cache_get($key))
			return $c ;
	
	// lister les répertoires / types d'entites : Pays Villes ...
	$types_entites_repertoires = inc_ls_to_array_dist(_DIR_RACINE . 'plugins/entites_nommees/listes_lexicales/*') ;
	
	foreach($types_entites_repertoires as $type){
		
		$entites_multi = array();
		$entites_mono = array();
		$ajout_entites = array();
		$entites_regexp = "" ;
		
		// pour chaque repertoire
		$t_entite = $type['file'] ;
		// var_dump($t_entite);
		
		// on liste les fichiers du repertoire
		$sous_categories = inc_ls_to_array_dist(_DIR_RACINE . "plugins/entites_nommees/listes_lexicales/$t_entite/*.txt");
		
		// creer un type d'entite si le répertoire contient des recettes au format txt.
		if( sizeof($sous_categories) >= 1){
			//var_dump(strtoupper($t_entite));
			
			// pour chaque fichier
			foreach($sous_categories as $sous_categorie){
				$sous_categorie_type = $sous_categorie['file'] ;
				
				// var_dump("<hr />-- $t_entite /" . $sous_categorie_type);
				//exit ;
				$fichier_liste = _DIR_RACINE . "plugins/entites_nommees/listes_lexicales/$t_entite/$sous_categorie_type" ;
				$sous_cat_entites = generer_mots_fichier($fichier_liste) ;
				
				foreach($sous_cat_entites as $k => $ligne){
					$ligne = trim($ligne) ;
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
					
					// sécuriser la regexp en neutralisant les caractère réservés, mais pas les sous-masques (?:) ou (?=)?
					$entite_unique = preg_quote($entite_unique);
					if(preg_match("/\\\\\(\\\\\?\\\\\:[^\\\]+\\\\\)(?:\\\\\?)?/", $entite_unique, $sous_masque)){
						$sous_masque_propre = str_replace('\\' , '' , $sous_masque);
						$entite_unique = str_replace($sous_masque, $sous_masque_propre, $entite_unique);
						// var_dump($entite_unique, $sous_masque, $sous_masque_propre, "<br>");
					}
					
					// gérer les accents
					$entite_unique = preg_replace("/E|É/u", "(?:É|E)", $entite_unique);
					
					// forme développée ou pas
					//$entite_unique = preg_replace("/\(\)/", "", $entite_unique);
					
					$entites_regexp .=  "\P{L}". $entite_unique . "\P{L}|" ; // ne doit pas etre trop long car les regexp ont une limite à 1000000.	
				
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
					$pos = strrpos(substr($chaine, 0, 10000), "\P{L}|") ;
					//echo "dernier | du paquet $i à la pos : $pos" ;
					$s_chaine = substr($chaine,0,$pos) ;
					$types_entites[$t_entite.$i] = $s_chaine . "\P{L}" ;
					//echo $type_entite.$i ." = " . $types_entites[$type_entite.$i] ;
					$chaine = str_replace($s_chaine . "\P{L}|" ,"", $chaine);
					$i ++ ;
				}
			}else{
				$types_entites[$t_entite] = $entites_regexp ;
			}
		}
	}
	
	// memoisation
	if (function_exists('cache_set')) cache_set($key, $types_entites);
	
	return $types_entites ;
	
}

// tabelau des mots dans un fichier dictionnaire

function generer_mots_fichier($fichier_mots){
	include_spip('iterateur/data');
	$liste = file_get_contents($fichier_mots);
	$mots = inc_file_to_array_dist(trim($liste)) ;
	
	foreach($mots as $k => $mot){
		$mot = trim($mot) ;
		
		//pas de ligne vides ou de // commentaires 
		if( preg_match(",^\/\/|^$,",$mot) || $mot == "")
			unset($mots[$k]) ;
	}
	
	//var_dump("<pre>", $mots, "</pre>");
	//die();
	return $mots ;
}

// fonctions de nettoyage

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


// fonctions d'affichage

// <BOUCLE_entites(DATA){source principales_entites}>
// Lister les principales entites aparraissant plus de 5 fois
// D'après un fichier csv pour des bonnes perfs.
// Entite	type	poids

function inc_principales_entites_to_array_dist(){
	$c = trim(file_get_contents(find_in_path("stats/decompte_references.txt")));
	$csv = inc_file_to_array_dist($c);
	
	// isoler les champs d'entete
	$entete = array_shift($csv);
	$entete = explode("	",strtolower($entete));
	
	// creer un tableau
	foreach($csv as $l){
		$valeurs = explode("	", $l);
		$r = array();
		for($i=0 ; $i < sizeof($entete) ; $i++)
			$r[$entete[$i]] = $valeurs[$i] ;
		$e[] = $r ;
	}
	// var_dump("<pre>", $e);
	return $e ;
}

// <BOUCLE_types(DATA){source types_entites}>

// Lister les types des entites principales

function inc_types_entites_to_array_dist(){
	$entites = inc_principales_entites_to_array_dist();
	foreach($entites as $e)
		$r[$e['type']] += $e['poids'] ;
	
	foreach($r as $type => $poids)
		$t[] = array("type" => $type, "poids" => $poids);
	
	return $t ;
}


function nuage_mot($poids, $max){
	$score = $poids/$max; # entre 0 et 1
	
	$p = ($unite=floor($score += 0.900001)) . floor(10*($score - $unite)); // technique de rastapopoulos de 0 Ã  10
	$p -= 9;
	
	$class = ($p >= 8) ? '2'
				: (($p > 4) ? '1.7'
				: (($p >= 2) ? '1.4' : '1'));
	
	return $class ;
}

function entites_to_array($entites_trouvees){
	
	foreach($entites_trouvees as $entite){
		$e = explode("|", $entite) ;
		$entites_nommees[] = array (
					"entite" => $e[0],
					"type_entite" => $e[1],
					"id_article" => $e[2],
					"extrait" => $e[3]
			);
	}
	return $entites_nommees ;
}

function peupler_timeline($timeline, $texte, $lien=""){
	$texte = preg_replace("/\R/", "\n", $texte);
	
	
	// Nettoyage
	//<br />
	$texte = preg_replace("/<br\s*\/*>/","",$texte);
	
	$texte = preg_replace("/(\{+)\s?\R+/is","\\1",$texte);
	$texte = preg_replace("/\s?\R+(\}+)/is"," \\1",$texte);
	
	//<div align="center">{{2006}}</div>
	$texte = preg_replace("/<div align=\"center\">\{+(\d+)\}+<\/div>/","{{{\\1}}}",$texte);
	
	// var_dump("<pre>", entites_html($texte));
	
	// var_dump($lien);
	// format 1
	// {{{2004}}}
	//	{{2 février}} : Ariel Sharon annonce...
	//	{{14 avril}} : lol

	if(preg_match("/\{\{\{\d{4}\}\}\}/", $texte, $m)){
		//var_dump("<pre>", $m);
		$a = preg_split("~\{\{\{~Uims", $texte);
		if($a[0] == "")
			unset($a[0]);
		//var_dump($a);
		foreach($a as $l){
			//var_dump("<pre>",$l);
			if(preg_match("/^(\d{4})\}/", $l, $annee)){
				$annee =$annee[1] ;
				$t = preg_replace("/". $annee ."\s*\.*\}\}\}/","", $l);
				//var_dump("<pre>",$timeline[$annee], $annee, $t);
				$s = trim(propre(typo($t)) . " " . $lien) ;
				if($s != "")
					$timeline[$annee] .= $s . "\n\n";
				
				//var_dump($timeline[$annee], $annee, $t);
				//die();
				$texte = trim(str_replace($l,"" , $texte));
			}
		}
		//var_dump("<pre>", $anne, $timeline[$annee], "</pre>");
	}
	
	$events = explode("\n", trim($texte));
	
	foreach($events as $e){
		if(preg_match("/(?:(?<!\>)(\d{4})(\.|\s|\})+/", $e, $m)){ // non précédé de > (lien)
			$timeline[$m[1]] .= propre(typo($e) . " " . $lien) . "\n\n";
		}
	}
	
	ksort($timeline);
	
	//var_dump("<pre>", $timeline ,"</pre>");
	
	return $timeline ;
}

// ??
function entites_nommees($noms = array()){
	
	if(!is_array($noms))
		return ;
	
	$lieux = ENTITES_LIEUX_HEURISTIQUE ;
	$institutions = ENTITES_INSTITUTIONS_HEURISTIQUE ;
	
	foreach($noms as $k => $v){
		if(preg_match("/$lieux/Uu",$v))
			$entites_nommees['lieux'][$v] = $v ;
		elseif(preg_match("/$institutions/Uu",$v)){
			$entites_nommees['institutions'][$v] = $v ;
		}
		else
			$entites_nommees['personnalites'][$v] = $v ;
		
	}
	
	return $entites_nommees ;
}

