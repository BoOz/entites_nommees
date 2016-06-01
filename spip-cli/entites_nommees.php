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
			->setDescription('Générer les entités nommées en parcourant la base.')
			->setAliases(array(
				'entites'
			));
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		global $spip_racine;
		global $spip_loaded;
		global $spip_version_branche ;		
	
		include_spip("base/abstract_sql");
				
		if ($spip_loaded) {
			chdir($spip_racine);

			if (!function_exists('passthru')){
				$output->writeln("<error>Votre installation de PHP doit pouvoir exécuter des commandes externes avec la fonction passthru().</error>");
			}
			// Si c'est bon on continue
			else{
				
				passthru("clear");

				// articles deja faits
				$articles_faits = array("0") ;
				$articles_sql = sql_allfetsel("id_article", "entites_nommees", "", "id_article");
				foreach($articles_sql as $a)
					$articles_faits[] = $a['id_article'] ;	

				// chopper les articles non déjà faits ;				
				$articles = sql_query("select a.id_article from spip_articles a where a.id_secteur=1 and a.id_article not in(" . implode(",", $articles_faits) . ") order by a.date_redac desc limit 0,1000");
				$res = sql_fetch_all($articles) ;
				
				// start and displays the progress bar
				$progress = new ProgressBar($output, sizeof($res));
				$progress->setBarWidth(100);
				$progress->setRedrawFrequency(1);
				$progress->setMessage("Génération des entités nommées...", 'message'); /**/  
				$progress->start();
				
				foreach($res as $a){
					
					$art = sql_fetsel("id_article, titre, chapo, texte, date_redac", "spip_articles", "id_article=" . $a['id_article']);
					$m = substr(" Traitement de l'article " . $art['id_article'] . " (" . $art['date_redac'] . ")", 0, 100) ;
    				$progress->setMessage($m, 'message');
    				
    				// Trouver et enregistrer les entites nommées
    				include_spip("entites_fonctions");
    				$texte = preparer_texte($art['titre'] . "\n" . $art['chapo'] . "\n" . $art['texte'] . "\n");
					$fragments = trouver_entites($texte, $art['id_article']) ;

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
