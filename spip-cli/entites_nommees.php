<?php

/***

Ajouter des infos liées à l'article fr dans les articles internationaux.
On passe Google Trad puis par simserver pour connaitre l'article FR d'origine.

Lenteur de Google trad : 30 minutes par numéro du diplo à traduire.

****/


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;

class entites_nommees extends Command {
	protected function configure() {
		$this
			->setName('entites_nommees:importer')
			->setDescription('Générer les entités nommées en parcourant la base. ` for i in {1..50} ; do spip entites ; done`')
			->setAliases(array(
				'entites'
			))
			->addArgument('fichier', InputArgument::OPTIONAL, 'Fichier texte à analyser')
			->addOption(
				'restart',
				'r',
				InputOption::VALUE_OPTIONAL,
				'Effacer les entités nommées en base et recommencer la recherche',
				'non'
			)
			->addOption(
				'maj',
				'm',
				InputOption::VALUE_OPTIONAL,
				'Requalifier les entités de statut INDETERMINE (en renommant le type d\'apres les fichiers du répertoire a_ajouter)',
				'non'
			)
			->addOption(
				'type',
				't',
				InputOption::VALUE_OPTIONAL,
				'Chercher les entites dans le type `-t spip_syndic_articles`...',
				'spip_articles'
			);
		
	}
	
	protected function execute(InputInterface $input, OutputInterface $output) {
		global $spip_racine;
		global $spip_loaded;
		global $spip_version_branche ;
		
		if($fichier = $input->getArgument('fichier')){
			if(!is_file($fichier)){
				echo "Fichier $fichier non trouvé...\n" ;
				exit ;
			}
			echo "Calcul des entités nommées dans le fichier " . basename($fichier) . "...\n" ;
			lire_fichier($fichier,$f);
			//var_dump($f);
			include_spip("entites_fonctions");
			$texte = preparer_texte($f);
			$fragments = trouver_entites($texte,0) ;
			if($fragments)
				foreach($fragments as $e)
					echo preg_replace("/\|/","	", str_replace("|0|", "	" , $e));
			else
				echo "Pas d'entités nommées dans $fichier";
			echo "\n\n";
			exit ;
		}
		
		$restart = $input->getOption('restart') ;
		$requalifier = $input->getOption('maj') ;
		$type_source = $input->getOption('type') ;
		
		include_spip("base/abstract_sql");
		include_spip("entites_fonctions");
		
		if ($spip_loaded) {
			chdir($spip_racine);
			
			if (!function_exists('passthru')){
				$output->writeln("<error>Votre installation de PHP doit pouvoir exécuter des commandes externes avec la fonction passthru().</error>");
			}
			// Si c'est bon on continue
			else{
				
				// requalifier les entites a posteriori
				if($requalifier !=="non"){
					passthru("clear");
					
					// Maj du fichier recaler.txt sur GD en bash.
					passthru("./plugins/entites_nommees/spip-cli/sync_data.sh", $reponse); // chmod +x sync_data.sh la premiere fois
					if($reponse == 1){
						echo "On annule la lise à jour de la base de donnée car le diff des listes a été rejeté.\n\n" ;
						exit ;
					}
					
					$output->writeln("<info>Mise à jour des entités dans la base de données d'après le fichier recaler.txt</info>");
					
					// recaler apres coup d'apres le fichier recaler.txt
					// prevoir aussi des : update entites_nommees set entite='Pays basque', type_entite='Pays' where entite='Pays' and extrait like '%Pays basque%' and type_entite='INDETERMINE' ;
					
					lire_fichier('plugins/entites_nommees/recaler.txt', $recale);
					$entites_a_revoir = explode("\n", $recale);
					if(sizeof($entites_a_revoir) > 1 )
							foreach($entites_a_revoir as $e){
								if(preg_match(",^//," ,$e) OR preg_match(",^$," ,$e)) /**/
									continue;
								list($entite_actuelle,$entite_dans_extrait, $type_entite, $entite) = explode("\t", $e);
								//var_dump($entite_actuelle,$entite_dans_extrait, $type_entite, $entite);
								$sel = 	"select * from entites_nommees where BINARY entite= " . sql_quote($entite_actuelle) . " and extrait like '%". addslashes($entite_dans_extrait) ."%'" ;
								$q = sql_query($sel);
								$nb = sql_count($q);
								if($nb > 0){
									echo "$nb $entite_actuelle ($entite_dans_extrait) => $entite\n" ;
									$up = "update entites_nommees set entite=". sql_quote($entite) .", type_entite=". sql_quote($type_entite) ." where BINARY entite=" . sql_quote($entite_actuelle) . " and extrait like '%". addslashes($entite_dans_extrait) ."%'" ;
									echo $up . "\n" ;
									sql_query($up);
									echo "\n" ;
								}
							}
					
					// recaler après coup les ajouts dans les fichiers /listes_lexicales/*/*
					$output->writeln("<info>Requalification des données d'après les listes_lexicales/*/*</info>");
					include_spip('iterateur/data');
					$types_requalif = inc_ls_to_array_dist(_DIR_RACINE . 'plugins/entites_nommees/listes_lexicales/*/*') ; /**/
					foreach($types_requalif as $t){
						$type_entite = basename($t['dirname']);
						$entites_a_revoir = $freq = array(); 
						$entites_a_revoir = generer_mots_fichier($t['dirname'] . "/" . $t['basename']);
						
						if(sizeof($entites_a_revoir) > 1 ){
							foreach($entites_a_revoir as $e){
								if(trim($e) == "")
									continue ;
								$ent = sql_query("select * from entites_nommees where type_entite in ('INDETERMINE', 'Personnalités', 'Auteurs', 'Institutions (auto)', 'Villes','a ajouter','Géographie (auto)') and (entite= " . sql_quote($e) . " or entite=" . sql_quote("auteur:$e") .")");
								
								$nb = sql_count($ent);
								if($nb > 0){
									echo $nb . " entites " . $e . " de statut INDETERMINE => "  . $t['filename'] .  "\n";
									$up =  "update entites_nommees set type_entite=" . str_replace("_", " " , sql_quote($type_entite)) . " where type_entite in ('INDETERMINE', 'Personnalités', 'Auteurs', 'Institutions (auto)','Villes','a ajouter','Géographie (auto)') and (entite=" . sql_quote($e) . " or entite=" . sql_quote("auteur:$e") . ")" ;	
									echo $up . "\n";
									sql_query($up);
									echo "\n\n" ;
								}
							}
						}
					}
					$output->writeln("<info>Requalification des données d'après les mots courants (stop words)</info>");
					include_spip("inc/entites_nommees");
					$words = generer_stop_words();
					//var_dump($words);
					
					if(sizeof($words) > 1 ){
						foreach($words as $e){
							$ent = sql_query("select * from entites_nommees where type_entite in ('INDETERMINE', 'Personnalités', 'Auteurs', 'Institutions (auto)') and entite= " . sql_quote($e));
							$nb = sql_count($ent);
							if($nb > 0){
								echo $nb . " entites '" . $e . "' de statut INDETERMINE => Poubelle\n";
								$del =  "delete from entites_nommees where type_entite in ('INDETERMINE', 'Personnalités', 'Auteurs', 'Institutions (auto)') and entite=" . sql_quote($e) . "\n" ;
								echo $del . "\n";
								sql_query($del);
								echo "\n" ;
							}
						}
					}
					
					$output->writeln("<info>Requalification des personnalités après maj heuristique institutions</info>");
					$query="select entite, id_entite from entites_nommees where type_entite='Personnalités' and entite regexp '" . str_replace('(?:É|E)','É',ENTITES_INSTITUTIONS_HEURISTIQUE) . "'" ;
					// echo $query ;
					$ent = sql_query($query);
					$nb = sql_count($ent);
					if($nb > 0){
						echo $nb . " personalités à requalifier institutions\n";
						
						while($res = sql_fetch($ent)){
							echo $res["entite"] ."\n";
							$up =  "update entites_nommees set type_entite='Institutions (auto)' where id_entite=" . intval($res['id_entite']);
							//echo $up . "\n";
							sql_query($up);
							
						}
					}
					
					
					// effacer les entites trop peu frequentes
					$date_e = sql_fetch(sql_query("select DATE_ADD(date,INTERVAL -5 YEAR) ladate from entites_nommees order by date desc limit 0,1"));
					$output->writeln("<info>On efface les entites vues dans un seul article et qu'on a pas revu depuis 5 ans (" . $date_e['ladate'] . ")</info>");
					// select entite, count(distinct(id_article)) nb, max(date) d from entites_nommees group by entite having nb=1 and d < date_add(now(), interval -5 year) order by d desc ;
					$ent = sql_query("select entite, count(distinct(id_article)) nb, max(date) d from entites_nommees group by entite having nb=1 and d < date_add(". _q($date_e["ladate"]) .", interval -5 year) order by d desc");
					$nb = sql_count($ent);
					if($nb > 0){
						echo $nb . " entite pas connues \n";
						while($res = sql_fetch($ent)){
							$del =  "delete from entites_nommees where entite=" . sql_quote($res['entite']) ;
							echo $del . " (" . $res['d'] . "< \n";
							sql_query($del);
							echo "\n" ;
						}
					}
					
					$output->writeln("<info>Requalifier des citations du Monde diplomatique</info>");
					// citation du diplo dans le texte
					// «[->50393]» ({Le Monde diplomatique,} mai 2014)
					// Dans « Le Monde diplomatique » de septembre 1977
					// Le Monde diplomatique, décembre 2013
					
					$ent = sql_query("select * from entites_nommees where entite='Le Monde diplomatique'");
					$nb = sql_count($ent);
					if($nb > 0){
						echo $nb . " mentions du diplo\n";
						
						while($res = sql_fetch($ent)){
							// echo $res["extrait"] ."\n";
							// par Victor de La Fuente, {Le Monde diplomatique,} décembre 1989
							// (« Le Monde diplomatique », octobre 2012)
							//
							if(preg_match("/Le Monde diplomatique\s?»?\"?}?,.*\d{4}/", $res['extrait'])){
								echo "///////\n CITATION : " . $res["extrait"] . "\n///////////" ;
								$up =  "update entites_nommees set entite='media:Le Monde diplomatique' where id_entite=" . intval($res['id_entite']);
								echo $up . "\n";
								sql_query($up);
							}
						}
						
						// $up =  "update entites_nommees set type_entite=" . str_replace("_", " " , sql_quote($type_entite)) . " where  (type_entite = 'INDETERMINE' or type_entite='Personnalités' or type_entite='Institutions (auto)') and entite=" . sql_quote($e) . "\n" ;	
						// echo $up . "\n";
						// sql_query($up);
					}
					
					$output->writeln("<info>Générer le fichier txt du décompte des principales entités</info>");
					// Générer le fichier txt du décompte des principales entites
					
					$references = sql_allfetsel("entite, type_entite, count(id_entite) nb","entites_nommees","","entite, type_entite","nb desc","", "nb>10");
					foreach($references as $reference){
						$decompte_entites .= preg_replace("/\R/", "", $reference['entite']) . "	" . $reference['type_entite'] . "	" . $reference['nb'] . "\n" ;
						sql_query("update entites_nommees set statut='publie' where entite=" . _q($reference['entite']));
					}
					if(!is_dir('plugins/entites_nommees/stats'))
						mkdir('plugins/entites_nommees/stats');
					ecrire_fichier('plugins/entites_nommees/stats/decompte_references.txt', "Entite	type	poids\n" . $decompte_entites);
					
					echo "Maj de 'plugins/entites_nommees/stats/decompte_references.txt'\n" ;
					echo sizeof($references) . " références apparaissant plus de 10 fois.\n\n" ;
					exit();
				}
				
				if($restart !=="non"){
					$output->writeln("<info>On efface tout et on recommence.</info>");
					sql_query("truncate table entites_nommees");
				}
				
				//
				// chercher les entites dans 1 000 ... non déjà traités
				//
				
				// articles deja faits
				$articles_faits = array("0") ;
				$articles_sql = sql_allfetsel("id_article", "entites_nommees", "", "id_article");
				foreach($articles_sql as $a)
					$articles_faits[] = $a['id_article'] ;
				
				
				// articles
				$requete = "select id_article from spip_articles where statut !='prepa' and id_secteur IN (" . _SECTEUR_ENTITES . ") and id_article not in(" . implode(",", $articles_faits) . ") order by date_redac desc limit 0,1000" ;
				// syndic articles
				if($type_source == "spip_syndic_articles")
					$requete = "select id_syndic_article id_article from spip_syndic_articles where id_syndic_article not in(" . implode(",", $articles_faits) . ") order by date desc limit 0,1000" ;
				
				//var_dump($requete);
				//die();
				
				// chopper les articles non déjà faits ;
				$articles = sql_query($requete);
				$res = sql_fetch_all($articles) ;
				
				// start and displays the progress bar
				$progress = new ProgressBar($output, sizeof($res));
				$progress->setBarWidth(100);
				$progress->setRedrawFrequency(1);
				$progress->setMessage("Génération des entités nommées...", 'message'); /**/  
				$progress->start();
				
				foreach($res as $a){
					
					/// articles
					$select = "id_article, titre, chapo, texte, date_redac" ;
					$table = "spip_articles" ;
					$where = "id_article=" . $a['id_article'] ;
					
					if($type_source == "spip_syndic_articles"){
						/// syndic_articles
						$select = "id_syndic_article id_article, titre, descriptif, date date_redac" ;
						$table = "spip_syndic_articles" ;
						$where = "id_syndic_article=" . $a['id_article'] ;
					}
					
					$q = "select " . $select . " from " . $table ." where " . $where ;
					$query = sql_query($q);
					$art = sql_fetch($query) ;
					
					$m = substr(" Traitement de l'article " . $art['id_article'] . " (" . $art['date_redac'] . ")", 0, 100) ;
					$progress->setMessage($m, 'message');
					
					
					// Trouver et enregistrer les entites nommées
					include_spip("entites_fonctions");
					$texte = preparer_texte($art['titre'] . " // \n" . $art['chapo'] . " // \n" . $art['texte'] . "\n");
					$fragments = trouver_entites($texte, $art['id_article']) ;
					//var_dump($fragments);
					
					if(!$fragments)
						$fragments = array("rien|rien|" . $art['id_article'] ."|rien");
					
					//var_dump($fragments);
					
					enregistrer_entites($fragments, $art['id_article'], $art['date_redac']);
					
					// Si tout s'est bien passé, on avance la barre
					$progress->setFormat("<fg=white;bg=blue>%message%</>\n" . '%current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%' ."\n\n");
					$progress->advance();
					
				}	
				
				## FIN
				$output->writeln("<info>Check des entités : done.</info>");
				$output->writeln("\n\nMerci bien.\n");
			}	
		}
		else{
			$output->writeln('<error>Vous n’êtes pas dans une installation de SPIP. Impossible de convertir le texte.</error>');
		}
	}
}
