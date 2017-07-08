<?php

// TODO
// passer la liste des institutions automatique et personnalité dans une liste d'alias pour repecher les FARC par exemple.

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
				$entite = trim($s[1]) ;
				
				// Enregistrer l'entité de type Sources.
				if(strlen($entite) > 1)
					$fragments[] = "media:" . $entite . "|Sources|" . $id_article . "|" . $note ;
			}
			
			// var_dump("<pre>", $regex_types_connus ,"</pre><hr><hr>");
			
			// Enlever les lieux et médias multi entités
			foreach($regex_types_connus as $r){
				if(preg_match_all( "`" . $r . "`u" , $note ,$e)){
					foreach($e[0] as $l){
						// reperer un lieu de publication
						$l = nettoyer_entite($l);
						if($l)
							$fragments[] = "lieu:" . $l . "|Lieux de publication|" . $id_article . "|" . $note ;
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
						$lieu = nettoyer_entite($l);
						if($l)
							$fragments[] = "lieu:" . $l . "|Lieux de publication|" . $id_article . "|" . $note ;
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
					$fragments[] = "media:" . $a . "|Sources|" . $id_article . "|" . $note ;
			
			// reste des noms
			if(is_array($auteurs ["personnalites"]) AND sizeof($auteurs ["personnalites"]) > 0)
				foreach($auteurs ["personnalites"] as $a)
					$fragments[] = "auteur:" . $a . "|Auteurs|" . $id_article . "|" . $note ;
			
			// virer la note de bas de page du texte pour la suite
			$texte = str_replace($note_originale, "", $texte);
		}
	}
	
	return array('fragments' => $fragments, 'texte' => $texte);
}

function recolter_fragments($type_entite, $regex, $texte, $fragments, $id_article, $texte_original){
	// Trouver toutes les occurences d'une entité en respectant la casse bien sur.
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

	// virer les débuts de phrases fréquents avec une liste de mots fréquents
	$reg =  "%(?:\P{L})". // lettre ou ponctuation non capturée
			"(?!(?i)(?:". MOTS_DEBUT .")\s+)". // pas suivie d'un mot fréquent en debut de phrase, espace
			"(".
				"(?:(?<!\.)" . LETTRE_CAPITALE . "(?!')(?:" . LETTRES . ")(?:". LETTRESAP ."+|\.))". // Un mot avec une capitale non précédée d'un . (C.I.A. Le ...), suivie de lettres ou - ou ' (mais pas en deuxieme) ou d'un .
				"(?:\s+" . LETTRE_CAPITALE . "(?:". LETTRES ."+|\.))*". // Des éventuels mots avec une capitale suivie de lettres ou - ou d'un . 
				"(?:\s+(?!(?:". MOTS_MILIEU ."))". LETTRES ."+){0,2}". // Un ou deux éventuels mots (van der), mais pas des mots courants
				"(?:(?:\s+|'|’)(?!". MOTS_FIN .")" . LETTRE_CAPITALE . LETTRES ."+)". // Un mot avec une capitale suivie de lettres ou - , mais pas des mots de fins
			"|". ENTITES_PERSO .")". // Personnalités à pseudo // a virer ?
	"%mu"	;

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
	
	return $entite ;
}

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
