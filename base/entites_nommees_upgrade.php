<?php
	
	include_spip('inc/meta');
	include_spip('base/create');
	include_spip('base/abstract_sql');
	include_spip('base/entites_nommees');
	 
	function entites_nommees_upgrade($nom_meta_base_version, $version_cible){

		$current_version = "0.0";
		if (isset($GLOBALS['meta'][$nom_meta_base_version]))
			$current_version = $GLOBALS['meta'][$nom_meta_base_version];
					
		if ($current_version=="0.0") {
				creer_base();
				ecrire_meta($nom_meta_base_version, $current_version=$version_cible); // marche pas ?
		}
		
		/*
			alter table entites_nommees add statut varchar(10) DEFAULT 'prop' NOT NULL
			alter table entites_nommees add index rapido (statut) ; 
		*/
				/*
		if (version_compare($current_version,"0.3","<")){
			// ajout de champs
			maj_tables('entites_nommees');
			ecrire_meta($nom_meta_base_version,$current_version="0.3");
		}
		*/
		
	}

	function entites_nommees_vider_tables($nom_meta_base_version) {
		//sql_drop_table("entites_nommees");
		//effacer_meta($nom_meta_base_version);
	}
