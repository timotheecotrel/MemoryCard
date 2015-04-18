<?php
/**
 * Created by PhpStorm.
 * User: timotheecotrel
 * Date: 26/01/15
 * Time: 16:32
 */
require_once('config/configDB.php');
require_once('control/authentification.php');
require_once('control/URLBuilder.php');
require_once('views/view.php');
require_once('views/privateView.php');
require_once('views/adminView.php');
require_once('views/gameView.php');

session_start();
$url = $_SERVER['REQUEST_URI'];

if(end(explode('/',$url)) == "admin"){
    $action = "admin";
}
// Instance de l'Url Builder qui crée les urls
$url = new URLBuilder();

// On demande l'authentification dès que $_POST est rempli
authentification($_POST,$db);


//On crée supprime feedback une fois qu'il à était affiché une fois
$feedback = isset($_SESSION["feedback"]) ? $_SESSION["feedback"] : null;
$_SESSION["feedback"] = null;

/**
 * Vérifie si un utilisateur est connecté ou non
 * si oui il affiche toutes les infos neccessaires
 * suivant si c'est un admin ou non
 */
if (isset($_SESSION['user']) ) {

    $user = $_SESSION["user"];
    $idUser = $user[0];

    $profil = $db->retrieveIdUser($idUser);

    $_SESSION['idUserConnect'] = $profil["idUser"];

    $friend = $dbFriend->retrieveAllFriend($user[0]);

    if($_POST['recherche'] !== NULL){
        try{
            $search = $db->retrieve($_POST['recherche']);
        }
        catch(Exception $e){
           $search = $e->getMessage();

        }
    }
    $idUser = $db->retrieveIdUser($user[0]);
    $requestFriend = $dbFriend->retrieveRequestFriend($idUser["idUser"]);
    $nbRequestFriend = count($requestFriend);

    if($db->isAdmin($idUser['idUser'])){
        $_SESSION['statut'] = "admin";
        $view = new AdminView("css/styleAdmin.css", $url, $dbCard, $db,$dbFriend, $dbFriendOnly,$user, $feedback);
    }
    else {
        $_SESSION['statut'] = "user";
        $view = new PrivateView("css/style.css", $user, $url, $friend, $nbRequestFriend, $feedback, $search, $db, $dbFriend, $dbFriendOnly, $dbCard, $dbGame);
    }
} else {
    $view = new View("css/styleIndex.css");
}
//On test si une action à était passé
if (isset($_GET['action'])) {
    $action = $_GET['action'];
}

//On appel les pages qui correspondent aux actions
switch ($action) {
    case "welcome":
        $view->makeWelcomePage($dbGame, $profil["idUser"]);
        break;

    case "createGame":
        $view->makeCreateGamePage($dbGame);
        break;

    case "inscription":
        $newUser = new Person($_POST['nom'], $_POST['prenom'], $_POST['emailInscr'], $_POST['pseudoInscr']);
        $db->create($newUser , $_POST['mdpInscr']);
        $view->makeWelcomePage($dbGame, $profil["idUser"]);
        break;

    case "search":
        $view->displaySearch();
        break;

    case "profil":
        $idFriend = $_GET['id'];
        $view->makeProfilFriendPage($idFriend,$user,$dbFriendOnly, $dbCard);
        break;

    case "add":
        $idFriend = $_GET['id'];
        $view->makeAddPage($idFriend,$user, $dbFriendOnly);
        break;

    case "demande":
        $view->makeRequestPage($user);
        break;

    case "accept":
        $idFriend = $_GET['id'];
        $view->makeAcceptFriendlyPage($user, $idFriend, $dbFriendOnly);
        break;

    case "deny":
        $idFriend = $_GET['id'];
        $view->makeDeleteRefuseFriendPage($user, $idFriend, $dbFriendOnly);
        break;

    case "delete":
        $idFriend = $_GET['id'];
        $view->makeDeleteFriendPage($user, $idFriend, $dbFriendOnly);
        break;

    case "monProfil":
        $view->makeProfilPage($user);
        break;

    case "modifyProfil":
        $idUser = $_GET['id'];
        $updateUser = new Person($_POST['modifierNom'], $_POST['modifierPrenom'], $_POST['modifierEmail'], $_POST['modifierPseudo']);
        if($_POST['newPwd'] !== '' and $_POST['confirmPwd'] !== ''){
            $db->updatePassword($idUser,$_POST['newPwd']);
        }
        $db->update($idUser, $updateUser);
        header("Refresh:0; url=index.php");
        break;

    case "carte":
        $view->makeCardPage($profil["idUser"], $dbCard);
        break;

    case "addCard":
        if(isset($_FILES['fichier']) && $_FILES['fichier']['name'] !== "" )
        {
            if($_SESSION['statut'] === "admin") {
                $dossier = 'lib/card/card/deck/';

            }
            else{
                $dossier = 'lib/card/card/';
            }
            $fichier = basename($_FILES['fichier']['name']);
            if($dbCard->retrieveCard($fichier) == FALSE) {
                if (@move_uploaded_file($_FILES['fichier']['tmp_name'], $dossier . $fichier)) {
                    if($_SESSION['statut'] === "admin") {
                        $lastCardAdmin = end($view->makeDeckCard($dbAdmin, $dbCard));
                        if ($lastCardAdmin != NULL) {
                            $cardDeckExplode = explode("_", $lastCardAdmin->getCard());
                            $cardDeckExplodeNum = intval(substr($cardDeckExplode[1],0, -4));
                            $ext = ".jpg";
                            rename($dossier . $fichier, $dossier . "/"."deck_" . ($cardDeckExplodeNum + 1) . $ext);
                            $fichier = "deck_" . ($cardDeckExplodeNum + 1) . $ext;
                        }
                    }

                    $card = new Card($fichier);
                    $dbCard->create($card);
                    $idCard = $dbCard->retrieveId($card->getCard());
                    if($_SESSION['statut'] === "admin") {
                        $idAdmin = array_unique($dbAdmin->retrieveIdAdmin());
                        foreach($idAdmin as $value){
                            $dbCard->createAssoAdmin($idCard["idCard"], $value);
                        }
                    }
                    else{
                        $dbCard->createAsso($idCard["idCard"], $profil["idUser"]);
                    }
                    $feedback = "Carte ajout&eacute;";

                } else
                    $feedback = 'L\'upload ne fonctionne pas !';
            }
            else
                $feedback = 'Vous possedez déjà cette carte';
        }
        else
            $feedback = 'Veuillez selectionner une image';

        if($_SESSION['statut'] === "admin") {
            $view->makeCardPage($view->makeDeckCard($dbAdmin, $dbCard),$dbCard);
        }
        else{
            $view->makeCardPage($profil["idUser"], $dbCard, $feedback);
        }
        break;

    case "deleteCard":
        if($_SESSION['statut'] === "admin"){
            unlink("lib/card/card/deck/".$_GET['path']);

            $idCard = $dbCard->retrieveId($_GET['path']);
            $dbCard->deleteAllCardAsso($idCard["idCard"]);
            $dbCard->deleteCard($idCard["idCard"]);
            $view->makeCardPage($view->makeDeckCard($dbAdmin, $dbCard),$dbCard);
        }
        else {
            $idCard = $dbCard->retrieveId($_GET['path']);
            $dbCard->deleteCardAsso($idCard["idCard"], $profil["idUser"]);
            $someonehaveCard = $dbCard->someoneHaveCard($idCard["idCard"]);
            if ($someonehaveCard == null) {
                $dbCard->deleteCard($idCard["idCard"]);
                unlink("lib/card/card/" . $_GET['path']);
            }
            $view->makeCardPage($profil["idUser"], $dbCard);
        }

        break;

    case "takeShare":
        $idCardCurrent = $_GET['idCard'];
        $dbCard->createAsso($idCardCurrent,$idUser['idUser']);
        $view->makeCardPage($profil["idUser"], $dbCard);
        break;

    case "shareOn":
        $dbCard->updateShareOn($_GET['idCard'], $idUser['idUser']);
        $view->makeCardPage($profil["idUser"], $dbCard);
        break;

    case "shareOff":
        $idCardCurrent = $_GET['idCard'];
        $dbCard->updateShareOff($idCardCurrent, $idUser['idUser']);
        $view->makeCardPage($profil["idUser"], $dbCard);
        break;

    case "launchGame":
        $styleGame = $_POST['nbrCarte'];
        $_SESSION['style'] = $styleGame;
        $view = new GameView("css/styleGame.css", $url, $db, $dbCard, $dbGame,$user);
        $view->makeLaunchGamePage($styleGame,$dbGame,$dbCard, $dbAdmin,$profil["idUser"]);
        break;

    case "finishGame":
        if(isset($_GET['quitter'])){
            $view->makeAddStats($_SESSION['style'], 0,$profil["idUser"],0, $dbGame);
        }
        else{
            $view->makeAddStats($_SESSION['style'], $_GET['score'],$profil["idUser"],1, $dbGame);
        }

        $view->makeWelcomePage($dbGame, $profil["idUser"]);
        break;

    case "guestGame":
        $styleGame = $_POST['nbrCarte'];
        $_SESSION['statut'] = "guest";
        $view = new GameView("css/styleGame.css", $url, $db, $dbCard, $dbGame);
        $view->makeLaunchGamePage($styleGame,$dbGame,$dbCard, $dbAdmin,$profil["idUser"]);
        break;

    case "admin":
        header('Location: http://www.google.com');
        break;

    case "modifyRules":
        $newText = $_POST['textarea'];
        $file = "fragments/regles.php";
        $open = fopen($file, "w+");
        fwrite($open, $newText);
        fclose($open);
        $view->makeWelcomePage();
        break;

    case "deck":
        $view->makeCardPage($view->makeDeckCard($dbAdmin, $dbCard),$dbCard);
        break;

    default:
        $view->makeWelcomePage($dbGame,$profil["idUser"]);
        break;

}

$view->render();

?>