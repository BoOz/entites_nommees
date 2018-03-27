<?php

// Extraire des entités nommées d'un texte

include_spip('mots_courants');

// remonter la limite de taille d'une regexp
// essential for huge PCREs
// ini_set("pcre.backtrack_limit", "10000000000000");
// ini_set("pcre.recursion_limit", "10000000000000");

// http://fr.wikipedia.org/wiki/Table_des_caract%C3%A8res_Unicode/U0080
// http://www.regular-expressions.info/unicode.html
// To match a letter including any diacritics, use \p{L}\p{M}*+.
define("LETTRES","[a-zA-ZàáâãäåæçèéêëìíîïðñòóôõöøùúûüýþÿÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßœŒğı-]"); // il en manque, passer en /u
define("LETTRESAP","[a-zA-ZàáâãäåæçèéêëìíîïðñòóôõöøùúûüýþÿÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßœŒğı'-]"); // il en manque, passer en /u

// http://www.regular-expressions.info/unicode.html
/*

 \p{L} lettres
 \p{Lu} or \p{Uppercase_Letter}: an uppercase letter that has a lowercase variant.
 \p{Z} or \p{Separator}: any kind of whitespace or invisible separator.
      \p{Zs} or \p{Space_Separator}: a whitespace character that is invisible, but does take up space.
      \p{Zl} or \p{Line_Separator}: line separator character U+2028.
      \p{Zp} or \p{Paragraph_Separator}: paragraph separator character U+2029. 
    \p{N} or \p{Number}: any kind of numeric character in any script.
        \p{Nd} or \p{Decimal_Digit_Number}: a digit zero through nine in any script except ideographic scripts.
        \p{Nl} or \p{Letter_Number}: a number that looks like a letter, such as a Roman numeral.
        \p{No} or \p{Other_Number}: a superscript or subscript digit, or a number that is not a digit 0–9 (excluding numbers from ideographic scripts). 
    \p{P} or \p{Punctuation}: any kind of punctuation character.
        \p{Pd} or \p{Dash_Punctuation}: any kind of hyphen or dash.
        \p{Ps} or \p{Open_Punctuation}: any kind of opening bracket.
        \p{Pe} or \p{Close_Punctuation}: any kind of closing bracket.
        \p{Pi} or \p{Initial_Punctuation}: any kind of opening quote.
        \p{Pf} or \p{Final_Punctuation}: any kind of closing quote.
        \p{Pc} or \p{Connector_Punctuation}: a punctuation character such as an underscore that connects words.
        \p{Po} or \p{Other_Punctuation}: any kind of punctuation character that is not a dash, bracket, quote or connector. 
*/

define("LETTRE_CAPITALE","\p{Lu}");

function trouver_entites($texte,$id_article){
	
	/*
		Trouver d'abord des entites connues (Institutions, Traités,..., Pays (en dernier)) à partir de listes txt.
		Trouver ensuite dans le texte les personnalités restantes.
		
		TODO
			- passer un unique sur les listes texte (PKK) ?
		
	*/
	
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
		if(preg_match("/(^villes.*|^pays.*|^medias.*)/i", $k, $r))
			$types_connus[$r[1]] = $v ;
	
	// $types d'entites lieux ou média à repérer dans les notes.
	foreach($types_entites_mono as $k => $v)
		if(preg_match("/(^villes.*|^pays.*|^medias.*)/i", $k, $r))
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
	
	$acronymes = "((?<!\P{L}\s)" . LETTRE_CAPITALE . "(?:". LETTRES ."|\s|')+)\((" . LETTRE_CAPITALE . "+)\)";
	
	// Gérer ensuite les institutions et partis politiques en mode développé + acronyme connus pour trouver ensuite les autres.
	foreach($types_entites as $k => $v)
		if(preg_match("/^(institution.*|parti.*)/i", $k, $r))
			$orgas[$r[1]] = $v ;
		
		//var_dump("<pre>", $orgas);
		
		// monter un tableau acronyme => nom pou ressayer de capter des FARC directement dans le texte.
		// [PS] => Parti socialiste
		// en cas d'homonymie laisser tomber ex : Front national (FN) et Front national (PBKS) ou Les Républiccains (LR) et Les Raleurs (LR)
		// on demonte des regexp \P{L}Cour suprême\P{L}|\P{L}Congrès des (?:É|E)tats\-Unis\P{L}|...
		// doublons par la cle OU la valeur (FN ou Front national) 
		// Parti communiste (PCU)
		$homonymes = $institutions = array();
		foreach($orgas as $orga){
			$insts = explode("\P{L}", $orga);
			foreach($insts as $i){
				$i = stripslashes($i) ;
				if(!preg_match("`$acronymes`",$i, $m))
					continue ;
				//var_dump("<pre>",$m);
				if($institutions[$m[2]] OR in_array(trim($m[1]),$institutions)){
					$homonymes[] = trim($m[1]) ;
					$institutions[$m[2]] = "homonymes" ;
				}else
					$institutions[$m[2]] = trim($m[1]) ;
			}
			//var_dump("<pre>", $institutions);
			foreach($institutions as $i => $v)
				if($v == "homonymes")
					unset($institutions[$i]) ;
		}
		//var_dump("<pre>", $homonymes);
		
		foreach($orgas as $type => $reg){
			//var_dump($reg);
			$label = preg_replace("/\d$/", "", $type);
			
			// On cherche la forme developpée + acronyme : Confédération générale du travail (CGT)
			
			$recolte = recolter_fragments($label, $reg, $texte, $fragments, $id_article, $texte_original);
			$fragments = $recolte['fragments'];
			$texte = $recolte['texte'];
			
			//var_dump("<pre>",$fragments, $texte, "<hr>");
			
			// Ensuite la même chose sans l'acronyme : Confédération générale du travail.
			// Mais plus tard sinon on se fait avoir par les homonymes.
			$types_reduits[$label][] = preg_replace("/\s.\([^\)]+\)/u","",$reg);
			
			//var_dump($types_reduits);
			
			//var_dump("<pre>",$fragments, $texte);
			
			// ensuite que l'acronyme s'il fait plus qu'une lettre...
			preg_match_all("/\\\\\([^)]{2,}\\\\\)/Uu", str_replace("(?:É|E)", "E", $reg), $acros);
			
			$acros[0] = str_replace("\(", "\P{L}", $acros[0]);
			$acros[0] = str_replace("\)", "\P{L}", $acros[0]);
			
			$acros = join("|", array_unique($acros[0]));
			
			//var_dump("<pre>",$reg,$acros,"</pre>");
			
			$recolte = recolter_fragments($label, $acros, $texte, $fragments, $id_article, $texte_original);
			$fragments = $recolte['fragments'];
			$texte = $recolte['texte'];
			/**/
		}
		foreach($types_reduits as $l => $ts)
			foreach($ts as $t){
				$recolte = recolter_fragments($l, $t, $texte, $fragments, $id_article, $texte_original);
				$fragments = $recolte['fragments'];
				$texte = $recolte['texte'];
			}
		
	// a debug
	//var_dump("<pre>", $fragments);
	
	/* recaler les acro et les developpés */
	// Repérer les organisations dans le texte pour surcharger les institutions générales (des regexp)
	// si une forme longue est trouvée, on cherche
	$institutions_locales = array();
	if(is_array($fragments))
		foreach($fragments as $v){
			if(preg_match("`$acronymes`u",$v,$m))
				$institutions_locales[$m[2]] = trim($m[1]) ;
		}
	
	//var_dump("<pre>",$institutions_locales,"<hr>",$homonymes);
	
	if(is_array($fragments))
		foreach($fragments as $v){
			if(preg_match("/^(.*)\|(Institutions|Partis politiques)\|/u",$v,$m)){
				// cas des Parti communiste (PC)
				// recaler des institutions réduites PC
				// ["PS"]=>"Parti socialiste "
				$ar_inst = ($institutions_locales[$m[1]]) ? $institutions_locales : $institutions ;
				if($ar_inst[$m[1]]){
					// PS => Parti socialiste PS
					$f = preg_replace("/^".$m[1]."/u", trim($ar_inst[$m[1]]) . " (" . $m[1] . ")", $v) ;
					$fragments_fusionnes[] = $f ;
				}elseif( (in_array($m[1], $institutions) AND !in_array($m[1],$homonymes) ) OR in_array($m[1], $institutions_locales)){ 
					$ar_inst = (in_array($m[1], $institutions_locales)) ? $institutions_locales : $institutions ;
					// recaler des institutions réduites moyenne
					// Parti socialiste => Parti socialiste (PS)
					// trouver la clé de Parti communiste
					$ac = array_keys($ar_inst, $m[1]);
					$ac = $ac[0] ;
					
					$f = preg_replace("/^".$m[1]."/u", $m[1] . " (" . $ac . ")", $v) ;
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
	$recolte = recolter_fragments("Institutions (auto)", $acronymes, $texte, $fragments, $id_article, $texte_original);
	$fragments = $recolte['fragments'];
	$texte = $recolte['texte'];
	
	// on enlève des FONCTIONS de QUELQUEPART : premier ministre du Luxembourg etc
	$recolte = recolter_fragments("Fonctions", "(" . FONCTIONS_PERSONNALITES . ")\s(?:du|de la|d'|des)\s(". LETTRE_CAPITALE . LETTRES ."+)", $texte, 	$fragments, $id_article, $texte_original);
	$fragments = $recolte['fragments'];
	$texte = $recolte['texte'];
	
	// Dans le texte expurgé des entités connues, on passe la regexp qui trouve des noms.
	// a mettre en démo	
	
	//var_dump("<pre>", $fragments,"hop");
	
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
	
	//var_dump("<pre>",$fragments,"</pre>","<hr>",$texte);
	
	// on cherche des termes qui ont des majuscules dans le texte restant.
	$entites_residuelles = trouver_entites_residuelles($texte);
	
	//var_dump($texte);
	//var_dump($entites_residuelles);
	
	$recolte = traiter_fragments($entites_residuelles, "INDETERMINE", $texte, $fragments, $id_article, $texte) ;
	$fragments = $recolte['fragments'];
	$texte = $recolte['texte'];
	
	//var_dump($fragments,"fragments");
	
	// var_dump("<pre>",$entites_residuelles,"</pre><hr>zou<pre>",$fragments,"<hr>",$texte,"<hr>");
	$persos = array() ;
	// fusionner les personnalités Barack Obama + Mr Obama => Barack Obama
	// En cas d'acronyme Parti communiste (PC), virer aussi la forme réduite
	
	if(is_array($fragments))
		foreach($fragments as $v){
			if(preg_match("/(.*)\|Personnalités\|/u",$v,$m))
				$personnalites[] = $m[1] ;
			elseif(preg_match("`$acronymes`u",$v,$m)){
				$persos[$m[2]]['valeur'] = trim($m[1]) ;
				preg_match(",^[^|]+\|([^|]+)\|,", $v, $type_orga);
				//var_dump($v, $type_orga, "lol");
				$persos[$m[2]]['type'] = trim($type_orga[1]) ;
			}
		}
	
	//var_dump($fragments,"fragments");
	//var_dump($persos);
	if(is_array($fragments))
		foreach($fragments as $v){
			if(preg_match("/^(.*)\|(INDETERMINE)\|/u",$v,$m)){
				// cas des institutions : Parti communiste (PC)
				// recaler les acronymes : PC
				if($persos[$m[1]]){
					$f = preg_replace("/^".$m[1]."/u", trim($persos[$m[1]]['valeur']) . " (" . $m[1] . ")", $v) ;
					$f = preg_replace("/\|".$m[2]."\|/u","|". $persos[$m[1]]['type'] ."|",$f) ;
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
				$f = preg_replace("/\|".$m[2]."\|/u","|Institutions (auto)|",$v) ;
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

function entites_nommees_notes_bas_page($texte, $id_article, $regex_types_connus, $regex_types_connus_mono){
	
	//var_dump("<pre>", $regex_types_connus,"</pre><hr>");
	//var_dump("<pre>", $regex_types_connus_mono,"</pre><hr>");
	
	// trouver des notes spip [[ note de bas de page ]]
	// Enregistrer l'entite
	// "Black Boy|Sources|47572|se prenaient pour des amis des Nègres} [[ Richard Wright, {Black Boy,} traduit de l'anglais par Marcel Duhamel et Andrée R. Picard,"
	
	if(preg_match_all("/\[\[(.*)\]\]/Umsu", $texte, $notes)){
		
		foreach($notes[1] as $note){
			$note_originale = $note ;
			// trouver la source en ital spip {}
			if(preg_match("/\{(?!\s?Cf\s?|\s?Ibid\s?|\s?in\s?|\s?et al\.?\s?|\s?op\.?\s?cit\.?\s?)([^,]+),?\}/uims",$note,$s)){ //  et al. 
				$entite = nettoyer_entite($s[1]) ;
				//var_dump($entite,"<br>");
				
				// Enregistrer l'entité de type Sources.
				if(strlen($entite) > 1){
					$fragments[] = "media:" . $entite . "|Sources|" . $id_article . "|" . $note_originale ;
					$note = str_replace($s[1],"", $note);
				}
			}
			
			// var_dump("<pre>", $regex_types_connus ,"</pre><hr><hr>");
			
			// Enlever les lieux et médias multi entités
			foreach($regex_types_connus as $r){
				if(preg_match_all( "`" . $r . "`u" , $note ,$e)){
					foreach($e[0] as $l){
						// reperer un lieu de publication
						$l = nettoyer_entite($l);
						//var_dump($l,"<br>");
						if($l)
							$fragments[] = "lieu:" . $l . "|Lieux de publication|" . $id_article . "|" . $note_originale ;
						// virer de la note
						$note = str_replace($l, "", $note);
					}
				}
			}
			
			// var_dump("<pre>", $regex_types_connus_mono ,"</pre><hr><hr>");
			
			// Enlever les lieux et médias mono entités
			foreach($regex_types_connus_mono as $r){
				if(preg_match_all( "`" . $r . "`u" , $note ,$e)){
					foreach($e[0] as $l){
						// reperer un lieu de publication
						$l = nettoyer_entite($l);
						// var_dump($l,"<br>");
						if($l)
							$fragments[] = "lieu:" . $l . "|Lieux de publication|" . $id_article . "|" . $note_originale ;
						// virer de la note
						$note = str_replace($l, "", $note);
					}
				}
			}
			
			// auteurs
			// en virant les lieux et institutions devinés par Heuristique
			$auteurs = entites_nommees(trouver_noms($note)) ;
			
			// retrouver des institutions
			if(is_array($auteurs ["institutions"]) AND sizeof($auteurs ["institutions"]) > 0)
				foreach($auteurs ["institutions"] as $a)
					$fragments[] = "media:" . $a . "|Sources|" . $id_article . "|" . $note_originale ;
			
			// reste des noms
			if(is_array($auteurs ["personnalites"]) AND sizeof($auteurs ["personnalites"]) > 0)
				foreach($auteurs ["personnalites"] as $a)
					$fragments[] = "auteur:" . $a . "|Auteurs|" . $id_article . "|" . $note_originale ;
			
			// virer la note de bas de page du texte pour la suite
			$texte = str_replace($note_originale, "", $texte);
		}
	}
	
	return array('fragments' => $fragments, 'texte' => $texte);
}

function recolter_fragments($type_entite, $regex, $texte, $fragments, $id_article, $texte_original){
	// Trouver toutes les occurences d'une entité en respectant la casse bien sur.
	//if(preg_match("/medias/", $type_entite))
	//	var_dump($regex);
	
	if(preg_match_all( "`" . $regex . "`u" , $texte ,$e)){
		$entites = $e[0];
		//var_dump("<pre> recolte :",$type_entite, $regex, $entites,"</pre>lol");
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

	// Chasse aux entites ouverte !
	// On supprime les entites du texte pour les chasser toutes à la fin ou ne reste plus qu'un texte sans entites.
	foreach($entites as $entite){
			
		if($entite == "")
			continue ;

		// Trouver l'extrait incluant l'entite dans le texte débité
		preg_match("`(?:\P{L})(?:.{0,60})(?:\p{Z}|\p{P})" . str_replace("`", "", preg_quote($entite)) . "(?:\p{Z}|\p{P})(?:.{0,60})(?:\P{L})`u", $texte, $m);
		$extrait = preg_replace(",\R,","",trim($m[0]));

		//var_dump($entite);
		//if($entite == "Cuba"){
		//	var_dump("<pre>","Traiter frag :" ,$type, $entite,"</pre>", $extrait);
		//}

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
	
	// Trouver des noms de personnalités dans un texte en recherchant le masque : Xxx Xxx xx xx Xxx
	// http://stackoverflow.com/questions/7653942/find-names-with-regular-expression
	
	// http://php.net/manual/fr/regexp.reference.assertions.php
	// http://php.net/manual/fr/regexp.reference.subpatterns.php
	// (?<!foo)bar trouve les occurrences de "bar" qui ne sont pas précédées par "foo". // assertion arriere negative
	// foo(?!bar) trouve toutes les occurrences de "foo" qui ne sont pas suivies par "bar". // assertion avant negative
	
	$reg =  "%(?:\P{L})". // Caractere qui n'est pas une lettre
			"(?!(?i)(?:". MOTS_DEBUT .")\s+)". // non suivi d'un mot fréquent en debut de phrase, espace
			"(".
				"(?:".
					"(?<!\.)". 
					LETTRE_CAPITALE . // Lettre capitale non précédée d'un . (C.I.A)
					"(?!')" . // non suivie par un apostrophe
					"(?:" . LETTRES . ")". // suivie par des lettres ou -
					"(?:". LETTRESAP ."+)*". // éventuellement suivi par un ' et des lettres
				")". // On a trouvé un mot avec une capitale avec un apostrophe possible mais pas en seconde position.
				"(?:\s+" . // Eventuellement un espace suivi
					LETTRE_CAPITALE . "(?:". LETTRES ."+|\.)". //d un autre mot avec capitale derrière, ou bien une capitale suivie d'un . (George W. Bush)
				")*".
				"(?:\s+". // Eventuellement un espace
					"(?!(?:". MOTS_MILIEU ."))". // non suivi par un mot courrant
						LETTRES ."+" .
				"){0,2}". // suivi d un ou deux mots sans capitale (van der)
				"(?:".
					"(?:\s+|'|’)". // un espace ou un apostrophe
					"(?!". MOTS_FIN .")" . // non suivi par un mot courrant
						LETTRE_CAPITALE . LETTRES ."+". // Un mot avec une capitale suivie de lettres ou -
				")".
				"|". ENTITES_PERSO . // Personnalités à pseudo // a virer ?
			")".
	"%u";
	
	preg_match_all($reg,$texte,$m);
	$noms = $m[1] ;
	//var_dump("<pre>",$m);
	
	array_walk($noms,"nettoyer_noms");
	
	// recaler les noms ici meme si on le refait plus tard pour ne pas prendre deby maintenant
	
	//var_dump($noms);
	
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
	$item1 = preg_replace("/(^M\.|^Mmes?|^Mgr|^Dr|^Me|MM\.)\s+/Uu","",$item1); // pas les civilités
	$item1 = preg_replace("/-$/u"," ",$item1);
	
	// http://archives.mondediplo.com/ecrire/?exec=articles&id_article=8337
	$item1 = preg_replace("/\R/"," ",$item1);
	
	$item1 = trim($item1);

}
// dans un array_walk
function nettoyer_entite_nommee(&$entite, $key){

	//var_dump($entite);

	$entite = nettoyer_entite($entite);
	
	//if($entite == "Cuba")
	//	var_dump("<pre>", "nettoyage entites : ", $entite, "</pre>");

}

function trouver_entites_residuelles($texte){
	
	//var_dump("<pre>",$texte);
	
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

function nettoyer_entite($entite){
	
	// nettoyage des entités choppées avec une ,. ou autre.
	if(strpos($entite, "("))
		$entite = trim(preg_replace("`(?!\))[^\p{L}\p{N}]+$`u", "", $entite));
	else{
		$entite = trim(preg_replace("`[^\p{L}\p{N}]+$`u", "", $entite));
	}
	if(preg_match("`^(" . MOTS_DEBUT . ")$`u", $entite))
		$entite = "" ;
	
	$entite = trim(preg_replace("/^[^\p{L}\p{N}]+/u", "", $entite));
	
	$entite = preg_replace("/\R/Uim", " ", $entite);
	//var_dump("<pre>",$entite,"<br>");
	
	return $entite ;
}

function generer_stop_words(){
	// effacer les mots courrants (stop words)
	lire_fichier(find_in_path("mots_courants.php"), $stop_words);
	// virer les com
	$stop_words = preg_replace(",^//.*,um","",$stop_words);
	preg_match_all('`\=\s*"([^"]+)"`Uims', $stop_words, $w);
	
	$words = array();
	foreach($w[1] as $reg){
		$words = array_merge($words, explode("|",$reg));
	}
	
	return $words ;
}
