<?php
/**
 * A configuração de base do WordPress
 *
 * Este ficheiro define os seguintes parâmetros: MySQL settings, Table Prefix,
 * Secret Keys, WordPress Language, e ABSPATH. Pode obter mais informação
 * visitando {@link http://codex.wordpress.org/Editing_wp-config.php Editing
 * wp-config.php} no Codex. As definições de MySQL são-lhe fornecidas pelo seu serviço de alojamento.
 *
 * Este ficheiro é usado para criar o script  wp-config.php, durante
 * a instalação, mas não tem que usar essa funcionalidade se não quiser.
 * Salve este ficheiro como "wp-config.php" e preencha os valores.
 *
 * @package WordPress
 */

// ** Definições de MySQL - obtenha estes dados do seu serviço de alojamento** //
/** O nome da base de dados do WordPress */
define('DB_NAME', 'tambem_voto');

/** O nome do utilizador de MySQL */
define('DB_USER', 'tambem.voto');

/** A password do utilizador de MySQL  */
define('DB_PASSWORD', '(%Votot**&_)');

/** O nome do serviddor de  MySQL  */
define('DB_HOST', 'localhost');

/** O "Database Charset" a usar na criação das tabelas. */
define('DB_CHARSET', 'utf8mb4');

/** O "Database Collate type". Se tem dúvidas não mude. */
define('DB_COLLATE', '');

/**#@+
 * Chaves Únicas de Autenticação.
 *
 * Mude para frases únicas e diferentes!
 * Pode gerar frases automáticamente em {@link https://api.wordpress.org/secret-key/1.1/salt/ Serviço de chaves secretas de WordPress.org}
 * Pode mudar estes valores em qualquer altura para invalidar todos os cookies existentes o que terá como resultado obrigar todos os utilizadores a voltarem a fazer login
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         'R3zk@VPv)jYT+iVE-mY$MRh(0cd6;9|^(.G9 ,bscR7$m5t=<~g8|iQF}DnCX:>*');
define('SECURE_AUTH_KEY',  'Zf&;d`q!Co=L&sIQ|Lu:<3FY6!Y=NqF{]+g6i_ua=q(FMxo{{Jd~E@fIoGYuo,&c');
define('LOGGED_IN_KEY',    '|aR8t/B&idsVI |6v Vya$<.=ILS!dP+[27jkhJBMgZrUSpUR6/Q)]UGr7m/iW4c');
define('NONCE_KEY',        '-22R^+eefvE*byD~0vuG<Z4o2^_-&-yNWE2{T/2|W8,vhbE9c87TqKmf-4:j&-1{');
define('AUTH_SALT',        '}R1Y7t pH]U/<_T{Se_d|H>WnD_yhO(`gQEz~ (}38Z(Wbes+sY${L*21SvT) r,');
define('SECURE_AUTH_SALT', '%L?x.r<cNc.h12qd=IxY*cbvx7L76xe{_ITwsxm0y>M6[U%=>cFD#<Z^3!3R&wZD');
define('LOGGED_IN_SALT',   '1egM|+tjQ9S5x!ax5crk%=1`CB!^y_psL|0L-cbz/qweY6`M)vZYt),brJ|.ND|j');
define('NONCE_SALT',       ')ZfHt1poXhZAvN4]nGPF)I6~er8Fn||saDV(-2(u)/)HH!OLleIntA<#Dr+}h-K-');

/**#@-*/

/**
 * Prefixo das tabelas de WordPress.
 *
 * Pode suportar múltiplas instalações numa só base de dados, ao dar a cada
 * instalação um prefixo único. Só algarismos, letras e underscores, por favor!
 */
$table_prefix  = 'tv_';

/**
 * Para developers: WordPress em modo debugging.
 *
 * Mude isto para true para mostrar avisos enquanto estiver a testar.
 * É vivamente recomendado aos autores de temas e plugins usarem WP_DEBUG
 * no seu ambiente de desenvolvimento.
 */

define('WP_DEBUG', false);

define('WP_ALLOW_MULTISITE', true);
define('MULTISITE', true);
define('SUBDOMAIN_INSTALL', true);

$base = '/';

define('PATH_CURRENT_SITE', '/');
define('SITE_ID_CURRENT_SITE', 1);
define('BLOG_ID_CURRENT_SITE', 1);
define('WP_DEFAULT_THEME', 'pinbin');


/* E é tudo. Pare de editar! */

/** Caminho absoluto para a pasta do WordPress. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Define as variáveis do WordPress e ficheiros a incluir. */
require_once(ABSPATH . 'wp-settings.php');

