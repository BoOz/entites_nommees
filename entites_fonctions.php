<?php

include('mots_courants.php');

// remonter la limite de taille d'une regexp
// essential for huge PCREs
// ini_set("pcre.backtrack_limit", "10000000000000");
// ini_set("pcre.recursion_limit", "10000000000000");

// http://fr.wikipedia.org/wiki/Table_des_caract%C3%A8res_Unicode/U0080
define("LETTRES","[a-zA-ZàáâãäåæçèéêëìíîïðñòóôõöøùúûüýþÿÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßœŒğı-]"); // il en manque, passer en /u

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
			if(preg_match_all("/\{(?!Cf|Ibid)[^,]+,?\}/uims",$note,$e)){

				//exit();
				
				$i = 0 ;
				
				foreach($e[0] as $ent){
					// Trouver les extraits ou apparaissent l'entite dans le texte original
					//var_dump("<pre>",$ent);
					
					if(preg_match_all("`(?:\P{L})((?:.{0,60})\P{L}" . str_replace("`", "", preg_quote($ent)) . "\P{L}(?:.{0,60}))(?:\P{L})`u", $texte_original, $m)){
						
			
						foreach($m[1] as $extrait){
							$extrait = preg_replace(",\R,","",trim($extrait));	
						
							//var_dump("<pre>",$ent . "|Source|" . $id_article . "|" . $extrait);
							$ent = preg_replace("~\{|\}|\,~","", $ent);
							// Enregistrer l'entite
							$fragments[] = $ent . "|Sources|" . $id_article . "|" . $extrait ;
				
						}
					}
					$i++;	
				}
			}
		}
	}

	//var_dump($fragments,"<hr><hr>");

	// types d'entites définis dans les listes txt.
	// on commence par celles qui font plusieurs mots.
	$types_entites =  generer_types_entites("multi");	


	/* regex automatisées depuis les fichiers de listes */
	// on commence par tout sauf les organisations à cause de la RDC
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

	//var_dump("<pre>", $fragments);

	// Gérer ensuite les institutions et partis politiques en mode développé + accronyme connus pour trouver ensuite les autres.
	foreach($types_entites as $k => $v)
		if(preg_match("/^(institution.*|parti.*)/i", $k, $r))
			$orgas[$r[1]] = $v ;
	
	//var_dump("<pre>", $orgas);
	
	foreach($orgas as $type => $reg){
		// On cherche la forme developpée + acrronyme : Confédération générale du travail (CGT)
		$label = preg_replace("/\d$/", "", $type);
		$recolte = recolter_fragments($label, $reg, $texte, $fragments, $id_article, $texte_original);
		$fragments = $recolte['fragments'];
		$texte = $recolte['texte'];
	
		/*
		// Ensuite l amême chose sans l'ccaonyme : Confédération générale du travail.
		$types_reduits = preg_replace("/\s.\([^\)]+\)/u","",$reg);
		
		//var_dump($types_reduits);
		
		$recolte = recolter_fragments($label, $types_reduits, $texte, $fragments, $id_article, $texte_original);
		$fragments = $recolte['fragments'];
		$texte = $recolte['texte'];
		
		//var_dump($reg);
		
		// ensuite que l'acconyme s'il fait plus qu'une lettre...
		preg_match_all("/\\\\\([^)]{2,}\\\\\)/Uu", str_replace("(?:É|E)", "E", $reg), $accros);
		
		//var_dump($reg,$accros[0]);
		
		$accros[0] = str_replace("\(", "\P{L}", $accros[0]);
		$accros[0] = str_replace("\)", "\P{L}", $accros[0]);
		
		$accros = join("|", array_unique($accros[0]));
		
		$recolte = recolter_fragments($label, $accros, $texte, $fragments, $id_article, $texte_original);
		$fragments = $recolte['fragments'];
		$texte = $recolte['texte'];
		
		*/


	}
	
	/* recaler les accro et les developpés */
	$institutions = array() ;
	$acronymes = "((?<!\.\s)" . LETTRE_CAPITALE . "(?:". LETTRES ."|\s|')+)\((" . LETTRE_CAPITALE . "+)\)";

	// Repérer les organisations
	foreach($fragments as $v){
		if(preg_match("`$acronymes`u",$v,$m))
			$institutions[$m[2]] = $m[1] ;
	}

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

	$fragments_fusionnes = array();

	
	//var_dump($fragments,"<hr><hr>", $texte);

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

	//var_dump("lol", $fragments);

	// on cherche des termes qui ont des majuscules dans le texte restant.
	$entites_residuelles = trouver_entites_residuelles($texte);

	$recolte = traiter_fragments($entites_residuelles, "INDETERMINE", $texte, $fragments, $id_article, $texte_original) ;
	$fragments = $recolte['fragments'];
	$texte = $recolte['texte'];

	//var_dump($fragments,"fragments");

	// var_dump("<pre>",$entites_residuelles,"</pre><hr>zou<pre>",$fragments,"<hr>",$texte,"<hr>");
	$institutions = array() ;
	// fusionner les personnalités Barack Obama + Mr Obama => Barack Obama
	// En cas d'accronyme Parti communiste (PC), virer aussi la forme réduite		
	
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
	foreach($fragments as $v){

		//var_dump($v,"hum");

		if(preg_match("`\d+\|(.*xxx.*)`u",$v,$extraitsc)){ // extraits caviardés précédemment
			if(preg_match("`" . str_replace("`", "" , str_replace("xxx", ".*" , preg_quote($extraitsc[1]))) . "`u" , $texte_original , $extrait )){
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


function recolter_fragments($type_entite, $regex, $texte, $fragments, $id_article, $texte_original){

	// trouver toutes les occurences d'une entité
	if(preg_match_all( "`" . $regex . "`u" , $texte ,$e)){
		$entites = $e[0];
		//var_dump("<pre>",$entites,"</pre>lol");
		$recolte = traiter_fragments($entites, $type_entite, $texte, $fragments, $id_article, $texte_original);
		$fragments = $recolte["fragments"];
		$texte = $recolte["texte"];
	}
	return array("texte" => $texte, "fragments" => $fragments) ;
}

function traiter_fragments($entites, $type_entite, $texte, $fragments, $id_article, $texte_original){

	// réguler les types avec plusieurs sous_chaines
	$type = preg_replace("/([^\d]+)\d+$/u", "$1", $type_entite);
	$type = str_replace("_", " ", $type);	

	// On recupere un tableau d'entites possiblement redondantes
	// nettoyer un peu
	// array_walk($entites,"nettoyer_entite_nommee"); // marche pas
	if(is_array($entites) and sizeof($entites) >= 1)
		array_walk($entites,"nettoyer_entite_nommee");
	else	
		return array("texte" => $texte, "fragments" => $fragments);

	//var_dump("<pre>",$entites,"</pre>lol");

	//var_dump("<pre>");

	// Chasse aux entites ouverte !
	// On supprime les entites du texte pour les chasser toutes à la fin ou ne reste plus qu'un texte sans entites.
	foreach($entites as $entite){
			
		if($entite == "")
			continue ;

		// Trouver l'extrait dans le texte débité
		preg_match("`(?:\P{L})(?:.{0,60})" . str_replace("`", "", preg_quote($entite)) . "(?:.{0,60})(?:\P{L})`u", $texte, $m);
		$extrait = preg_replace(",\R,","",trim($m[0]));

		// Virer l'entité dans cet extrait, puis dans le texte débité.
		if(!$m[0])
			$texte = str_replace($entite, "xxx" , $texte);
		else{
			$extrait_propre = str_replace($entite,"xxx",$extrait);
			$texte = str_replace($extrait, $extrait_propre , $texte);
		}

		//var_dump($entite, $extrait, "lol");
		//var_dump("<pre>",$entite . "|$type|" . $id_article . "|" . $extrait);
				
		// Enregistrer l'entite
		$fragments[] = $entite . "|$type|" . $id_article . "|" . $extrait ;

	}

	// var_dump($entites,$fragments,"lol");

	// trouver des formes réduites résiduelles
	// if(is_array($reduite))
	//	var_dump("<pre>", $reduite ,"</pre><hr>");

	return array("texte" => $texte, "fragments" => $fragments);
}


function trouver_noms($texte){

	// M. Joseph (« Joe ») Lhota. http://localhost:8888/diplo/?page=noms&id_article=50640

	// http://stackoverflow.com/questions/7653942/find-names-with-regular-expression

	// virer les débuts de phrases
	$reg =  "%(?:\P{L})". // un espace ou saut de ligne ou guillement ou apostrophe non capturé
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
	
	// recaler les noms ici meme si on le refait plus tard pour ne pas prendre deby maintenant
	$noms = array_unique($noms);

	// var_dump("<pre>",$personnalites,"</pre><hr>");
	if(is_array($noms)){
		foreach($noms as $v){
			$a = explode(" ",str_replace("'", " ", $v)) ;
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
		//var_dump("<pre>",$noms, $patronymes);
	
		// voir si on a pas un nom court qui existe en long
		foreach($noms as $v)
			if(!$patronymes[$v])
				$noms_fusionnes[] = $v ;
		
		$noms = $noms_fusionnes ;
		
	}

	
	//var_dump("<pre>",$noms);
	
	//var_dump($noms);

	return $noms ;
}

// renvoyer en fait patronyme => forme longue
function nettoyer_noms(&$item1, $key){
	// (^M\.|^Mme|^Mgr|^Dr|^Me) \s+
	$item1 = preg_replace("/(^M\.|^Mmes?|^Mgr|^Dr|^Me)\s+/Uu","",$item1); // pas les civilités
	$item1 = preg_replace("/-$/u"," ",$item1);
	
	// http://archives.mondediplo.com/ecrire/?exec=articles&id_article=8337
	$item1 = preg_replace("/\R/"," ",$item1);
	
	$item1 = trim($item1);

}

function nettoyer_entite_nommee(&$entite, $key){

	//var_dump($entite);

	// nettoyage des entités choppées avec une ,. ou autre.
	if(strpos($entite, "("))
		$entite = trim(preg_replace("`(?!\))[^\p{L}\p{N}]+$`u", "", $entite));
	else
		$entite = trim(preg_replace("`[^\p{L}\p{N}]+$`u", "", $entite));
		
	if(preg_match("`^(" . MOTS_DEBUT . ")$`u", $entite))
		$entite = "" ;	

	$entite = trim(preg_replace("/^[^\p{L}\p{N}]+/u", "", $entite));
	
	//var_dump($entite);

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
	preg_match_all("`(?!(?i)(?:". MOTS_DEBUT .")\s+)" . LETTRE_CAPITALE ."(?:". LETTRES ."+)\s+`u", $texte, $m);

	$entites_residuelles = $m[0];

	if(is_array($entites_residuelles) and sizeof($entites_residuelles) >= 1)
		array_walk($entites_residuelles,"nettoyer_entite_nommee");

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
