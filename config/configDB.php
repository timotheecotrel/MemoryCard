<?php
/**
 * Created by PhpStorm.
 * User: timotheecotrel
 * Date: 27/01/15
 * Time: 17:54
 */

require_once('lib/src/person.php');
require_once('lib/src/personsdb.php');

require_once('lib/card/card.php');
require_once('lib/card/cardDB.php');

require_once('lib/game/game.php');
require_once('lib/game/gameDB.php');

require_once('lib/admin/adminDB.php');



require_once ('config.inc');

$pdo = new PDO(DB, USER, PASSWD);

$db = new PersonsDB($pdo , "user", "admin");
$dbFriend = new PersonsDB($pdo , "user" , "friend");
$dbFriendOnly = new PersonsDB($pdo , "friend");

$dbCard = new CardDB($pdo , "card", "card_asso");

$dbGame = new GameDB($pdo , "styleGame", "stats");

$dbAdmin = new AdminDB($pdo, "admin", "card_asso", "user");



?>

