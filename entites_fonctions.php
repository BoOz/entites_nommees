<?php

function trouver_entites($texte,$id_article){
	
	/*
		Trouver d'abord des entites connues (Pays, Institutions, Traités etc) à partir de listes txt.
		Trouver ensuite dans le texte les personnalités restantes.
		
		TODO
			- passer un unique sur les listes texte (PKK) ?
		
	*/
	
	// Charger les entites nommees
	include_spip("inc/entites_nommees");
	
	// Charger les regexp par types d'entites définis dans des fichiers dictionnaires au format txt.
	// - entités nommées qui font plusieurs mots.
	$types_entites =  generer_types_entites("multi");
	// - entites d'un mot.
	$types_entites_mono = generer_types_entites("mono");
	
	//var_dump("<pre>",$types_entites,"</pre>");
	//var_dump("<pre>",$types_entites_mono,"</pre>");
	
	// Enregistrer les entites dans un tableau
	$fragments = array();
	// Garder le texte original pour chercher des extraits.
	$texte_original = $texte ;
	
	// Traiter d'abord les notes de bas de pages.
	// SOURCES
	
	// $types d'entites lieux ou média à repérer dans les notes.
	foreach($types_entites as $k => $v)
		if(preg_match("/(^villes.*|^pays.*|^journaux.*)/i", $k, $r))
			$types_connus[$r[1]] = $v ;
	
	// $types d'entites lieux ou média à repérer dans les notes.
	foreach($types_entites_mono as $k => $v)
		if(preg_match("/(^villes.*|^pays.*|^journaux.*)/i", $k, $r))
			$types_connus_mono[$r[1]] = $v ;
	
	$notes = entites_nommees_notes_bas_page($texte, $id_article, $types_connus, $types_connus_mono);
	if(is_array($notes)){
		$fragments = $notes['fragments'];
		$texte = $notes['texte'];
	}
	//var_dump("<pre>",$fragments,$texte,"</pre><hr><hr>");
	
	// Chercher d'abord les entités connues sauf les organisations (à cause de la RDC)
	foreach($types_entites as $type => $regex){
		
		// on ne fait pas les organisations tout de suite.
		if(preg_match("/^(institution.*|parti.*)/i", $type)){
			continue;
		}
		if($regex == "")
			continue;
		
		$recolte = recolter_fragments($type, $regex, $texte, $fragments, $id_article, $texte_original);
		$fragments = $recolte['fragments'];
		$texte = $recolte['texte'];
	}

	// var_dump($fragments, "lol");

	// Gérer ensuite les institutions et partis politiques en mode développé + accronyme connus pour trouver ensuite les autres.
	foreach($types_entites as $k => $v)
		if(preg_match("/^(institution.*|parti.*)/i", $k, $r))
			$orgas[$r[1]] = $v ;
	
	//var_dump("<pre>", $orgas);
	
	foreach($orgas as $type => $reg){
		// On cherche la forme developpée + acrronyme : Confédération générale du travail (CGT)
		$label = preg_replace("/\d$/", "", $type);
		
		//var_dump($reg);
		
		$recolte = recolter_fragments($label, $reg, $texte, $fragments, $id_article, $texte_original);
		$fragments = $recolte['fragments'];
		$texte = $recolte['texte'];
		
		/*
		// Ensuite la même chose sans l'accronyme : Confédération générale du travail.
		$types_reduits = preg_replace("/\s.\([^\)]+\)/u","",$reg);
		
		//var_dump($types_reduits);
		
		$recolte = recolter_fragments($label, $types_reduits, $texte, $fragments, $id_article, $texte_original);
		$fragments = $recolte['fragments'];
		$texte = $recolte['texte'];
		
		//var_dump($reg);
		
		*/
		
		// ensuite que l'acconyme s'il fait plus qu'une lettre...
		preg_match_all("/\\\\\([^)]{2,}\\\\\)/Uu", str_replace("(?:É|E)", "E", $reg), $accros);
		
		$accros[0] = str_replace("\(", "\P{L}", $accros[0]);
		$accros[0] = str_replace("\)", "\P{L}", $accros[0]);
		
		$accros = join("|", array_unique($accros[0]));
		
		//var_dump("<pre>",$reg,$accros,"</pre>");
		
		$recolte = recolter_fragments($label, $accros, $texte, $fragments, $id_article, $texte_original);
		$fragments = $recolte['fragments'];
		$texte = $recolte['texte'];
		
		/**/
	}
	
	// a debug
	//var_dump($fragments);
	
	/* recaler les accro et les developpés */
	$institutions = array() ;
	$acronymes = "((?<!\.\s)" . LETTRE_CAPITALE . "(?:". LETTRES ."|\s|')+)\((" . LETTRE_CAPITALE . "+)\)";
	
	// Repérer les organisations
	// si une forme longue est trouvée, oncherche
	// var_dump($fragment);
	if(is_array($fragments))
		foreach($fragments as $v){
			if(preg_match("`$acronymes`u",$v,$m))
				$institutions[$m[2]] = $m[1] ;
		}
	
	//var_dump("<hr>",$fragments,"<hr>",$institutions,"<hr>");
	
	if(is_array($fragments))
		foreach($fragments as $v){
			if(preg_match("/^(.*)\|(Institutions|Partis politiques)\|/u",$v,$m)){
				// cas des Parti communiste (PC)
				// recaler des institutions réduites PC
				if($institutions[$m[1]]){
					$f = preg_replace("/^".$m[1]."/u", trim($institutions[$m[1]]) . " (" . $m[1] . ")", $v) ;
					$fragments_fusionnes[] = $f ;
				}elseif(in_array($m[1], $institutions)){ // recaler des institutions réduites moyenne Parti communiste
					$f = preg_replace("/^".$m[1]."/u", $institutions[$m[1]] . " (" . $m[1] . ")", $v) ;
					$fragments_fusionnes[] = $f ;
				}else
					$fragments_fusionnes[] = $v ;
			}else
				$fragments_fusionnes[] = $v ;
		}
	if(is_array($fragments_fusionnes))
		$fragments = array_unique($fragments_fusionnes) ;

	//var_dump("<pre>", $fragments,"<hr><hr>", $texte);

	$fragments_fusionnes = array();
	
	// itals spip
	$texte = str_replace("{", "", $texte);
	$texte = str_replace("}", "", $texte);

	// itals spip
	$texte_original = str_replace("{", "", $texte);
	$texte_original = str_replace("}", "", $texte);
	
	//var_dump($fragments,"<hr><hr>");
	
	// Isoler les entites inconnues de la forme : Conseil national pour la défense de la démocratie (CNDD)
	$recolte = recolter_fragments("Institutions automatiques", $acronymes, $texte, $fragments, $id_article, $texte_original);
	$fragments = $recolte['fragments'];
	$texte = $recolte['texte'];


	// on enlève des FONCTIONS de QUELQUEPART : premier ministre du Luxembourg etc
	$recolte = recolter_fragments("Fonctions", "(" . FONCTIONS_PERSONNALITES . ")\s(?:du|de la|d'|des)\s(". LETTRE_CAPITALE . LETTRES ."+)", $texte, $fragments, $id_article, $texte_original);
	$fragments = $recolte['fragments'];
	$texte = $recolte['texte'];
	
	// Dans le texte expurgé des entités connues, on passe la regexp qui trouve des noms.
	// a mettre en démo	

	//var_dump($fragments,"hop");

	$noms = trouver_noms($texte) ;

	$recolte = traiter_fragments($noms, "Personnalités", $texte, $fragments, $id_article, $texte_original) ;
	$fragments = $recolte['fragments'];
	$texte = $recolte['texte'];

	
	//var_dump($texte);

/*
	// Isoler les présidents résiduels // non car ca rechoppe les memes et ca va aller de toute facons dans les indeterminés
	$recolte  = recolter_fragments("Personnalités", "(?:Dr|présidente?)\s(" . LETTRE_CAPITALE . LETTRES ."+)" , $texte, $fragments, $id_article, $texte_original) ;
	$fragments = $recolte['fragments'];
	$texte = $recolte['texte'];
	
	var_dump($fragments);
*/	
	//var_dump("zou", $fragments);
	
	// trouver des entites constituées d'un seul mot
	
	
	// var_dump("<pre>",$types_entites_mono,"</pre>");
	
	/* regex automatisées depuis les fichiers de listes */
	foreach($types_entites_mono as $type => $regex){
		if($regex == "")
			continue;
	
		// var_dump($texte);
		// var_dump($texte_original); // hum pas si original... il y a des xxx
		
		$recolte = recolter_fragments($type, $regex, $texte, $fragments, $id_article, $texte_original);
		$fragments = $recolte['fragments'];
		$texte = $recolte['texte'];

	}
	
	// var_dump("<pre>",$fragments,"</pre>","<hr>",$texte);
	
	// on cherche des termes qui ont des majuscules dans le texte restant.
	$entites_residuelles = trouver_entites_residuelles($texte);
	
	//var_dump($texte);
	//var_dump($entites_residuelles);
	
	$recolte = traiter_fragments($entites_residuelles, "INDETERMINE", $texte, $fragments, $id_article, $texte) ;
	$fragments = $recolte['fragments'];
	$texte = $recolte['texte'];
	
	//var_dump($fragments,"fragments");
	
	// var_dump("<pre>",$entites_residuelles,"</pre><hr>zou<pre>",$fragments,"<hr>",$texte,"<hr>");
	$institutions = array() ;
	// fusionner les personnalités Barack Obama + Mr Obama => Barack Obama
	// En cas d'accronyme Parti communiste (PC), virer aussi la forme réduite		
	
	if(is_array($fragments))
		foreach($fragments as $v){
			if(preg_match("/(.*)\|Personnalités\|/u",$v,$m))
				$personnalites[] = $m[1] ;
			elseif(preg_match("`$acronymes`u",$v,$m)){
				$institutions[$m[2]]['valeur'] = trim($m[1]) ;
				preg_match(",^[^|]+\|([^|]+)\|,", $v, $type_orga);
				//var_dump($v, $type_orga, "lol");
				$institutions[$m[2]]['type'] = trim($type_orga[1]) ;
			}
		}
	
	//var_dump($fragments,"fragments");
	//var_dump($institutions);
	if(is_array($fragments))
		foreach($fragments as $v){
			if(preg_match("/^(.*)\|(INDETERMINE)\|/u",$v,$m)){
				// cas des institutions : Parti communiste (PC)
				// recaler les accronymes : PC
				if($institutions[$m[1]]){
					$f = preg_replace("/^".$m[1]."/u", trim($institutions[$m[1]]['valeur']) . " (" . $m[1] . ")", $v) ;
					$f = preg_replace("/\|".$m[2]."\|/u","|". $institutions[$m[1]]['type'] ."|",$f) ;
					$fragments_fusionnes[] = $f ;
				}else
					$fragments_fusionnes[] = $v ;
			}else
				$fragments_fusionnes[] = $v ;
		}
	
	$fragments = $fragments_fusionnes ;
	$fragments_fusionnes = array();
	
	//var_dump($fragments);
	
	// var_dump("<pre>",$personnalites,$institutions,"</pre><hr>");
	if(is_array($personnalites))
		foreach($personnalites as $v){
			$a = explode(" ",$v) ;
			if(sizeof($a) > 1){
				// on suppose que le nom de famille est a la fin (mais ce n'est pas toujours le cas cf les personnalités asiatiques)
				$patronyme = array_pop($a);
				if(!$patronymes[$patronyme])
					$patronymes[$patronyme] = $v ;
			}
			if(sizeof($a) > 1){
				// on suppose que le nom de famille est au milieu : Idriss Déby Itno => M. Déby
				$patronyme = array_pop($a);
				if(!$patronymes[$patronyme])
					$patronymes[$patronyme] = $v ;
			}
		}
	
	// var_dump($personnalites,$institutions,$patronymes);
	// var_dump("<pre>",$fragments,"</pre><hr>");
	if(is_array($fragments))
		foreach($fragments as $v){
			
			// Dans ce qu'il reste plus les persos auto
			if(preg_match("/^(.*)\|(INDETERMINE)\|/u",$v,$m)){
				// attention aux noms de plus de deux mots
				$noms = explode(" ",$m[1]) ;
				$nom = array_pop($noms);
	
				// cas des institutions automatiques Parti communiste (PC) et personnalités
				// recaler des institutions réduites PC
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
	
	$fragments = $fragments_fusionnes ;
	$fragments_fusionnes = array();
	
	//var_dump("<pre>", $fragments);
	
	
	// remplacer les extraits caviardés par des vrais.
	if(is_array($fragments))
		foreach($fragments as $v){
	
			//var_dump($v,"hum");
	
			if(preg_match("`\d+\|(.*xxx.*)`u",$v,$extraitsc)){ // extraits caviardés précédemment
				$r = str_replace("`", "" , str_replace("xxx", ".*?" , preg_quote($extraitsc[1]))) ;
				if(preg_match("`" . $r . "`u" , $texte_original , $extrait )){ // peut donner un résultat long d'un paragraphe entier si l'entite xxx est au début.
					$f = str_replace($extraitsc[1], $extrait[0], $v) ;
					$fragments_fusionnes[] = $f ;
				}else{
					$fragments_fusionnes[] = $v ;
				}
	
			//var_dump("deb", $v, $f,"zou");
	
			}else{
				$fragments_fusionnes[] = $v ;
			}
		}

	/**/

	if(!is_array($fragments_fusionnes))
		$fragments_fusionnes = array(0 => "PASDENTITE|PASDENTITE|$id_article|");

	//$fragments_fusionnes = array_unique($fragments_fusionnes);
	//sort($fragments_fusionnes);
	//var_dump("<pre>",$fragments_fusionnes);


	// requalifier les Personnalités qui n'en sont pas par heuristique.

	$lieux = ENTITES_LIEUX_HEURISTIQUE ;
	$institutions = ENTITES_INSTITUTIONS_HEURISTIQUE ;

	foreach($fragments_fusionnes as $v){
		if(preg_match("/^(.*)\|(Personnalités)\|/u",$v,$m)){
			if(preg_match("`$lieux`Uu",$m[1])){
				$f = preg_replace("/\|".$m[2]."\|/u","|Géographie (auto)|",$v) ;
				$fragments_traites[] = $f ;
			}elseif(preg_match("`$institutions`Uu",$m[1])){
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
	
	// Mots avec une Capitale pas en début de phrase.
	preg_match_all("`" . 
					LETTRES . // une lettre ponctuation NON incluse.
					"\s+" . //  espace 
					"(" . LETTRE_CAPITALE . LETTRES ."+)\s+". // une capitale suivie de lettres
					"`u", $texte, $m);
	
	$entites_residuelles = $m[1];
	
	if(is_array($entites_residuelles) and sizeof($entites_residuelles) >= 1)
		array_walk($entites_residuelles,"nettoyer_entite_nommee");
	
	if(is_array($entites_residuelles))
		return $entites_residuelles ;
	else{ // ne pas merger avec un tableau NULL
		return array(0 => 'fail');
	}
}

function enregistrer_entites($entites = array(), $id_article, $date){

	$date = _q($date);

	// effacer les entites deja enregistrées pour cet article (maj)
	include_spip("base/abstract_sql");
	//var_dump("delete from entites_nommees where id_article=$id_article");
	sql_query("delete from entites_nommees where id_article=$id_article");

	if(is_array($entites))
		foreach($entites as $entite){
	
	/*
			if(preg_match("/^a\|/", $entite)){
				var_dump("enregis",$entite);
				exit ;
			}
	*/
			if(preg_match("/^\|/", $entite)){
				var_dump("alert pas d'entite",$entite,$entites);
				continue ;
			}
			
			$e = explode("|", $entite);
			
			$id_article_entite = $e[2];
			$extrait = _q($e[3]);
			$entite = _q($e[0]);
			
			$type_entite = _q($e[1]);
			
			//var_dump($date);
			$req = "INSERT INTO entites_nommees (entite, type_entite, id_article, extrait, date) VALUES ($entite,$type_entite,$id_article_entite,$extrait,$date)" ;
			sql_query($req);
			//var_dump("<pre>",$req,"</pre>");
		}

}

/*
	Tableaux de regexp par types d'entites
	à partir de fichiers dictionnaires
*/

function generer_types_entites($nb_mots="multi"){
	include_spip('iterateur/data');
	include_spip("inc/entites_nommees");
	
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
	
	return $types_entites ;
	
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
