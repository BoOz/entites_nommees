<?php

	function entites_nommees_declarer_tables_principales($tables_principales){
		//-- Table des entites nomees ------------------------------------------
		$entites = array(
			"id_entite" => "bigint(21) NOT NULL",
			"entite"	=> "VARCHAR(255) NOT NULL",
			"type_entite"	=> "VARCHAR(255) NOT NULL",	
			"id_article"	=> "bigint(21) DEFAULT '0' NOT NULL",
			"extrait"	=> "VARCHAR(1000) NOT NULL",				
			"date" => "datetime DEFAULT '0000-00-00 00:00:00' NOT NULL"
		);

		$entites_key = array(
				"PRIMARY KEY"     => "id_entite",
				"KEY entite"        => "entite",
				"KEY type_entite"        => "type_entite",
				"KEY date"        => "date",
		);

		$tables_principales['entites_nommees'] = array('field' => &$entites, 'key' => &$entites_key);

		return $tables_principales;
	}

	function entites_nommees_declarer_tables_interface($interface){
		return $interface;
	}
