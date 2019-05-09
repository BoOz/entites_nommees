<?php

if (!defined('_SECTEUR_ENTITES')) {
	define('_SECTEUR_ENTITES',1);
}

// informer le plugin indexer_diplo 
define('_INDEXER_ENTITES_NOMMEES', true);
if (!defined('_TIME_OUT')
&& !preg_match("/spip/", $_ENV['_']) // sauf SPIP_CLI
)
	define('_TIME_OUT', time() + 15);
